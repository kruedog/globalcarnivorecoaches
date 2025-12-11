/* ============================================================
   GLOBAL CONFIG
============================================================ */
const API_BASE = window.location.origin + '/webapi/';
const DASH_API = API_BASE + 'dashboard/';

/* ============================================================
   GCC ADMIN UTILITIES (Reusable)
============================================================ */
const GCC = {
  qs(sel){ return document.querySelector(sel); },
  qsa(sel){ return document.querySelectorAll(sel); },

  fadeIn(el){ el.style.opacity = 0; el.classList.remove("hidden"); setTimeout(()=>el.style.opacity=1,10); },
  fadeOut(el){ el.style.opacity = 1; setTimeout(()=>{el.style.opacity=0; setTimeout(()=>el.classList.add("hidden"),200)},10); },

  async json(url, opts={}){
    const res = await fetch(url, opts);
    if(!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  },

  setHTML(el, html){
    if(typeof el === "string") el = GCC.qs(el);
    if(el) el.innerHTML = html;
  }
};

/* ============================================================
   TAB MANAGER
============================================================ */
function initTabs(){
  const buttons = GCC.qsa('.tab-btn');
  const panels  = GCC.qsa('.tab-panel');

  function showTab(tab){
    buttons.forEach(btn=>{
      const active = btn.dataset.tab === tab;
      btn.classList.toggle('active', active);
    });
    panels.forEach(panel=>{
      panel.classList.toggle('hidden', panel.dataset.panel !== tab);
    });

    // Lazy-load each section
    if(tab === 'engagement') loadEngagement();
    if(tab === 'leads')      loadLeads();
    if(tab === 'devices')    loadDevices();
    if(tab === 'geography')  loadGeoExtra();
  }

  buttons.forEach(btn=>{
    btn.addEventListener('click', ()=> showTab(btn.dataset.tab));
  });

  showTab('overview');
}

/* ============================================================
   SESSION CHECK
============================================================ */
async function ensureSession(){
  try{
    const data = await GCC.json(API_BASE + 'login.php', {credentials:'include'});
    if(!data.success){
      window.location.href = '/webapi/login.html';
      return null;
    }
    GCC.setHTML('#userBadge', data.username);
    return data;
  }catch(err){
    window.location.href = '/webapi/login.html';
    return null;
  }
}

/* ============================================================
   COACH NAME MAP
============================================================ */
let coachNameMap = {};

async function loadCoachNames(){
  try{
    const res = await fetch('/uploads/coaches.json', {cache:'no-store'});
    const data = await res.json();
    data.forEach(c=>{
      const u = (c.Username||'').toLowerCase();
      coachNameMap[u] = c.CoachName || c.Username;
    });
  }catch{}
}

/* ============================================================
   REFRESH INDICATOR & TIMESTAMP
============================================================ */
function showRefresh(){
  const el = GCC.qs('#refreshStatus');
  el.classList.add('show');
  setTimeout(()=>el.classList.remove('show'),1000);
}
function updateTimestamp(){
  GCC.setHTML('#updateTime', 'Last updated: ' + new Date().toLocaleTimeString());
}

/* ============================================================
   OVERVIEW LOADERS
============================================================ */
async function loadStats(){
  try{
    const data = await GCC.json(API_BASE + 'webapi.php?action=get_stats', {credentials:'include'});
    GCC.setHTML('#statToday', data.today ?? 0);
    GCC.setHTML('#statWeek', data.week ?? 0);
    GCC.setHTML('#statTotal', data.total ?? 0);

    const locList = GCC.qs('#locationList');
    locList.innerHTML = '';
    const locs = Object.entries(data.locationCount || {}).sort((a,b)=>b[1]-a[1]);

    if(locs.length === 0){
      locList.innerHTML = `<div class="location-row"><span class="text-muted">No visits yet</span></div>`;
    }else{
      locs.forEach(([loc,count])=>{
        locList.innerHTML += `<div class="location-row"><span>${loc}</span><span>${count}</span></div>`;
      });
    }
  }catch{
    GCC.setHTML('#dashStatus','Analytics unavailable.');
  }
}

let visitsChartObj=null, profileChartObj=null;

async function loadVisitChart(){
  try{
    const data = await GCC.json(API_BASE + 'webapi.php?action=get_visits_14days',{credentials:'include'});
    const labels = data.points.map(p=>p.date);
    const counts = data.points.map(p=>p.count);

    const ctx = GCC.qs('#visitsChart').getContext('2d');
    if(visitsChartObj) visitsChartObj.destroy();

    const grad = ctx.createLinearGradient(0,0,0,220);
    grad.addColorStop(0,'rgba(175,30,45,0.45)');
    grad.addColorStop(1,'rgba(25,33,104,0.05)');

    visitsChartObj = new Chart(ctx,{
      type:'line',
      data:{ labels, datasets:[{data:counts, borderColor:'rgba(175,30,45,1)', backgroundColor:grad, fill:true,tension:.35}] },
      options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
    });
  }catch{}
}

async function loadProfileViews(){
  try{
    const data = await GCC.json(DASH_API + 'get_profile_views.php', {credentials:'include'});
    if(!data.success) return;

    GCC.setHTML('#statProfileTotal', data.total_views ?? 0);

    // Build 14-day history
    const now = new Date();
    let dm = {};
    for(let i=13;i>=0;i--){
      const d = new Date(now);
      d.setDate(d.getDate()-i);
      dm[d.toISOString().split('T')[0]] = 0;
    }
    (data.history||[]).forEach(r=>{
      if(dm[r.date]!==undefined) dm[r.date]+=r.views;
    });

    const labels = Object.keys(dm);
    const counts = Object.values(dm);

    const ctx = GCC.qs('#profileChart').getContext('2d');
    if(profileChartObj) profileChartObj.destroy();

    const grad = ctx.createLinearGradient(0,0,0,220);
    grad.addColorStop(0,'rgba(25,33,104,0.45)');
    grad.addColorStop(1,'rgba(175,30,45,0.05)');

    profileChartObj = new Chart(ctx,{
      type:'line',
      data:{ labels, datasets:[{data:counts, borderColor:'rgba(25,33,104,1)', backgroundColor:grad, fill:true}] },
      options:{ responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}} }
    });

    // Leaderboard
    const leaders = data.totals_per_coach || {};
    const list = Object.entries(leaders).sort((a,b)=>b[1]-a[1]).slice(0,5);
    const el = GCC.qs('#leaderList');
    el.innerHTML = list.length
      ? list.map(([u,c])=>{
          const name = coachNameMap[u.toLowerCase()] || u;
          return `<div class="leader-row"><span>${name}</span><span>${c}</span></div>`;
        }).join('')
      : `<div class="text-muted">No data yet</div>`;

  }catch{}
}

/* ============================================================
   ENGAGEMENT TAB
============================================================ */
let hourlyChartObj=null;

async function loadEngagement(){
  try{
    const data = await GCC.json(API_BASE + 'webapi.php?action=get_engagement',{credentials:'include'});

    GCC.setHTML('#engReturnToday', data.return_today ?? '0');
    GCC.setHTML('#engReturnWeek', data.return_week ?? '0');
    GCC.setHTML('#engReturnRate', data.return_rate ? data.return_rate.toFixed(1)+'%' : '–');

    GCC.setHTML('#engDurShort', data.duration_short ?? '0');
    GCC.setHTML('#engDurMed',   data.duration_med ?? '0');
    GCC.setHTML('#engDurLong',  data.duration_long ?? '0');

    // Top pages
    const pages = data.top_pages || [];
    const list = GCC.qs('#engTopPages');
    list.innerHTML = pages.length
      ? pages.map(p=>`<li><span>${p.path}</span><span>${p.count}</span></li>`).join('')
      : '<li><span class="text-muted">No data yet</span></li>';

    // Hourly chart
    const hourly = data.hourly || [];
    const labels = hourly.map(h=>h.label||h.hour);
    const counts = hourly.map(h=>h.count);
    const ctx = GCC.qs('#hourlyChart').getContext('2d');
    if(hourlyChartObj) hourlyChartObj.destroy();
    hourlyChartObj = new Chart(ctx,{
      type:'line',
      data:{labels,datasets:[{data:counts,borderColor:'rgba(25,33,104,1)',backgroundColor:'rgba(25,33,104,0.1)',fill:true}]},
      options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}}}
    });
  }catch{
    GCC.setHTML('#engagementStatus',"Engagement data unavailable.");
  }
}

/* ============================================================
   LEADS TAB
============================================================ */
async function loadLeads(){
  try{
    const data = await GCC.json(API_BASE + 'webapi.php?action=get_leads',{credentials:'include'});

    GCC.setHTML('#leadsContactToday', data.contact_today ?? '0');
    GCC.setHTML('#leadsContactWeek', data.contact_week ?? '0');

    GCC.setHTML('#funnelViews', data.funnel_views ?? '0');
    GCC.setHTML('#funnelScrolled', data.funnel_scrolled ?? '0');
    GCC.setHTML('#funnelContact', data.funnel_contact ?? '0');

    const scoreToday = data.score_today;
    const scoreYest  = data.score_yesterday;

    if(typeof scoreToday === 'number'){
      GCC.setHTML('#leadsScoreToday', scoreToday.toFixed(1));
      const delta = GCC.qs('#leadsScoreDelta');

      if(typeof scoreYest === 'number'){
        const diff = scoreToday - scoreYest;
        if(diff > 0){
          delta.textContent = '+'+diff.toFixed(1)+' vs yesterday';
          delta.className = 'kpi-delta up';
        }else if(diff < 0){
          delta.textContent = diff.toFixed(1)+' vs yesterday';
          delta.className = 'kpi-delta down';
        }else{
          delta.textContent = 'No change';
          delta.className = 'kpi-delta flat';
        }
      } else {
        delta.textContent = 'Waiting for comparison data…';
      }
    }

    GCC.setHTML('#leadsSummaryText', data.summary || 'Lead summary endpoint is active.');

  }catch{
    GCC.setHTML('#leadsStatus',"Lead analytics unavailable.");
  }
}

/* ============================================================
   DEVICES TAB
============================================================ */
let deviceChartObj=null, browserChartObj=null, osChartObj=null;

async function loadDevices(){
  try{
    const data = await GCC.json(API_BASE + 'webapi.php?action=get_devices',{credentials:'include'});

    function doughnut(canvasId, arr){
      if(!arr.length) return null;
      const el = GCC.qs(canvasId);
      const ctx = el.getContext('2d');
      const labels = arr.map(i=>i.label);
      const counts = arr.map(i=>i.count);
      const total = counts.reduce((a,b)=>a+b,0) || 1;

      return new Chart(ctx,{
        type:'doughnut',
        data:{labels,datasets:[{data:counts}]},
        options:{
          responsive:true,
          maintainAspectRatio:false,
          plugins:{
            legend:{position:'bottom'},
            tooltip:{callbacks:{label:(ctx)=>`${ctx.label}: ${ctx.raw} (${(ctx.raw/total*100).toFixed(1)}%)`}}
          },
          cutout:'55%'
        }
      });
    }

    if(deviceChartObj) deviceChartObj.destroy();
    if(browserChartObj) browserChartObj.destroy();
    if(osChartObj) osChartObj.destroy();

    deviceChartObj  = doughnut('#deviceChart',  data.devices  || []);
    browserChartObj = doughnut('#browserChart', data.browsers || []);
    osChartObj      = doughnut('#osChart',      data.os       || []);

  }catch{
    GCC.setHTML('#devicesStatus',"Device analytics unavailable.");
  }
}

/* ============================================================
   GEOGRAPHY TAB
============================================================ */
async function loadGeoExtra(){
  try{
    const data = await GCC.json(API_BASE + 'webapi.php?action=get_geo',{credentials:'include'});

    const list = GCC.qs('#geoTopCountries');
    const arr  = data.top_countries || [];
    list.innerHTML = arr.length
      ? arr.map(c=>`<li><span>${c.name}</span><span>${c.count}</span></li>`).join('')
      : '<li><span class="text-muted">No data yet</span></li>';

    GCC.setHTML('#geoTrendText', data.trend_text || 'Geo endpoint active.');

  }catch{
    GCC.setHTML('#geoStatus',"Geo data unavailable.");
  }
}

/* ============================================================
   INIT
============================================================ */
document.addEventListener('DOMContentLoaded', async ()=>{
  const session = await ensureSession();
  if(!session) return;

  initTabs();
  loadCoachNames();

  showRefresh();
  await loadStats();
  await loadVisitChart();
  await loadProfileViews();
  updateTimestamp();

  // Preload other tabs silently
  loadEngagement();
  loadLeads();
  loadDevices();
  loadGeoExtra();

  // Auto refresh every 60s
  setInterval(async ()=>{
    showRefresh();
    await loadStats();
    await loadVisitChart();
    await loadProfileViews();
    updateTimestamp();
  },60000);
});

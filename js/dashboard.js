/* ============================================
   DASHBOARD.JS — Global Carnivore Coaches
   Complete Analytics Frontend Engine
   ============================================ */

const API_BASE = "/webapi/webapi.php?action=";

/* -------------------------------
   Helper: Fetch JSON with safety
--------------------------------*/
async function apiGet(action) {
    try {
        const res = await fetch(API_BASE + action, {
            method: "GET",
            credentials: "include",
            cache: "no-store"
        });

        if (!res.ok) throw new Error("Server returned " + res.status);

        const data = await res.json();
        if (!data || data.success === false) {
            console.warn("API error:", data);
            return null;
        }
        return data;
    } catch (err) {
        console.error("Fetch failed:", err);
        return null;
    }
}

/* -------------------------------
   UI Helper: Set loading / error
--------------------------------*/
function setStatus(id, msg, type = "loading") {
    const el = document.getElementById(id);
    if (!el) return;

    el.textContent = msg;

    el.className = "dash-status " + type; // CSS will style this
}

/* -------------------------------
   Tabs
--------------------------------*/
function initTabs() {
    const tabs = document.querySelectorAll("[data-tab]");
    const panels = document.querySelectorAll("[data-panel]");

    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            const target = tab.dataset.tab;

            tabs.forEach(t => t.classList.remove("active"));
            panels.forEach(p => p.classList.remove("active"));

            tab.classList.add("active");
            document.querySelector(`[data-panel="${target}"]`).classList.add("active");

            loadTab(target);
        });
    });

    // Load default tab
    const first = tabs[0];
    if (first) {
        first.classList.add("active");
        document.querySelector(`[data-panel="${first.dataset.tab}"]`).classList.add("active");
        loadTab(first.dataset.tab);
    }
}

/* -------------------------------
   Main loader dispatcher
--------------------------------*/
function loadTab(tab) {
    switch (tab) {
        case "overview":      return loadOverview();
        case "engagement":    return loadEngagement();
        case "devices":       return loadDevices();
        case "geo":           return loadGeo();
        case "leads":         return loadLeads();
        default:
            console.warn("Unknown tab:", tab);
    }
}

/* -------------------------------
   1. OVERVIEW TAB
--------------------------------*/
async function loadOverview() {
    setStatus("overview-status", "Loading overview…");

    const stats = await apiGet("get_stats");
    const history = await apiGet("get_visits_14days");

    if (!stats || !history) {
        setStatus("overview-status", "Failed to load data.", "error");
        return;
    }

    // Insert stats
    document.getElementById("ov-today").textContent = stats.today;
    document.getElementById("ov-week").textContent = stats.week;
    document.getElementById("ov-total").textContent = stats.total;

    setStatus("overview-status", "Loaded", "ok");

    // Chart
    drawVisitsChart(history.points);
}

/* Chart.js daily visits */
function drawVisitsChart(points) {
    const ctx = document.getElementById("visitsChart").getContext("2d");

    if (window._visitsChart) window._visitsChart.destroy();

    window._visitsChart = new Chart(ctx, {
        type: "line",
        data: {
            labels: points.map(p => p.date),
            datasets: [{
                label: "Visitors",
                data: points.map(p => p.count),
                fill: true,
                tension: 0.25
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

/* -------------------------------
   2. ENGAGEMENT TAB
--------------------------------*/
async function loadEngagement() {
    setStatus("engage-status", "Loading engagement…");

    const data = await apiGet("get_engagement");

    if (!data) {
        setStatus("engage-status", "Failed to load engagement.", "error");
        return;
    }

    document.getElementById("eng-return-today").textContent = data.return_today;
    document.getElementById("eng-return-week").textContent  = data.return_week;
    document.getElementById("eng-return-rate").textContent  = data.return_rate.toFixed(1) + "%";

    document.getElementById("eng-dur-short").textContent = data.duration_short;
    document.getElementById("eng-dur-med").textContent   = data.duration_med;
    document.getElementById("eng-dur-long").textContent  = data.duration_long;

    setStatus("engage-status", "Loaded", "ok");

    drawEngagementHourlyChart(data.hourly);
}

/* Hourly chart */
function drawEngagementHourlyChart(items) {
    const ctx = document.getElementById("engChart").getContext("2d");

    if (window._engChart) window._engChart.destroy();

    window._engChart = new Chart(ctx, {
        type: "bar",
        data: {
            labels: items.map(i => i.label),
            datasets: [{
                label: "Visits per hour",
                data: items.map(i => i.count)
            }]
        },
        options: {
            responsive: true
        }
    });
}

/* -------------------------------
   3. DEVICES TAB
--------------------------------*/
async function loadDevices() {
    setStatus("devices-status", "Loading devices…");

    const data = await apiGet("get_devices");

    if (!data) {
        setStatus("devices-status", "Failed to load devices.", "error");
        return;
    }

    drawDoughnut("devChartDevices",  data.devices);
    drawDoughnut("devChartBrowser",  data.browsers);
    drawDoughnut("devChartOS",       data.os);

    setStatus("devices-status", "Loaded", "ok");
}

function drawDoughnut(canvasId, items) {
    const ctx = document.getElementById(canvasId).getContext("2d");

    if (ctx._chart) ctx._chart.destroy();

    ctx._chart = new Chart(ctx, {
        type: "doughnut",
        data: {
            labels: items.map(i => i.label),
            datasets: [{
                data: items.map(i => i.count)
            }]
        },
        options: { responsive: true }
    });
}

/* -------------------------------
   4. GEO TAB
--------------------------------*/
async function loadGeo() {
    setStatus("geo-status", "Loading regions…");

    const data = await apiGet("get_geo");

    if (!data) {
        setStatus("geo-status", "Failed to load geography.", "error");
        return;
    }

    // Country list
    const list = document.getElementById("geo-list");
    list.innerHTML = "";

    data.top_countries.forEach(row => {
        const li = document.createElement("li");
        li.textContent = `${row.name} — ${row.count}`;
        list.appendChild(li);
    });

    document.getElementById("geo-trend").textContent = data.trend_text;

    setStatus("geo-status", "Loaded", "ok");
}

/* -------------------------------
   5. LEADS TAB
--------------------------------*/
async function loadLeads() {
    setStatus("leads-status", "Loading leads…");

    const data = await apiGet("get_leads");

    if (!data) {
        setStatus("leads-status", "Failed to load leads.", "error");
        return;
    }

    document.getElementById("lead-today").textContent     = data.contact_today;
    document.getElementById("lead-week").textContent      = data.contact_week;
    document.getElementById("lead-score-today").textContent  = data.score_today;
    document.getElementById("lead-score-yesterday").textContent = data.score_yesterday;

    document.getElementById("lead-summary").textContent = data.summary;

    setStatus("leads-status", "Loaded", "ok");
}

/* -------------------------------
   Auto-refresh every 60s
--------------------------------*/
setInterval(() => {
    const active = document.querySelector(".tab.active");
    if (active) loadTab(active.dataset.tab);
}, 60000);


/* -------------------------------
   Init
--------------------------------*/
document.addEventListener("DOMContentLoaded", initTabs);

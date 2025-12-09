// ============================================
// Profile Page Script (Final)
// Uses shared GCCModals system
// ============================================

const API_BASE = window.location.origin + '/webapi/';

let currentCoach = {};
let specializations = [];
const newFiles = new Map();
const pendingDeletes = new Set();

// ---------- UTILITIES ----------
function setStatus(el, msg, ok = true) {
  if (!el) return;
  el.textContent = msg || '';
  el.className = 'status-line ' + (msg ? (ok ? 'ok' : 'err') : '');
}

function imgUrl(file) {
  if (!file) return '/images/earth_steak.png';
  return '/uploads/' + file + '?v=' + Date.now();
}

// ---------- SESSION CHECK ----------
async function checkSession() {
  try {
    const res = await fetch(API_BASE + 'login.php', {
      method: 'GET',
      credentials: 'include',
      cache: 'no-store'
    });
    const data = await res.json();
    if (!data.success) {
      window.location.href = '/webapi/login.html';
      return null;
    }
    if (data.username) localStorage.setItem('username', data.username);
    if (data.role) localStorage.setItem('role', data.role.toLowerCase());
    return data;
  } catch {
    window.location.href = '/webapi/login.html';
    return null;
  }
}

// ---------- LOAD PROFILE ----------
async function loadProfile() {
  const sessionInfo = await checkSession();
  if (!sessionInfo) return;

  try {
    const res = await fetch(API_BASE + 'get_coach.php', {
      method: 'GET',
      credentials: 'include',
      cache: 'no-store'
    });
    const data = await res.json();
    if (!data.success) {
      alert(data.message || 'Load failed');
      return;
    }

    currentCoach = data.coach || {};

    document.getElementById('coachName').textContent =
      (currentCoach.CoachName || currentCoach.Username || 'Coach');

    document.getElementById('coachNameInput').value = currentCoach.CoachName || '';
    document.getElementById('email').value       = currentCoach.Email || '';
    document.getElementById('phone').value       = currentCoach.Phone || '';
    document.getElementById('bio').value         = currentCoach.Bio || '';

    // Specializations
    if (Array.isArray(currentCoach.Specializations)) {
      specializations = [...currentCoach.Specializations];
    } else if (typeof currentCoach.Specializations === 'string') {
      specializations = currentCoach.Specializations
        .split(/[;,|]/).map(s => s.trim()).filter(Boolean);
    } else {
      specializations = [];
    }
    renderSpecializations();
    renderImages();

    // Admin-only button
    const btn = document.getElementById('manageCoachesBtn');
    if (btn) btn.style.display =
      (sessionInfo.role || '').toLowerCase() === 'admin'
        ? 'inline-flex'
        : 'none';

  } catch (err) {
    alert('Error loading profile');
    console.error(err);
  }
}

// ---------- SPECIALIZATIONS ----------
function renderSpecializations() {
  const list = document.getElementById('specList');
  list.innerHTML = '';
  specializations.forEach((s, i) => {
    const tag = document.createElement('span');
    tag.textContent = s;
    tag.title = 'Click to remove';
    tag.onclick = () => {
      specializations.splice(i, 1);
      renderSpecializations();
    };
    list.appendChild(tag);
  });
}

// ---------- IMAGES ----------
function renderImages() {
  const list = document.getElementById('uploadsList');
  if (!list) return;
  list.innerHTML = '';

  const files = currentCoach.Files || {};
  const labels = { Profile:'Profile', Before:'Before', After:'After', Certificate:'Certificate' };

  Object.entries(files).forEach(([slot, file]) => {
    if (!file) return;
    const div = document.createElement('div');
    div.className = 'preview-item';
    div.innerHTML = `
      <img src="${imgUrl(file)}" class="preview-img">
      <div class="preview-caption">${labels[slot]}</div>
      <button class="remove-btn" data-slot="${slot}">&times;</button>
    `;
    div.querySelector('.remove-btn').onclick = () => {
      pendingDeletes.add(slot);
      div.remove();
    };
    list.appendChild(div);
  });
}

function setupUploader(id, slot) {
  const zone = document.getElementById(id);
  if (!zone) return;
  const input = document.createElement('input');
  input.type = 'file';
  input.accept = 'image/*';
  input.onchange = () => {
    if (input.files[0]) {
      newFiles.set(slot, input.files[0]);
      pendingDeletes.delete(slot);
      previewTemp(slot, input.files[0]);
    }
  };
  zone.addEventListener('click', () => input.click());
}

function previewTemp(slot, file) {
  const list = document.getElementById('uploadsList');
  if (!list) return;
  const reader = new FileReader();
  reader.onload = e => {
    const div = document.createElement('div');
    div.className = 'preview-item';
    div.innerHTML = `
      <img src="${e.target.result}" class="preview-img">
      <div class="preview-caption">${slot}</div>
      <button class="remove-btn">&times;</button>
    `;
    div.querySelector('.remove-btn').onclick = () => {
      newFiles.delete(slot);
      div.remove();
    };
    list.appendChild(div);
  };
  reader.readAsDataURL(file);
}

// ---------- SAVE PROFILE ----------
async function handleProfileSave(e) {
  e.preventDefault();
  const statusEl = document.getElementById('profileStatus');
  setStatus(statusEl, 'Saving…');

  const formData = new FormData();
  formData.append('coachName', document.getElementById('coachNameInput').value.trim());
  formData.append('email', document.getElementById('email').value.trim());
  formData.append('phone', document.getElementById('phone').value.trim());
  formData.append('bio', document.getElementById('bio').value);
  formData.append('specializations', JSON.stringify(specializations));

  newFiles.forEach((file, slot) => formData.append(`files[${slot}]`, file));
  pendingDeletes.forEach(slot => formData.append('deleteSlots[]', slot));

  try {
    const res = await fetch(API_BASE + 'update_coach.php', {
      method: 'POST',
      credentials: 'include',
      body: formData
    });
    const data = await res.json();
    if (!data.success) return setStatus(statusEl, data.message, false);

    newFiles.clear();
    pendingDeletes.clear();
    await loadProfile();
    setStatus(statusEl, 'Saved!', true);

  } catch (err) {
    console.error(err);
    setStatus(statusEl, 'Network error', false);
  }
}

// ---------- CHANGE PASSWORD ----------
async function handleChangePassword() {
  const cur = document.getElementById('currentPassword').value;
  const next = document.getElementById('newPassword').value;
  const conf = document.getElementById('confirmPassword').value;
  const s = document.getElementById('passwordStatus');

  if (!cur || !next || !conf) return setStatus(s, 'All fields required', false);
  if (next !== conf) return setStatus(s, 'Passwords do not match', false);
  if (next.length < 8) return setStatus(s, 'Minimum 8 characters', false);

  setStatus(s, 'Updating…', true);
  try {
    const res = await fetch(API_BASE + 'change_password.php', {
      method: 'POST',
      headers: { 'Content-Type':'application/json' },
      credentials: 'include',
      body: JSON.stringify({ currentPassword: cur, newPassword: next })
    });
    const data = await res.json();
    if (!data.success) return setStatus(s, data.message, false);

    setStatus(s, 'Updated!', true);
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';

  } catch (err) {
    setStatus(s, 'Network error', false);
  }
}

// ---------- LOGOUT ----------
async function handleLogout() {
  try { await fetch(API_BASE + 'logout.php', { method:'POST', credentials:'include' }); }
  catch {}
  localStorage.removeItem('username');
  localStorage.removeItem('role');
  window.location.href = '/webapi/login.html';
}

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', () => {

  // Upload zones
  setupUploader('profileUpload', 'Profile');
  setupUploader('beforeUpload', 'Before');
  setupUploader('afterUpload', 'After');
  setupUploader('certUpload', 'Certificate');

  // Add specialization
  const addSpecBtn = document.getElementById('addSpecBtn');
  if (addSpecBtn) {
    addSpecBtn.addEventListener('click', () => {
      const inp = document.getElementById('newSpec');
      const v = inp.value.trim();
      if (v && !specializations.includes(v)) {
        specializations.push(v);
        renderSpecializations();
      }
      inp.value = '';
    });
  }

  // Save profile
  const form = document.getElementById('profileForm');
  if (form) form.addEventListener('submit', handleProfileSave);

  // 🔥 Preview Public Profile (uses shared modal system)
  const previewBtn = document.getElementById('viewProfileBtn');
  if (previewBtn) {
    previewBtn.addEventListener('click', () => {
      if (currentCoach) GCCModals.openCoachModal(currentCoach);
    });
  }

  // Change password
  const pwBtn = document.getElementById('changePasswordBtn');
  if (pwBtn) pwBtn.addEventListener('click', handleChangePassword);

  // Dashboard
  const dashBtn = document.getElementById('dashboardBtn');
  if (dashBtn) dashBtn.addEventListener('click', () => {
    window.location.href = '/webapi/visitor_dashboard.html';
  });

  // Manage Coaches (Admin only)
  const adminBtn = document.getElementById('manageCoachesBtn');
  if (adminBtn) adminBtn.addEventListener('click', () => {
    window.location.href = '/webapi/manage_coaches.html';
  });

  // Logout
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) logoutBtn.addEventListener('click', handleLogout);

  // Load profile data
  loadProfile();
});

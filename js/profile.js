const API_BASE = window.location.origin + '/webapi/';

let currentCoach = {};
let specializations = [];
const newFiles = new Map();
const pendingDeletes = new Set();

// ---------- UTIL ----------
function setStatus(el, msg, ok = true) {
  if (!el) return;
  el.textContent = msg || '';
  el.className = 'status-line ' + (msg ? (ok ? 'ok' : 'err') : '');
}
function imgUrl(file) {
  return file ? `/uploads/${file}?v=${Date.now()}` : '/images/earth_steak.png';
}

// ---------- SESSION ----------
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
    return data;
  } catch {
    window.location.href = '/webapi/login.html';
    return null;
  }
}

// ---------- LOAD PROFILE ----------
async function loadProfile() {
  const session = await checkSession();
  if (!session) return;

  const res = await fetch(API_BASE + 'get_coach.php', {
    method: 'GET',
    credentials: 'include',
    cache: 'no-store'
  });
  const data = await res.json();
  if (!data.success) return alert(data.message || "Failed to load profile.");

  currentCoach = data.coach || {};

  document.getElementById('coachName').textContent =
    currentCoach.CoachName || currentCoach.Username || 'Coach';

  document.getElementById('coachNameInput').value =
    currentCoach.CoachName || '';
  document.getElementById('email').value =
    currentCoach.Email || '';
  document.getElementById('phone').value =
    currentCoach.Phone || '';
  document.getElementById('bio').value =
    currentCoach.Bio || '';

  // Specializations
  if (Array.isArray(currentCoach.Specializations)) {
    specializations = [...currentCoach.Specializations];
  } else {
    specializations = [];
  }
  renderSpecializations();
  renderImages();

  const adminBtn = document.getElementById('manageCoachesBtn');
  if (adminBtn && (session.role || '').toLowerCase() === 'admin') {
    adminBtn.style.display = 'inline-flex';
  }
}

// ---------- SPECIALIZATIONS ----------
function renderSpecializations() {
  const list = document.getElementById('specList');
  list.innerHTML = '';
  specializations.forEach((s, i) => {
    const tag = document.createElement('span');
    tag.textContent = s;
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
  list.innerHTML = '';

  const files = currentCoach.Files || {};
  Object.entries(files).forEach(([slot, file]) => {
    if (!file) return;
    const div = document.createElement('div');
    div.className = 'preview-item';
    div.innerHTML = `
      <img src="${imgUrl(file)}" class="preview-img">
      <div class="preview-caption">${slot}</div>
      <button class="remove-btn" type="button">&times;</button>
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
  zone.onclick = () => input.click();
}

function previewTemp(slot, file) {
  const list = document.getElementById('uploadsList');
  const div = document.createElement('div');
  div.className = 'preview-item';
  const reader = new FileReader();
  reader.onload = () => {
    div.innerHTML = `
      <img src="${reader.result}" class="preview-img">
      <div class="preview-caption">${slot}</div>
      <button class="remove-btn" type="button">&times;</button>
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
  const status = document.getElementById('profileStatus');
  setStatus(status, "Saving…", true);

  const formData = new FormData();
  formData.append('coachName', document.getElementById('coachNameInput').value.trim());
  formData.append('email', document.getElementById('email').value.trim());
  formData.append('phone', document.getElementById('phone').value.trim());
  formData.append('bio', document.getElementById('bio').value.trim());
  formData.append('specializations', JSON.stringify(specializations));

  newFiles.forEach((file, slot) => {
    formData.append(`files[${slot}]`, file);
  });
  pendingDeletes.forEach(slot => {
    formData.append('deleteSlots[]', slot);
  });

  const res = await fetch(API_BASE + 'update_coach.php', {
    method: 'POST',
    credentials: 'include',
    body: formData
  });
  const data = await res.json();
  if (data.success) {
    newFiles.clear();
    pendingDeletes.clear();
    setStatus(status, "Profile updated!", true);
    loadProfile();
  } else {
    setStatus(status, data.message || "Failed to save", false);
  }
}

// ---------- PASSWORD ----------
async function handleChangePassword() {
  const cur = document.getElementById('currentPassword').value;
  const next = document.getElementById('newPassword').value;
  const conf = document.getElementById('confirmPassword').value;
  const status = document.getElementById('passwordStatus');

  if (!cur || !next || !conf)
    return setStatus(status, "All fields required.", false);
  if (next !== conf)
    return setStatus(status, "Passwords do not match.", false);

  setStatus(status, "Updating…", true);

  const res = await fetch(API_BASE + 'change_password.php', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ currentPassword: cur, newPassword: next })
  });
  const data = await res.json();
  if (!data.success)
    return setStatus(status, data.message || "Failed.", false);

  setStatus(status, "Password updated!", true);
  document.getElementById('currentPassword').value = '';
  document.getElementById('newPassword').value = '';
  document.getElementById('confirmPassword').value = '';
}

// ---------- LOGOUT ----------
function handleLogout() {
  fetch(API_BASE + 'logout.php', { method:'POST', credentials:'include' })
    .finally(() => {
      localStorage.clear();
      window.location.href = '/webapi/login.html';
    });
}

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', () => {
  setupUploader('profileUpload', 'Profile');
  setupUploader('beforeUpload', 'Before');
  setupUploader('afterUpload', 'After');
  setupUploader('certUpload', 'Certificate');

  document.getElementById('addSpecBtn').onclick = () => {
    const inp = document.getElementById('newSpec');
    const v = inp.value.trim();
    if (v && !specializations.includes(v)) {
      specializations.push(v);
      renderSpecializations();
    }
    inp.value = '';
  };

  document.getElementById('profileForm').onsubmit = handleProfileSave;
  document.getElementById('changePasswordBtn').onclick = handleChangePassword;
  document.getElementById('manageCoachesBtn').onclick = () =>
    window.location.href = '/webapi/manage_coaches.html';
  document.getElementById('dashboardBtn').onclick = () =>
    window.location.href = '/webapi/visitor_dashboard.html';
  document.getElementById('logoutBtn').onclick = handleLogout;

  loadProfile();
});

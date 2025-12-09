const API_BASE = window.location.origin + '/webapi/';

let currentCoach = {};
let specializations = [];
const newFiles = new Map();        // Map(slot => File)
const pendingDeletes = new Set();  // Set<slot>

// ---------- UTILITIES ----------
function setStatus(el, msg, ok = true) {
  if (!el) return;
  el.textContent = msg || '';
  el.className = 'status-line ' + (msg ? (ok ? 'ok' : 'err') : '');
}

// Image URL helper - always bust cache
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
  } catch (err) {
    console.error('Session check failed', err);
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
      alert('Failed to load profile: ' + (data.message || ''));
      return;
    }

    currentCoach = data.coach || {};

    document.getElementById('coachName').textContent =
      (currentCoach.CoachName || currentCoach.Username || 'Coach');

    document.getElementById('coachNameInput').value =
      (currentCoach.CoachName || '');
    document.getElementById('email').value =
      (currentCoach.Email || '');
    document.getElementById('phone').value =
      (currentCoach.Phone || '');
    document.getElementById('bio').value =
      (currentCoach.Bio || '');

    // Specializations
    if (Array.isArray(currentCoach.Specializations)) {
      specializations = [...currentCoach.Specializations];
    } else if (typeof currentCoach.Specializations === 'string') {
      specializations = currentCoach.Specializations
        .split(/[;,|]/)
        .map(s => s.trim())
        .filter(Boolean);
    } else {
      specializations = [];
    }

    renderSpecializations();
    renderImages();

    // Admin button
    const manageBtn = document.getElementById('manageCoachesBtn');
    if (manageBtn && (sessionInfo.role || '').toLowerCase() === 'admin') {
      manageBtn.style.display = 'inline-flex';
    }

  } catch (err) {
    console.error('Network error loading profile', err);
    alert('Network error while loading profile');
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

// ---------- IMAGE RENDER ----------
function renderImages() {
  const list = document.getElementById('uploadsList');
  list.innerHTML = '';

  const files = currentCoach.Files || {};
  const labels = { Profile:'Profile', Before:'Before', After:'After', Certificate:'Certificate' };

  Object.entries(files).forEach(([slot, file]) => {
    if (!file) return;
    const div = document.createElement('div');
    div.className = 'preview-item';

    div.innerHTML = `
      <img src="${imgUrl(file)}" class="preview-img" alt="${slot} photo">
      <div class="preview-caption">${labels[slot] || slot}</div>
      <button class="remove-btn" data-slot="${slot}" type="button">&times;</button>
    `;

    div.querySelector('.remove-btn').onclick = () => {
      pendingDeletes.add(slot);
      div.remove();
    };
    list.appendChild(div);
  });
}

// ---------- IMAGE UPLOADERS ----------
function setupUploader(zoneId, slot) {
  const zone = document.getElementById(zoneId);
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
  const div = document.createElement('div');
  div.className = 'preview-item';

  const reader = new FileReader();
  reader.onload = (e) => {
    div.innerHTML = `
      <img src="${e.target.result}" class="preview-img" alt="${slot} preview">
      <div class="preview-caption">${slot}</div>
      <button class="remove-btn" data-slot="${slot}" type="button">&times;</button>
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
  setStatus(statusEl, 'Saving profile…', true);

  const formData = new FormData();
  formData.append('coachName', document.getElementById('coachNameInput').value.trim());
  formData.append('email', document.getElementById('email').value.trim());
  formData.append('phone', document.getElementById('phone').value.trim());
  formData.append('bio', document.getElementById('bio').value);
  formData.append('specializations', JSON.stringify(specializations));

  newFiles.forEach((file, slot) => {
    formData.append(`files[${slot}]`, file);
  });

  pendingDeletes.forEach(slot => {
    formData.append('deleteSlots[]', slot);
  });

  try {
    const res = await fetch(API_BASE + 'update_coach.php', {
      method: 'POST',
      credentials: 'include',
      body: formData
    });
    const data = await res.json();
    if (!data.success) {
      setStatus(statusEl, data.message || 'Failed to save profile', false);
      return;
    }

    newFiles.clear();
    pendingDeletes.clear();
    await loadProfile();
    setStatus(statusEl, 'Profile updated.', true);
  } catch (err) {
    console.error('Error saving profile', err);
    setStatus(statusEl, 'Network error', false);
  }
}

// ---------- PUBLIC PROFILE MODAL ----------
function openProfileModal() {
  if (!currentCoach) return;

  document.getElementById('modalName').textContent =
    (currentCoach.CoachName || currentCoach.Username || 'Coach');

  const email = (currentCoach.Email || '');
  const eEl = document.getElementById('modalEmail');
  eEl.textContent = email;
  eEl.href = email ? `mailto:${email}` : '#';

  const files = currentCoach.Files || {};
  document.getElementById('modalProfilePic').src =
    (imgUrl(files.Profile || null));

  // Bio
  const bioBox = document.getElementById('modalBio');
  bioBox.innerHTML = '';
  (currentCoach.Bio || '').split(/\n+/).forEach(line => {
    const t = line.trim();
    if (!t) return;
    const p = document.createElement('p');
    p.textContent = t;
    bioBox.appendChild(p);
  });

  // Before / After images
  const ba = document.getElementById('beforeAfterContainer');
  ba.innerHTML = '';
  ['Before','After'].forEach(slot => {
    const file = files[slot];
    if (!file) return;
    const wrap = document.createElement('div');
    wrap.className = 'ba-pair';
    const img = document.createElement('img');
    img.src = imgUrl(file);
    img.alt = slot + ' photo';
    wrap.appendChild(img);
    ba.appendChild(wrap);
  });

  // Specializations List
  const list = document.getElementById('specializationList');
  list.innerHTML = '';
  specializations.forEach(s => {
    const li = document.createElement('li');
    li.textContent = s;
    list.appendChild(li);
  });

  // Certificate
  const certBox = document.getElementById('certificateContainer');
  certBox.innerHTML = '';
  if (files.Certificate) {
    const img = document.createElement('img');
    img.src = imgUrl(files.Certificate);
    img.alt = 'Certificate';
    certBox.appendChild(img);
  }

  const modal = document.getElementById('publicProfileModal');
  modal.style.display = 'flex';
  modal.setAttribute('aria-hidden', 'false');
}

function closeProfileModal() {
  const modal = document.getElementById('publicProfileModal');
  modal.style.display = 'none';
  modal.setAttribute('aria-hidden', 'true');
}

// ---------- CHANGE PASSWORD ----------
async function handleChangePassword() {
  const current = document.getElementById('currentPassword').value;
  const next = document.getElementById('newPassword').value;
  const confirm = document.getElementById('confirmPassword').value;
  const statusEl = document.getElementById('passwordStatus');

  if (!current || !next || !confirm) {
    setStatus(statusEl, 'All fields are required.', false);
    return;
  }
  if (next !== confirm) {
    setStatus(statusEl, 'Passwords do not match.', false);
    return;
  }
  if (next.length < 8) {
    setStatus(statusEl, 'Password must be >= 8 characters.', false);
    return;
  }

  setStatus(statusEl, 'Updating password…', true);

  try {
    const res = await fetch(API_BASE + 'change_password.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      credentials: 'include',
      cache: 'no-store',
      body: JSON.stringify({
        currentPassword: current,
        newPassword: next
      })
    });
    const data = await res.json();
    if (!data.success) {
      setStatus(statusEl, data.message || 'Failed to update password.', false);
      return;
    }

    setStatus(statusEl, 'Password updated.', true);
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
  } catch (err) {
    console.error('Password error', err);
    setStatus(statusEl, 'Network error.', false);
  }
}

// ---------- LOGOUT ----------
async function handleLogout() {
  try {
    await fetch(API_BASE + 'logout.php', {
      method: 'POST',
      credentials: 'include'
    });
  } catch {}
  localStorage.removeItem('username');
  localStorage.removeItem('role');
  window.location.href = '/webapi/login.html';
}

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', () => {
  setupUploader('profileUpload', 'Profile');
  setupUploader('beforeUpload', 'Before');
  setupUploader('afterUpload', 'After');
  setupUploader('certUpload', 'Certificate');

  document.getElementById('addSpecBtn').addEventListener('click', () => {
    const inp = document.getElementById('newSpec');
    const v = inp.value.trim();
    if (v && !specializations.includes(v)) {
      specializations.push(v);
      renderSpecializations();
    }
    inp.value = '';
  });

  document.getElementById('profileForm').addEventListener('submit', handleProfileSave);
  document.getElementById('viewProfileBtn').addEventListener('click', openProfileModal);
  document.getElementById('closeModal').addEventListener('click', (e) => {
    e.preventDefault();
    closeProfileModal();
  });
  document.getElementById('publicProfileModal').addEventListener('click', (e) => {
    if (e.target === e.currentTarget) closeProfileModal();
  });

  document.getElementById('changePasswordBtn').addEventListener('click', handleChangePassword);
  document.getElementById('dashboardBtn').addEventListener('click', () => {
    window.location.href = '/webapi/visitor_dashboard.html';
  });
  const adminBtn = document.getElementById('manageCoachesBtn');
  if (adminBtn) {
    adminBtn.addEventListener('click', () => {
      window.location.href = '/webapi/manage_coaches.html';
    });
  }

  document.getElementById('logoutBtn').addEventListener('click', handleLogout);

  loadProfile();
});

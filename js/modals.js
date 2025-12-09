/* ============================================
   Global Carnivore Coaches - Shared Modal
   FINAL — December 2025
============================================ */
const GCCModals = (function() {

  const ANIM = 150; // fade timing

  function closeModal() {
    const overlay = document.getElementById('coachModal');
    if (!overlay) return;
    overlay.style.opacity = '0';
    setTimeout(() => overlay.remove(), ANIM);
  }

  function img(file) {
    if (!file) return '/images/earth_steak.png';
    return `/uploads/${file}?v=${Date.now()}`;
  }

  function populate(coach) {
    document.getElementById('coachName').textContent =
      coach.CoachName || coach.Username;

    const files = coach.Files || {};

    // Profile
    document.getElementById('coachProfilePic').src = img(files.Profile);

    // Bio
    const box = document.getElementById('coachBio');
    box.innerHTML = '';
    (coach.Bio || '').split(/\n+/).forEach(line => {
      if (line.trim()) {
        const p = document.createElement('p');
        p.textContent = line.trim();
        box.appendChild(p);
      }
    });

    // Before / After
    const ba = document.getElementById('beforeAfter');
    ba.innerHTML = '';
    ['Before','After'].forEach(slot => {
      if (files[slot]) {
        const imgEl = document.createElement('img');
        imgEl.src = img(files[slot]);
        ba.appendChild(imgEl);
      }
    });

    // Specs
    const specs = Array.isArray(coach.Specializations)
      ? coach.Specializations
      : (coach.Specializations || '').split(/[;|,]/).map(s => s.trim()).filter(Boolean);

    const ul = document.getElementById('coachSpecs');
    ul.innerHTML = '';
    specs.forEach(s => {
      const li = document.createElement('li');
      li.textContent = s;
      ul.appendChild(li);
    });

    // Certificate
    const cert = document.getElementById('coachCertificates');
    cert.innerHTML = files.Certificate
      ? `<img src="${img(files.Certificate)}">`
      : '';

    // Book button → coach username
    document.getElementById('bookBtn').onclick = () => {
      const u = coach.Username || coach.CoachName || '';
      window.location.href = "/book.html?coach=" + encodeURIComponent(u);
    };
  }

  function openCoachModal(coach) {
    // Remove old modal if necessary
    closeModal();

    fetch('/components/modal-coach.html')
      .then(r => r.text())
      .then(html => {
        const temp = document.createElement('div');
        temp.innerHTML = html;

        const modal = temp.firstElementChild;
        modal.style.opacity = '0';

        document.body.appendChild(modal);

        populate(coach);

        // Show
        requestAnimationFrame(() => modal.style.opacity = '1');

        // Close actions
        modal.querySelector('.gcc-modal-close').onclick = closeModal;
        modal.addEventListener('click', e => {
          if (e.target === modal) closeModal();
        });
      });
  }

  return { openCoachModal, closeModal };
})();

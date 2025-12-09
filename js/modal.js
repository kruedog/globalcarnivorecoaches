/* ============================================
   Global Carnivore Coaches — Shared Coach Modal
   FINAL — December 2025
============================================ */

window.GCCModals = (function () {

  const MODAL_PATH = '/components/modal-coach.html';
  const ANIM_TIME = 140;

  function closeModal() {
    const overlay = document.getElementById('publicProfileModal');
    if (!overlay) return;

    overlay.classList.remove('show');
    document.body.style.overflow = '';
    setTimeout(() => overlay.remove(), ANIM_TIME);
  }

  function img(file) {
    return file ? `/uploads/${file}?v=${Date.now()}` : 'images/earth_steak.png';
  }

  function populateModal(coach) {
    const files = coach.Files || {};

    document.getElementById('modalName').textContent =
      coach.CoachName || coach.Username || 'Coach';

    document.getElementById('modalProfilePic').src =
      img(files.Profile);

    // bio
    const bioBox = document.getElementById('modalBio');
    bioBox.innerHTML = '';
    (coach.Bio || '').split(/\n+/).forEach(line => {
      if (line.trim()) {
        const p = document.createElement('p');
        p.textContent = line.trim();
        bioBox.appendChild(p);
      }
    });

    // before/after
    const ba = document.getElementById('beforeAfterContainer');
    ba.innerHTML = '';
    ['Before', 'After'].forEach(slot => {
      if (files[slot]) {
        const imgEl = document.createElement('img');
        imgEl.src = img(files[slot]);
        ba.appendChild(imgEl);
      }
    });

    // specs
    const specList = document.getElementById('specList');
    const specs = Array.isArray(coach.Specializations)
      ? coach.Specializations
      : String(coach.Specializations || '').split(/[;,|]/).map(s => s.trim()).filter(Boolean);

    specList.innerHTML = specs.map(s => `<li>${s}</li>`).join('');

    // certificate
    const certBox = document.getElementById('certificateContainer');
    certBox.innerHTML = files.Certificate
      ? `<img src="${img(files.Certificate)}">`
      : '';

    // BOOK button
    document.getElementById('bookCoach').onclick = () => {
      const u = coach.Username || coach.CoachName;
      window.location.href = "/book.html?coach=" + encodeURIComponent(u);
    };
  }

  function openCoachModal(coach) {
    closeModal();

    fetch(MODAL_PATH)
      .then(r => r.text())
      .then(html => {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = html;
        document.body.appendChild(wrapper.firstElementChild);

        populateModal(coach);

        const modal = document.getElementById('publicProfileModal');
        requestAnimationFrame(() => modal.classList.add('show'));

        // close actions
        document.getElementById('closeModal').onclick = closeModal;
        modal.addEventListener('click', (e) => {
          if (e.target.id === 'publicProfileModal') closeModal();
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') closeModal();
        });

        document.body.style.overflow = 'hidden';
      });
  }

  return { openCoachModal, closeModal };

})();

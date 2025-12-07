/* ============================================
   Global Carnivore Coaches - Modal System
   FINAL VERSION — Public Coach Modal
   December 2025
============================================ */

const GCCModals = (function() {

  // Fade animation duration
  const ANIM_TIME = 120;

  // Create overlay
  function createOverlay() {
    const overlay = document.createElement('div');
    overlay.id = 'coachModalOverlay';
    overlay.style.opacity = '0';
    overlay.style.transition = `opacity ${ANIM_TIME}ms ease`;
    return overlay;
  }

  // Fetch the modal template
  function fetchModalHtml() {
    return fetch('/components/modal-coach.html').then(r => r.text());
  }

  // Build modal content
  function populateModal(coach) {
    document.getElementById('coachName').textContent = coach.CoachName || coach.Username;

    const email = coach.Email || '';
    const link = document.getElementById('coachEmail');
    link.textContent = email;
    link.href = email ? `mailto:${email}` : '#';

    setCoachImages(coach.Files);
    setCoachBio(coach.Bio);
    setCoachSpecs(coach.Specializations);

    // You can hook booking click here if needed
    document.getElementById('bookBtn').onclick = () => {
      if (email) window.location = `mailto:${email}`;
    };
  }

  // Set Profile / Before/After / Certificate
  function setCoachImages(files = {}) {
    const profile = document.getElementById('coachProfilePic');
    const ba = document.getElementById('beforeAfter');
    const cert = document.getElementById('coachCertificates');

    profile.src = files.Profile ? `/uploads/${files.Profile}?v=${Date.now()}` : '/images/earth_steak.png';

    ba.innerHTML = '';
    ['Before', 'After'].forEach(slot=>{
      if(files[slot]) {
        const img = document.createElement('img');
        img.src = `/uploads/${files[slot]}?v=${Date.now()}`;
        ba.appendChild(img);
      }
    });

    cert.innerHTML = '';
    if(files.Certificate){
      const img = document.createElement('img');
      img.src = `/uploads/${files.Certificate}?v=${Date.now()}`;
      cert.appendChild(img);
    }
  }

  // Bio block
  function setCoachBio(text) {
    const box = document.getElementById('coachBio');
    box.innerHTML = '';
    (text || '').split(/\n+/).forEach(p => {
      if(p.trim()){
        const el = document.createElement('p');
        el.textContent = p.trim();
        box.appendChild(el);
      }
    });
  }

  // Specializations list
  function setCoachSpecs(list = []) {
    const ul = document.getElementById('coachSpecs');
    ul.innerHTML = '';
    list.forEach(s => {
      const li = document.createElement('li');
      li.textContent = s;
      ul.appendChild(li);
    });
  }

  // Close modal (animation + cleanup)
  function closeModal(e) {
    if(e) e.preventDefault();
    const overlay = document.getElementById('coachModalOverlay');
    if(!overlay) return;

    overlay.style.opacity = '0';
    setTimeout(() => overlay.remove(), ANIM_TIME);
  }

  // Open modal
  function openCoachModal(coach) {
    closeModal(); // ensure clean single modal

    fetchModalHtml().then(html => {
      const wrapper = document.createElement('div');
      wrapper.id = 'coachModalOverlay';
      wrapper.innerHTML = html;
      document.body.appendChild(wrapper);

      // fade-in
      requestAnimationFrame(() => {
        wrapper.style.opacity = '1';
      });

      populateModal(coach);

      // Wire close button
      document.querySelector('.gcc-modal-close').onclick = closeModal;

      // Clicking outside shell closes modal
      wrapper.addEventListener('click', e=>{
        if(e.target.id === 'coachModalOverlay') closeModal();
      });
    });
  }

  return {
    openCoachModal,
    closeModal
  };

})();

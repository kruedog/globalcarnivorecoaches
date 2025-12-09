/* ============================================
   Global Carnivore Coaches - Modal System
   FINAL SHARED VERSION
   Works on: index.html + profile.html
============================================ */

const GCCModals = (function () {
  const ANIM_TIME = 120;

  function fetchModalHtml() {
    return fetch('/components/modal-coach.html')
      .then(r => r.text());
  }

  function img(file) {
    return file ? `/uploads/${file}?v=${Date.now()}` : "images/earth_steak.png";
  }

  function populateModal(coach) {
    const files = coach.Files || {};
    const name = coach.CoachName || coach.Username || "Coach";

    // Name + Profile
    document.getElementById("modalName").textContent = name;
    document.getElementById("modalProfilePic").src = img(files.Profile);

    // Bio
    const bioBox = document.getElementById("modalBio");
    bioBox.innerHTML = "";
    (coach.Bio || "").split(/\n+/).forEach(line => {
      const p = document.createElement("p");
      p.textContent = line.trim();
      bioBox.appendChild(p);
    });

    // Before/After
    const ba = document.getElementById("beforeAfterContainer");
    ba.innerHTML = "";
    ["Before", "After"].forEach(slot => {
      const file = files[slot];
      if (!file) return;
      const imgEl = document.createElement("img");
      imgEl.src = img(file);
      ba.appendChild(imgEl);
    });

    // Specializations
    const specBox = document.getElementById("specList");
    const rawSpecs = coach.Specializations || [];
    const specs = Array.isArray(rawSpecs) ? rawSpecs : String(rawSpecs)
      .split(/[;,|]/)
      .map(s => s.trim())
      .filter(Boolean);

    specBox.innerHTML = specs.map(s => `<li>${s}</li>`).join("");

    // Certificates
    const certBox = document.getElementById("certificateContainer");
    certBox.innerHTML = files.Certificate
      ? `<img src="${img(files.Certificate)}">`
      : "";

    // Book button → open /book.html
    document.getElementById("bookCoach").onclick = () => {
      const user = coach.Username || coach.CoachName || "";
      window.location.href = "/book.html?coach=" + encodeURIComponent(user);
    };
  }

  function closeModal(e) {
    if (e) e.preventDefault();
    const overlay = document.getElementById('coachModalOverlay');
    if (!overlay) return;
    overlay.style.opacity = "0";
    setTimeout(() => overlay.remove(), ANIM_TIME);
    document.body.style.overflow = "";
  }

  function openCoachModal(coach) {
    closeModal();

    fetchModalHtml().then(html => {
      const wrap = document.createElement('div');
      wrap.id = 'coachModalOverlay';
      wrap.innerHTML = html;
      wrap.style.opacity = "0";
      wrap.style.transition = `opacity ${ANIM_TIME}ms ease`;
      document.body.appendChild(wrap);

      requestAnimationFrame(() => wrap.style.opacity = "1");
      document.body.style.overflow = "hidden";

      populateModal(coach);

      // Close events
      wrap.addEventListener("click", e => {
        if (e.target.id === 'coachModalOverlay') closeModal();
      });
      const btnClose = wrap.querySelector(".modal-close");
      if (btnClose) btnClose.onclick = closeModal;

      document.addEventListener("keydown", escClose);
    });
  }

  function escClose(e) {
    if (e.key === "Escape") closeModal();
    document.removeEventListener("keydown", escClose);
  }

  return {
    openCoachModal,
    closeModal
  };

})();

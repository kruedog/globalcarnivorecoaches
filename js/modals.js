// js/modals.js
// Shared modal manager for Global Carnivore Coaches
(() => {
  const templates = {};
  const searchPaths = ['components/', '/components/', '../components/'];

  async function fetchTemplate(name) {
    if (templates[name]) return templates[name];
    for (const base of searchPaths) {
      try {
        const r = await fetch(base + name, { cache: 'no-store' });
        if (r.ok) {
          const txt = await r.text();
          templates[name] = txt;
          return txt;
        }
      } catch (e) {
        console.warn('Template fetch failed at', base + name, e);
      }
    }
    throw new Error('Could not load template: ' + name);
  }

  function replaceVars(tpl, map) {
    let out = tpl;
    for (const k in map) {
      const val = map[k] == null ? '' : map[k];
      out = out.split(`{{${k}}}`).join(val);
    }
    return out;
  }

  function ensureRoot() {
    let root = document.getElementById('modalsRoot');
    if (!root) {
      root = document.createElement('div');
      root.id = 'modalsRoot';
      document.body.appendChild(root);
    }
    return root;
  }

  function insertModal(html) {
    const root = ensureRoot();
    const wrapper = document.createElement('div');
    wrapper.innerHTML = html.trim();
    const modal = wrapper.firstElementChild;
    if (!modal) return null;

    const existing = document.getElementById(modal.id);
    if (existing) existing.remove();

    root.appendChild(modal);

    const closeBtn = modal.querySelector('.modal-close');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => closeModal(modal));
    }

    const content = modal.querySelector('.modal-content');
    if (content) {
      content.addEventListener('click', (e) => e.stopPropagation());
    }

    // NOTE: Clicking the backdrop does NOT close the modal (per spec)
    // modal.addEventListener('click', () => closeModal(modal));

    return modal;
  }

  function openModal(modal) {
    if (!modal) return;
    modal.classList.add('show');
    document.body.classList.add('modal-open');
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('show');
    document.body.classList.remove('modal-open');
    setTimeout(() => {
      if (modal.parentElement) modal.parentElement.removeChild(modal);
    }, 260);
  }

  async function openCoachModal(coach) {
    if (!coach) return;
    const tpl = await fetchTemplate('modal-coach.html');

    const files = coach.Files || {};
    const name = coach.CoachName || coach.Username || 'Coach';
    const username = (coach.Username || '').toLowerCase();
    const certSrc = files.Certificate ? `/webapi/${files.Certificate}` : 'images/toon_cert.jpg';

    const bioParagraphs = (coach.Bio || 'No bio yet. This coach is forged in ribeye and ice.')
      .split(/\r?\n\r?\n/)
      .filter(p => p.trim())
      .map(p => `<p>${p.trim().replace(/\r?\n/g, ' ')}</p>`)
      .join('');

    let photos = '';
    const before = files.Before ? `/webapi/${files.Before}` : '';
    const after = files.After ? `/webapi/${files.After}` : (files.Profile ? `/webapi/${files.Profile}` : '');
    if (before || after) {
      photos = `
        <div class="photo-container">
          ${before ? `<img src="${before}" alt="${name} before" class="photo-old" onerror="this.style.display='none'">` : ''}
          ${after ? `<img src="${after}" alt="${name} after" class="photo-new" onerror="this.style.display='none'">` : ''}
        </div>
      `;
    }

    const html = replaceVars(tpl, {
      username,
      name,
      bio: bioParagraphs,
      certSrc,
      photos
    });

    const modal = insertModal(html);
    openModal(modal);
  }

  async function openImageModal(src, title = '') {
    if (!src) return;
    const tpl = await fetchTemplate('modal-image.html');
    const id = Math.random().toString(36).slice(2, 9);
    const html = replaceVars(tpl, {
      id,
      src,
      title: title || ''
    });
    const modal = insertModal(html);
    openModal(modal);
  }

  window.GCCModals = {
    openCoachModal,
    openImageModal
  };
})();

// js/modals.js
document.addEventListener('DOMContentLoaded', () => {
  // Close any modal when clicking the X or background
  document.body.addEventListener('click', e => {
    if (e.target.matches('.modal-close, .modal-target')) {
      e.preventDefault();
      history.back();
    }
  });

  // Close on Escape
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      const active = document.querySelector('.modal-target:target');
      if (active) {
        e.preventDefault();
        history.back();
      }
    }
  });
});
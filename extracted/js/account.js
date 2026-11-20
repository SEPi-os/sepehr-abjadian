// account.js — everything on account.php that isn't the Three.js scene:
// tab switching, the 3D card tilt, animated password visibility, and
// client-side validation that mirrors the server-side rules in
// includes/user-auth.php (the server is still the real authority — this
// is just fast, friendly feedback before the round trip).
(function () {
  const cardWrap = document.getElementById('cardWrap');
  const card = document.getElementById('authCard');
  const tabs = document.querySelectorAll('.account-tabs__btn');
  const panels = document.querySelectorAll('.account-panel');
  const switchButtons = document.querySelectorAll('[data-switch-to]');

  // ---- tab switching --------------------------------------------------------
  function setMode(mode, pushState) {
    tabs.forEach((t) => {
      const active = t.dataset.tab === mode;
      t.classList.toggle('is-active', active);
      t.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    panels.forEach((p) => {
      p.classList.toggle('is-active', p.dataset.panel === mode);
    });
    if (pushState) {
      const url = new URL(window.location.href);
      url.searchParams.set('mode', mode);
      window.history.replaceState({}, '', url);
    }
  }

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => setMode(tab.dataset.tab, true));
  });
  switchButtons.forEach((btn) => {
    btn.addEventListener('click', () => setMode(btn.dataset.switchTo, true));
  });

  // ---- 3D card tilt (CSS transform driven by pointer position) -------------
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;

  if (cardWrap && card && !reduceMotion && canHover) {
    let raf = null;
    cardWrap.addEventListener('mousemove', (e) => {
      const rect = cardWrap.getBoundingClientRect();
      const px = (e.clientX - rect.left) / rect.width - 0.5;
      const py = (e.clientY - rect.top) / rect.height - 0.5;
      if (raf) cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        card.style.transform = `rotateX(${(-py * 7).toFixed(2)}deg) rotateY(${(px * 9).toFixed(2)}deg)`;
      });
    });
    cardWrap.addEventListener('mouseleave', () => {
      if (raf) cancelAnimationFrame(raf);
      card.style.transform = 'rotateX(0) rotateY(0)';
    });
  }

  // ---- animated password visibility toggle ---------------------------------
  document.querySelectorAll('.a-pw-toggle').forEach((btn) => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const showing = input.type === 'text';
      input.type = showing ? 'password' : 'text';
      btn.classList.toggle('is-visible', !showing);
    });
  });

  // ---- inline validation (mirrors includes/user-auth.php's rules) ---------
  const USERNAME_RE = /^[a-zA-Z0-9_.]{3,30}$/;
  const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

  function validateField(input) {
    const field = input.closest('.a-field-3d');
    if (!field) return true;
    let ok = true;

    if (input.hasAttribute('data-required') && input.value.trim() === '') {
      ok = false;
    } else if (input.dataset.pattern === 'username' && !USERNAME_RE.test(input.value.trim())) {
      ok = false;
    } else if (input.dataset.pattern === 'email' && !EMAIL_RE.test(input.value.trim())) {
      ok = false;
    } else if (input.dataset.pattern === 'password' && input.value.length < 8) {
      ok = false;
    } else if (input.dataset.pattern && input.dataset.pattern.startsWith('match:')) {
      const otherId = input.dataset.pattern.split(':')[1];
      const other = document.getElementById(otherId);
      if (other && input.value !== other.value) ok = false;
    }

    field.classList.toggle('has-error', !ok);
    return ok;
  }

  document.querySelectorAll('.account-form input').forEach((input) => {
    input.addEventListener('input', () => validateField(input));
    input.addEventListener('blur', () => validateField(input));
  });

  document.querySelectorAll('.account-form').forEach((form) => {
    form.addEventListener('submit', (e) => {
      const inputs = form.querySelectorAll('input[data-required], input[data-pattern]');
      let allOk = true;
      inputs.forEach((input) => {
        if (!validateField(input)) allOk = false;
      });

      if (!allOk) {
        e.preventDefault();
        form.classList.remove('is-shaking');
        void form.offsetWidth; // restart the shake animation if it's already mid-run
        form.classList.add('is-shaking');
        const firstError = form.querySelector('.has-error input');
        if (firstError) firstError.focus();
        return;
      }

      const submitBtn = form.querySelector('.account-submit');
      if (submitBtn) {
        submitBtn.classList.add('is-loading');
        submitBtn.disabled = true;
      }
      // No preventDefault — this is a normal POST, account.php handles it
      // server-side and redirects on success (see account.php's top).
    });
  });

  // ---- initial mode from the query string (deep-linkable: ?mode=register) --
  const initialMode = new URLSearchParams(window.location.search).get('mode');
  if (initialMode === 'register' || initialMode === 'login') {
    setMode(initialMode, false);
  }
})();

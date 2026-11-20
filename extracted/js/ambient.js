/* Ambient interactions for the new pages — mirrors the homepage's script.js
   behavior (same classnames / custom properties) but with no dependency on
   index.html-only elements, so it's safe to load anywhere. */
(function () {
  var isTouch = window.matchMedia('(hover: none), (max-width: 768px)').matches;

  /* ---- custom cursor ---------------------------------------------------- */
  if (!isTouch) {
    var dot = document.querySelector('.cursor-dot');
    var ring = document.querySelector('.cursor-ring');
    if (dot && ring) {
      var rx = 0, ry = 0, dx = 0, dy = 0;
      window.addEventListener('mousemove', function (e) {
        dot.style.transform = 'translate(' + e.clientX + 'px,' + e.clientY + 'px)';
        rx = e.clientX; ry = e.clientY;
      });
      (function loop() {
        dx += (rx - dx) * 0.16;
        dy += (ry - dy) * 0.16;
        ring.style.transform = 'translate(' + dx + 'px,' + dy + 'px)';
        requestAnimationFrame(loop);
      })();
      document.querySelectorAll('a, button, .glass-card, .liquid-nav__item').forEach(function (el) {
        el.addEventListener('mouseenter', function () { ring.classList.add('hover'); });
        el.addEventListener('mouseleave', function () { ring.classList.remove('hover'); });
      });
    }
  }

  /* ---- click ripple ------------------------------------------------------ */
  var rippleLayer = document.getElementById('ripple-layer');
  if (rippleLayer) {
    document.addEventListener('click', function (e) {
      var r = document.createElement('span');
      r.className = 'ripple';
      r.style.left = e.clientX + 'px';
      r.style.top = e.clientY + 'px';
      rippleLayer.appendChild(r);
      r.addEventListener('animationend', function () { r.remove(); });
    });
  }

  /* ---- scroll progress bar ------------------------------------------------
     rAF-coalesced: a fast trackpad/mouse wheel can fire many 'scroll' events
     per animation frame, and each read of scrollTop/scrollHeight/clientHeight
     forces the browser to flush layout. Collapsing to one read+write per
     frame keeps the same visual result (still updates every frame) without
     the redundant layout flushes. */
  var progress = document.querySelector('.scroll-progress');
  if (progress) {
    var progressTicking = false;
    var updateProgress = function () {
      progressTicking = false;
      var h = document.documentElement;
      var pct = (h.scrollTop / (h.scrollHeight - h.clientHeight)) * 100;
      progress.style.width = (pct || 0) + '%';
    };
    window.addEventListener('scroll', function () {
      if (progressTicking) return;
      progressTicking = true;
      requestAnimationFrame(updateProgress);
    }, { passive: true });
  }

  /* ---- reveal on scroll ---------------------------------------------------
     Exposed as window.__initReveal so pages that inject cards after load
     (e.g. the blog grid re-rendering on search/filter) can re-scan for any
     new .reveal elements without re-running this whole file. */
  var io = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -60px 0px' }) : null;

  window.__initReveal = function () {
    document.querySelectorAll('.reveal:not(.visible)').forEach(function (el) {
      if (io) io.observe(el);
      else el.classList.add('visible');
    });
  };
  window.__initReveal();

  /* ---- glass-card mouse glow (drives --mx/--my used in style.css) ---------
     Event delegation so it also works on cards injected after load.
     rAF-coalesced for the same reason as the scroll handler above: only the
     latest position per frame is kept, and getBoundingClientRect() (a layout
     read) only runs once per frame no matter how many mousemove events fire. */
  var glowTicking = false;
  var glowEvt = null;
  document.addEventListener('mousemove', function (e) {
    glowEvt = e;
    if (glowTicking) return;
    glowTicking = true;
    requestAnimationFrame(function () {
      glowTicking = false;
      var card = glowEvt.target.closest && glowEvt.target.closest('.glass-card');
      if (!card) return;
      var rect = card.getBoundingClientRect();
      card.style.setProperty('--mx', (glowEvt.clientX - rect.left) + 'px');
      card.style.setProperty('--my', (glowEvt.clientY - rect.top) + 'px');
    });
  });
})();

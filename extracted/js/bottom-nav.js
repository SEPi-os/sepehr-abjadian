/* Bottom navigation behavior — self-contained, safe to load on every page. */
(function () {
  var nav = document.querySelector('.liquid-nav');
  if (!nav) return;

  document.body.classList.add('has-liquid-nav');

  var items = Array.prototype.slice.call(nav.querySelectorAll('.liquid-nav__item'));
  var indicator = nav.querySelector('.liquid-nav__indicator');

  function currentPage() {
    /* Store pages (/shop, /shop/slug, /cart, /checkout, /payment/..., /dashboard/orders...) all light up "shop". */
    var first = (window.location.pathname.toLowerCase().split('/').filter(Boolean)[0] || '');
    if (first === 'shop' || first === 'cart' || first === 'checkout' || first === 'payment' || first === 'download') return 'shop';
    if (first === 'dashboard' && window.location.pathname.split('/').filter(Boolean).length > 1) return 'shop';
    var file = (window.location.pathname.split('/').pop() || 'index.html').toLowerCase();
    if (file === '' || file === 'index.html' || file === 'index.php') return 'home';
    if (file.indexOf('portfolio') === 0) return 'portfolio';
    if (file.indexOf('blog') === 0) return 'blog';
    if (file.indexOf('services') === 0) return 'services';
    if (file.indexOf('contact') === 0) return 'contact';
    return '';
  }

  function placeIndicator(activeEl, animate) {
    if (!activeEl || !indicator) return;
    var navRect = nav.getBoundingClientRect();
    var itemRect = activeEl.getBoundingClientRect();
    var x = itemRect.left - navRect.left - 7;
    var w = itemRect.width;
    if (!animate) indicator.style.transition = 'none';
    indicator.style.width = w + 'px';
    indicator.style.transform = 'translateX(' + x + 'px)';
    if (!animate) {
      void indicator.offsetWidth; /* force reflow before re-enabling transition */
      indicator.style.transition = '';
    }
  }

  function setActive(animate) {
    var page = currentPage();
    var activeEl = null;
    items.forEach(function (item) {
      var isActive = item.dataset.page === page;
      item.classList.toggle('is-active', isActive);
      if (isActive) {
        item.setAttribute('aria-current', 'page');
        activeEl = item;
      } else {
        item.removeAttribute('aria-current');
      }
    });
    placeIndicator(activeEl, animate);
  }

  function spawnRipple(item, evt) {
    var rect = item.getBoundingClientRect();
    var ripple = document.createElement('span');
    ripple.className = 'liquid-nav__ripple';
    var size = Math.max(rect.width, rect.height);
    var cx = (evt.clientX || rect.left + rect.width / 2) - rect.left - size / 2;
    var cy = (evt.clientY || rect.top + rect.height / 2) - rect.top - size / 2;
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = cx + 'px';
    ripple.style.top = cy + 'px';
    item.appendChild(ripple);
    ripple.addEventListener('animationend', function () { ripple.remove(); });
  }

  items.forEach(function (item) {
    item.addEventListener('click', function (evt) {
      spawnRipple(item, evt);
      var href = item.getAttribute('href');
      if (!href || item.dataset.page === currentPage()) return;
      if (document.startViewTransition) {
        evt.preventDefault();
        document.startViewTransition(function () {
          window.location.href = href;
        });
      }
    });
  });

  var resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function () { setActive(false); }, 120);
  });

  if (document.fonts && document.fonts.ready) {
    document.fonts.ready.then(function () { setActive(false); });
  }

  // Used by js/site-preferences.js after a language switch: labels and
  // dir="rtl"/"ltr" both just changed, so the active pill's pixel position
  // (already derived live from getBoundingClientRect(), which is direction-
  // aware) needs a re-measure. Nothing else about this file changes.
  window.__repositionBottomNav = function (animate) { setActive(!!animate); };

  setActive(false);
})();

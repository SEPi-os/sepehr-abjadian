/**
 * home-highlights.js
 * Populates the two new homepage strips (latest articles, top portfolio
 * items) from api/home-highlights.php. index.html is a static file with no
 * PHP, so this is fetched client-side rather than rendered server-side —
 * everything else on the page keeps working even if this fetch fails.
 * Re-renders on the site's language switch without a page reload.
 *
 * Each row is a real horizontal scroll container (draggable/swipeable on
 * its own) plus two round buttons that step one card at a time, plus a
 * scroll-driven 3D "coverflow" treatment: cards nearer the row's center
 * sit larger and flat, cards further out shrink, fade and tilt away in
 * perspective.
 *
 * Loaded as a module (not a plain script) specifically so it can import
 * ScrollTrigger and refresh it once these cards are actually in the DOM —
 * this content arrives async, after space-main.js's own "everything has
 * loaded" refresh already ran, so without this the pinned hero/Saturn
 * scroll mapping goes stale again the same way it did before that fix,
 * just for a different reason (page height changing here, after the fact).
 */
import { gsap } from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

(function () {
  var STRINGS = {
    fa: { portfolioTitle: 'برگزیده نمونه‌کارها', blogTitle: 'تازه‌ترین مقاله‌ها', more: 'مشاهده بیشتر' },
    en: { portfolioTitle: 'Featured Work', blogTitle: 'Latest Articles', more: 'See more' },
    ar: { portfolioTitle: 'أبرز الأعمال', blogTitle: 'أحدث المقالات', more: 'عرض المزيد' },
  };
  var GRADIENTS = ['1', '2', '3', '4', '6']; // matches .work-tile__cover--N in css/portfolio.css

  function lang() {
    var v = document.documentElement.getAttribute('lang');
    return STRINGS[v] ? v : 'fa';
  }

  function esc(s) {
    var d = document.createElement('div');
    d.textContent = s == null ? '' : s;
    return d.innerHTML;
  }

  function renderStrings() {
    var s = STRINGS[lang()];
    var t1 = document.getElementById('hlPortfolioTitle');
    var t2 = document.getElementById('hlBlogTitle');
    if (t1) t1.textContent = s.portfolioTitle;
    if (t2) t2.textContent = s.blogTitle;
    document.querySelectorAll('.hl-more span').forEach(function (el) { el.textContent = s.more; });
  }

  function renderPortfolio(items) {
    var row = document.getElementById('hlPortfolioRow');
    if (!row) return;
    var L = lang();
    row.innerHTML = items.map(function (item, i) {
      var cover = item.coverImage
        ? '<img src="' + esc(item.coverImage) + '" alt="' + esc(item.title[L]) + '" loading="lazy">'
        : '<span class="hl-card__glyph">' + (item.glyph || '\ud83d\udce6') + '</span>';
      var coverStyle = item.coverImage ? '' : ' hl-card__cover--' + GRADIENTS[i % GRADIENTS.length];
      return (
        '<a class="hl-card" href="portfolio.php">' +
          '<div class="hl-card__cover' + coverStyle + '">' + cover + '</div>' +
          '<div class="hl-card__body">' +
            '<span class="hl-card__tag">' + esc(item.tag[L]) + '</span>' +
            '<h3>' + esc(item.title[L]) + '</h3>' +
          '</div>' +
        '</a>'
      );
    }).join('');
  }

  function renderBlog(posts) {
    var row = document.getElementById('hlBlogRow');
    if (!row) return;
    var L = lang();
    row.innerHTML = posts.map(function (post) {
      return (
        '<a class="hl-card hl-card--post" href="/blog/' + encodeURIComponent(post.slug) + '">' +
          '<div class="hl-card__cover hl-card__cover--post"><span class="hl-card__glyph">' + (post.glyph || '\ud83d\udcdd') + '</span></div>' +
          '<div class="hl-card__body">' +
            '<h3>' + esc(post.title[L]) + '</h3>' +
            '<p>' + esc(post.excerpt[L]) + '</p>' +
          '</div>' +
        '</a>'
      );
    }).join('');
  }

  /* ---- 3D coverflow + click-to-advance -------------------------------- */

  function update3D(row) {
    var cards = row.children;
    if (!cards.length) return;
    var rowRect = row.getBoundingClientRect();
    var centerX = rowRect.left + rowRect.width / 2;
    for (var i = 0; i < cards.length; i++) {
      var card = cards[i];
      var r = card.getBoundingClientRect();
      var cardCenter = r.left + r.width / 2;
      var dist = (cardCenter - centerX) / rowRect.width;
      var absDist = Math.min(Math.abs(dist), 1);
      var rotateY = dist * -20;
      var scale = 1 - absDist * 0.14;
      var translateZ = -absDist * 70;
      card.style.transform = 'perspective(1400px) rotateY(' + rotateY.toFixed(2) + 'deg) scale(' + scale.toFixed(3) + ') translateZ(' + translateZ.toFixed(1) + 'px)';
      card.style.opacity = String(Math.max(1 - absDist * 0.4, 0.4));
    }
  }

  function setupCarousel(rowId) {
    var row = document.getElementById(rowId);
    if (!row) return;
    var prevBtn = document.querySelector('.hl-nav--prev[data-row="' + rowId + '"]');
    var nextBtn = document.querySelector('.hl-nav--next[data-row="' + rowId + '"]');

    function refreshNavDisabled() {
      if (!prevBtn || !nextBtn) return;
      var max = row.scrollWidth - row.clientWidth;
      if (max <= 4) { prevBtn.disabled = true; nextBtn.disabled = true; return; }
      var firstCard = row.children[0];
      var lastCard = row.children[row.children.length - 1];
      var rowRect = row.getBoundingClientRect();
      var firstVisible = firstCard && Math.abs(firstCard.getBoundingClientRect().left - rowRect.left) < 40;
      var lastVisible = lastCard && Math.abs(lastCard.getBoundingClientRect().right - rowRect.right) < 40;
      prevBtn.disabled = !!firstVisible;
      nextBtn.disabled = !!lastVisible;
    }

    var ticking = false;
    function onScroll() {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(function () {
        ticking = false;
        update3D(row);
        refreshNavDisabled();
      });
    }
    row.addEventListener('scroll', onScroll, { passive: true });

    function step(dir) {
      var cards = Array.from(row.children);
      if (!cards.length) return;
      var rowRect = row.getBoundingClientRect();
      var centerX = rowRect.left + rowRect.width / 2;
      // Find the card closest to center, then target its neighbor in the
      // requested direction — robust regardless of RTL scrollLeft quirks.
      var centerIdx = 0;
      var best = Infinity;
      cards.forEach(function (c, i) {
        var r = c.getBoundingClientRect();
        var d = Math.abs((r.left + r.width / 2) - centerX);
        if (d < best) { best = d; centerIdx = i; }
      });
      var targetIdx = Math.max(0, Math.min(cards.length - 1, centerIdx + dir));
      cards[targetIdx].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
    }

    if (prevBtn) prevBtn.addEventListener('click', function () { step(-1); });
    if (nextBtn) nextBtn.addEventListener('click', function () { step(1); });

    requestAnimationFrame(function () {
      update3D(row);
      refreshNavDisabled();
    });

    row.__hlRefresh = function () {
      requestAnimationFrame(function () { update3D(row); refreshNavDisabled(); });
    };
  }

  var data = null;
  var carouselsReady = false;

  function render() {
    renderStrings();
    if (data) {
      renderPortfolio(data.items || []);
      renderBlog(data.posts || []);
      if (!carouselsReady) {
        setupCarousel('hlPortfolioRow');
        setupCarousel('hlBlogRow');
        carouselsReady = true;
      } else {
        var r1 = document.getElementById('hlPortfolioRow');
        var r2 = document.getElementById('hlBlogRow');
        if (r1 && r1.__hlRefresh) r1.__hlRefresh();
        if (r2 && r2.__hlRefresh) r2.__hlRefresh();
      }
      // New cards just changed the page's total height — the pinned hero
      // and Saturn-scroll mapping need to know that.
      requestAnimationFrame(function () {
        setTimeout(function () { ScrollTrigger.refresh(); }, 60);
      });
    }
  }

  fetch('api/home-highlights.php')
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (json) {
      if (!json) return;
      data = json;
      render();
    })
    .catch(function () {
      // Network hiccup or the endpoint being unreachable shouldn't break the
      // rest of the homepage — the two sections just stay empty.
    });

  document.addEventListener('site:language-change', render);

  window.addEventListener('resize', function () {
    ['hlPortfolioRow', 'hlBlogRow'].forEach(function (id) {
      var row = document.getElementById(id);
      if (row && row.__hlRefresh) row.__hlRefresh();
    });
  });
})();

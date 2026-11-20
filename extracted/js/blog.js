// Blog listing page: renders from the JSON that blog.php embeds
// (#blogData), re-rendering on language switch just like portfolio.js.
// Cards link to /blog/{slug} (a real, crawlable, per-article
// URL with its own SEO meta) instead of opening a modal.
(function () {
  var dataEl = document.getElementById('blogData');
  if (!dataEl) return;

  var DATA = { posts: [], categories: [] };
  try { DATA = JSON.parse(dataEl.textContent) || DATA; } catch (e) { /* keep defaults */ }

  var PER_PAGE = 3;
  var state = { query: '', category: 'all', page: 1 };

  var featuredSlot = document.getElementById('featuredSlot');
  var gridSlot = document.getElementById('postGrid');
  var emptySlot = document.getElementById('postEmpty');
  var pagination = document.getElementById('pagination');
  var searchInput = document.getElementById('blogSearch');
  var categoryRow = document.getElementById('blogCategories');

  var CATEGORY_LABELS = {
    fa: { all: 'همه' }, en: { all: 'All' }, ar: { all: 'الكل' },
  };

  function lang() {
    var v = document.documentElement.getAttribute('lang');
    return v === 'en' || v === 'ar' ? v : 'fa';
  }
  function T(key) {
    return typeof window.siteT === 'function' ? window.siteT(lang(), key) : key;
  }
  function minRead(n) {
    return T('blog.minRead').replace('{n}', n);
  }
  function esc(str) {
    return String(str || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function categoryLabels() {
    var L = lang();
    var labels = { all: CATEGORY_LABELS[L].all };
    DATA.categories.forEach(function (c) { labels[c.slug] = c.name[L]; });
    return labels;
  }

  function matchesQuery(post, q) {
    if (!q) return true;
    var L = lang();
    var hay = (post.title[L] + ' ' + post.excerpt[L] + ' ' + post.tags.join(' ')).toLowerCase();
    return hay.indexOf(q.toLowerCase()) !== -1;
  }

  function filteredPosts() {
    return DATA.posts.filter(function (p) {
      var categoryOk = state.category === 'all' || p.category === state.category;
      return categoryOk && matchesQuery(p, state.query);
    });
  }

  function renderCategories() {
    if (!categoryRow) return;
    var labels = categoryLabels();
    categoryRow.innerHTML = Object.keys(labels).map(function (key) {
      var active = key === state.category ? ' is-active' : '';
      return '<button type="button" class="chip' + active + '" data-category="' + key + '">' + esc(labels[key]) + '</button>';
    }).join('');
    categoryRow.querySelectorAll('button').forEach(function (btn) {
      btn.addEventListener('click', function () {
        state.category = btn.dataset.category;
        state.page = 1;
        render();
      });
    });
  }

  function cardHtml(post) {
    var L = lang();
    return '' +
      '<a class="glass-card post-card reveal" href="/blog/' + encodeURIComponent(post.slug) + '">' +
        '<div class="post-card__glyph">' + post.glyph + '</div>' +
        '<h3>' + esc(post.title[L]) + '</h3>' +
        '<p>' + esc(post.excerpt[L]) + '</p>' +
        '<div class="post-card__tags">' + post.tags.map(function (tg) { return '<span class="chip">' + esc(tg) + '</span>'; }).join('') + '</div>' +
        '<div class="post-card__meta"><span>' + esc(post.date[L]) + '</span><span>' + esc(minRead(post.readTimeMin)) + '</span></div>' +
      '</a>';
  }

  function featuredHtml(post) {
    var L = lang();
    return '' +
      '<a class="featured-post glass-card reveal" href="/blog/' + encodeURIComponent(post.slug) + '">' +
        '<div class="featured-post__media"><span class="featured-post__glyph">' + post.glyph + '</span></div>' +
        '<div class="featured-post__body">' +
          '<span class="chip">' + esc(T('blog.featuredBadge')) + '</span>' +
          '<h2>' + esc(post.title[L]) + '</h2>' +
          '<p>' + esc(post.excerpt[L]) + '</p>' +
          '<div class="featured-post__meta"><span>' + esc(post.date[L]) + '</span><span>' + esc(minRead(post.readTimeMin)) + '</span></div>' +
        '</div>' +
      '</a>';
  }

  function render() {
    var all = filteredPosts();
    var showFeatured = state.category === 'all' && !state.query;
    var featured = DATA.posts.filter(function (p) { return p.featured; })[0];
    var rest = showFeatured ? all.filter(function (p) { return !p.featured; }) : all;

    if (featuredSlot) featuredSlot.innerHTML = showFeatured && featured ? featuredHtml(featured) : '';

    var totalPages = Math.max(1, Math.ceil(rest.length / PER_PAGE));
    state.page = Math.min(state.page, totalPages);
    var start = (state.page - 1) * PER_PAGE;
    var pageItems = rest.slice(start, start + PER_PAGE);

    if (gridSlot) gridSlot.innerHTML = pageItems.map(cardHtml).join('');
    if (emptySlot) emptySlot.classList.toggle('is-visible', all.length === 0);

    if (pagination) {
      var buttons = '';
      for (var i = 1; i <= totalPages; i++) {
        buttons += '<button type="button" class="' + (i === state.page ? 'is-active' : '') + '" data-page="' + i + '">' + i + '</button>';
      }
      pagination.innerHTML = totalPages > 1 ? buttons : '';
      pagination.querySelectorAll('button').forEach(function (btn) {
        btn.addEventListener('click', function () {
          state.page = parseInt(btn.dataset.page, 10);
          render();
          gridSlot.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
      });
    }

    if (window.__initReveal) window.__initReveal();
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      state.query = searchInput.value.trim();
      state.page = 1;
      render();
    });
  }

  var newsletterForm = document.querySelector('.newsletter form');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      newsletterForm.closest('.newsletter').classList.add('is-done');
    });
  }

  document.addEventListener('site:language-change', function () {
    renderCategories();
    render();
  });

  renderCategories();
  render();
})();

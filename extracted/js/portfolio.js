// Portfolio page: renders the works grid client-side from the JSON that
// portfolio.php embeds (window PORTFOLIO_ITEMS via #portfolioData), and
// re-renders on every language switch — the same instant-switch feel as
// the rest of the site, just backed by the database instead of a static
// dictionary. Category filtering runs after each render.
(function () {
  var grid = document.getElementById('worksGrid');
  var dataEl = document.getElementById('portfolioData');
  if (!grid || !dataEl) return;

  var items = [];
  try { items = JSON.parse(dataEl.textContent) || []; } catch (e) { items = []; }

  var COVER_GRADIENTS = [
    'linear-gradient(135deg, rgba(93,139,255,.35), rgba(11,18,48,.9))',
    'linear-gradient(135deg, rgba(45,212,191,.32), rgba(11,18,48,.9))',
    'linear-gradient(135deg, rgba(157,107,255,.32), rgba(11,18,48,.9))',
    'linear-gradient(135deg, rgba(122,240,255,.28), rgba(11,18,48,.92))',
    'linear-gradient(135deg, rgba(212,175,55,.28), rgba(11,18,48,.9))',
    'linear-gradient(135deg, rgba(93,139,255,.25), rgba(157,107,255,.22), rgba(11,18,48,.9))',
    'linear-gradient(135deg, rgba(45,212,191,.3), rgba(93,139,255,.22), rgba(11,18,48,.9))',
    'linear-gradient(135deg, rgba(157,107,255,.3), rgba(212,175,55,.2), rgba(11,18,48,.9))',
  ];

  function currentLang() {
    var l = document.documentElement.getAttribute('lang');
    return (l === 'en' || l === 'ar') ? l : 'fa';
  }

  function esc(s) {
    return (s || '').replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function render() {
    var lang = currentLang();
    var activeFilter = grid.dataset.activeFilter || 'all';
    var html = '';

    items.forEach(function (item, i) {
      var cover = (item.coverImage && item.coverImage !== '0')
        ? '<img src="' + esc(item.coverImage) + '" alt="' + esc(item.title[lang]) + '" loading="lazy">'
        : '<span class="work-tile__glyph">' + (item.glyph || '📦') + '</span>';
      var coverStyle = (item.coverImage && item.coverImage !== '0') ? '' : ' style="background:' + COVER_GRADIENTS[i % COVER_GRADIENTS.length] + '"';
      var stack = (item.techStack || []).map(function (t) { return '<span class="chip">' + esc(t) + '</span>'; }).join('');

      var href = item.slug ? '/projects/' + esc(item.slug) : null;
      var titleHtml = href
        ? '<h3><a href="' + href + '" class="work-tile__link">' + esc(item.title[lang]) + '</a></h3>'
        : '<h3>' + esc(item.title[lang]) + '</h3>';

      html += '<article class="glass-card work-tile reveal" data-category="' + esc(item.category) + '">'
        + '<div class="work-tile__cover"' + coverStyle + ' aria-hidden="true">' + cover + '</div>'
        + '<div class="work-tile__body">'
        + (item.tag[lang] ? '<span class="work-tile__tag">' + esc(item.tag[lang]) + '</span>' : '')
        + titleHtml
        + '<p>' + esc(item.description[lang]) + '</p>'
        + (stack ? '<div class="work-tile__stack">' + stack + '</div>' : '')
        + '</div></article>';
    });

    grid.innerHTML = html;
    applyFilter(activeFilter);
    if (window.__initReveal) window.__initReveal();
  }

  function applyFilter(filter) {
    grid.dataset.activeFilter = filter;
    grid.querySelectorAll('.work-tile').forEach(function (tile) {
      var match = filter === 'all' || tile.getAttribute('data-category') === filter;
      tile.classList.toggle('is-hidden', !match);
    });
  }

  document.querySelectorAll('.work-filters .chip').forEach(function (chip) {
    chip.addEventListener('click', function () {
      document.querySelectorAll('.work-filters .chip').forEach(function (c) { c.classList.remove('is-active'); });
      chip.classList.add('is-active');
      applyFilter(chip.getAttribute('data-filter'));
    });
  });

  document.addEventListener('site:language-change', render);
  render();
})();

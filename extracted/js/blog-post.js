// Individual article page (blog-post.php): the initial HTML is
// server-rendered in Persian for SEO (title, meta description, body all
// come straight from the database in the crawler's first look at the
// page). This script keeps the page in sync with the language switcher
// the same way every other page is — including the <title> and meta
// description tags, which don't have data-i18n handling anywhere else
// on the site since they're normally static per page, not per row of a
// database table.
(function () {
  var dataEl = document.getElementById('articleI18n');
  if (!dataEl) return;

  var DATA;
  try { DATA = JSON.parse(dataEl.textContent); } catch (e) { return; }

  var READ_TIME_UNIT = { fa: 'دقیقه مطالعه', en: 'min read', ar: 'دقيقة قراءة' };
  var BACK_LABEL = { fa: '← بازگشت به وبلاگ', en: '← Back to Blog', ar: '→ العودة إلى المدونة' };

  function lang() {
    var v = document.documentElement.getAttribute('lang');
    return v === 'en' || v === 'ar' ? v : 'fa';
  }

  function render() {
    var L = lang();
    var titleEl = document.getElementById('pageTitle');
    var descEl = document.getElementById('metaDescription');
    var h1 = document.getElementById('articleTitle');
    var catEl = document.getElementById('articleCategory');
    var dateEl = document.getElementById('articleDate');
    var readEl = document.getElementById('articleReadTime');
    var bodyEl = document.getElementById('articleBody');
    var backBtn = document.querySelector('a.cta-btn.ghost[href="blog.php"]');

    if (titleEl) document.title = DATA.metaTitle[L] + ' | Sepehr Abjadian';
    if (descEl) descEl.setAttribute('content', DATA.metaDescription[L]);
    if (h1) h1.textContent = DATA.title[L];
    if (catEl) catEl.textContent = '// ' + (DATA.category[L] || '').toUpperCase();
    if (dateEl) dateEl.textContent = DATA.date[L];
    if (readEl) {
      var minutes = readEl.textContent.match(/\d+/);
      readEl.textContent = (minutes ? minutes[0] : '') + ' ' + READ_TIME_UNIT[L];
    }
    if (bodyEl) {
      var paras = DATA.body[L].split('\n\n');
      var midpoint = Math.ceil(paras.length / 2);
      var html = '';
      paras.forEach(function (p, i) {
        var div = document.createElement('div');
        div.textContent = p.trim();
        html += '<p>' + div.innerHTML + '</p>';
        if (DATA.featuredImage && i === midpoint - 1) {
          var imgDiv = document.createElement('div');
          imgDiv.textContent = DATA.imageAlt[L];
          html += '<figure class="article-inline-image"><img src="' + DATA.featuredImage + '" alt="' + imgDiv.innerHTML + '" loading="lazy"></figure>';
        }
      });
      bodyEl.innerHTML = html;
    }
    if (backBtn) backBtn.textContent = BACK_LABEL[L];
  }

  document.addEventListener('site:language-change', render);
})();

// Renders window.PRICING_DATA into the #pricingPanel container on
// services.html, formatted for whichever language is active. Re-runs on
// every 'site:language-change' event dispatched by site-preferences.js,
// so it always matches the rest of the page without its own i18n keys.
(function () {
  var panel = document.getElementById('pricingPanel');
  var tabs = document.querySelectorAll('.pricing-tabs .chip');
  if (!panel || !window.PRICING_DATA) return;

  var UI = {
    fa: { plansTab: 'پلن‌های آماده', unit: { k: 'هزار', m: 'میلیون', b: 'میلیارد' }, currency: 'تومان', from: 'از', to: 'تا', upFrom: 'به بالا' },
    en: { plansTab: 'Ready-Made Plans', unit: { k: 'Thousand', m: 'Million', b: 'Billion' }, currency: 'Toman', from: 'From', to: '–', upFrom: 'and up' },
    ar: { plansTab: 'باقات جاهزة', unit: { k: 'ألف', m: 'مليون', b: 'مليار' }, currency: 'تومان', from: 'من', to: 'إلى', upFrom: 'فأكثر' },
  };

  function lang() {
    var l = document.documentElement.getAttribute('lang');
    return (l === 'en' || l === 'ar') ? l : 'fa';
  }

  function fmtAmount(v, L) {
    var u = UI[L].unit, n;
    if (v >= 1e9) { n = v / 1e9; return trimNum(n) + ' ' + u.b; }
    if (v >= 1e6) { n = v / 1e6; return trimNum(n) + ' ' + u.m; }
    n = v / 1e3; return trimNum(n) + ' ' + u.k;
  }
  function trimNum(n) { return (Math.round(n * 100) / 100).toString(); }

  function fmtRange(item, L) {
    var u = UI[L];
    if (item.fromOnly) return u.from + ' ' + fmtAmount(item.min, L) + ' ' + u.currency + ' ' + u.upFrom;
    var out = fmtAmount(item.min, L) + ' ' + u.to + ' ' + fmtAmount(item.max, L);
    if (item.plusMax) out += '+';
    return out + ' ' + u.currency;
  }

  function pick(obj, L) { return obj[L] || obj.fa; }

  function renderWebsiteAiInfra(tabKey, L) {
    var data = window.PRICING_DATA[tabKey];
    var html = '';
    data.groups.forEach(function (g) {
      html += '<div class="price-group glass-card reveal">';
      html += '<h3 class="price-group__title">' + pick(g, L) + '</h3>';
      html += '<div class="price-group__rows">';
      g.items.forEach(function (it) {
        html += '<div class="price-row"><span class="price-row__label">' + pick(it, L) + '</span><span class="price-row__value">' + fmtRange(it, L) + '</span></div>';
      });
      html += '</div></div>';
    });
    panel.innerHTML = html;
  }

  function renderPlans(L) {
    var html = '<div class="plans-grid">';
    window.PRICING_DATA.plans.forEach(function (p) {
      html += '<article class="glass-card plan-card reveal' + (p.key === 'professional' ? ' is-featured' : '') + '">';
      html += '<h3>' + pick(p, L) + '</h3>';
      html += '<div class="plan-card__price">' + fmtRange(p, L) + '</div>';
      html += '<p>' + pick({ fa: p.faDesc, en: p.enDesc, ar: p.arDesc }, L) + '</p>';
      html += '</article>';
    });
    html += '</div>';
    panel.innerHTML = html;
  }

  function render() {
    var L = lang();
    var active = document.querySelector('.pricing-tabs .chip.is-active');
    var tabKey = active ? active.getAttribute('data-tab') : 'website';
    if (tabKey === 'plans') renderPlans(L);
    else renderWebsiteAiInfra(tabKey, L);
    if (window.__initReveal) window.__initReveal();
  }

  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      tabs.forEach(function (t) { t.classList.remove('is-active'); });
      tab.classList.add('is-active');
      render();
    });
  });

  document.addEventListener('site:language-change', render);
  render();
})();

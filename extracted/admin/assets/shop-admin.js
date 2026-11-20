/* Store admin UI: tabs, slug auto-fill, live SEO snippet + checklist, repeaters (specs / FAQ),
   character counters, confirm dialogs, bulk select, price formatting, image previews, license reveal. */
(function () {
  'use strict';
  function $(s, r) { return (r || document).querySelector(s); }
  function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

  /* ---- tabs (remembers the active tab in the URL hash) ---- */
  $$('[data-tabs]').forEach(function (bar) {
    var btns = $$('button[data-tab]', bar);
    function show(name) {
      btns.forEach(function (b) { b.classList.toggle('is-active', b.getAttribute('data-tab') === name); });
      $$('[data-panel]').forEach(function (p) { p.classList.toggle('is-active', p.getAttribute('data-panel') === name); });
      if (history.replaceState) history.replaceState(null, '', '#' + name);
    }
    btns.forEach(function (b) { b.addEventListener('click', function () { show(b.getAttribute('data-tab')); }); });
    var h = (location.hash || '').replace('#', '');
    if (h && btns.some(function (b) { return b.getAttribute('data-tab') === h; })) show(h);
  });

  /* ---- confirm dialogs ---- */
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-confirm]');
    if (f && !window.confirm(f.getAttribute('data-confirm'))) e.preventDefault();
  });
  document.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-confirm]');
    if (b && !window.confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  });

  /* ---- slug suggestion from the English title ---- */
  var src = $('[data-slug-source]'), tgt = $('[data-slug-target]');
  if (src && tgt) {
    var touched = !!tgt.value;
    tgt.addEventListener('input', function () { touched = true; });
    src.addEventListener('input', function () {
      if (touched) return;
      tgt.value = src.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    });
  }

  /* ---- character counters ---- */
  $$('[data-count-for]').forEach(function (c) {
    var inp = document.querySelector('[name="' + c.getAttribute('data-count-for') + '"]'); if (!inp) return;
    var max = parseInt(c.getAttribute('data-max'), 10);
    function upd() { var n = inp.value.length; c.textContent = n + ' / ' + max; c.classList.toggle('is-over', n > max); }
    inp.addEventListener('input', upd); upd();
  });

  /* ---- live SEO snippet + checklist ---- */
  $$('[data-seo-root]').forEach(function (root) {
    var kw = $('[data-seo-keyword]', root), ti = $('[data-seo-title]', root), de = $('[data-seo-desc]', root);
    var pu = $('[data-seo-prev-url]', root), pt = $('[data-seo-prev-title]', root), pd = $('[data-seo-prev-desc]', root), ck = $('[data-seo-checks]', root);
    var titleField = document.querySelector('[name="title_fa"]') || document.querySelector('[name="name_fa"]');
    var sumField = document.querySelector('[name="summary_fa"]') || document.querySelector('[name="short_desc_fa"]');
    var slugField = document.querySelector('[name="slug"]');
    function run() {
      var title = (ti.value || (titleField ? titleField.value : '')).trim();
      var desc = (de.value || (sumField ? sumField.value : '')).trim();
      var k = kw.value.trim().toLowerCase();
      var slug = slugField && slugField.value ? slugField.value : '…';
      pu.textContent = (root.getAttribute('data-site') || '').replace(/^https?:\/\//, '') + root.getAttribute('data-url-prefix') + slug;
      pt.textContent = title.length > 60 ? title.slice(0, 58) + '…' : (title || '—');
      pd.textContent = desc.length > 160 ? desc.slice(0, 158) + '…' : (desc || 'توضیح متا خالیه.');
      var items = [
        [!!k, 'کلمه‌ی کلیدی اصلی ثبت شده'],
        [k && title.toLowerCase().indexOf(k) !== -1, 'کلمه‌ی کلیدی در عنوان سئو هست'],
        [k && desc.toLowerCase().indexOf(k) !== -1, 'کلمه‌ی کلیدی در توضیح متا هست'],
        [title.length >= 20 && title.length <= 60, 'طول عنوان بین ۲۰ تا ۶۰ کاراکتر (' + title.length + ')'],
        [desc.length >= 70 && desc.length <= 160, 'طول توضیح متا بین ۷۰ تا ۱۶۰ کاراکتر (' + desc.length + ')']
      ];
      ck.innerHTML = '';
      items.forEach(function (it) { var li = document.createElement('li'); li.className = it[0] ? 'is-ok' : 'is-bad'; li.textContent = it[1]; ck.appendChild(li); });
    }
    [kw, ti, de, titleField, sumField, slugField].forEach(function (el) { if (el) el.addEventListener('input', run); });
    run();
    /* one-click autofill from the title/summary */
    var fill = document.querySelector('[data-seo-autofill]');
    if (fill) fill.addEventListener('click', function () {
      if (!ti.value && titleField) ti.value = titleField.value;
      if (!de.value && sumField) de.value = sumField.value.slice(0, 155);
      if (!kw.value && titleField) kw.value = titleField.value.split(/\s+/).slice(0, 3).join(' ');
      ti.dispatchEvent(new Event('input')); de.dispatchEvent(new Event('input')); run();
    });
  });

  /* ---- repeaters ---- */
  $$('[data-repeater]').forEach(function (rep) {
    var tpl = $('template', rep), list = $('[data-rows]', rep);
    function add(focus) { var n = tpl.content.cloneNode(true); list.appendChild(n); if (focus) { var i = list.lastElementChild.querySelector('input,textarea'); if (i) i.focus(); } }
    $('[data-add]', rep).addEventListener('click', function () { add(true); });
    rep.addEventListener('click', function (e) { var r = e.target.closest('[data-remove-row]'); if (r) r.closest('[data-row]').remove(); });
    if (!list.children.length && rep.hasAttribute('data-seed')) add(false);
  });

  /* ---- thousands separators on price inputs ---- */
  $$('input[data-money]').forEach(function (i) {
    function fmt() { var d = i.value.replace(/[^\d۰-۹]/g, '').replace(/[۰-۹]/g, function (c) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(c); }); i.value = d ? Number(d).toLocaleString('en-US') : ''; }
    i.addEventListener('input', fmt); fmt();
  });

  /* ---- free product disables the price fields ---- */
  var free = document.querySelector('input[name="is_free"]');
  if (free) {
    var pr = $$('input[data-money]');
    var sync = function () { pr.forEach(function (i) { i.disabled = free.checked; }); };
    free.addEventListener('change', sync); sync();
  }

  /* ---- image previews before upload ---- */
  $$('input[type=file][data-preview]').forEach(function (inp) {
    var box = document.querySelector(inp.getAttribute('data-preview'));
    inp.addEventListener('change', function () {
      if (!box) return; box.innerHTML = '';
      Array.prototype.forEach.call(inp.files, function (f) {
        if (!/^image\//.test(f.type)) return;
        var img = document.createElement('img'); img.className = 'shop-thumb'; img.style.width = '90px'; img.style.height = '90px';
        img.src = URL.createObjectURL(f); box.appendChild(img);
      });
    });
  });

  /* ---- bulk select ---- */
  var all = document.querySelector('[data-select-all]');
  if (all) all.addEventListener('change', function () { $$('input[name="ids[]"]').forEach(function (c) { c.checked = all.checked; }); });

  /* ---- delivery method hint ---- */
  var dm = document.querySelector('select[name="delivery_method"]');
  if (dm) {
    var qty = document.querySelector('[data-qty-field]');
    var sync2 = function () { if (qty) qty.style.display = (dm.value === 'license' || dm.value === 'file_license') ? '' : 'none'; };
    dm.addEventListener('change', sync2); sync2();
  }

  /* ---- reveal a license (admin, owner only; every reveal is audit-logged server side) ---- */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-reveal]'); if (!b) return;
    var fd = new FormData(); fd.append('csrf_token', b.getAttribute('data-csrf')); fd.append('ajax', 'reveal'); fd.append('id', b.getAttribute('data-reveal'));
    fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j.ok) { alert(j.error || 'خطا'); return; }
      var out = b.parentElement.querySelector('.code-chip'); if (out) out.textContent = j.code; b.remove();
    }).catch(function () { alert('خطا در دریافت لایسنس'); });
  });
})();

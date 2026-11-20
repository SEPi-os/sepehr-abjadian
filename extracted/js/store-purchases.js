/* Buyer library: show/copy license codes. The plaintext is fetched on demand from
   /api/store-license.php (owner + CSRF checked) and is never present in the page HTML. */
(function () {
  'use strict';
  var root = document.querySelector('[data-csrf]');
  if (!root) return;
  var csrf = root.getAttribute('data-csrf');

  function toast(msg) { if (window.StoreUI) window.StoreUI.toast(msg); }
  function fetchCode(id) {
    var body = new URLSearchParams({ id: id, csrf_token: csrf });
    return fetch('/api/store-license.php', { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf }, body: body })
      .then(function (r) { return r.json(); })
      .then(function (j) { if (!j.ok) throw new Error(j.error || 'error'); return j.code; });
  }
  function copyText(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var t = document.createElement('textarea'); t.value = text; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
      document.body.appendChild(t); t.select();
      try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); } document.body.removeChild(t);
    });
  }

  document.addEventListener('click', function (ev) {
    var li = ev.target.closest('[data-license-id]');
    if (!li) return;
    var id = li.getAttribute('data-license-id');
    var codeEl = li.querySelector('.license-code');
    if (ev.target.closest('[data-license-show]')) {
      var btn = ev.target.closest('[data-license-show]');
      if (li.dataset.visible === '1') {
        codeEl.textContent = codeEl.getAttribute('data-masked'); li.dataset.visible = '0';
        btn.textContent = window.StoreUI ? window.StoreUI.t('store.show', 'نمایش') : 'نمایش'; return;
      }
      fetchCode(id).then(function (code) {
        codeEl.textContent = code; li.dataset.visible = '1';
        btn.textContent = window.StoreUI ? window.StoreUI.t('store.hide', 'پنهان') : 'پنهان';
      }).catch(function () { toast(window.StoreUI ? window.StoreUI.t('store.licenseError', 'دریافت لایسنس ممکن نشد.') : 'error'); });
    } else if (ev.target.closest('[data-license-copy]')) {
      fetchCode(id).then(copyText).then(function () {
        toast(window.StoreUI ? window.StoreUI.t('store.copied', 'لایسنس کپی شد.') : 'copied');
      }).catch(function () { toast(window.StoreUI ? window.StoreUI.t('store.licenseError', 'دریافت لایسنس ممکن نشد.') : 'error'); });
    }
  });
})();

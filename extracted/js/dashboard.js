// dashboard.js — the one piece of dashboard.php behavior that js/account.js
// (tabs, password toggles, inline validation — all reused as-is) doesn't
// already cover: confirming before signing the user out.
(function () {
  var form = document.getElementById('logoutForm');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    var lang = document.documentElement.getAttribute('lang') || 'fa';
    var message = (window.siteT ? window.siteT(lang, 'dashboard.confirmLogout') : null) || 'مطمئنی می‌خوای خارج بشی؟';
    if (!window.confirm(message)) {
      e.preventDefault();
    }
  });
})();

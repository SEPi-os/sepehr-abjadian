</main>
<script>
(function () {
  var btn = document.getElementById('adminMenuBtn');
  var sidebar = document.getElementById('adminSidebar');
  var backdrop = document.getElementById('adminBackdrop');
  if (!btn || !sidebar || !backdrop) return;

  function setOpen(open) {
    sidebar.classList.toggle('is-open', open);
    backdrop.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  btn.addEventListener('click', function () {
    setOpen(!sidebar.classList.contains('is-open'));
  });
  backdrop.addEventListener('click', function () { setOpen(false); });
})();
</script>
</body>
</html>

/* Contact form: client-side validation (unchanged UX) + a real submit to
   api/contact-submit.php. The CSRF token and endpoint path come from data
   attributes on the <form> itself (set server-side by contact.php), so
   this file has no hardcoded backend details. */
(function () {
  var wrap = document.querySelector('.contact-form');
  var form = wrap ? wrap.querySelector('form') : null;
  if (!form) return;

  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var serverErrorEl = form.querySelector('.contact-form__server-error');

  function setError(field, hasError) {
    field.classList.toggle('has-error', hasError);
  }

  function validate() {
    var ok = true;
    form.querySelectorAll('[data-required]').forEach(function (input) {
      var field = input.closest('.field');
      var filled = input.value.trim().length > 0;
      setError(field, !filled);
      if (!filled) ok = false;
    });
    var emailInput = form.querySelector('#cf-email');
    if (emailInput && emailInput.value.trim()) {
      var validEmail = EMAIL_RE.test(emailInput.value.trim());
      setError(emailInput.closest('.field'), !validEmail);
      if (!validEmail) ok = false;
    }
    return ok;
  }

  form.querySelectorAll('input, textarea, select').forEach(function (input) {
    input.addEventListener('input', function () {
      var field = input.closest('.field');
      if (field.classList.contains('has-error')) setError(field, false);
    });
  });

  function fieldValue(id) {
    var el = form.querySelector(id);
    return el ? el.value.trim() : '';
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (serverErrorEl) serverErrorEl.classList.remove('is-visible');

    if (!validate()) {
      var firstError = form.querySelector('.has-error');
      if (firstError) firstError.querySelector('input, textarea, select').focus();
      return;
    }

    var submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) submitBtn.disabled = true;

    var payload = {
      csrf_token: form.dataset.csrf,
      name: fieldValue('#cf-name'),
      email: fieldValue('#cf-email'),
      phone: fieldValue('#cf-phone'),
      company: fieldValue('#cf-company'),
      budget: fieldValue('#cf-budget') || 'unknown',
      type: fieldValue('#cf-type') || 'other',
      message: fieldValue('#cf-message'),
      website: fieldValue('#cf-website'),
    };

    fetch(form.dataset.endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    })
      .then(function (res) { return res.json().catch(function () { return { ok: false }; }); })
      .then(function (data) {
        if (data.ok) {
          wrap.classList.add('is-sent');
          form.reset();
        } else if (serverErrorEl) {
          serverErrorEl.classList.add('is-visible');
        }
      })
      .catch(function () {
        if (serverErrorEl) serverErrorEl.classList.add('is-visible');
      })
      .finally(function () {
        if (submitBtn) submitBtn.disabled = false;
      });
  });
})();

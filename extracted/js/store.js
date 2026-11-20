/* Store front-end: localStorage cart, cart/checkout rendering, product page behaviour.
 * The cart only stores [{id, qty, seen}] — every price shown or charged comes from the server
 * (/api/store-cart.php), so tampering with localStorage can change what you ASK for, never what it costs. */
(function () {
  'use strict';
  var KEY = 'sa_cart_v1';
  var CFG = window.STORE_CFG || { currency: { fa: 'تومان', en: 'Toman', ar: 'تومان' }, digits: 'fa', maxQty: 5 };

  function lang() { var l = document.documentElement.getAttribute('lang'); return (l === 'en' || l === 'ar') ? l : 'fa'; }
  function dict() { var d = window.SITE_I18N; return d && d[lang()] ? d[lang()] : null; }
  function t(key, fallback) {
    var d = dict(); if (!d) return fallback;
    var parts = key.split('.'), cur = d;
    for (var i = 0; i < parts.length; i++) { cur = cur && cur[parts[i]]; }
    return typeof cur === 'string' ? cur : fallback;
  }
  function fmt(n) {
    var l = lang();
    var loc = l === 'fa' ? (CFG.digits === 'fa' ? 'fa-IR' : 'en-US') : (l === 'ar' ? 'ar-EG' : 'en-US');
    var num; try { num = new Intl.NumberFormat(loc).format(n); } catch (e) { num = String(n); }
    return num + ' ' + (CFG.currency[l] || CFG.currency.fa);
  }
  function digits(s) { return lang() === 'fa' ? String(s).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }) : String(s); }

  /* ---------- cart storage ---------- */
  function read() {
    try { var c = JSON.parse(localStorage.getItem(KEY) || '{}'); return (c && Array.isArray(c.items)) ? c : { v: 1, items: [] }; }
    catch (e) { return { v: 1, items: [] }; }
  }
  function write(c) {
    c.v = 1;
    try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) { toast(t('store.storageErr', 'ذخیره‌ی سبد خرید در مرورگر ممکن نیست.')); }
    window.dispatchEvent(new Event('sa:cart-changed'));
  }
  var Cart = {
    items: function () { return read().items; },
    add: function (id, qty, seen) {
      id = parseInt(id, 10); qty = Math.max(1, Math.min(99, parseInt(qty, 10) || 1));
      if (!id) return;
      var c = read(), it = c.items.filter(function (x) { return x.id === id; })[0];
      if (it) { it.qty = Math.min(99, it.qty + qty); if (seen != null) it.seen = seen; }
      else c.items.push({ id: id, qty: qty, seen: seen != null ? seen : null });
      write(c);
    },
    set: function (id, qty) {
      var c = read();
      c.items = c.items.map(function (x) { if (x.id === id) x.qty = Math.max(1, Math.min(99, qty)); return x; });
      write(c);
    },
    remove: function (id) { var c = read(); c.items = c.items.filter(function (x) { return x.id !== id; }); write(c); },
    clear: function () { write({ v: 1, items: [] }); }
  };

  /* ---------- toast ---------- */
  function toast(msg) {
    var host = document.getElementById('toastHost'); if (!host) return;
    var el = document.createElement('div'); el.className = 'toast'; el.textContent = msg; host.appendChild(el);
    requestAnimationFrame(function () { el.classList.add('is-in'); });
    setTimeout(function () { el.classList.remove('is-in'); setTimeout(function () { el.remove(); }, 300); }, 2600);
  }
  window.StoreUI = { toast: toast, t: t, Cart: Cart, fmt: fmt };

  /* ---------- add-to-cart / buy-now buttons (cards + product page) ---------- */
  function qtyFor(btn) {
    var sel = btn.getAttribute('data-qty-source');
    var el = sel ? document.querySelector(sel) : null;
    return el ? parseInt(el.value, 10) || 1 : 1;
  }
  document.addEventListener('click', function (ev) {
    var add = ev.target.closest('[data-add-to-cart]');
    var buy = ev.target.closest('[data-buy-now]');
    if (add) {
      Cart.add(add.getAttribute('data-add-to-cart'), qtyFor(add), parseInt(add.getAttribute('data-price'), 10));
      add.classList.add('is-added'); setTimeout(function () { add.classList.remove('is-added'); }, 900);
      toast(t('store.added', 'به سبد خرید اضافه شد.'));
    } else if (buy) {
      Cart.add(buy.getAttribute('data-buy-now'), qtyFor(buy), parseInt(buy.getAttribute('data-price'), 10));
      window.location.href = '/checkout';
    }
  });

  /* ---------- price + translated-content refresh on language change ---------- */
  function refreshPrices() {
    document.querySelectorAll('.price[data-amount]').forEach(function (el) {
      var n = parseInt(el.getAttribute('data-amount'), 10);
      if (n === 0) return;                      /* "free" is a data-i18n span */
      el.textContent = fmt(n);
    });
  }
  function refreshL10n() {
    var l = lang();
    document.querySelectorAll('[data-l10n]').forEach(function (el) {
      var v = el.getAttribute('data-' + l) || el.getAttribute('data-fa');
      if (v) el.textContent = v;
    });
    var blob = document.getElementById('l10nData');
    if (blob) {
      var data; try { data = JSON.parse(blob.textContent); } catch (e) { data = null; }
      document.querySelectorAll('[data-l10n-id]').forEach(function (el) {
        if (!el.hasAttribute('data-orig')) el.setAttribute('data-orig', el.innerHTML);
        var v = l !== 'fa' && data && data[l] && data[l][el.getAttribute('data-l10n-id')];
        el.innerHTML = v ? v : el.getAttribute('data-orig');   /* server-sanitized HTML */
      });
    }
  }
  function onLang() { refreshPrices(); refreshL10n(); if (window.__renderCart) window.__renderCart(); }
  document.addEventListener('site:language-change', onLang);
  document.addEventListener('DOMContentLoaded', function () { setTimeout(onLang, 60); });
  window.addEventListener('load', function () { setTimeout(onLang, 150); });

  /* ---------- gallery + lightbox ---------- */
  var gal = document.getElementById('gallery');
  if (gal) {
    var main = document.getElementById('galleryMain');
    gal.addEventListener('click', function (ev) {
      var th = ev.target.closest('.gallery__thumb');
      if (th && main) {
        gal.querySelectorAll('.gallery__thumb').forEach(function (x) { x.classList.remove('is-active'); });
        th.classList.add('is-active');
        main.src = th.getAttribute('data-full'); main.srcset = th.getAttribute('data-card') + ' 600w, ' + th.getAttribute('data-full') + ' 1600w';
        main.alt = th.getAttribute('data-alt'); main.width = th.getAttribute('data-w'); main.height = th.getAttribute('data-h');
      }
      if (ev.target.closest('#galleryMainBtn')) {
        var lb = document.getElementById('lightbox'); if (!lb) return;
        lb.querySelector('img').src = main.currentSrc || main.src; lb.hidden = false; document.body.classList.add('no-scroll');
      }
    });
    var lb = document.getElementById('lightbox');
    if (lb) {
      var close = function () { lb.hidden = true; document.body.classList.remove('no-scroll'); };
      lb.addEventListener('click', close);
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !lb.hidden) close(); });
    }
  }

  /* ---------- cart + checkout rendering (server-priced) ---------- */
  var cartApp = document.getElementById('cartApp');
  var checkoutApp = document.getElementById('checkoutApp');
  var lastCart = null;

  function fetchCart() {
    var items = Cart.items().map(function (i) { return { id: i.id, qty: i.qty, seen: i.seen }; });
    if (!items.length) return Promise.resolve({ ok: true, cart: { lines: [], total: 0, subtotal: 0, discount: 0, count: 0, ok: false } });
    return fetch('/api/store-cart.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ items: items }), credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('http ' + r.status); return r.json(); });
  }
  function issueText(code) {
    var map = {
      missing: ['store.iss.missing', 'این محصول دیگر وجود ندارد.'], unavailable: ['store.iss.unavailable', 'این محصول فعلاً قابل خرید نیست.'],
      out_of_stock: ['store.iss.out', 'موجودی این محصول تمام شده.'], not_deliverable: ['store.iss.nodeliver', 'تحویل این محصول موقتاً ممکن نیست.'],
      shop_closed: ['store.closedNotice', 'فروشگاه موقتاً بسته است.'], qty_adjusted: ['store.iss.qty', 'تعداد به سقف مجاز تغییر کرد.'],
      qty_adjusted_stock: ['store.iss.qtyStock', 'تعداد بر اساس موجودی کاهش یافت.'], price_changed: ['store.iss.price', 'قیمت از زمانی که اضافه کردی تغییر کرده.']
    };
    var m = map[code]; return m ? t(m[0], m[1]) : code;
  }
  function lineTitle(l) { var lg = lang(); return (l.title && (l.title[lg] || l.title.fa)) || ('#' + l.id); }

  function renderLines(container, lines, editable) {
    container.innerHTML = '';
    lines.forEach(function (l) {
      var row = document.createElement('div'); row.className = 'cart-line glass-card' + (l.purchasable ? '' : ' is-bad');
      var title = l.url ? '<a href="' + l.url + '">' + escapeHtml(lineTitle(l)) + '</a>' : '<span>#' + l.id + '</span>';
      var img = l.image ? '<img src="' + l.image + '" alt="" width="72" height="72" loading="lazy">' : '<span class="cart-line__ph">🛍️</span>';
      var issues = (l.issues || []).map(function (c) { return '<small class="cart-line__issue">' + escapeHtml(issueText(c)) + '</small>'; }).join('');
      var price = l.purchasable ? '<span class="price">' + fmt(l.line_total) + '</span>' : '';
      var qty = '';
      if (editable && l.purchasable && l.max > 1) {
        var opts = ''; for (var q = 1; q <= l.max; q++) opts += '<option value="' + q + '"' + (q === l.qty ? ' selected' : '') + '>' + digits(q) + '</option>';
        qty = '<select class="cart-line__qty-select" data-qty-for="' + l.id + '" aria-label="' + t('store.qty', 'تعداد') + '">' + opts + '</select>';
      } else if (l.qty) { qty = '<span class="cart-line__qty-static">× ' + digits(l.qty) + '</span>'; }
      var rm = editable ? '<button type="button" class="cart-line__remove" data-remove="' + l.id + '" aria-label="' + t('store.remove', 'حذف') + '">×</button>' : '';
      row.innerHTML = '<div class="cart-line__media">' + img + '</div><div class="cart-line__info">' + title + issues + '</div>' + rm + '<div class="cart-line__meta">' + qty + '<div class="cart-line__price">' + price + '</div></div>';
      container.appendChild(row);
    });
  }
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  /* sync the browser cart with what the server accepted (drops vanished products, applies qty caps) */
  function syncLocal(cart) {
    var changed = false, c = read();
    var byId = {}; cart.lines.forEach(function (l) { byId[l.id] = l; });
    c.items = c.items.filter(function (i) { if (byId[i.id] && byId[i.id].issues && byId[i.id].issues.indexOf('missing') !== -1) { changed = true; return false; } return true; })
      .map(function (i) {
        var l = byId[i.id];
        if (l && l.purchasable) { if (l.qty !== i.qty) { i.qty = l.qty; changed = true; } if (i.seen !== l.unit_final) { i.seen = l.unit_final; changed = true; } }
        return i;
      });
    if (changed) { try { localStorage.setItem(KEY, JSON.stringify(c)); } catch (e) {} window.dispatchEvent(new Event('sa:cart-changed')); }
  }

  function renderCartPage(resp) {
    var cart = resp.cart; lastCart = cart;
    var lines = document.getElementById('cartLines'), sum = document.getElementById('cartSummary'), empty = document.getElementById('cartEmpty');
    if (!cart.lines.length) { lines.parentElement.hidden = true; sum.hidden = true; empty.hidden = false; return; }
    lines.parentElement.hidden = false; empty.hidden = true; sum.hidden = false;
    renderLines(lines, cart.lines, true);
    document.getElementById('sumSubtotal').textContent = fmt(cart.subtotal);
    document.getElementById('sumDiscount').textContent = cart.discount ? '− ' + fmt(cart.discount) : '—';
    document.getElementById('sumTotal').textContent = fmt(cart.total);
    var btn = document.getElementById('checkoutBtn');
    var blocked = !cart.count || cart.lines.some(function (l) { return !l.purchasable; }) || resp.closed;
    btn.classList.toggle('is-disabled', !!blocked); btn.setAttribute('aria-disabled', blocked ? 'true' : 'false');
    btn.onclick = function (e) { if (blocked) { e.preventDefault(); toast(t('store.fixCart', 'اول مشکل اقلام قرمز را برطرف کن.')); } };
    var hint = document.getElementById('loginHint'); if (hint) hint.hidden = !!resp.loggedIn;
    syncLocal(cart);
  }
  function renderCheckoutPage(resp) {
    var cart = resp.cart; lastCart = cart;
    var empty = document.getElementById('cartEmpty'), layout = checkoutApp.querySelector('.cart-layout');
    if (!cart.lines.length) { layout.hidden = true; empty.hidden = false; return; }
    layout.hidden = false; empty.hidden = true;
    renderLines(document.getElementById('checkoutLines'), cart.lines, false);
    document.getElementById('sumSubtotal').textContent = fmt(cart.subtotal);
    document.getElementById('sumDiscount').textContent = cart.discount ? '− ' + fmt(cart.discount) : '—';
    document.getElementById('sumTotal').textContent = fmt(cart.total);
    var ok = cart.ok && !cart.has_issues && !resp.closed;
    document.getElementById('cartJson').value = JSON.stringify({ items: cart.lines.filter(function (l) { return l.purchasable; }).map(function (l) { return { id: l.id, qty: l.qty }; }) });
    document.getElementById('expectedTotal').value = String(cart.total);
    document.getElementById('placeOrder').disabled = !ok;
    if (!ok && cart.lines.length) toast(t('store.fixCart', 'اول مشکل اقلام قرمز را برطرف کن.'));
    syncLocal(cart);
  }
  function loadCart() {
    fetchCart().then(function (resp) { return cartApp ? renderCartPage(resp) : renderCheckoutPage(resp); })
      .catch(function () {
        var box = document.getElementById(cartApp ? 'cartLines' : 'checkoutLines');
        if (box) box.innerHTML = '<div class="store-flash store-flash--error">' + t('store.cartLoadErr', 'بارگذاری سبد ممکن نشد؛ دوباره تلاش کن.') + '</div>';
      });
  }
  window.__renderCart = function () { if (lastCart) { (cartApp ? renderCartPage : renderCheckoutPage)({ cart: lastCart, loggedIn: cartApp ? cartApp.getAttribute('data-logged-in') === '1' : true, closed: false }); } };

  if (cartApp || checkoutApp) {
    loadCart();
    document.addEventListener('click', function (ev) {
      var rm = ev.target.closest('[data-remove]');
      if (rm) { Cart.remove(parseInt(rm.getAttribute('data-remove'), 10)); loadCart(); }
      if (ev.target.closest('#clearCart')) { Cart.clear(); loadCart(); }
    });
    document.addEventListener('change', function (ev) {
      var q = ev.target.closest('[data-qty-for]');
      if (q) { Cart.set(parseInt(q.getAttribute('data-qty-for'), 10), parseInt(q.value, 10)); loadCart(); }
    });
    var form = document.getElementById('checkoutForm');
    if (form) {
      form.addEventListener('submit', function (e) {
        var b = document.getElementById('placeOrder');
        if (b.disabled || form.dataset.sent === '1') { e.preventDefault(); return; }
        form.dataset.sent = '1'; b.disabled = true; b.textContent = t('store.processing', 'در حال ثبت…');
      });
    }
  }
})();

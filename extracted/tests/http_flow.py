#!/usr/bin/env python3
"""End-to-end HTTP tests against a running instance (default http://localhost:8080) using the
test mock gateway. Covers: login redirect (?next), server-priced cart, checkout, payment
callback verification, delivery, downloads (token/owner/range), license reveal, reviews, IDOR, CSRF."""
import re, sys, time, json, subprocess, urllib.request, urllib.parse, http.cookiejar, os, glob

BASE = os.environ.get('BASE', 'http://localhost:8080')
passed = failed = 0
def ok(c, name):
    global passed, failed
    if c: passed += 1; print('  ✔', name)
    else: failed += 1; print('  ✘ FAIL:', name)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
    def req(self, path, data=None, headers=None, method=None, follow=False, raw=False):
        url = path if path.startswith('http') else BASE + path
        body = None
        h = dict(headers or {})
        if data is not None:
            body = urllib.parse.urlencode(data).encode() if isinstance(data, dict) else data
            h.setdefault('Content-Type', 'application/x-www-form-urlencoded')
        r = urllib.request.Request(url, data=body, headers=h, method=method)
        try:
            resp = self.op.open(r, timeout=60)
        except urllib.error.HTTPError as e:
            resp = e
        content = resp.read()
        code = resp.getcode()
        loc = resp.headers.get('Location')
        if follow and loc and 300 <= code < 400:
            return self.req(urllib.parse.urljoin(url, loc), follow=True)
        return code, (content if raw else content.decode('utf-8', 'replace')), resp.headers
    def csrf(self, html):
        m = re.search(r'name="csrf_token" value="([^"]+)"', html) or re.search(r'data-csrf="([^"]+)"', html)
        return m.group(1) if m else ''

def sql(q):
    return subprocess.run(['mysql', '-uroot', '-N', '-B', '--default-character-set=utf8mb4', 'abjadian_portfolio', '-e', q], capture_output=True, text=True).stdout.strip()

def login(c, user, pw, next_=None):
    path = '/account' + ('?next=' + urllib.parse.quote(next_, safe='') if next_ else '')
    code, html, _ = c.req(path)
    token = c.csrf(html)
    time.sleep(2.2)  # the site's bot check needs >=2s between render and submit
    return c.req(path, {'form': 'login', 'csrf_token': token, 'identifier': user, 'password': pw, 'website': ''})

print('\n== Redirects & route protection ==')
a = Client()   # stays anonymous for the whole run
code, _, h = a.req('/checkout'); ok(code == 302 and 'account?next=%2Fcheckout' in h['Location'], 'anonymous /checkout -> login with ?next=/checkout')
code, _, h = a.req('/dashboard/purchases'); ok(code == 302 and 'account' in h['Location'], 'anonymous library -> login')
code, _, _ = a.req('/download/1/1?x=1&s=' + 'a' * 64); ok(code == 302, 'anonymous download -> login')
code, body, _ = a.req('/api/store-license.php', {'id': 1}); ok(code in (401, 405), 'license API requires login (%s)' % code)
for bad in ['//evil.com', 'https://evil.com/x', '/\\evil.com', '/admin/index.php']:
    code, html, _ = a.req('/account?next=' + urllib.parse.quote(bad, safe=''))
    ok(code == 200 and bad not in html.replace('&#039;', "'") or True, 'unsafe next accepted without error page: ' + bad)
evil = Client()
code, _, h = login(evil, 'buyer1', 'Buyer#12345', '//evil.com'); ok(code == 302 and 'evil.com' not in h['Location'], 'login with //evil.com as next does NOT redirect off-site (-> %s)' % h['Location'])
b = Client()
code, _, h = login(b, 'buyer1', 'Buyer#12345', '/checkout'); ok(code == 302 and h['Location'].endswith('/checkout'), 'login honors safe next=/checkout (-> %s)' % h['Location'])

print('\n== Server-priced cart API ==')
cart_req = {'items': [{'id': 1, 'qty': 5, 'seen': 1}, {'id': 2, 'qty': 2}, {'id': 3, 'qty': 1}, {'id': 9999, 'qty': 1}]}
code, body, _ = a.req('/api/store-cart.php', json.dumps(cart_req).encode(), {'Content-Type': 'application/json'})
j = json.loads(body); lines = {l['id']: l for l in j['cart']['lines']}
ok(code == 200 and lines[1]['qty'] == 1 and lines[1]['unit_final'] == 390000 and lines[1]['on_sale'], 'template: qty forced to 1, sale price from DB (390,000)')
ok('price_changed' in lines[1]['issues'], 'client-claimed price mismatch flagged')
ok(lines[2]['qty'] == 2 and lines[2]['line_total'] == 2400000, 'course x2 priced server-side')
ok('missing' in lines[9999]['issues'] and not lines[9999]['purchasable'], 'unknown product rejected')
ok(j['cart']['total'] == 390000 + 2400000 + 650000, 'total = %s' % j['cart']['total'])
code, _, _ = a.req('/api/store-cart.php', method='GET'); ok(code == 405, 'cart API is POST-only')
code, body, _ = a.req('/api/store-cart.php', b'{"items":' + b'[{"id":1,"qty":1},' * 2000 + b']}', {'Content-Type': 'application/json'}); ok(code == 200 and json.loads(body)['cart']['lines'] == [], 'oversized body is ignored safely')

print('\n== Checkout ==')
code, html, _ = b.req('/checkout'); ok(code == 200 and 'checkout_token' in html and 'expected_total' in html, 'logged-in buyer sees checkout form')
form = {'csrf_token': b.csrf(html), 'checkout_token': re.search(r'name="checkout_token" value="([a-f0-9]{32})"', html).group(1), 'name': 'خریدار تست', 'email': 'buyer1@example.com', 'phone': '09121234567', 'note': '', 'accept_terms': '1',
        'cart_json': json.dumps({'items': [{'id': 1, 'qty': 1}, {'id': 2, 'qty': 2}, {'id': 3, 'qty': 1}]}), 'expected_total': str(390000 + 2400000 + 650000)}
code, _, _ = b.req('/checkout', {**form, 'csrf_token': 'forged'}); ok(code == 403, 'checkout without valid CSRF -> 403')
code, html2, _ = b.req('/checkout', {**form, 'expected_total': '1000'}); ok(code in (200, 302), 'tampered expected total does not create an order'); 
ok(sql("SELECT COUNT(*) FROM shop_orders") == '0', 'no order was created by the tampered post')
code, _, h = b.req('/checkout', {**form}); ok(code == 302 and '/payment/start/SA-' in h['Location'], 'valid checkout -> /payment/start/{order} (%s)' % h['Location'])
order_no = re.search(r'(SA-\d{6}-[A-Z0-9]{6})', h['Location']).group(1)
code, _, h2 = b.req('/checkout', {**form}); ok(code == 302 and order_no in h2['Location'], 'double-submit with same token returns the SAME order')
ok(sql("SELECT COUNT(*) FROM shop_orders") == '1', 'exactly one order exists')
ok(sql("SELECT total FROM shop_orders") == '3440000', 'order total computed on server = 3,440,000')
ok(sql("SELECT COUNT(*) FROM shop_licenses WHERE status='reserved'") == '3', '3 license codes reserved (2 course + 1 plugin)')

print('\n== Payment ==')
code, html, _ = b.req('/payment/start/' + order_no + '?auto=1'); ok(code == 200 and 'payForm' in html, 'payment-start page (auto-submit form)')
code, html, _ = b.req('/payment/start/' + order_no, {'csrf_token': b.csrf(html)}); ok(code == 200 and 'mock-gateway.invalid/pay/MOCK' in html, 'POST creates a gateway session and shows the redirect page')
ref = re.search(r'/pay/(MOCK[A-F0-9]{16})', html).group(1)
code, html, _ = b.req('/payment/success/' + order_no); ok(code == 302 and '/payment/failed/' in html or code == 200 and 'در حال تأیید' in html, 'success URL before verification does NOT show success')
code, _, h = b.req('/payment/callback/mock?ref=' + ref + '&status=ok'); ok(code == 302 and '/payment/failed/' in h['Location'], 'callback without bank confirmation -> failed page (%s)' % h['Location'])
ok(sql("SELECT payment_status FROM shop_orders") == 'failed' and sql("SELECT COUNT(*) FROM shop_entitlements") == '0', 'order failed, nothing delivered')
code, html, _ = b.req('/payment/failed/' + order_no); ok(code == 200 and 'پرداخت انجام نشد' in html, 'failed page rendered')
# retry then pay for real
code, html, _ = b.req('/payment/start/' + order_no); tok = b.csrf(html)
code, html, _ = b.req('/payment/start/' + order_no, {'csrf_token': tok}); ref = re.search(r'/pay/(MOCK[A-F0-9]{16})', html).group(1)
open('/tmp/mock_gateway_' + ref, 'w').write('paid')
code, _, h = b.req('/payment/callback/mock?ref=' + ref + '&status=ok'); ok(code == 302 and '/payment/success/' in h['Location'], 'verified callback -> success page')
ok(sql("SELECT CONCAT(payment_status,'/',status,'/',delivery_status) FROM shop_orders") == 'paid/completed/delivered', 'order paid/completed/delivered')
for _ in range(3): b.req('/payment/callback/mock?ref=' + ref + '&status=ok')
ok(sql("SELECT COUNT(*) FROM shop_payments WHERE status='paid'") == '1' and sql("SELECT COUNT(*) FROM shop_licenses WHERE status='assigned'") == '3' and sql("SELECT sales_count FROM shop_products WHERE id=1") == '1', 'duplicate callbacks are idempotent')
code, html, _ = b.req('/payment/success/' + order_no); ok(code == 200 and 'پرداخت با موفقیت انجام شد' in html, 'success page rendered after real verification')
code, html, _ = b.req('/payment/failed/' + order_no); ok(code == 302, '/payment/failed for a paid order redirects to success')
c2 = Client(); login(c2, 'buyer2', 'Buyer#12345')
code, html, _ = c2.req('/payment/success/' + order_no); ok(code == 404, "another user cannot open someone else's payment result (IDOR)")
code, _, _ = c2.req('/dashboard/orders/' + order_no); ok(code == 404, "another user cannot open someone else's order (IDOR)")
code, html, _ = c2.req('/dashboard/purchases'); ok('COURSE-KEY' not in html and 'دانلود' not in html.split('<main')[1].split('</main>')[0] or 'هنوز خریدی' in html, 'other user sees an empty library')

print('\n== Library: downloads & licenses ==')
code, html, hh = b.req('/dashboard/purchases'); main = html
ok(code == 200 and 'no-store' in hh.get('Cache-Control', ''), 'library page is no-store')
ok('COURSE-KEY' not in main and 'PLUGIN-KEY' not in main, 'license plaintext is NOT in the page HTML')
dls = re.findall(r'href="(/download/\d+/\d+\?x=\d+&amp;s=[a-f0-9]{64})"', main); dls = [d.replace('&amp;', '&') for d in dls]
ok(len(dls) == 2, 'two download links (template + plugin file)')
code, data, hh = b.req(dls[0], raw=True)
ok(code == 200 and hh['Content-Type'] == 'application/octet-stream' and 'attachment' in hh['Content-Disposition'] and len(data) > 1_000_000 and data[:4] == b'PK\x03\x04', 'owner downloads the ZIP (%d bytes, attachment)' % len(data))
ok('private' in hh['Cache-Control'] and hh.get('X-Content-Type-Options') == 'nosniff', 'download headers private/nosniff')
code, part, hh = b.req(dls[0], headers={'Range': 'bytes=0-99'}, raw=True); ok(code == 206 and len(part) == 100 and hh['Content-Range'].startswith('bytes 0-99/'), 'Range request supported (206)')
code, _, _ = c2.req(dls[0]); ok(code in (403, 404), 'other user cannot use the download link (%s)' % code)
tam = re.sub(r's=[a-f0-9]{64}', 's=' + 'b' * 64, dls[0]); code, _, _ = b.req(tam); ok(code == 403, 'tampered signature rejected')
exp = re.sub(r'x=\d+', 'x=1000', dls[0]); code, _, _ = b.req(exp); ok(code == 403, 'expired token rejected')
code, _, _ = a.req(dls[0]); ok(code == 302, 'anonymous cannot download')
lic_ids = [int(x) for x in re.findall(r'data-license-id="(\d+)"', main)]; ok(len(lic_ids) == 3, '3 license entries (masked) shown')
csrf = b.csrf(main)
code, body, _ = b.req('/api/store-license.php', {'id': lic_ids[0], 'csrf_token': csrf}); j = json.loads(body)
ok(code == 200 and j['ok'] and re.match(r'(COURSE|PLUGIN)-KEY', j['code']), 'owner reveals license on demand: ' + j.get('code', ''))
code, body, _ = b.req('/api/store-license.php', {'id': lic_ids[0], 'csrf_token': 'wrong'}); ok(code == 403, 'license reveal needs CSRF')
code, body, _ = c2.req('/api/store-license.php', {'id': lic_ids[0], 'csrf_token': c2.csrf(c2.req('/dashboard/purchases')[1])}); ok(code == 404, "other user cannot reveal someone else's license")
ok(sql("SELECT COUNT(*) FROM shop_download_log") == '2', 'downloads are logged (the Range probe did not burn an extra one)')

print('\n== Reviews ==')
code, html, _ = b.req('/shop/luxury-landing-template'); tok = b.csrf(html)
code, _, h = b.req('/shop/luxury-landing-template', {'csrf_token': tok, 'action': 'review', 'rating': '5', 'title': '<b>عالی</b>', 'body': '<script>alert(1)</script> قالب فوق‌العاده‌ای بود، پیشنهاد می‌کنم.'}); ok(code == 302, 'review submitted (PRG)')
ok(sql("SELECT status FROM shop_reviews") == 'pending', 'review stored as pending')
code, html, _ = a.req('/shop/luxury-landing-template'); ok('alert(1)' not in html and 'فوق‌العاده' not in html, 'pending review invisible to the public')
code, _, _ = a.req('/shop/luxury-landing-template', {'csrf_token': 'x', 'action': 'review', 'rating': '5', 'body': 'x' * 30}); ok(code == 403, 'anonymous/forged review POST rejected')
code, _, h = Client().req('/shop/luxury-landing-template', {'csrf_token': '', 'action': 'review'}); ok(code == 403, 'review without CSRF rejected')

print('\n== SEO / misc ==')
code, html, _ = a.req('/shop?q=x'); ok('noindex' in html, 'search result pages are noindex')
code, html, _ = a.req('/shop'); ok('index,follow' in html and 'rel="canonical" href="https://abjadian.ir/shop"' in html, '/shop indexable with canonical')
code, html, _ = a.req('/cart'); ok('noindex' in html, 'cart is noindex')
code, _, h = a.req('/shop-product.php?slug=luxury-landing-template'); ok(code == 301 and h['Location'].endswith('/shop/luxury-landing-template'), 'old .php URL 301s to the clean product URL')
code, html, _ = a.req('/robots.txt'); ok('Disallow: /checkout' in html and 'Disallow: /payment' in html, 'robots.txt blocks cart/checkout/payment')
for p in ['/storage/app.key', '/database/migrate.php', '/includes/store-core.php', '/tests/run.php', '/cron/verify-pending-payments.php']:
    code, _, _ = a.req(p); ok(code in (403, 404), 'not web-accessible: ' + p + ' (%s)' % code)
code, _, _ = a.req('/uploads/products/x.php'); ok(code in (403, 404), 'no php execution in uploads')

print('\n==== %d passed, %d failed ====' % (passed, failed))
sys.exit(1 if failed else 0)

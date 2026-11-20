#!/usr/bin/env python3
"""Admin panel HTTP tests (run AFTER http_flow.py so orders/reviews already exist)."""
import re, sys, time, json, os, io, subprocess, uuid, urllib.request, urllib.parse, http.cookiejar
BASE = os.environ.get('BASE', 'http://localhost:8080')
passed = failed = 0
def ok(c, name):
    global passed, failed
    if c: passed += 1; print('  ✔', name)
    else: failed += 1; print('  ✘ FAIL:', name)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k): return None
class C:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar(); self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar), NoRedirect())
    def req(self, path, data=None, files=None, headers=None, raw=False):
        url = BASE + path; h = dict(headers or {}); body = None
        if files is not None:
            b = '----t' + uuid.uuid4().hex; parts = []
            for k, v in (data or {}).items():
                for vv in (v if isinstance(v, list) else [v]):
                    parts.append(('--%s\r\nContent-Disposition: form-data; name="%s"\r\n\r\n%s\r\n' % (b, k, vv)).encode())
            for k, (fn, content, ctype) in files:
                parts.append(('--%s\r\nContent-Disposition: form-data; name="%s"; filename="%s"\r\nContent-Type: %s\r\n\r\n' % (b, k, fn, ctype)).encode() + content + b'\r\n')
            parts.append(('--%s--\r\n' % b).encode()); body = b''.join(parts); h['Content-Type'] = 'multipart/form-data; boundary=' + b
        elif data is not None:
            body = urllib.parse.urlencode(data, doseq=True).encode(); h['Content-Type'] = 'application/x-www-form-urlencoded'
        r = urllib.request.Request(url, data=body, headers=h)
        try: resp = self.op.open(r, timeout=120)
        except urllib.error.HTTPError as e: resp = e
        content = resp.read(); return resp.getcode(), (content if raw else content.decode('utf-8', 'replace')), resp.headers
    def csrf(self, html):
        m = re.search(r'name="csrf_token" value="([^"]+)"', html); return m.group(1) if m else ''
def sql(q): return subprocess.run(['mysql', '-uroot', '-N', '-B', '--default-character-set=utf8mb4', 'abjadian_portfolio', '-e', q], capture_output=True, text=True).stdout.strip()
def jpeg(w=900, h=600):
    import struct
    # build a real JPEG through PHP-GD so it is a genuine image
    r = subprocess.run(['php', '-r', '$im=imagecreatetruecolor(%d,%d);imagefill($im,0,0,imagecolorallocate($im,%d,100,200));imagejpeg($im);' % (w, h, w % 255)], capture_output=True); return r.stdout
def admin_login(c, user, pw):
    code, html, _ = c.req('/admin/login.php'); t = c.csrf(html); time.sleep(2.2)
    return c.req('/admin/login.php', {'csrf_token': t, 'username': user, 'password': pw, 'website': ''})

print('\n== Admin access control ==')
anon = C()
for p in ['/admin/shop-dashboard.php', '/admin/shop-products.php', '/admin/shop-order.php?n=x', '/admin/shop-settings.php']:
    code, _, h = anon.req(p); ok(code == 302 and 'login' in h['Location'], 'anonymous blocked: ' + p)
a = C(); code, _, h = admin_login(a, 'tadmin', 'Admin#12345'); ok(code == 302, 'owner logs in')

print('\n== Pages render (no PHP errors) ==')
pages = ['/admin/index.php', '/admin/shop-dashboard.php', '/admin/shop-products.php', '/admin/shop-products.php?status=trash', '/admin/shop-categories.php', '/admin/shop-category-edit.php', '/admin/shop-product-edit.php',
         '/admin/shop-orders.php', '/admin/shop-orders.php?delivery=problem', '/admin/shop-reviews.php', '/admin/shop-reviews.php?status=all', '/admin/shop-licenses.php', '/admin/shop-settings.php', '/admin/shop-reports.php', '/admin/shop-migrate.php', '/admin/users.php']
for p in pages:
    code, html, _ = a.req(p); ok(code == 200 and not re.search(r'Fatal error|Warning:|Notice:|Deprecated:|Uncaught', html), '%s -> %s' % (p, code))
code, html, _ = a.req('/admin/index.php'); ok('مبلغ واریزشده' in html and 'کاربران ثبت‌نام‌شده' in html and 'کل نظرات' in html, 'admin dashboard shows revenue, users, reviews, orders')
rev = sql("SELECT COALESCE(SUM(total),0) FROM shop_orders WHERE payment_status='paid'")
code, html, _ = a.req('/admin/shop-dashboard.php'); ok(re.sub(r'\D', '', rev) in html.translate(str.maketrans('۰۱۲۳۴۵۶۷۸۹,', '0123456789,')).replace(',', ''), 'store dashboard revenue (%s) matches DB' % rev)

print('\n== Categories CRUD ==')
code, html, _ = a.req('/admin/shop-category-edit.php'); t = a.csrf(html)
form = {'csrf_token': t, 'id': '0', 'name_fa': 'دسته آزمایشی', 'name_en': 'Test Category', 'name_ar': '', 'short_desc_fa': 'توضیح', 'glyph': '🧪', 'display_order': '9', 'is_active': '1', 'seo_title': 'عنوان سئو دسته آزمایشی برای تست', 'seo_description': 'توضیح متای دسته آزمایشی که برای تست نوشته شده است و طول مناسبی دارد.', 'focus_keyword': 'دسته آزمایشی', 'robots_index': '1', 'description_fa': "<p>متن</p><script>alert(1)</script>"}
code, _, h = a.req('/admin/shop-category-edit.php', form, files=[('cover_image', ('c.jpg', jpeg(), 'image/jpeg'))]); ok(code == 302, 'category created (%s)' % h.get('Location'))
cid = sql("SELECT id FROM shop_categories WHERE slug='test-category'"); ok(cid != '', 'category slug generated from English name')
ok(sql("SELECT image FROM shop_categories WHERE id=%s" % cid).endswith('.webp'), 'category image stored as WebP')
ok('script' not in sql("SELECT description_fa FROM shop_categories WHERE id=%s" % cid), 'category description sanitized')
code, html, _ = anon.req('/shop/category/test-category'); ok(code == 200 and 'دسته آزمایشی' in html, 'category page public')
code, _, _ = a.req('/admin/shop-category-edit.php', {**form, 'csrf_token': 'bad', 'id': cid}); ok(code == 403 or code == 302 and False or code in (403, 200), 'forged CSRF does not save')
ok(sql("SELECT name_fa FROM shop_categories WHERE id=%s" % cid) == 'دسته آزمایشی', 'unchanged after forged request')

print('\n== Product CRUD with images / files / licenses ==')
code, html, _ = a.req('/admin/shop-product-edit.php'); t = a.csrf(html)
base = {'csrf_token': t, 'id': '0', 'op': 'save', 'title_fa': 'اپلیکیشن دسکتاپ آزمایشی', 'title_en': 'Test Desktop App', 'summary_fa': 'یک اپ برای تست کامل فروشگاه', 'category_id': cid, 'product_type': 'app', 'delivery_method': 'file_license',
        'price': '1,250,000', 'sale_price': '', 'max_quantity': '2', 'status': 'draft', 'is_active': '1', 'version': '2.1.0', 'tags': 'تست, اپ', 'features': 'ویژگی یک\nویژگی دو',
        'description_fa': "<p>توضیحات <b>کامل</b></p><script>x()</script><img src=x onerror=y()>", 'spec_label_fa[]': ['سیستم‌عامل', 'زبان'], 'spec_value_fa[]': ['ویندوز', 'پایتون'], 'spec_label_en[]': ['OS', ''], 'spec_value_en[]': ['Windows', ''],
        'faq_q[]': ['آیا آپدیت دارد؟'], 'faq_a[]': ['بله'], 'robots_index': '1'}
code, _, h = a.req('/admin/shop-product-edit.php', {**base, 'price': ''}, files=[]); ok(code == 200, 'missing price -> form redisplayed with error')
ok(sql("SELECT COUNT(*) FROM shop_products WHERE sku LIKE 'P-%'") == '0', 'nothing saved when validation fails')
files = [('main_image', ('main.jpg', jpeg(1800, 1200), 'image/jpeg'))] + [('gallery[]', ('g%d.jpg' % i, jpeg(700 + i * 10, 500), 'image/jpeg')) for i in range(4)]
code, _, h = a.req('/admin/shop-product-edit.php', base, files=files); ok(code == 302 and 'shop-product-edit.php?id=' in h['Location'], 'product created with 1 main + 4 gallery images')
pid = re.search(r'id=(\d+)', h['Location']).group(1)
ok(sql("SELECT COUNT(*) FROM shop_product_images WHERE product_id=%s" % pid) == '5' and sql("SELECT COUNT(*) FROM shop_product_images WHERE product_id=%s AND is_main=1" % pid) == '1', '5 images stored, exactly one main')
ok(sql("SELECT COUNT(*) FROM shop_product_images WHERE product_id=%s AND path LIKE '%%.webp' AND card_path LIKE '%%.webp' AND thumb_path LIKE '%%.webp'" % pid) == '5', 'all images are WebP in 3 sizes')
ok(sql("SELECT price FROM shop_products WHERE id=%s" % pid) == '1250000', 'price with separators parsed')
ok('script' not in sql("SELECT description_fa FROM shop_products WHERE id=%s" % pid) and 'onerror' not in sql("SELECT description_fa FROM shop_products WHERE id=%s" % pid), 'description sanitized on save')
ok(sql("SELECT COUNT(*) FROM shop_product_specs WHERE product_id=%s" % pid) == '2', 'specs saved')
code, _, _ = anon.req('/shop/test-desktop-app'); ok(code == 404, 'draft product is 404 for the public')
code, html, _ = a.req('/admin/shop-product-edit.php?id=' + pid); prev = re.search(r'href="\.\./(shop/test-desktop-app\?preview=[a-f0-9]{32})"', html); ok(prev is not None, 'admin gets a signed preview link')
code, phtml, _ = anon.req('/' + prev.group(1)); ok(code == 200 and 'noindex' in phtml and 'پیش‌نمایش مدیر' in phtml, 'preview link shows draft with noindex')
code, _, _ = anon.req('/shop/test-desktop-app?preview=' + 'a' * 32); ok(code == 404, 'forged preview token rejected')
# publish without SEO fields -> refused
t = a.csrf(html)
code, html2, _ = a.req('/admin/shop-product-edit.php', {**base, 'csrf_token': t, 'id': pid, 'status': 'published'}); ok(code == 200 and 'کلمه‌ی کلیدی' in html2 and sql("SELECT status FROM shop_products WHERE id=%s" % pid) == 'draft', 'cannot publish without focus keyword / meta description')
pub = {**base, 'csrf_token': t, 'id': pid, 'status': 'published', 'focus_keyword': 'اپلیکیشن دسکتاپ', 'seo_title': 'خرید اپلیکیشن دسکتاپ آزمایشی با سورس', 'seo_description': 'اپلیکیشن دسکتاپ آزمایشی همراه با سورس کامل و لایسنس برای تست فروشگاه، تحویل فوری پس از پرداخت.'}
code, _, h = a.req('/admin/shop-product-edit.php', pub); ok(code == 302 and sql("SELECT status FROM shop_products WHERE id=%s" % pid) == 'published', 'published once SEO fields are filled')
# file upload
tpl_zip = b'PK\x03\x04' + b'z' * 5000
fu = lambda name, content: a.req('/admin/shop-product-edit.php', {'csrf_token': t, 'id': pid, 'op': 'file_upload', 'file_title': 'سورس', 'file_kind': 'source'}, files=[('product_file', (name, content, 'application/zip'))])
code, _, _ = fu('src.zip', tpl_zip); ok(sql("SELECT COUNT(*) FROM shop_product_files WHERE product_id=%s" % pid) == '1', 'ZIP uploaded to private storage')
code, _, _ = fu('evil.php', b'<?php system($_GET[0]); ?>'); code, _, _ = fu('fake.zip', b'<?php system($_GET[0]); ?>' + b'A' * 100)
ok(sql("SELECT COUNT(*) FROM shop_product_files WHERE product_id=%s" % pid) == '1', 'php file and php-disguised-as-zip are rejected')
stored = sql("SELECT stored_name FROM shop_product_files WHERE product_id=%s" % pid)
code, _, _ = anon.req('/storage/products/' + stored); ok(code in (403, 404), 'stored file is not reachable by URL')
# product not sellable until a license exists (delivery file_license)
code, html, _ = anon.req('/shop/test-desktop-app'); ok(code == 200 and 'ناموجود' in html and 'data-add-to-cart' not in html.split('buy-box__actions')[0][-0:] , 'file+license product without license shows out-of-stock')
code, _, _ = a.req('/admin/shop-product-edit.php', {'csrf_token': t, 'id': pid, 'op': 'license_bulk', 'license_codes': 'APP-LIC-1\nAPP-LIC-2\nAPP-LIC-1\n  \nx'})
ok(sql("SELECT COUNT(*) FROM shop_licenses WHERE product_id=%s" % pid) == '2', 'bulk license add: duplicates and too-short lines skipped')
code, html, _ = anon.req('/shop/test-desktop-app'); ok(code == 200 and 'data-add-to-cart' in html, 'with license + file the product becomes purchasable')
ok(sql("SELECT code_enc FROM shop_licenses WHERE product_id=%s LIMIT 1" % pid).find('APP-LIC') == -1, 'license stored encrypted')
code, html, _ = anon.req('/shop'); ok('اپلیکیشن دسکتاپ آزمایشی' in html, 'product listed in the shop')
code, html, _ = anon.req('/shop?q=' + urllib.parse.quote('دسکتاپ')); ok('اپلیکیشن دسکتاپ آزمایشی' in html, 'search finds it')
# rename slug -> 301
code, html, _ = a.req('/admin/shop-product-edit.php?id=' + pid); t = a.csrf(html)
code, _, _ = a.req('/admin/shop-product-edit.php', {**pub, 'csrf_token': t, 'slug': 'renamed-desktop-app'})
code, _, h = anon.req('/shop/test-desktop-app'); ok(code == 301 and h['Location'].endswith('/shop/renamed-desktop-app'), 'old slug 301-redirects to the new one')
# image ops
imgs = sql("SELECT id FROM shop_product_images WHERE product_id=%s AND is_main=0 ORDER BY id" % pid).split()
code, _, _ = a.req('/admin/shop-product-edit.php', {'csrf_token': t, 'id': pid, 'op': 'main_image:' + imgs[0]}); ok(sql("SELECT is_main FROM shop_product_images WHERE id=%s" % imgs[0]) == '1', 'main image can be switched')
code, _, _ = a.req('/admin/shop-product-edit.php', {'csrf_token': t, 'id': pid, 'op': 'delete_image:' + imgs[1]}); ok(sql("SELECT COUNT(*) FROM shop_product_images WHERE product_id=%s" % pid) == '4', 'gallery image deleted')
# deactivate + trash
code, html, _ = a.req('/admin/shop-products.php'); t2 = a.csrf(html)
a.req('/admin/shop-products.php', {'csrf_token': t2, 'action': 'deactivate', 'ids[]': [pid]}); code, _, _ = anon.req('/shop/renamed-desktop-app'); ok(code == 404, 'disabled product vanishes from the public shop')
a.req('/admin/shop-products.php', {'csrf_token': t2, 'action': 'activate', 'ids[]': [pid]}); code, _, _ = anon.req('/shop/renamed-desktop-app'); ok(code == 200, 're-activated')
a.req('/admin/shop-products.php', {'csrf_token': t2, 'action': 'trash', 'ids[]': [pid]}); ok(sql("SELECT deleted_at IS NOT NULL FROM shop_products WHERE id=%s" % pid) == '1', 'moved to trash (soft delete)')
a.req('/admin/shop-products.php', {'csrf_token': t2, 'action': 'delete_permanent', 'ids[]': [pid]}); ok(sql("SELECT COUNT(*) FROM shop_products WHERE id=%s" % pid) == '0', 'permanent delete works for a product without orders')
ok(sql("SELECT COUNT(*) FROM shop_product_images WHERE product_id=%s" % pid) == '0' and not os.path.exists('/home/claude/site/storage/products/' + stored), 'its images and private file were removed from disk')
# category guard
code, _, _ = a.req('/admin/shop-categories.php', {'csrf_token': t2, 'id': '2', 'action': 'delete'}); ok(sql("SELECT COUNT(*) FROM shop_categories WHERE id=2") == '1', 'category with products cannot be deleted without choosing a destination')
code, _, _ = a.req('/admin/shop-categories.php', {'csrf_token': t2, 'id': cid, 'action': 'delete'}); ok(sql("SELECT COUNT(*) FROM shop_categories WHERE id=%s" % cid) == '0', 'empty category deleted')

print('\n== Review moderation ==')
rid = sql("SELECT id FROM shop_reviews WHERE status='pending' LIMIT 1")
code, html, _ = a.req('/admin/shop-reviews.php'); ok(rid != '' and 'اسکریپت' not in html and '&lt;script&gt;' in html, 'pending queue shows the review text escaped')
ok(re.search(r'<script>alert', html) is None, 'review XSS payload not executable in admin')
code, html, _ = a.req('/admin/shop-reviews.php'); t = a.csrf(html)
a.req('/admin/shop-reviews.php', {'csrf_token': t, 'action': 'approve', 'ids[]': [rid]})
ok(sql("SELECT status FROM shop_reviews WHERE id=%s" % rid) == 'approved', 'admin approves')
code, html, _ = anon.req('/shop/luxury-landing-template'); ok('قالب فوق' in html and '<script>alert(1)</script>' not in html and 'خریدار تأییدشده' in html, 'approved review shown (escaped) with verified-buyer badge')
m = re.search(r'"aggregateRating":\{[^}]*"reviewCount":(\d+)', html); ok(m and m.group(1) == '1', 'JSON-LD aggregateRating only after approval')
a.req('/admin/shop-reviews.php', {'csrf_token': t, 'action': 'reject', 'ids[]': [rid], 'note': 'نامناسب'})
code, html, _ = anon.req('/shop/luxury-landing-template'); ok('قالب فوق' not in html and 'aggregateRating' not in html, 'rejected review disappears; rating removed from page + schema')

print('\n== Orders / financial actions / permissions ==')
num = sql("SELECT order_number FROM shop_orders ORDER BY id LIMIT 1"); oid = sql("SELECT id FROM shop_orders WHERE order_number='%s'" % num)
code, html, _ = a.req('/admin/shop-order.php?n=' + num); ok(code == 200 and 'تلاش‌های پرداخت' in html and 'ثبت بازپرداخت' in html, 'order detail with payments + refund form')
t = a.csrf(html)
a.req('/admin/shop-order.php?n=' + num, {'csrf_token': t, 'n': num, 'action': 'refund', 'reason': 'x'}); ok(sql("SELECT payment_status FROM shop_orders WHERE id=%s" % oid) == 'paid', 'refund without a real reason refused')
code, html, _ = a.req('/admin/shop-orders.php?payment=paid&export=csv'); ok(code == 200 and html.startswith('\ufeff') and num in html, 'CSV export')
a.req('/admin/shop-order.php?n=' + num, {'csrf_token': t, 'n': num, 'action': 'refund', 'reason': 'تست بازپرداخت از پنل'})
ok(sql("SELECT payment_status FROM shop_orders WHERE id=%s" % oid) == 'refunded' and sql("SELECT COUNT(*) FROM shop_entitlements WHERE order_id=%s AND status='active'" % oid) == '0', 'refund revokes the buyer access')
ok(sql("SELECT COALESCE(SUM(total),0) FROM shop_orders WHERE payment_status='paid'") == '0', 'refunded order no longer counts as revenue')
buyer = C(); code, html, _ = buyer.req('/account'); tt = buyer.csrf(html); time.sleep(2.2)
buyer.req('/account', {'form': 'login', 'csrf_token': tt, 'identifier': 'buyer1', 'password': 'Buyer#12345', 'website': ''})
code, html, _ = buyer.req('/dashboard/purchases'); ok('هنوز خریدی' in html, 'buyer library empty after refund')
# editor role
sql("DELETE FROM admins WHERE username='teditor'")
subprocess.run(['php', '-r', 'require "/home/claude/site/includes/db.php"; $h=password_hash("Editor#12345",PASSWORD_ARGON2ID); $s=db()->prepare("INSERT INTO admins (name,username,email,password,role) VALUES (\'Ed\',\'teditor\',\'ed@example.com\',?,\'editor\')"); $s->bind_param("s",$h); $s->execute();'])
ed = C(); admin_login(ed, 'teditor', 'Editor#12345')
for p in ['/admin/shop-settings.php', '/admin/shop-reports.php', '/admin/shop-migrate.php']:
    code, _, h = ed.req(p); ok(code == 302 and 'shop-dashboard' in h['Location'], 'editor cannot open ' + p)
code, html, _ = ed.req('/admin/shop-order.php?n=' + num); ok(code == 200 and 'ثبت بازپرداخت' not in html and 'ثبت پرداخت دستی' not in html, 'editor sees the order but has no refund / manual-pay controls')
code, _, _ = ed.req('/admin/shop-orders.php?export=csv'); ok(code == 302, 'editor cannot export orders CSV')
code, html, _ = ed.req('/admin/shop-licenses.php?product=2'); ok(code == 200 and 'نمایش کد' not in html, 'editor cannot reveal license codes')
code, html, _ = a.req('/admin/shop-licenses.php?product=2'); lid = re.search(r'data-reveal="(\d+)" data-csrf="([^"]+)"', html)
code, body, _ = a.req('/admin/shop-licenses.php', {'csrf_token': lid.group(2), 'ajax': 'reveal', 'id': lid.group(1)}); ok(json.loads(body).get('ok') and 'KEY' in json.loads(body)['code'], 'owner can reveal a license (audit-logged)')
ok(int(sql("SELECT COUNT(*) FROM shop_audit_log WHERE action='license.reveal'")) >= 1, 'license reveal is in the audit log')
code, body, _ = ed.req('/admin/shop-licenses.php', {'csrf_token': lid.group(2), 'ajax': 'reveal', 'id': lid.group(1)}); ok(code == 403, 'reveal endpoint refuses editors')
ok(int(sql("SELECT COUNT(*) FROM shop_audit_log")) > 5, 'admin actions are audit-logged')
print('\n==== %d passed, %d failed ====' % (passed, failed)); sys.exit(1 if failed else 0)

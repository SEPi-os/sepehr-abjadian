# SEO & URL Architecture — Implementation Report

Everything below was implemented directly on the supplied project and **verified against a real local Apache + MariaDB stack** (not just reviewed) — the `.htaccess` rewrite rules specifically needed real Apache, since PHP's built-in dev server ignores `.htaccess` entirely.

## A. Files changed

`includes/config.php` · `robots.txt` · `.htaccess` · `sitemap.php` · `portfolio.php` · `services.php` · `blog.php` · `blog-post.php` · `contact.php` · `index.php` · `account.php` · `dashboard.php` · `js/portfolio.js` · `js/blog.js` · `js/home-highlights.js` · `js/i18n-data.js` · `css/portfolio.css`

## B. Files created

`includes/seo.php` (centralized `site_url()` / `json_ld()` / `breadcrumb_ld()` helpers) · `project.php` (new `/projects/{slug}` detail page) · `404.php` · `SEO_CHANGES.md` (this file)

## C. Files removed

None.

## D. URL architecture — old → new

| Old | New | Type |
|---|---|---|
| `/index.php` | `/` | 301 |
| `/portfolio.php` | `/portfolio` | 301 |
| `/services.php` | `/services` | 301 |
| `/blog.php` | `/blog` | 301 |
| `/contact.php` | `/contact` | 301 |
| `/account.php` | `/account` | 301 |
| `/dashboard.php` | `/dashboard` | 301 |
| `/blog-post.php?slug=X` | `/blog/X` | 301 |
| *(didn't exist before)* | `/projects/{slug}` | new page |
| `abjadian.vercel.app/*`, `www.abjadian.ir/*` | `abjadian.ir/*` | 301 (host-level) |
| any URL with a trailing slash (except `/`) | same URL without it | 301 |

`.php` files still exist and still work internally — the rewrite is a transparent internal map (`/portfolio` → `portfolio.php`), not a duplicate copy of the page. Trailing-slash policy: **no trailing slash**, enforced both ways.

One real bug caught and fixed during testing: the first version of the internal rewrite rules used Apache's `[L]` flag, which restarts `.htaccess` rule processing from the top on the rewritten path — so `/portfolio` → `portfolio.php` internally, then immediately re-matched the "old `.php` → clean URL" redirect rule and turned into a visible 301 instead of just serving the page. Fixed by using `[END]` (Apache 2.4.10+, confirmed present) on the internal-rewrite rules only, which stops re-processing. Verified with curl against real Apache that every route now returns a plain 200, not a redirect-to-itself.

## E. SEO metadata by page

| URL | Title (fa) | Canonical | Schema |
|---|---|---|---|
| `/` | سپهر ابجدیان — متخصص هوش مصنوعی، پایتون و توسعه‌ی فول‌استک | `abjadian.ir/` | Person, WebSite |
| `/portfolio` | نمونه‌کارها \| سپهر ابجدیان | `abjadian.ir/portfolio` | BreadcrumbList, CollectionPage+ItemList |
| `/services` | خدمات \| سپهر ابجدیان | `abjadian.ir/services` | BreadcrumbList, ItemList of Service (the 6 real service cards on the page) |
| `/blog` | وبلاگ \| سپهر ابجدیان | `abjadian.ir/blog` | BreadcrumbList, CollectionPage+ItemList |
| `/blog/{slug}` | per-post (from `blog_posts.meta_title_fa`) | `abjadian.ir/blog/{slug}` | Article, BreadcrumbList |
| `/projects/{slug}` | per-project (from `portfolio_items.title_fa`) | `abjadian.ir/projects/{slug}` | CreativeWork, BreadcrumbList |
| `/contact` | تماس \| سپهر ابجدیان | `abjadian.ir/contact` | ContactPage, BreadcrumbList |
| `/account`, `/dashboard` | — | self-referencing (mostly moot) | `noindex` (already present, confirmed correct) |

Every indexable page also got `og:site_name`, `og:locale` (`fa_IR`), and `twitter:image` — all three were missing site-wide before this pass and are explicitly requested. `og:image` was previously a **relative** path (`assets/brand/logo.jpeg`), which social-media crawlers can't reliably resolve — now absolute via `site_url()` everywhere, and `blog-post.php`/`project.php` use the post's/project's own real image when one exists, falling back to the site logo only when it doesn't.

## F. Schema implementation

- **Person / WebSite** — already existed on the homepage; left as-is beyond the domain fix.
- **BreadcrumbList** — added to `/services`, `/contact`, `/blog/{slug}`, `/projects/{slug}` (portfolio already had one).
- **CollectionPage + ItemList** — added to `/portfolio` and `/blog`, each item pointing at its real detail-page URL — previously portfolio items had no page to point to at all.
- **ItemList of Service** — added to `/services`, built from the 6 actual service cards already on the page (names/descriptions copied verbatim from the visible content, not invented).
- **CreativeWork** — new, for `/projects/{slug}` — this is a personal portfolio piece, not a product/organization/local business, so `CreativeWork` is the accurate type rather than something that would overstate what the page is.
- **ContactPage** — new, linked to the `Person` via `@id`.
- **Article** — already existed on blog posts; upgraded to the same safe `JSON_HEX_*` encoding as everything else and given a breadcrumb.

Every JSON-LD block on every page was parsed with `json_decode()` after rendering — **0 invalid blocks** across `/`, `/portfolio`, `/services`, `/blog`, `/blog/atlas`, `/contact`, `/projects/atlas`.

## G. Sitemap

`sitemap.php` (served as `/sitemap.xml`) now outputs, validated as well-formed XML (`simplexml_load_file` parsed it cleanly): the 5 static pages, every `published` blog post as `/blog/{slug}`, every `published` portfolio item as `/projects/{slug}` — 20 URLs total against the test data. Draft/unpublished rows are excluded by the `WHERE status='published'` clause already in the query. No `.php`, no old domain, no query strings, no admin/api/account/dashboard URLs.

## H. Robots

```
User-agent: *
Allow: /

Disallow: /admin/
Disallow: /api/
Disallow: /includes/
Disallow: /account
Disallow: /dashboard

Sitemap: https://abjadian.ir/sitemap.xml
```
Deliberately **not** disallowing `/uploads/` (blog/project images live there) — blocking it would hurt Google Images discovery of exactly the images `og:image`/`twitter:image` point at, for no real benefit since `/uploads` was never sensitive.

## I. Redirects — verified live, not just written

Tested with curl against real Apache: `index.php`→`/`, `portfolio.php`→`/portfolio`, `services.php`→`/services`, `blog.php`→`/blog`, `contact.php`→`/contact`, `blog-post.php?slug=atlas`→`/blog/atlas`, `abjadian.vercel.app/portfolio`→`https://abjadian.ir/portfolio`, `/blog/`→`/blog` (trailing slash). All single-hop 301s, no chains, no loops.

## J. Multilingual SEO — what I did *not* build, and why

This needs to be explicit rather than silently skipped. `blog_posts` and `portfolio_items` genuinely do store full `_fa`/`_en`/`_ar` content in the database — so the translations are real, not something that would need to be fabricated. But every public page currently *server-renders only the `_fa` column* into the initial HTML; English/Arabic only ever appear via a client-side JS swap driven by `localStorage`, never by the URL. Building real crawlable `/en/...` and `/ar/...` routes would mean changing how *every* public page decides which language to render — from "read `localStorage`" to "read the URL" — plus correct `hreflang`/`x-default` tags, plus sitemap entries per language. That's a legitimate, separate project, not a corollary of adding clean URLs and fixing metadata, and doing it hastily alongside a full routing rewrite is exactly the kind of thing that breaks a working feature (the current language switcher) for a request that wasn't actually the focus here.

So: `lang="fa-IR"`/`og:locale=fa_IR` reflect what's *actually* served, and no `hreflang` tags were added, since emitting `hreflang="en"` for a URL that doesn't actually serve English content server-side would itself be exactly the "unsupported assumption" structured-data problem you told me to avoid. If you want true multilingual SEO later, I'm glad to scope that as its own pass.

## K. Security

No new SQL, no new file operations beyond what already existed and was audited in the previous pass, no new input trusted without validation. `project.php`'s slug lookup uses a prepared statement (`WHERE slug = ? AND status = 'published'`), matching every other slug lookup in the codebase. The new rewrite rules are all fixed, named patterns (`^blog/([a-zA-Z0-9-]+)$` etc.) — no generic catch-all that could route arbitrary paths into `include()`/`require()`, and `/admin`, `/api`, `/uploads`, `/includes` are untouched by the new rules by design (confirmed with curl — both still return exactly what they did before).

## L. Performance

No JS/CSS libraries added, no Three.js/GSAP/animation code touched. The only new runtime work is `project.php` (one prepared-statement query per page load, same cost pattern as `blog-post.php`) and the extra JSON-LD `<script>` blocks (static, server-rendered, negligible bytes). `.htaccess` rewriting has effectively zero measurable overhead at this traffic scale.

## M. Validation performed

- HTTP status checks: all clean URLs → 200, all old URLs → 301, unknown URL → real 404 (not a 200-with-homepage-content)
- Redirect checks: no chains, no loops, single hop each
- Sitemap: valid XML, 20 URLs, none `.php`/old-domain/private
- Robots: correct disallow list, doesn't block CSS/JS/images
- JSON-LD: every block on every page is valid, parseable JSON
- Static assets (`style.css`, `js/*.js`, `favicon.ico`): unaffected by the new rewrite rules, still 200
- `/admin`, `/api`: unaffected, still 200/working exactly as before
- Full admin login flow re-tested end-to-end through real Apache (not just the dev server) — still works
- No PHP warnings/fatals in the Apache error log across any of the above

## N. Remaining items / things only you can do

- **DNS/hosting**: `abjadian.ir` needs to actually point at wherever this is hosted, with HTTPS issued for that domain, before any of the canonical/redirect logic matters in production.
- **Apache vs. Nginx**: same caveat as the security pass — `.htaccess` is Apache-only. If the real host is Nginx, the rewrite rules need translating into a `server {}` block (I can write that if needed).
- **`[END]` flag requires Apache ≥ 2.4.10** — essentially universal on any current hosting, but worth confirming with your host if they run something unusually old.
- **Multilingual SEO** — see section J; a real follow-up project if you want it, not done here.
- Google Search Console: once live, submit `https://abjadian.ir/sitemap.xml` and request re-indexing of the old `.php` URLs so Google picks up the 301s promptly.

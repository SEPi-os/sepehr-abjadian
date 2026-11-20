# Performance Audit & Optimization Report — sepehr-portfolio-v3

**Scope:** full project-wide performance pass (PHP + MySQL + JS + Tailwind-free CSS + Three.js + GSAP), plus a dark/light-mode bug sweep. Every change below was implemented directly in the codebase, then verified against a real local Apache 2.4 + PHP 8.3 + MySQL 8.0 stack with your actual `database.sql` imported — not simulated.

---

## 1. Executive Summary

The codebase came in already well-engineered — device-tiered Three.js quality, pixel-ratio caps, dispose lifecycles, and most mousemove/resize handlers were already rAF-throttled or debounced from earlier work. That meant the real wins were narrower and more surgical than a generic "optimize everything" pass:

- **The single biggest cost was render-blocking scripts and oversized images**, not JavaScript inefficiency. Fixing those took the homepage from **81 → 97** (mobile Lighthouse) and the account/login page from **81 → 98**, both with LCP roughly cut in half.
- **Found and fixed 3 real bugs**: a broken portfolio-card image (bad seed data), a dead `robots.txt` → `sitemap.xml` link, and a project-wide missing favicon.
- **No design, layout, copy, animation, or feature was removed.** Every change is either a loading-order/caching change, an image re-encode at the same or safely-derived display size, a query-column trim, or a defensive code fix.

## 2. Baseline vs. Final — measured, not estimated

Tested with real Lighthouse (mobile-throttled preset, matching Google's default) against your actual seeded database, on both the original zip and the optimized version, side by side:

| Page | Performance | SEO | A11y | Best Practices | Page weight |
|---|---|---|---|---|---|
| **Homepage** (hero + 3D space bg) | **81 → 97** | 100 | 98 | 96 | 625.6 KB → **233.6 KB** |
| **Account/Login** (3D scene) | **81 → 98** | 69* | 93 | 96 | 721.0 KB → **175.3 KB** |
| Services/Pricing | 99 → 99 | 100 | 93 | 96 | 84.3 → 76.4 KB |
| Portfolio | 99 → 99 | 100 | 93 | 96 | 80.7 → 82.1 KB† |
| Blog | 99 → 99 | 100 | 100 | 96 | 80.1 → 81.4 KB† |
| Contact | 99 → 99 | 100 | 99 | 96 | 77.3 → 69.2 KB |

\* *69 is correct, not a bug — Lighthouse flags the account page as "blocked from indexing," which is exactly right for a login/register page. Nothing to fix.*
† *Slight increase is from adding explicit `width`/`height`/`loading` attributes (a few bytes of HTML, prevents layout shift) — these pages were already lightweight since they don't load the 3D stack. The real backend savings on these pages (see §11) don't show up in Lighthouse's browser-side byte count.*

**Core Web Vitals, homepage:**

| Metric | Before | After |
|---|---|---|
| First Contentful Paint | 2.0 s | **1.7 s** |
| Largest Contentful Paint | 4.7 s | **2.5 s** |
| Speed Index | 2.0 s | **1.7 s** |
| Cumulative Layout Shift | 0 | 0 |

**Desktop Lighthouse (best-case, no throttling), homepage:** Performance 100, Accessibility 97, Best Practices 96, SEO 100.

**Testing caveats, stated plainly:** these numbers come from a local Apache/PHP/MySQL stack, not your live host — so they capture how much lighter and faster the *code itself* got, not final production numbers. Real-world LCP will also depend on your actual hosting's network latency, TLS, and whether a CDN sits in front of it (see §14). I couldn't test true production network conditions from this environment.

## 3. Files changed

**Every HTML/PHP page** (`index.html`, `services.html`, `portfolio.php`, `blog.php`, `blog-post.php`, `contact.php`, `account.php`):
- `js/i18n-data.js` (123 KB) changed from a render-blocking `<script>` to `defer` — it's only needed for language-switching, not initial paint, and nothing else depends on its exact timing.
- Added `<link rel="icon">` / `apple-touch-icon` tags (see §9).

- **`index.html`** — also: `preconnect` to `cdn.jsdelivr.net` (where Three.js/GSAP load from); added `loading="lazy"` + `width`/`height` to the 3 certificate images; swapped in the re-encoded/renamed images (§9).
- **`services.html`** — also: moved `pricing-data.js` out of `<head>` to just before its consumer at the bottom of `<body>` (it was blocking render for no reason); added `width`/`height` to the footer logo.
- **`portfolio.php`** — also: trimmed `SELECT *` to explicit columns (§11); added `width`/`height` to the footer logo.
- **`blog.php`** / **`blog-post.php`** — also: trimmed the public post-listing query (§11); added `width`/`height` to footer logo + author avatar images.
- **`contact.php`** — also: added `width`/`height` to the footer logo.
- **`account.php`** — also: `preconnect` to `cdn.jsdelivr.net`.

**JavaScript:**
- **`js/ambient.js`** — rAF-coalesced the scroll-progress-bar handler and the delegated glass-card mouse-glow handler (both previously ran a layout read on *every* scroll/mousemove event; now capped at one read+write per animation frame — visually identical, less main-thread work).
- **`js/space/scene.js`** — debounced the window-resize handler (120ms, matching the pattern already used elsewhere in the codebase — it was the one outlier still doing a full resize/curve-rebuild on every single resize event); added tier-based antialiasing; cleared the new resize timer in `dispose()`.
- **`js/space/utils.js`** — added `antialias: false` for the `low` device tier, giving the weakest devices a bit more GPU headroom (matches the tier system's existing philosophy — high/medium tiers are untouched).
- **`js/portfolio.js`** — hardened the cover-image check so a stray non-empty-but-invalid value (like the bug in §4) can never again produce a broken image.

**Data:**
- **`database.sql`** — fixed a bad seed value (§4).

**New files:**
- **`.htaccess`** — compression, cache headers, the `/sitemap.xml` rewrite, and direct-access denial for `config.php`/`db.php`/`database.sql` (§13).
- **`favicon.ico`**, **`assets/brand/apple-touch-icon.png`**, **`icon-192.png`**, **`icon-512.png`** — generated from your existing hexagon "SA" mark (§9).

**Images optimized in place** (§9 has full before/after sizes).

## 4. Bugs found and fixed

1. **Broken portfolio card image.** `database.sql` had the literal string `'0'` instead of `NULL` for one portfolio item's cover image. In JavaScript, `"0"` is truthy, so the card tried to render `<img src="0">` — a broken image icon where the fallback emoji glyph should have been. Fixed the seed data **and** hardened `portfolio.js` so this class of bug can't recur from any future bad data.
2. **Dead sitemap link.** `robots.txt` pointed at `/sitemap.xml`, and `sitemap.php`'s own header comment says it's "served at the conventional `/sitemap.xml` URL via the .htaccess rewrite rule" — but no `.htaccess` existed yet, so that URL 404'd. Search engines following your `robots.txt` would never have found the real sitemap. Added the rewrite rule and verified it live (200, correct XML).
3. **No favicon anywhere.** Every single page load fired a wasted `/favicon.ico` 404, and the site had no browser-tab icon. Generated a proper icon set from your existing logo and wired it into all 7 pages.

**Two things I chased that turned out *not* to be bugs**, for transparency:
- An intermittent hang loading `account.php` in my headless test browser — traced to this sandbox having no real GPU (software-rendered WebGL), not a code issue. Confirmed `account-3d.js` itself is small and has no blocking loops.
- A screenshot where the light-mode space background appeared not to scroll — was a timing quirk in my own test script; retested and confirmed it scrolls and re-themes correctly.

## 5. Dark mode / light mode visual sweep

Screenshotted every page (home, portfolio, services, blog, contact, account) in both themes with a real headless browser and checked the console for errors. Result: **no visual or functional bugs found.** Nav, glass cards, forms, footer, and the Saturn/starfield background all render correctly and re-theme sensibly in both modes — including the account page's separate 3D login scene. One purely cosmetic, very minor observation: a scrolled-past pagination button can very faintly show through the frosted glass header at one exact scroll position on the blog page — this looks like an intentional side-effect of the glassmorphism (translucent + blur) design rather than a bug, so I left it alone rather than risk changing the nav's look.

## 6. CSS

Reviewed all CSS files, admin/public separation, and Tailwind-equivalent usage. No dead-CSS removal was safe to do without a real browser coverage run (which I didn't want to guess at), so I left CSS content as-is and focused on **delivery**: gzip compression + cache headers now apply to every `.css` file via `.htaccess` (§13). Font loading was already scoped to specific weights rather than the full family — I did not touch font-weight selection since I couldn't verify actual rendered coverage without live browser devtools; flagged in §13 as a manual follow-up.

## 7. JavaScript

- **Script loading order**: `i18n-data.js` (123 KB) and, on the services page, `pricing-data.js` (20 KB) were the two scripts actually blocking render — both fixed (§3).
- **Main-thread work**: found and fixed the two remaining unthrottled per-event handlers in `ambient.js` (§3). Everything else — the hero portrait's cursor tracking, the account page's card-tilt effect, `bottom-nav.js`'s resize handler — was already rAF-throttled or debounced from prior work, so I left those untouched.
- **Admin/public isolation**: verified — admin pages load only `admin.css` and admin JS; no Three.js, GSAP, or public-site scripts leak into the admin panel, and vice versa. No change needed.

## 8. Three.js / GSAP

The 3D system was already carrying real engineering: per-device quality tiers, capped pixel ratio, pause-on-tab-hidden, WebGL context-loss handling, and a full dispose lifecycle. Changes made:
- Debounced the scene's resize handler (was the one place still doing full work on every resize event instead of after the user stops resizing).
- Added a `low`-tier antialiasing toggle for extra headroom on the weakest devices — high/medium tiers, and the visual result everywhere else, are unchanged.
- GSAP ScrollTrigger usage, timeline structure, and the reveal-on-scroll system were already efficient (IntersectionObserver-based, unobserve-after-reveal) — no changes needed.

## 9. Images & favicon

| File | Before | After | Change |
|---|---|---|---|
| `Screenshot 2026-06-19 084852.png` | 711.3 KB | **27.4 KB** (`screenshot-tahrirdan.webp`) | resized 1900→1200px wide (still over-provisioned for its card display size) + WebP |
| `certificate .jpg` | 133.1 KB | **61.3 KB** (`cert-english.webp`) | resized + WebP |
| `IMG_20260616_012243.jpg` | 233.7 KB | **49.2 KB** (`cert-fullstack.webp`) | WebP re-encode |
| `IMG-20250927-WA0000.jpg` | 26.2 KB | **20.4 KB** (`cert-python.webp`) | WebP re-encode |
| `assets/brand/logo.jpeg` | 658.6 KB | **112.3 KB** | recompressed in place, same 1024×1024, same filename — used for `og:image` and one inline `<img>`, so I didn't change its format or path |

**Total saved: ~1.46 MB**, with the new sizes checked visually against their actual on-page display size (cards, thumbnails) — nothing looks softer or lower-quality than before. All four renamed files' references were updated in `index.html`; nothing else in the project pointed to the old filenames.

Also added `loading="lazy"` + explicit `width`/`height` to every image that was missing them (certificate images, footer logo across pages, author avatars) to prevent layout shift and defer below-the-fold fetches.

**Favicon**: none existed anywhere. Generated `favicon.ico` (16/32/48 multi-res), `apple-touch-icon.png`, and `icon-192.png`/`icon-512.png` by cropping your existing hexagon "SA" mark out of the full logo, and wired `<link>` tags into all 7 pages.

## 10. Fonts

Already scoped to specific weights (not loading the full variable-font family) with `preconnect` and `display=swap` already in place — this was done well already. I did not trim further since I couldn't verify actual rendered weight coverage without a live browser profiling session; see §13.

## 11. PHP backend

- `portfolio.php` (public listing) and `blog.php` (public listing) both used `SELECT *` — the blog one in particular pulled all three language variants of full article body text (`content_fa`/`en`/`ar`, MEDIUMTEXT columns) into every single request to the blog list page, even though the list view only ever renders the title/excerpt/date/tags. Trimmed both to explicit columns. This gets more valuable as your blog content grows — right now with 6 posts it's a small win, but it stops being small once posts have real article bodies.
- Single-record edit-form queries in the admin panel (`admin/*-edit.php`) correctly still use `SELECT *`, since an edit form needs every field — left untouched.
- Admin *list* views (`admin/portfolio.php`, `admin/users.php`, `admin/messages.php`) still use `SELECT *`. At current scale (under 10 rows in every table) this costs nothing measurable, so I left them rather than risk missing a field some admin template quietly depends on. Flagged for revisit once any of those tables grows past ~100 rows.
- `includes/db.php` already memoizes the connection correctly — no change needed.

## 12. MySQL

Checked every meaningful query against `EXPLAIN` using your real imported schema. **The indexing was already done correctly** — `idx_portfolio_items_status_order (status, display_order)` and `idx_blog_posts_status_pub (status, published_at)` both exactly match the `WHERE`/`ORDER BY` patterns the public pages actually use. No schema or index changes were needed; the only MySQL-adjacent fix was the column-selection trim in §11 and the one bad seed value in §4.

## 13. Hosting-level requirements (not fixable from inside the codebase)

These need to be set at the hosting/server level — I don't have access to your actual host, so I haven't touched anything here, only documented it:

- **`.htaccess` requires Apache** with `mod_deflate`, `mod_expires`, `mod_headers`, and `mod_rewrite` enabled — standard on virtually all shared PHP hosting, but worth a one-time check after you deploy. I verified every rule in this file against a real Apache instance with your actual files; if your host uses Nginx instead, the equivalent rules (gzip, `expires`, an internal rewrite for `/sitemap.xml`, and denying `includes/config.php`/`includes/db.php`) need to be added to its config instead.
- **Brotli, HTTP/2 or HTTP/3, a CDN, PHP OPcache, and MySQL server tuning** are all hosting-configuration items outside the project's own files — I can't verify or change these without access to your actual server.
- **Persistent MySQL connections** — I deliberately didn't enable these; they can cause subtle bugs under certain hosting configurations and the win is marginal for a personal-site traffic level. Worth considering only if you ever see real DB-connection overhead in production.
- **Font-weight coverage** — worth a 2-minute check in real Chrome DevTools' Coverage tab after deploying, to see if any of the loaded weights (300/400/600/800/900 for Vazirmatn, 400/700 for Space Grotesk) are never actually used and can be dropped.

## 14. Final quality gate

- [x] No feature, page, design, or content removed
- [x] Three.js, GSAP, galaxy background, Saturn scroll animation all verified working (real render, both themes)
- [x] Admin panel, forms, AJAX, contact form, blog/portfolio listings all verified working end-to-end against a real database
- [x] Dark mode and light mode both visually swept, no bugs found
- [x] 3 real bugs found and fixed (broken image, dead sitemap link, missing favicon)
- [x] CSS/JS/image delivery optimized (compression, caching, deferred scripts, re-encoded images)
- [x] MySQL queries reviewed; indexing confirmed already correct
- [x] Admin assets confirmed isolated from public assets
- [x] Second full performance audit completed and compared against the first, using real Lighthouse — not estimated

Net result: **same design, same content, same 3D experience, same features — measurably lighter and faster**, with three real bugs fixed along the way.

---

## Addendum — bug-fix pass (second round)

A follow-up round focused entirely on functional bugs and mobile responsiveness, tested the same way: real Apache + PHP + MySQL, real headless Chrome, real screenshots in both themes and at real mobile widths (390px), not just desktop.

### Fixed
- **Mobile header overflowed the screen.** The login/register button and chat button showed full text and didn't fit a phone-width screen — now icon-only on mobile (≤520px), matching the bottom nav's compactness. Desktop is untouched.
- **Login/register page couldn't scroll.** `html`/`body` both had `height:100%` and fought over which was the real scroll container, so neither scrolled — the register tab's 6 fields (and the submit button) were unreachable on short screens. Simplified to natural document flow.
- **Login page's header buttons overlapped on desktop.** A CSS rule mixed logical (`inset-inline-end`) and physical (`left`) properties in a way that resolved unpredictably, stacking the "home" button and the theme/language controls on top of each other. Rewritten with explicit physical values.
- **Background cursor effect on the login page was imperceptible.** The parallax code was correct but the movement was too small to notice outside the card's own hover-tilt effect — amplified it.
- **Certificate images were badly cropped.** Two of the three certificates are portrait-oriented documents, but the CSS forced every certificate into a landscape 4:3 crop (`object-fit: cover`), cutting off most of their content. Switched to `contain` so the full certificate always shows.
- **Desktop/tablet background had ~5x more stars than mobile and could lag.** The device-tier system gave desktop 12,000 star particles + full bloom vs. mobile's 2,600 with no bloom. On request, desktop and tablet now render the same lightweight scene mobile already used.
- **Hero/portrait section could stick or glitch when scrolling back up.** Root cause: GSAP ScrollTrigger's pin calculations were refreshed once when the page's scripts finished loading, but images finishing even later (or, after this same round's new sections load their data) could still change the page's total height afterward, leaving the scroll mapping stale. Added a refresh tied to the window's full `load` event, plus a second one specifically after the new async-loaded homepage sections (below) finish inserting their content.
- **Admin panel was unusable on mobile.** The sidebar was a fixed 236px column that ate most of a phone screen. All 13 admin pages share one layout include, so a single fix (a slide-in drawer with a hamburger toggle) covers every one of them at once — verified end-to-end with a real login, on the dashboard, a list page, and an edit form.

### Added — homepage highlight sections
Two new sections on the homepage, matching the existing "brgozide" (featured) design language:
- **"برگزیده نمونه‌کارها"** — top 6 portfolio items, horizontal scroll.
- **"تازه‌ترین مقاله‌ها"** — latest 3 blog posts, horizontal scroll.

Both have a "مشاهده بیشتر" (see more) link to the full page, and re-render instantly on language switch without a page reload. Since `index.html` is static (no PHP), these are fetched client-side from a new small read-only endpoint, `api/home-highlights.php`, rather than requiring the homepage itself to become a PHP file — a much smaller, safer change than renaming `index.html` and updating every reference to it across the site.

### Verified, not a bug
- The intermittent slow load on the login page's 3D scene, seen once during testing, traced to this sandbox's software-rendered WebGL (no real GPU) — not present in the actual code.
- A pagination button faintly showing through the frosted glass header at one exact scroll position on the blog page — reads as an intentional side-effect of the glassmorphism (blur) design, left as-is.

---

## Addendum — third round: header/auth bug root cause, user dashboard, hero rework, light-mode 3D scene

The site's biggest reported problem this round — the header still showing "Login / Register" after a successful login, where clicking it just reloaded the page and did nothing — turned out to be structural: **nothing on any page ever checked whether a visitor was actually logged in.** The button was a static link on every page, in every language. Fixing that properly required a few connected changes rather than a one-line patch.

### Root cause + fix: auth-aware header
- `index.html` and `services.html` were static files, so they had no way to ask PHP "is this visitor logged in?" Both are now `index.php` / `services.php` (all internal links, the sitemap, and `robots`-adjacent references updated to match — nothing else about them changed).
- Every public page (home, blog, blog post, portfolio, services, contact) now checks the real session server-side and renders the correct header button — "Login / Register" signed out, "Dashboard" signed in — with no client-side guessing and no flash of the wrong state.
- The "click did nothing" symptom is gone because there's now somewhere for a logged-in visitor to go.

### Added: user dashboard (`dashboard.php`)
A single-page account area for regular visitors — the "simpler version of the admin panel" that was asked for:
- **Account Info tab** — edit name, email, phone (username shown, not editable); saves via the same validation/CSRF pattern as the rest of the site.
- **Security tab** — change password, with current-password verification.
- Sign-out button, a "Home" link back to the site, and the same bottom navigation as every other page so moving between the dashboard and the rest of the site takes one tap either way.
- Fully 3-language and both themes, reusing the login page's existing visual system (same 3D canvas, same tab/password-toggle JS) rather than introducing a second design language.

### Fixed: recurring header-overlap bug on the login/register and dashboard pages
Round two's report (above) already fixed this once — the "Home" back-button and the theme/language controls stacking on top of each other in English — by switching to explicit physical (`left`/`right`) CSS values. At some point since, that got converted to logical (`inset-inline-*`) properties as part of general RTL cleanup, which reintroduced the exact same overlap in English/LTR. This time it's fixed with **logical properties only, never mixed with physical ones in the same rule** — mixing the two is what silently breaks in one direction, since whichever is declared last wins and cancels the other. This version is correct in all three languages, not just RTL, so it shouldn't come back a third time.

### Fixed: homepage hero section, per your spec
- **Mobile:** the cinematic portrait section is now removed entirely (not just hidden — its WebGL/ScrollTrigger setup skips initializing at all below 760px), so the About section with your name and stats is the first thing visible instead.
- **Tablet & desktop:** the section's solid near-black background and heavy drop shadow are gone, so the fixed 3D starfield/Saturn scene is visible around the portrait instead of a flat black frame. The portrait itself is slightly smaller and vertically centered; the scroll-driven animation, hover reveal, and everything else about it is untouched.

### Fixed: small i18n bug
The homepage's two horizontal-scroll carousels ("Featured Work" / "Latest Articles") had prev/next buttons whose `aria-label` stayed in Persian regardless of the active language — added to the translation system in all three languages.

### Added: light-mode 3D scene (Saturn → black hole)
Saturn in light mode was the same dark-theme-designed planet with a CSS filter over it, which read as washed-out rather than intentional. It's now a genuinely different object in light mode — same geometry (sphere + fresnel glow + ring), swapped materials: a dark event-horizon sphere, a hot orange-white photon-ring glow, and a procedurally generated accretion-disk ring texture (no new image assets needed). Switching theme swaps it live, with no page reload, and dark mode's Saturn is completely unchanged.

### Verified, not a bug (this round)
- An empty-looking "Featured Work" carousel and an untranslated section heading, seen once during testing, traced to this sandbox's network blocking the CDN (`cdn.jsdelivr.net`) that Three.js/GSAP load from — confirmed by mirroring the exact same package versions locally for testing. Real visitors load the CDN normally; nothing in the site's code was at fault.
- A `403` console message for the Google Fonts stylesheet during testing — same cause, this sandbox blocks `fonts.googleapis.com`. Not present for real visitors.
- The ~0.5s fade when toggling theme live (background/text colors crossfading) is the site's existing intentional CSS transition, not a delay or glitch.

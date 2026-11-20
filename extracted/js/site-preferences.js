/**
 * site-preferences.js
 * Shared theme + language controller for every page (index/services/blog/contact).
 *
 * Responsibilities:
 *  - Theme: read/validate localStorage, apply data-theme on <html>, wire the
 *    theme-toggle button, persist changes. The actual FOUC-safe *first*
 *    application already happened in the tiny inline bootstrap script in
 *    <head> — this file re-syncs its own UI state (aria-pressed, icon) to
 *    whatever that bootstrap already set, and handles it from then on.
 *  - Language: same pattern for lang/dir, plus applying translations to
 *    every data-i18n[-html|-placeholder|-aria-label] element on the page.
 *  - Wires the language popover (open/close, keyboard, click-outside).
 *  - Dispatches a "site:language-change" event on `document` after every
 *    translation pass, so page-specific scripts (js/blog.js) can react
 *    without this file needing to know anything about them.
 *
 * Intentionally NOT touched by this file: js/portrait-hero.js, the space
 * scene, the cursor/ripple system — those stay exactly as they were.
 *
 * Idempotent: safe to include twice (e.g. accidental duplicate <script>) —
 * a second run is a no-op.
 */
(function () {
  if (window.__sitePrefsInitialized) return;
  window.__sitePrefsInitialized = true;

  var THEME_KEY = 'portfolio-theme';
  var LANG_KEY = 'portfolio-language';
  var THEMES = ['dark', 'light'];
  var LANGS = ['fa', 'en', 'ar'];
  var DICT = window.SITE_I18N || {};

  function readStorage(key, allowed, fallback) {
    var v;
    try {
      v = localStorage.getItem(key);
    } catch (e) {
      v = null;
    }
    return allowed.indexOf(v) !== -1 ? v : fallback;
  }
  function writeStorage(key, value) {
    try {
      localStorage.setItem(key, value);
    } catch (e) {
      /* localStorage unavailable (private mode / quota) — theme & language
         still work for this page load, they just won't persist. */
    }
  }

  /** Dotted-path lookup, e.g. t('en', 'blog.posts.atlas.title'). Falls back
   *  to Persian, then to the key itself, so a missing translation never
   *  renders as literally blank. */
  function t(lang, key) {
    var langNode = DICT[lang] || DICT.fa || {};
    var parts = key.split('.');
    var node = langNode;
    for (var i = 0; i < parts.length; i++) {
      if (node && typeof node === 'object' && parts[i] in node) {
        node = node[parts[i]];
      } else {
        node = undefined;
        break;
      }
    }
    if (node === undefined && lang !== 'fa') return t('fa', key);
    return node === undefined ? key : node;
  }

  /* ------------------------------ theme ------------------------------ */
  function currentTheme() {
    var v = document.documentElement.getAttribute('data-theme');
    return THEMES.indexOf(v) !== -1 ? v : 'dark';
  }

  function setTheme(theme, opts) {
    opts = opts || {};
    if (THEMES.indexOf(theme) === -1) theme = 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    if (opts.persist !== false) writeStorage(THEME_KEY, theme);
    document.dispatchEvent(new CustomEvent('site:theme-change', { detail: { theme: theme } }));

    var lang = currentLang();
    var btn = document.getElementById('themeToggle');
    if (btn) {
      btn.setAttribute('aria-pressed', theme === 'light' ? 'true' : 'false');
      btn.setAttribute('aria-label', theme === 'dark' ? t(lang, 'common.themeToLight') : t(lang, 'common.themeToDark'));
      if (opts.animate) {
        btn.classList.remove('is-flipping');
        // Force reflow so the animation restarts if clicked twice quickly.
        void btn.offsetWidth;
        btn.classList.add('is-flipping');
      }
    }
  }

  function initThemeToggle() {
    var btn = document.getElementById('themeToggle');
    if (!btn) return;
    btn.addEventListener('click', function () {
      setTheme(currentTheme() === 'dark' ? 'light' : 'dark', { animate: true });
    });
  }

  /* ---------------------------- language ------------------------------ */
  function currentLang() {
    var v = document.documentElement.getAttribute('lang');
    return LANGS.indexOf(v) !== -1 ? v : 'fa';
  }

  function applyTranslations(lang) {
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
      el.textContent = t(lang, el.getAttribute('data-i18n'));
    });
    document.querySelectorAll('[data-i18n-html]').forEach(function (el) {
      el.innerHTML = t(lang, el.getAttribute('data-i18n-html'));
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
      el.setAttribute('placeholder', t(lang, el.getAttribute('data-i18n-placeholder')));
    });
    document.querySelectorAll('[data-i18n-aria-label]').forEach(function (el) {
      el.setAttribute('aria-label', t(lang, el.getAttribute('data-i18n-aria-label')));
    });
    document.querySelectorAll('[data-i18n-content]').forEach(function (el) {
      el.setAttribute('content', t(lang, el.getAttribute('data-i18n-content')));
    });
    var titleEl = document.querySelector('title[data-i18n]');
    if (titleEl) document.title = t(lang, titleEl.getAttribute('data-i18n'));

    // Theme-toggle aria-label depends on both language and theme.
    setTheme(currentTheme(), { persist: false });

    // A currently-open FAQ item has an inline max-height computed from the
    // *previous* language's text; new text can be a different length, so
    // recompute it or the answer silently clips.
    document.querySelectorAll('.faq-item.is-open .faq-item__a').forEach(function (a) {
      a.style.maxHeight = a.scrollHeight + 'px';
    });
  }

  function setLanguage(lang, opts) {
    opts = opts || {};
    if (LANGS.indexOf(lang) === -1) lang = 'fa';
    var dir = lang === 'en' ? 'ltr' : 'rtl';
    document.documentElement.setAttribute('lang', lang);
    document.documentElement.setAttribute('dir', dir);
    if (opts.persist !== false) writeStorage(LANG_KEY, lang);

    applyTranslations(lang);
    syncLangSwitchUI(lang);

    // The bottom-nav's active-pill indicator is positioned in pixels from a
    // live getBoundingClientRect() measurement, so it already adapts to the
    // new direction automatically — it just needs to be told to re-measure
    // now that the direction/labels actually changed.
    if (typeof window.__repositionBottomNav === 'function') {
      window.__repositionBottomNav(false);
    }

    document.dispatchEvent(new CustomEvent('site:language-change', { detail: { lang: lang, dir: dir } }));
  }

  function syncLangSwitchUI(lang) {
    var code = document.getElementById('langCode');
    if (code) code.textContent = lang === 'fa' ? 'فا' : lang === 'ar' ? 'عر' : 'EN';
    var trigger = document.getElementById('langTrigger');
    if (trigger) trigger.setAttribute('aria-label', t(lang, 'common.langLabel'));
    document.querySelectorAll('.lang-switch__option').forEach(function (opt) {
      opt.classList.toggle('is-active', opt.getAttribute('data-lang') === lang);
    });
  }

  function initLangSwitch() {
    var root = document.getElementById('langSwitch');
    var trigger = document.getElementById('langTrigger');
    var menu = document.getElementById('langMenu');
    if (!root || !trigger || !menu) return;

    function open() {
      root.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
    }
    function close() {
      root.classList.remove('is-open');
      trigger.setAttribute('aria-expanded', 'false');
    }
    function toggle() {
      if (root.classList.contains('is-open')) close();
      else open();
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      toggle();
    });
    menu.querySelectorAll('.lang-switch__option').forEach(function (opt) {
      opt.addEventListener('click', function () {
        setLanguage(opt.getAttribute('data-lang'));
        close();
        trigger.focus();
      });
    });
    document.addEventListener('click', function (e) {
      if (!root.contains(e.target)) close();
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && root.classList.contains('is-open')) {
        close();
        trigger.focus();
      }
    });
  }

  function init() {
    setTheme(currentTheme(), {});
    initThemeToggle();
    initLangSwitch();
    applyTranslations(currentLang());
    syncLangSwitchUI(currentLang());
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Exposed for page-specific scripts (currently js/blog.js) that need to
  // read translated strings for content they render dynamically.
  window.siteT = t;
})();

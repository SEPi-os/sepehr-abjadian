/**
 * space-main.js
 * Entry point (loaded as a module from index.html). Feature-detects WebGL,
 * picks a device/quality tier, boots the SpaceScene + scroll controller,
 * and falls back to the existing CSS aurora backdrop if anything goes
 * wrong — the rest of the page must never depend on this succeeding.
 */
import { supportsWebGL, detectQualityTier, TIER_SETTINGS } from './space/utils.js?v=20260913';
import { SpaceScene, loadSpaceTextures } from './space/scene.js?v=20260913';
import { createScrollController } from './space/scroll-controller.js?v=20260913';
import { gsap } from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

// This file's own trigger and portrait-hero's pinned trigger(s) each refresh
// once, right when they're created — but images/fonts can keep loading (or
// a web font can swap in) after that, quietly changing the page's total
// height and leaving those pin/scroll mappings stale. That's what showed up
// as the hero/portrait section glitching or sticking when scrolling back up.
// One more refresh once the whole window (every image included) has
// actually finished loading fixes it — registered unconditionally, here at
// the top, so it still runs even if the space scene below can't (no WebGL,
// init failure, etc.) since portrait-hero's own pin needs it regardless.
window.addEventListener('load', () => {
  setTimeout(() => ScrollTrigger.refresh(), 350);
}, { once: true });

function enableFallback() {
  document.body.classList.add('no-webgl');
  const container = document.getElementById('space-scene');
  if (container) container.remove();
}

async function boot() {
  const container = document.getElementById('space-scene');
  const canvas = document.getElementById('space-canvas');
  if (!container || !canvas) return;

  if (!supportsWebGL()) {
    enableFallback();
    return;
  }

  const { tier, reducedMotion } = detectQualityTier();
  const tierSettings = TIER_SETTINGS[tier];

  let scene = null;
  try {
    scene = new SpaceScene(canvas, { tierSettings, reducedMotion });
    const textures = await loadSpaceTextures();
    await scene.init(textures);
  } catch (err) {
    console.warn('[space-scene] WebGL init failed, using fallback backdrop:', err);
    if (scene) scene.dispose();
    enableFallback();
    return;
  }

  scene.start();
  const scrollController = createScrollController(scene, { reducedMotion });

  requestAnimationFrame(() => container.classList.add('is-ready'));

  window.addEventListener(
    'pagehide',
    () => {
      scrollController.destroy();
      scene.dispose();
    },
    { once: true }
  );
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

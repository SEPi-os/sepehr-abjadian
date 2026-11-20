/**
 * portrait-hero.js
 * The animated, scroll-pinned, cursor-reactive portrait at the very top of
 * the page — a full-bleed cinematic treatment inspired by the interaction
 * quality of landonorris.com, built entirely from this project's own two
 * source portraits (a natural photo + a stylised neon/illustrated version)
 * rather than any copied assets, markup or code.
 *
 * Interaction model (by design, on purpose separated so none of these
 * four ever fight each other):
 *  - Idle (default, no hover): a slow automatic "glimmer" drifts a small
 *    neon-reveal spot across the portrait, plus a gentle breathing scale
 *    and an ambient light sweep (CSS, always running) — never a full-image
 *    swap.
 *  - Hover (fine pointer): the reveal spot follows the cursor directly,
 *    still only revealing a small local area (an organic wobbling blob,
 *    not a crossfade), plus a gentle 3D tilt of the portrait plane and a
 *    touch of chromatic aberration.
 *  - Scroll: the section pins while the camera pulls back and the plane
 *    rotates, focus racks softly through the middle of the sequence, a
 *    faint parallax drift moves the image against the frame, and the
 *    portrait dissolves into the page background near the end — a pure
 *    camera move that never touches which pixels are "real" vs "neon".
 *  - No WebGL / reduced motion: a CSS mask-image reproduces the same
 *    local-spotlight reveal without Three.js, with every effect above
 *    either dropped or heavily dampened for a clean, simple fallback.
 */
import * as THREE from 'three';
import { gsap } from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

const REAL_SRC = new URL('../assets/hero/portrait-real.webp', import.meta.url).href;
const NEON_SRC = new URL('../assets/hero/portrait-neon.webp', import.meta.url).href;
const ASPECT = 976 / 1078;
const PIN_VH = 1.2;

function clamp(v, min, max) {
  return Math.max(min, Math.min(max, v));
}
function mapClamped(inMin, inMax, outMin, outMax, v) {
  const t = clamp((v - inMin) / (inMax - inMin), 0, 1);
  return outMin + (outMax - outMin) * t;
}
function damp(current, target, lambda, dt) {
  return current + (target - current) * (1 - Math.exp(-lambda * dt));
}
/** Slow organic drift target for the idle glimmer — never fully off, never full strength. */
function idleTarget(t) {
  return {
    x: 0.5 + Math.sin(t * 0.19) * 0.16,
    y: 0.42 + Math.cos(t * 0.14) * 0.13,
    strength: 0.3 + (Math.sin(t * 0.27) * 0.5 + 0.5) * 0.4,
  };
}
function supportsWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
  } catch (e) {
    return false;
  }
}

export function initPortraitHero() {
  const section = document.getElementById('portraitHero');
  const stage = document.getElementById('portraitStage');
  if (!section || !stage) return;

  // Phones don't get this section at all (see style.css) — the About
  // section becomes the first visible thing instead. Skip every bit of
  // setup below (WebGL context, ScrollTrigger pin, idle-animation RAF
  // loop, pointer listeners) rather than just letting it run invisibly.
  if (window.matchMedia('(max-width: 760px)').matches) return;

  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const isFinePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  const lowPower = reducedMotion || window.matchMedia('(max-width: 760px)').matches;

  if (supportsWebGL()) {
    try {
      initPortrait3D({ section, stage, isFinePointer, lowPower, reducedMotion });
      return;
    } catch (err) {
      console.warn('[portrait-hero] 3D init failed, using 2D fallback:', err);
    }
  }
  initPortrait2D({ section, stage, isFinePointer, reducedMotion });
}

/* ============================== 3D path ============================== */
function initPortrait3D({ section, stage, isFinePointer, lowPower, reducedMotion }) {
  const glCanvas = document.getElementById('portraitGL');
  const glow = document.getElementById('portraitGlow');

  const FOV = 32;
  const FIT_Z = 1 / (2 * Math.tan(THREE.MathUtils.degToRad(FOV / 2)));

  const renderer = new THREE.WebGLRenderer({ canvas: glCanvas, alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, lowPower ? 1 : 2));
  renderer.outputColorSpace = THREE.SRGBColorSpace;

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(FOV, ASPECT, 0.1, 10);
  camera.position.z = FIT_Z * 1.18;

  const geometry = new THREE.PlaneGeometry(ASPECT, 1, 1, 1);
  const material = new THREE.ShaderMaterial({
    transparent: true,
    uniforms: {
      uTexReal: { value: null },
      uTexNeon: { value: null },
      uMouse: { value: new THREE.Vector2(0.5, 0.42) },
      uHoverRadius: { value: 0.2 },
      uHoverStrength: { value: 0 },
      uAspect: { value: ASPECT },
      uAberration: { value: 0 },
      uTime: { value: 0 },
    },
    vertexShader: /* glsl */ `
      varying vec2 vUv;
      void main() {
        vUv = uv;
        gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
      }
    `,
    fragmentShader: /* glsl */ `
      uniform sampler2D uTexReal;
      uniform sampler2D uTexNeon;
      uniform vec2 uMouse;
      uniform float uHoverRadius;
      uniform float uHoverStrength;
      uniform float uAspect;
      uniform float uAberration;
      uniform float uTime;
      varying vec2 vUv;
      void main() {
        vec2 diff = vUv - uMouse;
        diff.x *= uAspect;
        float angle = atan(diff.y, diff.x);
        // Organic "blob" wobble: perturb the spotlight radius with two slow
        // angular harmonics so the reveal edge breathes unevenly instead of
        // tracing a perfect circle.
        float wobble = 1.0
          + 0.06 * sin(angle * 3.0 + uTime * 0.55)
          + 0.035 * sin(angle * 5.0 - uTime * 0.85);
        float dist = length(diff) / wobble;
        // Local spotlight only — this is the whole fix: reveal is always
        // clamped to a small radius around uMouse, never the full frame.
        float spotlight = 1.0 - smoothstep(uHoverRadius * 0.5, uHoverRadius, dist);
        spotlight = clamp(spotlight * uHoverStrength, 0.0, 1.0);

        // Subtle chromatic aberration: sample R/B with a tiny UV offset
        // radiating from the reveal centre — reads as lens dispersion
        // around the active spotlight/camera move, not a flat filter.
        vec2 off = diff * uAberration;
        vec4 realC = texture2D(uTexReal, vUv);
        vec4 neonC = texture2D(uTexNeon, vUv);
        float r = mix(texture2D(uTexReal, vUv + off).r, texture2D(uTexNeon, vUv + off).r, spotlight);
        float b = mix(texture2D(uTexReal, vUv - off).b, texture2D(uTexNeon, vUv - off).b, spotlight);
        float g = mix(realC.g, neonC.g, spotlight);
        float a = mix(realC.a, neonC.a, spotlight);
        gl_FragColor = vec4(r, g, b, a);
      }
    `,
  });

  const mesh = new THREE.Mesh(geometry, material);
  scene.add(mesh);

  const loader = new THREE.TextureLoader();
  let loadedCount = 0;
  let ready = false;
  let failed = false;
  function onTextureLoad() {
    loadedCount++;
    if (loadedCount === 2 && !ready && !failed) {
      ready = true;
      glCanvas.classList.add('is-ready');
      gsap.fromTo(
        camera.position,
        { z: FIT_Z * 1.18 },
        { z: FIT_Z, duration: 1.7, ease: 'expo.out', onUpdate: () => camera.updateProjectionMatrix() }
      );
      gsap.to(stage, { opacity: 1, y: 0, duration: 1.1, ease: 'expo.out' });
    }
  }
  function onTextureError(err) {
    // Leave the canvas transparent so the plain <img id="portraitImg">
    // underneath (already loaded independently, same src) stays visible —
    // a correct static portrait beats a shader sampling an empty texture.
    failed = true;
    console.warn('[portrait-hero] texture failed to load, showing static photo:', err);
    gsap.to(stage, { opacity: 1, y: 0, duration: 1.1, ease: 'expo.out' });
    stop();
    geometry.dispose();
    material.dispose();
    renderer.dispose();
  }
  gsap.set(stage, { opacity: 0, y: 24 });
  const texReal = loader.load(REAL_SRC, onTextureLoad, undefined, onTextureError);
  const texNeon = loader.load(NEON_SRC, onTextureLoad, undefined, onTextureError);
  texReal.colorSpace = THREE.SRGBColorSpace;
  texNeon.colorSpace = THREE.SRGBColorSpace;
  material.uniforms.uTexReal.value = texReal;
  material.uniforms.uTexNeon.value = texNeon;

  function resize() {
    const rect = glCanvas.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    renderer.setSize(rect.width, rect.height, false);
    camera.aspect = rect.width / rect.height;
    camera.updateProjectionMatrix();
  }
  resize();
  let resizeTimer;
  const onResize = () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(resize, 150);
  };
  window.addEventListener('resize', onResize);

  /* ---- hover (fine pointer): local spotlight follows cursor + tilt ---- */
  let hoverActive = false;
  let scrollLocked = false;
  let scrollAberration = 0;
  const tiltMax = THREE.MathUtils.degToRad(9);
  const raycaster = new THREE.Raycaster();
  const ndc = new THREE.Vector2();

  function pointerToUV(clientX, clientY) {
    const rect = glCanvas.getBoundingClientRect();
    ndc.x = ((clientX - rect.left) / rect.width) * 2 - 1;
    ndc.y = -((clientY - rect.top) / rect.height) * 2 + 1;
    camera.updateMatrixWorld();
    raycaster.setFromCamera(ndc, camera);
    const hits = raycaster.intersectObject(mesh);
    return { ndc, uv: hits.length ? hits[0].uv : null };
  }

  let onMove, onEnter, onLeave, onTouch;
  if (isFinePointer && !reducedMotion) {
    let raf = 0;
    let lastEvt = null;
    const frame = () => {
      raf = 0;
      if (!lastEvt || scrollLocked) return;
      const hit = pointerToUV(lastEvt.clientX, lastEvt.clientY);
      if (hit.uv) {
        gsap.to(material.uniforms.uMouse.value, {
          x: hit.uv.x,
          y: hit.uv.y,
          duration: 0.3,
          ease: 'power2.out',
          overwrite: 'auto',
        });
      }
      gsap.to(mesh.rotation, {
        x: -hit.ndc.y * tiltMax * 0.6,
        y: hit.ndc.x * tiltMax,
        duration: 0.6,
        ease: 'power3.out',
        overwrite: 'auto',
      });
    };
    onMove = (e) => {
      lastEvt = e;
      if (!raf) raf = requestAnimationFrame(frame);
    };
    onEnter = () => {
      if (scrollLocked) return;
      hoverActive = true;
      gsap.to(material.uniforms.uHoverStrength, { value: 1, duration: 0.6, ease: 'expo.out', overwrite: 'auto' });
      gsap.to(glow, { opacity: 1, duration: 0.6, ease: 'expo.out' });
    };
    onLeave = () => {
      hoverActive = false;
      gsap.to(mesh.rotation, { x: 0, y: 0, duration: 0.8, ease: 'power3.out' });
      gsap.to(glow, { opacity: 0, duration: 0.6 });
    };
    glCanvas.addEventListener('mousemove', onMove);
    glCanvas.addEventListener('mouseenter', onEnter);
    glCanvas.addEventListener('mouseleave', onLeave);
  } else if (!reducedMotion) {
    onTouch = (e) => {
      if (scrollLocked) return;
      const t = e.touches[0];
      const hit = pointerToUV(t.clientX, t.clientY);
      if (hit.uv) material.uniforms.uMouse.value.copy(hit.uv);
      gsap.to(glow, { opacity: 1, duration: 0.3, yoyo: true, repeat: 1, ease: 'power2.inOut' });
      gsap
        .timeline()
        .to(material.uniforms.uHoverStrength, { value: 1, duration: 0.4, ease: 'expo.out' })
        .to(material.uniforms.uHoverStrength, { value: 0, duration: 1.1, ease: 'power2.in' }, 0.5);
    };
    glCanvas.addEventListener('touchstart', onTouch, { passive: true });
  }

  /* ---- scroll: camera move + soft focus pull + parallax + dissolve,
     decoupled from the reveal mix ---- */
  const zoomTo = reducedMotion ? 1.07 : 1.34;
  const rotYDeg = reducedMotion ? 1.5 : 10;
  const rotXDeg = reducedMotion ? 0.6 : 4;
  const parallaxPx = reducedMotion ? 0 : 34;

  const trigger = ScrollTrigger.create({
    trigger: section,
    start: 'top top',
    end: () => '+=' + Math.round(window.innerHeight * PIN_VH),
    pin: true,
    scrub: 0.6,
    invalidateOnRefresh: true,
    onUpdate(self) {
      const p = self.progress;
      scrollLocked = p > 0.02;
      if (scrollLocked && hoverActive) {
        hoverActive = false;
        gsap.to(material.uniforms.uHoverStrength, { value: 0, duration: 0.4 });
      }

      camera.position.z = FIT_Z * mapClamped(0, 1, 1, zoomTo, p);
      camera.updateProjectionMatrix();
      mesh.rotation.y = mapClamped(0, 1, 0, THREE.MathUtils.degToRad(-rotYDeg), p);
      mesh.rotation.x = mapClamped(0, 1, 0, THREE.MathUtils.degToRad(rotXDeg), p);

      // Soft focus pull: sharp at rest, a brief rack-focus through the
      // depth-rich middle of the sequence, sharp again well before the end
      // (fixes the old version, which stayed slightly blurred at rest).
      const focusP = clamp(p, 0, 0.6) / 0.6;
      const blur = reducedMotion ? 0 : Math.sin(focusP * Math.PI) * 3.4;
      glCanvas.style.filter = 'blur(' + blur.toFixed(2) + 'px)';

      const py = mapClamped(0, 1, 0, -parallaxPx, p);
      glCanvas.style.transform = 'translateY(' + py.toFixed(2) + 'px)';

      scrollAberration = reducedMotion ? 0 : Math.sin(clamp(p, 0, 1) * Math.PI) * 0.0022;

      // Soft curtain dissolve into the About section as the sequence ends.
      const fadeH = mapClamped(0.62, 1, 30, reducedMotion ? 42 : 86, p);
      stage.style.setProperty('--fade-h', fadeH.toFixed(1) + '%');
    },
  });
  ScrollTrigger.refresh();

  /* ---- render loop: also drives the idle glimmer, frame-rate independent ---- */
  let rafId = null;
  let lastTime = null;
  let idleT = Math.random() * 100;
  let clockT = 0;
  function tick(now) {
    rafId = requestAnimationFrame(tick);
    const dt = lastTime === null ? 0 : Math.min((now - lastTime) / 1000, 0.1);
    lastTime = now;
    clockT += dt;
    material.uniforms.uTime.value = clockT;

    if (!hoverActive && !scrollLocked && ready && !reducedMotion) {
      idleT += dt * 0.6;
      const target = idleTarget(idleT);
      const m = material.uniforms.uMouse.value;
      m.x = damp(m.x, target.x, 1.4, dt);
      m.y = damp(m.y, target.y, 1.4, dt);
      material.uniforms.uHoverStrength.value = damp(
        material.uniforms.uHoverStrength.value,
        target.strength,
        1.4,
        dt
      );
    }

    const targetAberration = reducedMotion
      ? 0
      : 0.0004 + material.uniforms.uHoverStrength.value * 0.0016 + scrollAberration;
    material.uniforms.uAberration.value = damp(material.uniforms.uAberration.value, targetAberration, 3, dt);

    renderer.render(scene, camera);
  }
  function start() {
    if (rafId === null) {
      lastTime = null;
      rafId = requestAnimationFrame(tick);
    }
  }
  function stop() {
    if (rafId !== null) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
  }
  start();

  const onVisibility = () => (document.hidden ? stop() : start());
  document.addEventListener('visibilitychange', onVisibility);

  window.addEventListener(
    'pagehide',
    () => {
      stop();
      trigger.kill();
      window.removeEventListener('resize', onResize);
      document.removeEventListener('visibilitychange', onVisibility);
      if (onMove) glCanvas.removeEventListener('mousemove', onMove);
      if (onEnter) glCanvas.removeEventListener('mouseenter', onEnter);
      if (onLeave) glCanvas.removeEventListener('mouseleave', onLeave);
      if (onTouch) glCanvas.removeEventListener('touchstart', onTouch);
      geometry.dispose();
      material.dispose();
      texReal.dispose();
      texNeon.dispose();
      renderer.dispose();
    },
    { once: true }
  );
}

/* ======================= 2D fallback (no WebGL) ======================= */
function initPortrait2D({ section, stage, isFinePointer, reducedMotion }) {
  const glCanvas = document.getElementById('portraitGL');
  if (glCanvas) glCanvas.remove();
  const glow = document.getElementById('portraitGlow');
  const fallbackImg = document.getElementById('portraitImg');

  const neonImg = document.createElement('img');
  neonImg.src = NEON_SRC;
  neonImg.alt = '';
  neonImg.setAttribute('aria-hidden', 'true');
  neonImg.className = 'portrait-fallback portrait-fallback-neon';
  fallbackImg.insertAdjacentElement('afterend', neonImg);

  gsap.set(stage, { opacity: 0, y: 24 });
  gsap.to(stage, { opacity: 1, y: 0, duration: 1.1, ease: 'expo.out', delay: 0.15 });

  const state = { x: 0.5, y: 0.42, strength: 0 };
  function paint() {
    stage.style.setProperty('--pmx', state.x * 100 + '%');
    stage.style.setProperty('--pmy', state.y * 100 + '%');
    neonImg.style.opacity = String(state.strength);
  }
  paint();

  let hoverActive = false;
  let scrollLocked = false;
  let onMove, onEnter, onLeave, onTouch;

  if (isFinePointer && !reducedMotion) {
    onMove = (e) => {
      if (scrollLocked) return;
      const r = stage.getBoundingClientRect();
      state.x = clamp((e.clientX - r.left) / r.width, 0, 1);
      state.y = clamp((e.clientY - r.top) / r.height, 0, 1);
    };
    onEnter = () => {
      if (scrollLocked) return;
      hoverActive = true;
      gsap.to(glow, { opacity: 1, duration: 0.6 });
    };
    onLeave = () => {
      hoverActive = false;
      gsap.to(glow, { opacity: 0, duration: 0.6 });
    };
    stage.addEventListener('mousemove', onMove);
    stage.addEventListener('mouseenter', onEnter);
    stage.addEventListener('mouseleave', onLeave);
  } else if (!reducedMotion) {
    onTouch = () => {
      if (scrollLocked) return;
      gsap.to(glow, { opacity: 1, duration: 0.3, yoyo: true, repeat: 1 });
      gsap.fromTo(state, { strength: 1 }, { strength: 0, duration: 1.1, ease: 'power2.in' });
    };
    stage.addEventListener('touchstart', onTouch, { passive: true });
  }

  let rafId = null;
  let lastTime = null;
  let idleT = Math.random() * 100;
  function tick(now) {
    rafId = requestAnimationFrame(tick);
    const dt = lastTime === null ? 0 : Math.min((now - lastTime) / 1000, 0.1);
    lastTime = now;
    if (reducedMotion) {
      state.strength = damp(state.strength, 0, 4, dt);
    } else if (hoverActive) {
      state.strength = damp(state.strength, 1, 6, dt);
    } else if (!scrollLocked) {
      idleT += dt * 0.6;
      const target = idleTarget(idleT);
      state.x = damp(state.x, target.x, 1.4, dt);
      state.y = damp(state.y, target.y, 1.4, dt);
      state.strength = damp(state.strength, target.strength, 1.4, dt);
    } else {
      state.strength = damp(state.strength, 0, 6, dt);
    }
    paint();
  }
  function start() {
    if (rafId === null) {
      lastTime = null;
      rafId = requestAnimationFrame(tick);
    }
  }
  function stop() {
    if (rafId !== null) {
      cancelAnimationFrame(rafId);
      rafId = null;
    }
  }
  start();

  const onVisibility = () => (document.hidden ? stop() : start());
  document.addEventListener('visibilitychange', onVisibility);

  const trigger = ScrollTrigger.create({
    trigger: section,
    start: 'top top',
    end: () => '+=' + Math.round(window.innerHeight * PIN_VH),
    pin: true,
    scrub: 0.6,
    invalidateOnRefresh: true,
    onUpdate(self) {
      const p = self.progress;
      scrollLocked = p > 0.02;
      const fadeH = mapClamped(0.62, 1, 30, reducedMotion ? 42 : 86, p);
      stage.style.setProperty('--fade-h', fadeH.toFixed(1) + '%');
    },
  });
  ScrollTrigger.refresh();

  window.addEventListener(
    'pagehide',
    () => {
      stop();
      trigger.kill();
      document.removeEventListener('visibilitychange', onVisibility);
      if (onMove) stage.removeEventListener('mousemove', onMove);
      if (onEnter) stage.removeEventListener('mouseenter', onEnter);
      if (onLeave) stage.removeEventListener('mouseleave', onLeave);
      if (onTouch) stage.removeEventListener('touchstart', onTouch);
    },
    { once: true }
  );
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPortraitHero);
} else {
  initPortraitHero();
}

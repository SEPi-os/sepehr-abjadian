/**
 * scene.js
 * Owns the renderer/camera/lights/composer and the render loop. Composes
 * Starfield + Environment + Saturn, and exposes a small public API
 * (setScrollProgress, setPointer) that scroll-controller.js drives.
 * Nothing here reads the DOM or GSAP directly — keeps this module reusable
 * outside of this one page if needed.
 */
import * as THREE from 'three';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

import { Starfield } from './starfield.js?v=20260913';
import { Environment, createSceneFog, createFogWisp } from './environment.js?v=20260913';
import { Saturn } from './saturn.js?v=20260913';
import { clamp, damp, ndcToWorld } from './utils.js?v=20260913';

const ASSET_BASE = new URL('../../assets/space/', import.meta.url);

/** 'saturn' on dark theme, 'blackhole' on light theme — see saturn.js for
 *  why this swap exists (a filtered copy of the dark-mode planet read as
 *  washed-out/broken in light mode; a genuinely different object doesn't). */
function saturnModeForCurrentTheme() {
  return document.documentElement.getAttribute('data-theme') === 'light' ? 'blackhole' : 'saturn';
}

const ASSET_FILES = {
  star: 'star-sprite.png',
  nebula1: 'nebula-cloud-1.png',
  nebula2: 'nebula-cloud-2.png',
  galaxy1: 'galaxy-sprite-3.png',
  galaxy2: 'galaxy-sprite-4.png',
  saturnDiffuse: 'saturn-diffuse.jpg',
  saturnRing: 'saturn-ring.png',
};

// Saturn's path hugs the viewport edges/corners so it never sits behind
// dense text; nx/ny are normalized viewport coords (-1..1), z is world depth.
const SATURN_WAYPOINTS = [
  { nx: -0.74, ny: 0.3, z: -8 },
  { nx: 0.8, ny: 0.06, z: 4 },
  { nx: -0.7, ny: -0.24, z: -12 },
  { nx: 0.68, ny: -0.38, z: 7 },
  { nx: 0.0, ny: 0.5, z: -16 },
  { nx: -0.62, ny: 0.36, z: -2 },
];

export async function loadSpaceTextures() {
  const loader = new THREE.TextureLoader();
  const keys = Object.keys(ASSET_FILES);
  const textures = await Promise.all(
    keys.map((key) => loader.loadAsync(new URL(ASSET_FILES[key], ASSET_BASE).href))
  );
  const out = {};
  keys.forEach((key, i) => {
    out[key] = textures[i];
  });
  return out;
}

export class SpaceScene {
  constructor(canvas, { tierSettings, reducedMotion }) {
    this.canvas = canvas;
    this.tierSettings = tierSettings;
    this.reducedMotion = reducedMotion;

    this.timer = new THREE.Timer();
    this.pointer = { x: 0, y: 0 };
    this.targetPointer = { x: 0, y: 0 };
    this.scrollProgress = 0;
    this.scrollDirection = 1;

    this._rafId = null;
    this._disposed = false;
    this._saturnPathPoint = new THREE.Vector3();

    this.scene = new THREE.Scene();
    this.scene.fog = createSceneFog();

    this.camera = new THREE.PerspectiveCamera(52, window.innerWidth / window.innerHeight, 0.1, 2000);
    this.camera.position.set(0, 2, 62);
    this._cameraBase = this.camera.position.clone();

    this.renderer = new THREE.WebGLRenderer({
      canvas,
      antialias: tierSettings.antialias !== false,
      alpha: true,
      powerPreference: 'high-performance',
    });
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.05;
    this.renderer.setClearColor(0x000000, 0);

    this._setupLights();

    this._onResize = this._onResize.bind(this);
    this._onVisibilityChange = this._onVisibilityChange.bind(this);
    this._onContextLost = this._onContextLost.bind(this);
    this._onThemeChange = this._onThemeChange.bind(this);

    canvas.addEventListener('webglcontextlost', this._onContextLost, false);
    window.addEventListener('resize', this._onResize);
    document.addEventListener('visibilitychange', this._onVisibilityChange);
    document.addEventListener('site:theme-change', this._onThemeChange);
  }

  _setupLights() {
    const sun = new THREE.DirectionalLight(0xfff1d8, 2.2);
    sun.position.set(-40, 22, 30);
    this.scene.add(sun);

    const fill = new THREE.HemisphereLight(0x6a7fff, 0x0a0f22, 0.55);
    this.scene.add(fill);

    const rim = new THREE.PointLight(0x7af0ff, 60, 260, 2);
    rim.position.set(30, -10, -40);
    this.scene.add(rim);

    this.scene.add(new THREE.AmbientLight(0x2a3560, 0.4));
  }

  async init(textures) {
    this._textures = textures;
    const t = this.tierSettings;

    this.starfield = new Starfield({ counts: t.starCounts, sprite: textures.star, seed: 7 });
    this.scene.add(this.starfield.group);

    this.environment = new Environment({
      nebulaTextures: [textures.nebula1, textures.nebula2],
      galaxyTextures: [textures.galaxy1, textures.galaxy2],
      dustSprite: textures.star,
      counts: t,
      seed: 21,
    });
    this.scene.add(this.environment.group);

    this._fogWisp = createFogWisp(textures.nebula1);
    this.scene.add(this._fogWisp);

    this.saturn = new Saturn({
      diffuseTexture: textures.saturnDiffuse,
      ringTexture: textures.saturnRing,
      sphereSegments: t.sphereSegments,
      ringSegments: t.ringSegments,
      mode: saturnModeForCurrentTheme(),
    });
    this.saturn.root.scale.setScalar(t.saturnScale);
    this.scene.add(this.saturn.root);

    this._handleResize();
    this._rebuildSaturnCurve();
    this._setupComposer();
  }

  _setupComposer() {
    if (!this.tierSettings.bloom) return;
    this.composer = new EffectComposer(this.renderer);
    this.composer.addPass(new RenderPass(this.scene, this.camera));

    const size = new THREE.Vector2(window.innerWidth, window.innerHeight);
    this.bloomPass = new UnrealBloomPass(size, 0.5, 0.7, 0.86);
    this.composer.addPass(this.bloomPass);
    this.composer.addPass(new OutputPass());
  }

  _rebuildSaturnCurve() {
    const points = SATURN_WAYPOINTS.map((wp) =>
      ndcToWorld(this.camera, wp.nx * 0.92, wp.ny, wp.z, new THREE.Vector3())
    );
    this._saturnCurve = new THREE.CatmullRomCurve3(points, false, 'catmullrom', 0.5);
  }

  _handleResize() {
    const width = window.innerWidth;
    const height = window.innerHeight;
    this.camera.aspect = width / height;
    this.camera.updateProjectionMatrix();

    const dpr = Math.min(window.devicePixelRatio || 1, this.tierSettings.maxDpr);
    this.renderer.setPixelRatio(dpr);
    this.renderer.setSize(width, height, false);

    if (this.composer) this.composer.setSize(width, height);
    if (this.bloomPass) this.bloomPass.resolution.set(width, height);
    if (this._saturnCurve) this._rebuildSaturnCurve();
  }

  _onResize() {
    // Debounced (matches the same pattern already used for the hero canvas
    // and bottom-nav resize handlers): a window drag-resize or a mobile
    // browser chrome show/hide can fire this dozens of times a second, and
    // each call rebuilds the Saturn path curve and touches the renderer's
    // pixel ratio/size — coalescing to one call ~120ms after the last event
    // avoids doing that work on every intermediate frame.
    clearTimeout(this._resizeTimer);
    this._resizeTimer = setTimeout(() => this._handleResize(), 120);
  }

  _onVisibilityChange() {
    if (document.hidden) this.stop();
    else this.start();
  }

  _onContextLost(event) {
    event.preventDefault();
    this.stop();
    document.body.classList.add('no-webgl');
  }

  _onThemeChange(event) {
    const theme = event?.detail?.theme;
    const mode = theme === 'light' ? 'blackhole' : 'saturn';
    if (this.saturn) this.saturn.setMode(mode);

    // The page-wide light/dark CSS transition (body background-color/color,
    // nav/card surfaces, etc.) fades over ~0.5s. That's a paint-driven
    // transition, and this scene's per-frame JS (starfield/environment/
    // Saturn updates + a WebGL draw call) is heavy enough to starve the
    // main thread of the idle time such transitions need to sample and
    // commit — without this pause it was measured taking several seconds
    // to visually resolve instead of half a second, i.e. the rest of the
    // page looked stuck in the old theme. Pausing the render loop for the
    // transition's duration removes that contention; nothing else about
    // the scene (position/scroll state) changes while paused, so resuming
    // is seamless.
    clearTimeout(this._themeTransitionResumeTimer);
    const wasRunning = this._rafId !== null;
    this.stop();
    if (wasRunning) {
      this._themeTransitionResumeTimer = setTimeout(() => this.start(), 650);
    }
  }

  setScrollProgress(progress, direction) {
    this.scrollProgress = clamp(progress, 0, 1);
    if (direction) this.scrollDirection = direction;
  }

  setPointer(nx, ny) {
    this.targetPointer.x = clamp(nx, -1, 1);
    this.targetPointer.y = clamp(ny, -1, 1);
  }

  start() {
    if (this._rafId !== null || this._disposed) return;
    this.timer.reset();
    const tick = (timestamp) => {
      this._rafId = requestAnimationFrame(tick);
      this.timer.update(timestamp);
      this._update();
      this._render();
    };
    this._rafId = requestAnimationFrame(tick);
  }

  stop() {
    if (this._rafId !== null) {
      cancelAnimationFrame(this._rafId);
      this._rafId = null;
    }
  }

  _update() {
    const delta = Math.min(this.timer.getDelta(), 0.1);
    const elapsed = this.timer.getElapsed();
    const intensity = this.reducedMotion ? 0.35 : 1;

    this.pointer.x = damp(this.pointer.x, this.targetPointer.x, 4, delta);
    this.pointer.y = damp(this.pointer.y, this.targetPointer.y, 4, delta);

    this.starfield.update({
      scrollProgress: this.scrollProgress,
      elapsed,
      pointer: this.pointer,
      intensity,
    });
    this.environment.update({ elapsed, delta, scrollProgress: this.scrollProgress, intensity });
    this.saturn.update({ delta, elapsed, scrollDirection: this.scrollDirection, intensity });

    if (this._saturnCurve) {
      const tt = clamp(this.scrollProgress, 0, 1);
      this._saturnCurve.getPointAt(tt, this._saturnPathPoint);
      const breathe = Math.sin(elapsed * 0.35 + tt * Math.PI * 2) * (this.reducedMotion ? 0.5 : 2.2);
      this.saturn.root.position.x = damp(this.saturn.root.position.x, this._saturnPathPoint.x, 2.2, delta);
      this.saturn.root.position.y = damp(this.saturn.root.position.y, this._saturnPathPoint.y, 2.2, delta);
      this.saturn.root.position.z = damp(
        this.saturn.root.position.z,
        this._saturnPathPoint.z + breathe,
        2.2,
        delta
      );
    }

    const camDrift = this.reducedMotion ? 0 : Math.sin(elapsed * 0.06) * 0.6;
    this.camera.position.x = damp(
      this.camera.position.x,
      this._cameraBase.x + this.pointer.x * 2.2 + camDrift,
      3,
      delta
    );
    this.camera.position.y = damp(
      this.camera.position.y,
      this._cameraBase.y + this.pointer.y * 1.3 - this.scrollProgress * 1.4,
      3,
      delta
    );
    this.camera.lookAt(0, 0.5, 0);

    if (this._fogWisp) {
      this._fogWisp.position.x = Math.sin(elapsed * 0.04) * 12;
    }
  }

  _render() {
    if (this.composer) this.composer.render();
    else this.renderer.render(this.scene, this.camera);
  }

  dispose() {
    if (this._disposed) return;
    this._disposed = true;
    this.stop();
    clearTimeout(this._resizeTimer);
    clearTimeout(this._themeTransitionResumeTimer);

    window.removeEventListener('resize', this._onResize);
    document.removeEventListener('visibilitychange', this._onVisibilityChange);
    document.removeEventListener('site:theme-change', this._onThemeChange);
    this.canvas.removeEventListener('webglcontextlost', this._onContextLost);

    this.starfield?.dispose();
    this.environment?.dispose();
    this.saturn?.dispose();
    if (this._fogWisp) this._fogWisp.material.dispose();
    if (this.bloomPass) this.bloomPass.dispose();
    if (this.composer) this.composer.dispose();
    if (this._textures) {
      for (const tex of Object.values(this._textures)) tex.dispose();
    }
    this.renderer.dispose();
  }
}

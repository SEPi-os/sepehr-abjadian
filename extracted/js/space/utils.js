/**
 * utils.js
 * Small, dependency-free helpers shared across the space-scene modules:
 * math helpers, a seeded PRNG (so runs are reproducible/debuggable),
 * device capability detection, and a WebGL support check used for the
 * graceful no-WebGL fallback.
 */

export const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
export const lerp = (a, b, t) => a + (b - a) * t;
export const damp = (current, target, lambda, dt) =>
  lerp(current, target, 1 - Math.exp(-lambda * dt));

/** Deterministic PRNG (mulberry32) so generated fields are stable/reviewable. */
export function mulberry32(seed) {
  let a = seed >>> 0;
  return function rand() {
    a |= 0;
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}

/** Feature-detects WebGL (webgl2 or webgl) without throwing. */
export function supportsWebGL() {
  try {
    const canvas = document.createElement('canvas');
    const gl =
      canvas.getContext('webgl2') ||
      canvas.getContext('webgl') ||
      canvas.getContext('experimental-webgl');
    return !!gl;
  } catch (e) {
    return false;
  }
}

/**
 * Coarse device tiering so the scene scales itself down on phones / low-end
 * hardware instead of trying to run the full desktop scene everywhere.
 */
export function detectQualityTier() {
  const coarsePointer = window.matchMedia('(pointer: coarse)').matches;
  const smallViewport = Math.min(window.innerWidth, window.innerHeight) < 760;
  const fewCores = (navigator.hardwareConcurrency || 4) <= 4;
  const saveData = !!(navigator.connection && navigator.connection.saveData);
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let tier = 'high';
  if (coarsePointer || smallViewport) tier = 'medium';
  if (saveData || (coarsePointer && smallViewport) || (fewCores && smallViewport)) tier = 'low';

  return { tier, reducedMotion };
}

export const TIER_SETTINGS = {
  // 'high' and 'medium' were substantially heavier than 'low' (up to 6,000
  // stars per layer vs. 1,600, full bloom, 2x pixel ratio) — on request,
  // desktop and tablet now render the same lightweight star/dust/nebula/
  // galaxy scene mobile already used, rather than the much busier, heavier
  // one. The three tier names stay (detectQualityTier() below still expects
  // them, and antialiasing still steps down further on the true low tier),
  // but the visual density and the fields that add real GPU/CPU cost are now
  // identical across all three.
  high: {
    starCounts: [1600, 1000, 0],
    dustCount: 220,
    nebulaCount: 1,
    galaxyCount: 1,
    bloom: false,
    maxDpr: 1.4,
    sphereSegments: 48,
    ringSegments: 64,
    saturnScale: 1,
  },
  medium: {
    starCounts: [1600, 1000, 0],
    dustCount: 220,
    nebulaCount: 1,
    galaxyCount: 1,
    bloom: false,
    maxDpr: 1.4,
    sphereSegments: 48,
    ringSegments: 64,
    saturnScale: 0.85,
  },
  low: {
    starCounts: [1600, 1000, 0],
    dustCount: 220,
    nebulaCount: 1,
    galaxyCount: 1,
    bloom: false,
    maxDpr: 1.4,
    sphereSegments: 48,
    ringSegments: 64,
    saturnScale: 0.7,
    antialias: false,
  },
};

/** Convert a normalized viewport coordinate (-1..1) to a world-space point
 *  on the plane at `worldZ`, given the current camera. Keeps on-screen
 *  placement (e.g. "hug the edges") correct across any aspect ratio/resize. */
export function ndcToWorld(camera, nx, ny, worldZ, target) {
  const dist = camera.position.z - worldZ;
  const vFov = (camera.fov * Math.PI) / 180;
  const halfHeight = Math.tan(vFov / 2) * dist;
  const halfWidth = halfHeight * camera.aspect;
  target.set(nx * halfWidth, ny * halfHeight, worldZ);
  return target;
}

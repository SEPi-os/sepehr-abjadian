// account-3d.js — a self-contained Three.js scene for account.php: a
// starfield plus a handful of large translucent floating shapes in the
// site's accent colors. Deliberately simpler than js/space/* (the main
// site background) — this page needs an atmosphere, not a set piece —
// and lives entirely in its own file so it never touches that system.
import * as THREE from 'three';

(function () {
  const canvas = document.getElementById('authCanvas');
  if (!canvas) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  let supportsWebGL = true;
  try {
    const test = document.createElement('canvas');
    supportsWebGL = !!(window.WebGLRenderingContext && (test.getContext('webgl') || test.getContext('experimental-webgl')));
  } catch (e) {
    supportsWebGL = false;
  }
  if (!supportsWebGL) {
    canvas.style.display = 'none';
    document.body.classList.add('no-webgl-auth');
    return;
  }

  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.75));
  renderer.setSize(window.innerWidth, window.innerHeight);

  const scene = new THREE.Scene();
  const camera = new THREE.PerspectiveCamera(52, window.innerWidth / window.innerHeight, 0.1, 100);
  camera.position.set(0, 0, 9);

  // ---- starfield -----------------------------------------------------------
  const STAR_COUNT = 900;
  const starGeo = new THREE.BufferGeometry();
  const starPos = new Float32Array(STAR_COUNT * 3);
  for (let i = 0; i < STAR_COUNT; i++) {
    starPos[i * 3] = (Math.random() - 0.5) * 40;
    starPos[i * 3 + 1] = (Math.random() - 0.5) * 40;
    starPos[i * 3 + 2] = (Math.random() - 0.5) * 30 - 5;
  }
  starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
  const starMat = new THREE.PointsMaterial({ color: 0xbfd4ff, size: 0.045, transparent: true, opacity: 0.7, sizeAttenuation: true });
  const stars = new THREE.Points(starGeo, starMat);
  scene.add(stars);

  // ---- floating glass shapes ------------------------------------------------
  const palette = [0x5d8bff, 0x2dd4bf, 0x9d6bff, 0x7af0ff];
  const shapeDefs = [
    { geo: new THREE.IcosahedronGeometry(1.6, 0), pos: [-3.6, 1.6, -3], speed: 0.06 },
    { geo: new THREE.TorusGeometry(1.1, 0.35, 16, 60), pos: [3.4, -1.2, -4], speed: -0.05 },
    { geo: new THREE.OctahedronGeometry(1.1, 0), pos: [2.6, 2.2, -6], speed: 0.045 },
    { geo: new THREE.IcosahedronGeometry(0.75, 1), pos: [-3, -2, -5], speed: -0.07 },
  ];

  const shapes = shapeDefs.map((def, i) => {
    const mat = new THREE.MeshBasicMaterial({
      color: palette[i % palette.length],
      wireframe: true,
      transparent: true,
      opacity: 0.35,
    });
    const mesh = new THREE.Mesh(def.geo, mat);
    mesh.position.set(...def.pos);
    mesh.userData.speed = def.speed;
    mesh.userData.floatOffset = Math.random() * Math.PI * 2;
    scene.add(mesh);
    return mesh;
  });

  // ---- theme-aware intensity -------------------------------------------------
  function applyTheme() {
    const light = document.documentElement.getAttribute('data-theme') === 'light';
    starMat.opacity = light ? 0.45 : 0.7;
    shapes.forEach((m) => { m.material.opacity = light ? 0.22 : 0.35; });
  }
  applyTheme();
  new MutationObserver(applyTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

  // ---- mouse parallax (quickTo-style easing, hand-rolled to avoid a GSAP
  // dependency in a module that should keep working even if GSAP is slow
  // to load) ------------------------------------------------------------
  let targetX = 0;
  let targetY = 0;
  let camX = 0;
  let camY = 0;

  window.addEventListener('pointermove', (e) => {
    targetX = (e.clientX / window.innerWidth - 0.5) * 2;
    targetY = (e.clientY / window.innerHeight - 0.5) * 2;
  }, { passive: true });

  window.addEventListener('resize', () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
  });

  const clock = new THREE.Clock();
  let frameId;

  function tick() {
    frameId = requestAnimationFrame(tick);
    const t = clock.getElapsedTime();

    if (!reduceMotion) {
      // Multipliers raised (was 0.6/0.4, barely visible outside the card's own
      // hover-tilt effect) so the background actually reads as cursor-reactive
      // across the full page, not just over the card.
      camX += (targetX * 2.4 - camX) * 0.045;
      camY += (-targetY * 1.7 - camY) * 0.045;
      camera.position.x = camX;
      camera.position.y = camY;
      camera.lookAt(0, 0, -3);

      stars.rotation.y = t * 0.01;

      shapes.forEach((m) => {
        m.rotation.x = t * m.userData.speed;
        m.rotation.y = t * m.userData.speed * 1.3;
        m.position.y += Math.sin(t * 0.4 + m.userData.floatOffset) * 0.0015;
      });
    }

    renderer.render(scene, camera);
  }
  tick();

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      cancelAnimationFrame(frameId);
    } else {
      tick();
    }
  });
})();

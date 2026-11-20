/**
 * saturn.js
 * The centerpiece object: a shaded sphere with a baked band texture, a
 * fresnel "atmosphere" glow shell, and a flat ring with correct radial UVs
 * (RingGeometry's default UVs are not radial, so we remap them by hand).
 *
 * Hierarchy:
 *   root (moved/scaled every frame by the scroll controller)
 *    └ tilt (fixed 26.7° axial tilt — real Saturn's obliquity)
 *       ├ planet  (spins on its own local Y each frame)
 *       ├ glow    (fresnel rim shell, rotationally symmetric — no spin needed)
 *       └ ring    (flat disc, laid into the equatorial plane)
 *
 * Two visual modes share this exact same geometry/hierarchy — only
 * materials/colors/textures change between them, via setMode(), so the
 * scroll path, resize handling and dispose logic in scene.js never need to
 * know which mode is active:
 *  - 'saturn'    (dark theme): the real photographic ringed planet.
 *  - 'blackhole' (light theme): the same three meshes restyled as a dark
 *    event horizon with a bright photon-ring fresnel and a warm procedural
 *    accretion-disk ring texture — reads as a deliberate, different object
 *    rather than a filtered/washed-out copy of the dark-mode planet.
 */
import * as THREE from 'three';

const AXIAL_TILT = THREE.MathUtils.degToRad(26.7);

const GLOW_VERTEX = /* glsl */ `
  varying vec3 vNormal;
  varying vec3 vViewDir;
  void main() {
    vec4 worldPosition = modelMatrix * vec4(position, 1.0);
    vNormal = normalize(normalMatrix * normal);
    vViewDir = normalize(cameraPosition - worldPosition.xyz);
    gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
  }
`;

const GLOW_FRAGMENT = /* glsl */ `
  uniform vec3 glowColor;
  uniform float power;
  uniform float intensity;
  varying vec3 vNormal;
  varying vec3 vViewDir;
  void main() {
    float rim = pow(1.0 - max(dot(normalize(vNormal), vViewDir), 0.0), power);
    gl_FragColor = vec4(glowColor, rim * intensity);
  }
`;

// Per-mode tuning. Geometry is identical between modes — only these values
// (and the ring's texture, built separately below) change on setMode().
const MODE_SETTINGS = {
  saturn: {
    glowColor: 0xf4e3ba,
    glowPower: 2.6,
    glowIntensity: 0.85,
    planetColor: 0xffffff, // neutral — the diffuse map supplies all the color
    planetUsesMap: true,
    ringOpacity: 0.96,
  },
  blackhole: {
    // A tight, hot rim (low power = broad falloff would look like a soft
    // halo; a higher power concentrates it into a thin bright edge, closer
    // to a photon ring) in a warm white-orange rather than Saturn's cream.
    glowColor: 0xffcf9e,
    glowPower: 4.2,
    glowIntensity: 1.15,
    planetColor: 0x030304, // near-black event horizon
    planetUsesMap: false,
    ringOpacity: 0.92,
  },
};

function remapRingUVsRadially(geometry, innerRadius, outerRadius) {
  const pos = geometry.attributes.position;
  const uv = geometry.attributes.uv;
  const span = Math.max(1e-6, outerRadius - innerRadius);
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i);
    const y = pos.getY(i);
    const radius = Math.sqrt(x * x + y * y);
    const u = THREE.MathUtils.clamp((radius - innerRadius) / span, 0, 1);
    uv.setXY(i, u, 0.5);
  }
  uv.needsUpdate = true;
}

/**
 * Procedural accretion-disk ring texture: a radial gradient (white-hot inner
 * edge → orange → deep red → transparent outer edge) drawn on a 1D-ish
 * strip, mapped the same radial way as the Saturn ring texture via
 * remapRingUVsRadially. Generated at runtime (no new binary asset needed),
 * cached on first use since the gradient itself never changes.
 */
let _accretionDiskTexture = null;
function getAccretionDiskTexture() {
  if (_accretionDiskTexture) return _accretionDiskTexture;

  const size = 256;
  const canvas = document.createElement('canvas');
  canvas.width = size;
  canvas.height = 1;
  const ctx = canvas.getContext('2d');
  const grad = ctx.createLinearGradient(0, 0, size, 0);
  grad.addColorStop(0, 'rgba(255,248,235,0)');
  grad.addColorStop(0.06, 'rgba(255,248,235,0.95)');
  grad.addColorStop(0.22, 'rgba(255,205,120,0.9)');
  grad.addColorStop(0.5, 'rgba(255,130,60,0.62)');
  grad.addColorStop(0.78, 'rgba(200,60,40,0.28)');
  grad.addColorStop(1, 'rgba(120,30,30,0)');
  ctx.fillStyle = grad;
  ctx.fillRect(0, 0, size, 1);

  const texture = new THREE.CanvasTexture(canvas);
  texture.colorSpace = THREE.SRGBColorSpace;
  texture.wrapS = THREE.ClampToEdgeWrapping;
  texture.wrapT = THREE.ClampToEdgeWrapping;
  texture.needsUpdate = true;
  _accretionDiskTexture = texture;
  return texture;
}

export class Saturn {
  constructor({ diffuseTexture, ringTexture, sphereSegments = 96, ringSegments = 128, mode = 'saturn' }) {
    this.root = new THREE.Group();
    this.root.name = 'saturn-root';
    this.tilt = new THREE.Group();
    this.tilt.rotation.z = AXIAL_TILT;
    this.root.add(this.tilt);

    const RADIUS = 10;

    // --- Planet body -------------------------------------------------
    diffuseTexture.colorSpace = THREE.SRGBColorSpace;
    diffuseTexture.wrapS = THREE.RepeatWrapping;
    diffuseTexture.anisotropy = 4;
    this._saturnDiffuseTexture = diffuseTexture;

    const planetGeometry = new THREE.SphereGeometry(
      RADIUS,
      sphereSegments,
      Math.max(24, Math.round(sphereSegments * 0.6))
    );
    const planetMaterial = new THREE.MeshStandardMaterial({
      map: diffuseTexture,
      roughness: 0.95,
      metalness: 0.0,
    });
    this.planet = new THREE.Mesh(planetGeometry, planetMaterial);
    this.tilt.add(this.planet);

    // --- Atmosphere / photon-ring glow shell --------------------------
    const glowGeometry = new THREE.SphereGeometry(
      RADIUS * 1.045,
      sphereSegments,
      Math.max(24, Math.round(sphereSegments * 0.6))
    );
    const glowMaterial = new THREE.ShaderMaterial({
      uniforms: {
        glowColor: { value: new THREE.Color(0xf4e3ba) },
        power: { value: 2.6 },
        intensity: { value: 0.85 },
      },
      vertexShader: GLOW_VERTEX,
      fragmentShader: GLOW_FRAGMENT,
      transparent: true,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
      side: THREE.FrontSide,
    });
    this.glow = new THREE.Mesh(glowGeometry, glowMaterial);
    this.tilt.add(this.glow);

    // --- Ring ------------------------------------------------------
    const innerRadius = RADIUS * 1.55;
    const outerRadius = RADIUS * 2.6;
    const ringGeometry = new THREE.RingGeometry(innerRadius, outerRadius, ringSegments, 1);
    remapRingUVsRadially(ringGeometry, innerRadius, outerRadius);

    ringTexture.colorSpace = THREE.SRGBColorSpace;
    ringTexture.wrapS = THREE.ClampToEdgeWrapping;
    ringTexture.wrapT = THREE.ClampToEdgeWrapping;
    this._saturnRingTexture = ringTexture;

    const ringMaterial = new THREE.MeshBasicMaterial({
      map: ringTexture,
      transparent: true,
      side: THREE.DoubleSide,
      depthWrite: false,
      opacity: 0.96,
    });
    this.ring = new THREE.Mesh(ringGeometry, ringMaterial);
    this.ring.rotation.x = Math.PI / 2;
    this.tilt.add(this.ring);

    this._leanCurrent = 0;
    this.mode = null; // setMode below always runs the first application
    this.setMode(mode);
  }

  /** Swaps materials/colors/textures in place — same geometry, same
   *  objects, so this is cheap enough to call on a live theme toggle with
   *  no visible hitch and nothing for scene.js/scroll-controller.js to
   *  re-wire. No-ops if already in the requested mode. */
  setMode(mode) {
    if (mode !== 'saturn' && mode !== 'blackhole') mode = 'saturn';
    if (this.mode === mode) return;
    this.mode = mode;
    const s = MODE_SETTINGS[mode];

    this.planet.material.map = s.planetUsesMap ? this._saturnDiffuseTexture : null;
    this.planet.material.color.set(s.planetColor);
    this.planet.material.needsUpdate = true;

    this.glow.material.uniforms.glowColor.value.set(s.glowColor);
    this.glow.material.uniforms.power.value = s.glowPower;
    this.glow.material.uniforms.intensity.value = s.glowIntensity;

    this.ring.material.map = mode === 'blackhole' ? getAccretionDiskTexture() : this._saturnRingTexture;
    this.ring.material.opacity = s.ringOpacity;
    this.ring.material.needsUpdate = true;
  }

  /**
   * @param {{delta:number, elapsed:number, scrollDirection:number, intensity:number}} state
   */
  update({ delta, elapsed, scrollDirection, intensity }) {
    // Continuous self-rotation, frame-rate independent.
    this.planet.rotation.y += delta * 0.055 * intensity;
    // Cloud bands drift independently of the rigid-body spin — subtle "alive" read.
    // (Only meaningful in 'saturn' mode — harmless no-op offset otherwise since
    // the black-hole planet material has no map to scroll.)
    this._saturnDiffuseTexture.offset.x += delta * 0.006 * intensity;

    // Lean gently into the scroll direction (damped, never snaps).
    const target = scrollDirection * 0.06;
    this._leanCurrent += (target - this._leanCurrent) * Math.min(1, delta * 2.2);
    this.root.rotation.z = this._leanCurrent;

    // Tiny idle bob so it never looks perfectly frozen, even at rest.
    this.tilt.position.y = Math.sin(elapsed * 0.18) * 0.35 * intensity;
  }

  /** Disposes only geometries/materials Saturn itself created. The Saturn
   *  diffuse/ring textures are loaded (and owned/disposed) by scene.js,
   *  since they're shared with other parts of the scene. The accretion-disk
   *  texture is module-level/cached (shared across any Saturn instance),
   *  so it's intentionally not disposed here. */
  dispose() {
    this.planet.geometry.dispose();
    this.planet.material.dispose();
    this.glow.geometry.dispose();
    this.glow.material.dispose();
    this.ring.geometry.dispose();
    this.ring.material.dispose();
  }
}

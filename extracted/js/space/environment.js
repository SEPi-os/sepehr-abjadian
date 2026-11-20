/**
 * environment.js
 * Everything that dresses the space around Saturn and the stars:
 *  - Nebula: large soft tinted sprites, drifting + slowly rotating
 *  - Galaxies: small distant tinted sprites, deep background
 *  - Dust: a near, sparse point layer that drifts and wraps (foreground depth)
 *  - Fog: cheap scene fog + one very faint near-camera wisp for a
 *    "volumetric" read without a real raymarch cost
 */
import * as THREE from 'three';
import { mulberry32 } from './utils.js?v=20260913';

const NEBULA_PALETTE = [
  0x5d8bff, // site accent blue
  0x9d6bff, // site accent violet
  0x7af0ff, // site accent cyan
  0x2dd4bf, // aurora teal (new, background-only accent)
  0xff7ab8, // soft magenta accent
];

export class Environment {
  constructor({ nebulaTextures, galaxyTextures, dustSprite, counts, seed = 5 }) {
    this.group = new THREE.Group();
    this.group.name = 'environment';
    this.nebulae = [];
    this.galaxies = [];
    this.dust = null;

    const rand = mulberry32(seed);

    for (let i = 0; i < counts.nebulaCount; i++) {
      const color = new THREE.Color(NEBULA_PALETTE[i % NEBULA_PALETTE.length]);
      const map = nebulaTextures[i % nebulaTextures.length];
      const material = new THREE.SpriteMaterial({
        map,
        color,
        transparent: true,
        opacity: 0.16 + rand() * 0.1,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        rotation: rand() * Math.PI * 2,
      });
      const sprite = new THREE.Sprite(material);
      const scale = 220 + rand() * 260;
      sprite.scale.set(scale, scale * (0.65 + rand() * 0.3), 1);
      sprite.position.set(
        (rand() - 0.5) * 620,
        (rand() - 0.5) * 300 + 40,
        -260 - rand() * 260
      );
      sprite.userData.driftSpeed = 0.006 + rand() * 0.01;
      sprite.userData.driftPhase = rand() * Math.PI * 2;
      sprite.userData.baseX = sprite.position.x;
      sprite.userData.baseY = sprite.position.y;
      sprite.userData.spin = (rand() - 0.5) * 0.00004;
      this.group.add(sprite);
      this.nebulae.push(sprite);
    }

    for (let i = 0; i < counts.galaxyCount; i++) {
      const color = new THREE.Color(NEBULA_PALETTE[(i + 2) % NEBULA_PALETTE.length]);
      const map = galaxyTextures[i % galaxyTextures.length];
      const material = new THREE.SpriteMaterial({
        map,
        color,
        transparent: true,
        opacity: 0.35 + rand() * 0.2,
        blending: THREE.AdditiveBlending,
        depthWrite: false,
        rotation: rand() * Math.PI * 2,
      });
      const sprite = new THREE.Sprite(material);
      const scale = 60 + rand() * 70;
      sprite.scale.set(scale, scale * 0.55, 1);
      sprite.position.set((rand() - 0.5) * 700, (rand() - 0.5) * 260 - 30, -500 - rand() * 300);
      sprite.userData.spin = (rand() - 0.5) * 0.00006;
      this.group.add(sprite);
      this.galaxies.push(sprite);
    }

    if (counts.dustCount > 0) {
      const n = counts.dustCount;
      const positions = new Float32Array(n * 3);
      const colors = new Float32Array(n * 3);
      const bounds = { x: 90, y: 60, z: 70 };
      for (let p = 0; p < n; p++) {
        positions[p * 3] = (rand() - 0.5) * 2 * bounds.x;
        positions[p * 3 + 1] = (rand() - 0.5) * 2 * bounds.y;
        positions[p * 3 + 2] = (rand() - 0.5) * 2 * bounds.z;
        const tint = 0.55 + rand() * 0.45;
        colors[p * 3] = tint;
        colors[p * 3 + 1] = tint * 0.96;
        colors[p * 3 + 2] = 1.0;
      }
      const geometry = new THREE.BufferGeometry();
      geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
      geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));
      const material = new THREE.PointsMaterial({
        size: 0.55,
        map: dustSprite,
        alphaMap: dustSprite,
        transparent: true,
        opacity: 0.55,
        depthWrite: false,
        vertexColors: true,
        blending: THREE.AdditiveBlending,
        sizeAttenuation: true,
      });
      this.dust = new THREE.Points(geometry, material);
      this.dust.frustumCulled = false;
      this.dust.userData.bounds = bounds;
      this.dust.userData.velocities = new Float32Array(n * 3);
      for (let p = 0; p < n; p++) {
        this.dust.userData.velocities[p * 3] = (rand() - 0.5) * 0.4;
        this.dust.userData.velocities[p * 3 + 1] = 0.35 + rand() * 0.5;
        this.dust.userData.velocities[p * 3 + 2] = (rand() - 0.5) * 0.3;
      }
      this.group.add(this.dust);
    }
  }

  /** @param {{elapsed:number, delta:number, scrollProgress:number, intensity:number}} state */
  update(state) {
    const { elapsed, delta, scrollProgress, intensity } = state;

    for (const neb of this.nebulae) {
      const d = neb.userData;
      neb.position.x = d.baseX + Math.sin(elapsed * d.driftSpeed + d.driftPhase) * 30 * intensity;
      neb.position.y = d.baseY + Math.cos(elapsed * d.driftSpeed * 0.8 + d.driftPhase) * 18 * intensity;
      neb.material.rotation += d.spin * delta * 1000 * intensity;
      neb.position.z += Math.sin(elapsed * 0.02 + d.driftPhase) * 0.02 * intensity;
    }

    for (const gal of this.galaxies) {
      gal.material.rotation += gal.userData.spin * delta * 1000 * intensity;
    }

    if (this.dust) {
      const posAttr = this.dust.geometry.attributes.position;
      const vel = this.dust.userData.velocities;
      const b = this.dust.userData.bounds;
      const arr = posAttr.array;
      const drift = scrollProgress * 6;
      for (let p = 0; p < arr.length / 3; p++) {
        arr[p * 3] += vel[p * 3] * delta * intensity;
        arr[p * 3 + 1] += vel[p * 3 + 1] * delta * intensity;
        arr[p * 3 + 2] += vel[p * 3 + 2] * delta * intensity;

        if (arr[p * 3 + 1] > b.y + drift) arr[p * 3 + 1] = -b.y + drift;
        if (arr[p * 3] > b.x) arr[p * 3] = -b.x;
        if (arr[p * 3] < -b.x) arr[p * 3] = b.x;
        if (arr[p * 3 + 2] > b.z) arr[p * 3 + 2] = -b.z;
        if (arr[p * 3 + 2] < -b.z) arr[p * 3 + 2] = b.z;
      }
      posAttr.needsUpdate = true;
    }
  }

  dispose() {
    for (const neb of this.nebulae) {
      neb.material.dispose();
    }
    for (const gal of this.galaxies) {
      gal.material.dispose();
    }
    if (this.dust) {
      this.dust.geometry.dispose();
      this.dust.material.dispose();
    }
  }
}

export function createSceneFog() {
  return new THREE.FogExp2(0x03050d, 0.0016);
}

/** Very faint near-camera fog wisp for a soft "volumetric" read (cheap: one sprite). */
export function createFogWisp(nebulaTexture) {
  const material = new THREE.SpriteMaterial({
    map: nebulaTexture,
    color: new THREE.Color(0x263a72),
    transparent: true,
    opacity: 0.05,
    blending: THREE.NormalBlending,
    depthWrite: false,
  });
  const sprite = new THREE.Sprite(material);
  sprite.scale.set(340, 220, 1);
  sprite.position.set(0, -10, 34);
  return sprite;
}

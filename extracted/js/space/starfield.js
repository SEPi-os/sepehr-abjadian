/**
 * starfield.js
 * Three depth layers of THREE.Points, each with a different radius, point
 * size and parallax factor, so scroll/mouse movement produces real
 * multi-plane depth instead of a single flat star layer.
 */
import * as THREE from 'three';
import { mulberry32 } from './utils.js?v=20260913';

const STAR_TINTS = [
  [1.0, 1.0, 1.0],
  [0.78, 0.87, 1.0],
  [1.0, 0.92, 0.78],
  [0.86, 0.8, 1.0],
];

const LAYER_RADII = [880, 520, 280];
const LAYER_SIZES = [2.4, 3.4, 5.0];
const LAYER_PARALLAX = [0.35, 0.75, 1.4];

export class Starfield {
  constructor({ counts, sprite, seed = 1 }) {
    this.group = new THREE.Group();
    this.group.name = 'starfield';
    this.layers = [];

    const rand = mulberry32(seed);

    counts.forEach((count, i) => {
      if (!count) return;
      const positions = new Float32Array(count * 3);
      const colors = new Float32Array(count * 3);
      const radius = LAYER_RADII[i];

      for (let p = 0; p < count; p++) {
        const theta = rand() * Math.PI * 2;
        const v = rand();
        const phi = Math.acos(2 * v - 1);
        const r = radius * (0.32 + 0.68 * Math.cbrt(rand()));

        positions[p * 3] = r * Math.sin(phi) * Math.cos(theta);
        positions[p * 3 + 1] = r * Math.cos(phi) * 0.62;
        positions[p * 3 + 2] = r * Math.sin(phi) * Math.sin(theta);

        const tint = STAR_TINTS[(rand() * STAR_TINTS.length) | 0];
        const flicker = 0.7 + rand() * 0.3;
        colors[p * 3] = tint[0] * flicker;
        colors[p * 3 + 1] = tint[1] * flicker;
        colors[p * 3 + 2] = tint[2] * flicker;
      }

      const geometry = new THREE.BufferGeometry();
      geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
      geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

      const material = new THREE.PointsMaterial({
        size: LAYER_SIZES[i],
        map: sprite,
        alphaMap: sprite,
        transparent: true,
        depthWrite: false,
        vertexColors: true,
        blending: THREE.AdditiveBlending,
        sizeAttenuation: true,
        opacity: 0.95,
      });

      const points = new THREE.Points(geometry, material);
      points.frustumCulled = false;
      points.userData.parallax = LAYER_PARALLAX[i];
      points.userData.spin = 0.0015 * (i + 1);

      this.group.add(points);
      this.layers.push(points);
    });
  }

  /** @param {{scrollProgress:number, elapsed:number, pointer:{x:number,y:number}, intensity:number}} state */
  update(state) {
    const { scrollProgress, elapsed, pointer, intensity } = state;
    for (const layer of this.layers) {
      const p = layer.userData.parallax * intensity;
      layer.rotation.y = elapsed * layer.userData.spin * intensity;
      layer.position.x = pointer.x * 14 * p;
      layer.position.y = -scrollProgress * 60 * p + pointer.y * 8 * p;
    }
  }

  dispose() {
    for (const layer of this.layers) {
      layer.geometry.dispose();
      layer.material.dispose();
    }
    this.layers = [];
  }
}

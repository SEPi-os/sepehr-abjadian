/**
 * scroll-controller.js
 * Thin orchestration layer: turns whole-page scroll into a 0..1 progress
 * value (+ direction) and mouse position into normalized pointer
 * coordinates, both fed into the SpaceScene's public API. No Three.js here
 * on purpose — this file only knows about scroll and the scene's API.
 */
import { gsap } from 'gsap';
import ScrollTrigger from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export function createScrollController(scene, { reducedMotion } = {}) {
  const trigger = ScrollTrigger.create({
    trigger: document.body,
    start: 'top top',
    end: 'bottom bottom',
    scrub: reducedMotion ? true : 0.6,
    onUpdate(self) {
      scene.setScrollProgress(self.progress, self.direction || 1);
    },
  });
  // Both this trigger and the portrait-hero's pinned trigger load their
  // assets asynchronously and can finish in either order. A global refresh
  // here re-measures every registered trigger (this one included) against
  // whatever the final document height turns out to be, so Saturn's scroll
  // mapping stays correct no matter which module finishes loading last.
  ScrollTrigger.refresh();

  const pointerEnabled = window.matchMedia('(pointer: fine)').matches && !reducedMotion;

  function onPointerMove(event) {
    const nx = (event.clientX / window.innerWidth) * 2 - 1;
    const ny = -((event.clientY / window.innerHeight) * 2 - 1);
    scene.setPointer(nx, ny);
  }

  if (pointerEnabled) {
    window.addEventListener('pointermove', onPointerMove, { passive: true });
  }

  function destroy() {
    trigger.kill();
    if (pointerEnabled) window.removeEventListener('pointermove', onPointerMove);
  }

  return { destroy, refresh: () => ScrollTrigger.refresh() };
}

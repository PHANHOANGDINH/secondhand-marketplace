import { onMounted, onUnmounted } from 'vue'

// Every animation is opt-in through this media query: without an explicit
// "no-preference" (reduced motion, or no matchMedia at all) content just appears.
const MOTION_QUERY = '(prefers-reduced-motion: no-preference)'
let library = null
let loading = null

export function motionAllowed() {
  return (
    typeof window !== 'undefined' &&
    typeof window.matchMedia === 'function' &&
    window.matchMedia(MOTION_QUERY)?.matches === true
  )
}

/** Loads GSAP once, in the background. Callers never wait for it: until it is ready, nothing animates. */
export function loadMotion() {
  if (!motionAllowed()) return Promise.resolve(null)
  loading ??= Promise.all([import('gsap'), import('gsap/ScrollTrigger')])
    .then(([{ gsap }, { ScrollTrigger }]) => {
      gsap.registerPlugin(ScrollTrigger)
      library = { gsap, ScrollTrigger }
      return library
    })
    .catch(() => null)
  return loading
}

/**
 * Exit animation for something Vue is about to remove: an inert, click-through copy fades
 * out in its place, so closing is never delayed and the copy cannot be interacted with.
 */
export function fadeOutCopy(node) {
  if (!library || !motionAllowed() || !(node instanceof Element) || !node.parentNode) return
  const copy = node.cloneNode(true)
  for (const element of [copy, ...copy.querySelectorAll('*')]) {
    for (const name of ['id', 'role', 'aria-modal', 'aria-labelledby', 'aria-describedby', 'tabindex'])
      element.removeAttribute(name)
  }
  copy.setAttribute('aria-hidden', 'true')
  copy.setAttribute('inert', '')
  copy.setAttribute('data-motion-copy', '')
  copy.style.pointerEvents = 'none'
  node.parentNode.insertBefore(copy, node.nextSibling)
  library.gsap.to(copy, { opacity: 0, duration: 0.2, ease: 'none', onComplete: () => copy.remove() })
}

const elements = (targets) =>
  (Array.isArray(targets) ? targets : [targets]).filter((node) => node instanceof Element)

/**
 * GSAP presets bound to the calling component. Only transform and opacity are animated,
 * for 0.2–0.6 s, and nothing here disables pointer events or holds data back.
 */
export function useMotion() {
  const contexts = new Map()
  const cleanups = []
  let disposed = false

  // Runs `build` inside gsap.context + gsap.matchMedia, so it is reverted on unmount
  // and also the moment the user switches to reduced motion.
  function animate(key, build) {
    contexts.get(key)?.revert()
    contexts.delete(key)
    if (disposed || !library || !motionAllowed()) return
    const { gsap } = library
    contexts.set(
      key,
      gsap.context(() => {
        gsap.matchMedia().add(MOTION_QUERY, () => {
          build(gsap)
        })
      }),
    )
  }

  /** Staggered entrance for a grid of cards, once, when the grid scrolls into view. */
  function fadeUp(targets, { stagger = 0.05, key = 'fadeUp' } = {}) {
    const nodes = elements(targets)
    if (!nodes.length) return
    animate(key, (gsap) => {
      gsap.from(nodes, {
        opacity: 0,
        y: 16,
        duration: 0.4,
        ease: 'power2.out',
        stagger,
        clearProps: 'opacity,transform',
        scrollTrigger: { trigger: nodes[0].parentElement || nodes[0], start: 'top 92%', once: true },
      })
    })
  }

  /** Press feedback for every enabled button inside `root` (one delegated listener). */
  function pressScale(root, selector = '.btn, .ui-press') {
    if (!(root instanceof Element) && root !== document) return
    const scale = (event, value) => {
      const button = event.target instanceof Element ? event.target.closest(selector) : null
      if (!button || button.disabled || !library || !motionAllowed()) return
      library.gsap.to(button, { scale: value, duration: 0.2, ease: 'power2.out', overwrite: 'auto' })
    }
    const down = (event) => scale(event, 0.97)
    const up = (event) => scale(event, 1)
    root.addEventListener('pointerdown', down, { passive: true })
    for (const type of ['pointerup', 'pointercancel', 'pointerout'])
      root.addEventListener(type, up, { passive: true })
    cleanups.push(() => {
      root.removeEventListener('pointerdown', down)
      for (const type of ['pointerup', 'pointercancel', 'pointerout'])
        root.removeEventListener(type, up)
    })
  }

  /** A changed value (a price) drops in. */
  function flashValue(target) {
    const [node] = elements(target)
    if (!node) return
    animate('flashValue', (gsap) => {
      gsap.fromTo(
        node,
        { opacity: 0.35, y: -6 },
        { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out', clearProps: 'opacity,transform' },
      )
    })
  }

  /** One beat per tick of a countdown in its last seconds. */
  function countPulse(target) {
    const [node] = elements(target)
    if (!node) return
    animate('countPulse', (gsap) => {
      gsap.fromTo(
        node,
        { scale: 1.12 },
        { scale: 1, duration: 0.3, ease: 'power2.out', clearProps: 'transform' },
      )
    })
  }

  /** Dialog entrance: backdrop fades, panel rises. */
  function dialogIn(backdrop, panel) {
    if (!(backdrop instanceof Element) || !(panel instanceof Element)) return
    animate('dialog', (gsap) => {
      gsap.from(backdrop, { opacity: 0, duration: 0.2, ease: 'none', clearProps: 'opacity' })
      gsap.from(panel, {
        opacity: 0,
        y: 16,
        scale: 0.98,
        duration: 0.28,
        ease: 'power2.out',
        clearProps: 'opacity,transform',
      })
    })
  }

  onMounted(loadMotion)
  onUnmounted(() => {
    disposed = true
    for (const context of contexts.values()) context.revert()
    contexts.clear()
    for (const cleanup of cleanups.splice(0)) cleanup()
  })

  return { fadeUp, pressScale, flashValue, countPulse, dialogIn }
}

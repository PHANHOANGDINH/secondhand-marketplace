import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import BrandLogo from '../components/ui/BrandLogo.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import EmptyState from '../components/ui/EmptyState.vue'

const calls = vi.hoisted(() => ({
  from: vi.fn(),
  to: vi.fn(),
  fromTo: vi.fn(),
  revert: vi.fn(),
  queries: [],
  registerPlugin: vi.fn(),
}))
vi.mock('gsap', () => ({
  gsap: {
    registerPlugin: calls.registerPlugin,
    context: (build) => {
      build()
      return { revert: calls.revert }
    },
    matchMedia: () => ({
      add: (query, build) => {
        calls.queries.push(query)
        build()
      },
    }),
    from: calls.from,
    to: calls.to,
    fromTo: calls.fromTo,
  },
}))
vi.mock('gsap/ScrollTrigger', () => ({ ScrollTrigger: {} }))

const NO_PREFERENCE = '(prefers-reduced-motion: no-preference)'
const animatable = ['opacity', 'x', 'y', 'scale']
const tweenOptions = ['duration', 'ease', 'stagger', 'clearProps', 'scrollTrigger', 'overwrite', 'onComplete']
function expectSafeTween(vars) {
  expect(vars.duration).toBeGreaterThanOrEqual(0.2)
  expect(vars.duration).toBeLessThanOrEqual(0.6)
  for (const key of Object.keys(vars)) expect([...animatable, ...tweenOptions]).toContain(key)
}
async function harness(allowed) {
  vi.resetModules()
  if (allowed === undefined) vi.stubGlobal('matchMedia', undefined)
  else vi.stubGlobal('matchMedia', vi.fn((query) => ({ matches: allowed && query === NO_PREFERENCE })))
  const motion = await import('../composables/useMotion')
  let presets
  const wrapper = mount(
    defineComponent({
      setup() {
        presets = motion.useMotion()
        return () =>
          h('div', { class: 'grid' }, [
            h('article'),
            h('article'),
            h('button', { class: 'btn' }, 'Lưu'),
            h('button', { class: 'btn', disabled: true }, 'Khóa'),
          ])
      },
    }),
    { attachTo: document.body },
  )
  await motion.loadMotion()
  await flushPromises()
  return { wrapper, presets, motion }
}
beforeEach(() => {
  for (const mock of [calls.from, calls.to, calls.fromTo, calls.revert, calls.registerPlugin]) mock.mockClear()
  calls.queries.length = 0
})
afterEach(() => {
  vi.unstubAllGlobals()
  document.body.innerHTML = ''
})

it.each([
  ['reduced motion is requested', false],
  ['matchMedia is unavailable', undefined],
])('never animates when %s: content is simply there', async (_, allowed) => {
  const { wrapper, presets, motion } = await harness(allowed)
  const [first, second] = wrapper.findAll('article').map((node) => node.element)
  presets.fadeUp([first, second])
  presets.flashValue(first)
  presets.countPulse(first)
  presets.dialogIn(first, second)
  presets.pressScale(wrapper.element)
  await wrapper.get('button').trigger('pointerdown')
  motion.fadeOutCopy(first)
  expect(motion.motionAllowed()).toBe(false)
  expect(await motion.loadMotion()).toBeNull()
  expect(calls.registerPlugin).not.toHaveBeenCalled()
  for (const mock of [calls.from, calls.to, calls.fromTo]) expect(mock).not.toHaveBeenCalled()
  expect(document.querySelector('[data-motion-copy]')).toBeNull()
  expect(first.getAttribute('style')).toBeNull()
  wrapper.unmount()
})

it('runs presets through gsap.matchMedia, animates only transform and opacity, and reverts on unmount', async () => {
  const { wrapper, presets } = await harness(true)
  const [first, second] = wrapper.findAll('article').map((node) => node.element)
  presets.fadeUp([first, second])
  expect(calls.queries).toEqual([NO_PREFERENCE])
  const [nodes, entrance] = calls.from.mock.calls[0]
  expect(nodes).toEqual([first, second])
  expectSafeTween(entrance)
  expect(entrance.stagger).toBeGreaterThan(0)
  expect(entrance.scrollTrigger).toMatchObject({ trigger: wrapper.element, once: true })

  presets.flashValue(first)
  presets.countPulse(second)
  expect(calls.fromTo).toHaveBeenCalledTimes(2)
  for (const [, start, end] of calls.fromTo.mock.calls) {
    expectSafeTween(end)
    for (const key of Object.keys(start)) expect(animatable).toContain(key)
  }
  presets.dialogIn(first, second)
  expect(calls.from).toHaveBeenCalledTimes(3)
  for (const [, vars] of calls.from.mock.calls) expectSafeTween(vars)
  expect(calls.queries.every((query) => query === NO_PREFERENCE)).toBe(true)

  // Replaying a preset reverts its previous run instead of stacking animations.
  calls.revert.mockClear()
  presets.fadeUp([first])
  expect(calls.revert).toHaveBeenCalledTimes(1)
  calls.revert.mockClear()
  wrapper.unmount()
  expect(calls.revert).toHaveBeenCalledTimes(4)
  presets.fadeUp([first])
  expect(calls.from).toHaveBeenCalledTimes(4)
})

it('press feedback scales enabled buttons only, never cancels the event and is removed on unmount', async () => {
  const { wrapper, presets } = await harness(true)
  const clicked = vi.fn()
  wrapper.element.addEventListener('click', clicked)
  presets.pressScale(wrapper.element)
  const [enabled, disabled] = wrapper.findAll('button')
  const down = new Event('pointerdown', { bubbles: true, cancelable: true })
  enabled.element.dispatchEvent(down)
  expect(down.defaultPrevented).toBe(false)
  expect(calls.to).toHaveBeenLastCalledWith(enabled.element, expect.objectContaining({ scale: 0.97, duration: 0.2 }))
  await enabled.trigger('pointerup')
  expect(calls.to).toHaveBeenLastCalledWith(enabled.element, expect.objectContaining({ scale: 1 }))
  await enabled.trigger('click')
  expect(clicked).toHaveBeenCalledTimes(1)
  calls.to.mockClear()
  await disabled.trigger('pointerdown')
  await wrapper.get('article').trigger('pointerdown')
  expect(calls.to).not.toHaveBeenCalled()
  const element = wrapper.element
  wrapper.unmount()
  element.dispatchEvent(new Event('pointerdown', { bubbles: true }))
  expect(calls.to).not.toHaveBeenCalled()
})

it('fades an inert, anonymous copy when something closes, without keeping the original', async () => {
  const { motion } = await harness(true)
  const dialog = document.createElement('div')
  dialog.innerHTML = '<section id="d" role="dialog" aria-modal="true" tabindex="-1"><button id="x">Đóng</button></section>'
  document.body.append(dialog)
  motion.fadeOutCopy(dialog)
  const copy = document.querySelector('[data-motion-copy]')
  expect(copy.hasAttribute('inert')).toBe(true)
  expect(copy.getAttribute('aria-hidden')).toBe('true')
  expect(copy.style.pointerEvents).toBe('none')
  expect(copy.querySelector('[id], [role], [aria-modal], [tabindex]')).toBeNull()
  expect(document.querySelectorAll('[role=dialog]')).toHaveLength(1)
  const [target, vars] = calls.to.mock.calls.at(-1)
  expect(target).toBe(copy)
  expectSafeTween(vars)
  vars.onComplete()
  expect(document.querySelector('[data-motion-copy]')).toBeNull()
})

it('interim logo and line icons are SVG, labelled by text, with no emoji glyphs', () => {
  const logo = mount(BrandLogo)
  expect(logo.text()).toBe('pass lại')
  expect(logo.get('.brand-logo-mark').attributes('aria-hidden')).toBe('true')
  expect(logo.get('svg path').attributes('stroke')).toBe('var(--accent)')
  const icon = mount(AppIcon, { props: { name: 'heart', filled: true } })
  expect(icon.get('svg').attributes()).toMatchObject({ 'aria-hidden': 'true', fill: 'currentColor', 'stroke-width': '1.6' })
  expect(icon.get('path').attributes('d')).toBeTruthy()
  const empty = mount(EmptyState, { props: { title: 'Chưa có sản phẩm' } })
  expect(empty.get('.buyer-empty-icon svg').exists()).toBe(true)
  expect(empty.text()).toBe('Chưa có sản phẩm')
})

import { readFileSync } from 'node:fs'
import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import BrandLogo from '../components/ui/BrandLogo.vue'

// A matchMedia stub whose "prefers dark" answer can be flipped while the app runs.
function mediaStub(dark) {
  const listeners = []
  const query = {
    matches: dark,
    addEventListener: (_type, listener) => listeners.push(listener),
    removeEventListener: vi.fn(),
  }
  window.matchMedia = vi.fn(() => query)
  return (next) => {
    query.matches = next
    for (const listener of listeners) listener({ matches: next })
  }
}
const root = () => document.documentElement
// useTheme keeps one shared state per page, so each test loads a fresh copy of the module.
async function freshTheme() {
  vi.resetModules()
  return import('../composables/useTheme')
}

beforeEach(() => {
  localStorage.clear()
  root().removeAttribute('data-theme')
  root().removeAttribute('data-bs-theme')
})
afterEach(() => {
  delete window.matchMedia
  vi.restoreAllMocks()
})

it('useTheme follows the system by default, and an explicit choice is stored and wins', async () => {
  const setSystem = mediaStub(true)
  const { useTheme, THEME_KEY } = await freshTheme()
  const { preference, theme, setPreference } = useTheme()
  expect(preference.value).toBe('system')
  expect(theme.value).toBe('dark')
  expect(root().dataset.theme).toBe('dark')
  expect(root().getAttribute('data-bs-theme')).toBe('dark')

  setSystem(false)
  await nextTick()
  expect(root().dataset.theme).toBe('light')

  setPreference('dark')
  await nextTick()
  expect(localStorage.getItem(THEME_KEY)).toBe('dark')
  expect(root().getAttribute('data-bs-theme')).toBe('dark')
  setSystem(false)
  await nextTick()
  expect(root().dataset.theme).toBe('dark')

  setPreference('system')
  await nextTick()
  expect(localStorage.getItem(THEME_KEY)).toBeNull()
  expect(root().dataset.theme).toBe('light')
  setPreference('purple')
  expect(preference.value).toBe('system')
})

it('useTheme reads a stored choice and keeps working when storage is blocked', async () => {
  mediaStub(false)
  localStorage.setItem('passdi:theme', 'dark')
  let module = await freshTheme()
  expect(module.useTheme().theme.value).toBe('dark')

  vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
    throw new Error('blocked')
  })
  vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
    throw new Error('blocked')
  })
  module = await freshTheme()
  const { theme, setPreference } = module.useTheme()
  expect(theme.value).toBe('light')
  expect(() => setPreference('dark')).not.toThrow()
  expect(theme.value).toBe('dark')
})

it('the inline script in index.html sets the theme before the app mounts (no white flash)', () => {
  const html = readFileSync('index.html', 'utf8')
  const inline = html.match(/<script>([\s\S]*?)<\/script>/)[1]
  // It runs before the module script and the stylesheets, so the first paint already has the theme.
  expect(html.indexOf(inline)).toBeLessThan(html.indexOf('type="module"'))
  expect(html.indexOf(inline)).toBeLessThan(html.indexOf('rel="stylesheet"'))
  const run = new Function(inline)

  mediaStub(true)
  run()
  expect(root().dataset.theme).toBe('dark')
  expect(root().getAttribute('data-bs-theme')).toBe('dark')

  localStorage.setItem('passdi:theme', 'light')
  run()
  expect(root().dataset.theme).toBe('light')

  mediaStub(false)
  localStorage.setItem('passdi:theme', 'dark')
  run()
  expect(root().dataset.theme).toBe('dark')

  // No storage and no matchMedia: still sets a theme instead of throwing.
  delete window.matchMedia
  vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
    throw new Error('blocked')
  })
  run()
  expect(root().dataset.theme).toBe('light')
})

it('the header toggle is a labelled 44px button that flips the theme; the menu offers three choices', async () => {
  mediaStub(false)
  await freshTheme()
  const { default: ThemeToggle } = await import('../components/ui/ThemeToggle.vue')
  const { default: ThemeChoices } = await import('../components/ui/ThemeChoices.vue')
  const toggle = mount(ThemeToggle)
  const button = toggle.get('button[data-theme-toggle]')
  expect(button.attributes('type')).toBe('button')
  expect(button.attributes('aria-label')).toBe('Chuyển sang giao diện tối')
  expect(button.attributes('aria-pressed')).toBe('false')

  await button.trigger('click')
  expect(root().dataset.theme).toBe('dark')
  expect(button.attributes('aria-label')).toBe('Chuyển sang giao diện sáng')
  expect(button.attributes('aria-pressed')).toBe('true')

  const choices = mount(ThemeChoices)
  const buttons = choices.findAll('[data-theme-choice]')
  expect(buttons.map((node) => node.text())).toEqual(['Theo hệ thống', 'Sáng', 'Tối'])
  expect(choices.get('[data-theme-choice="dark"]').attributes('aria-pressed')).toBe('true')
  await choices.get('[data-theme-choice="light"]').trigger('click')
  expect(root().dataset.theme).toBe('light')
  expect(localStorage.getItem('passdi:theme')).toBe('light')
  await choices.get('[data-theme-choice="system"]').trigger('click')
  expect(localStorage.getItem('passdi:theme')).toBeNull()
  expect(choices.get('[data-theme-choice="system"]').attributes('aria-pressed')).toBe('true')
})

it('BrandLogo shows the round badge and the two-colour "pass·di" wordmark', () => {
  const full = mount(BrandLogo)
  expect(full.attributes()).toMatchObject({ role: 'img', 'aria-label': 'Passdi' })
  expect(full.get('.brand-logo-mark').attributes()).toMatchObject({
    width: '52',
    height: '52',
    alt: '',
    'aria-hidden': 'true',
  })
  expect(full.get('.brand-logo-mark').attributes('src')).toContain('passdi-mark')
  // "pass" and "di" are separate masks so each takes a theme colour.
  const word = full.get('.brand-logo-word')
  expect(word.attributes('aria-hidden')).toBe('true')
  expect(word.attributes('style')).toContain('passdi-word-ink')
  expect(word.attributes('style')).toContain('passdi-word-accent')
  expect(word.find('.brand-logo-ink').exists()).toBe(true)
  expect(word.find('.brand-logo-accent').exists()).toBe(true)

  const mark = mount(BrandLogo, { props: { variant: 'mark', size: 28 } })
  expect(mark.find('.brand-logo-word').exists()).toBe(false)
  expect(mark.attributes('aria-label')).toBe('Passdi')
  expect(mark.get('.brand-logo-mark').attributes('width')).toBe('28')

  expect(
    mount(BrandLogo, { props: { compact: true } })
      .get('.brand-logo-mark')
      .attributes('width'),
  ).toBe('44')
})

it('favicon is the badge and the page links the Apple touch icon', () => {
  const html = readFileSync('index.html', 'utf8')
  expect(html).toContain('rel="icon" href="/favicon.png" type="image/png"')
  expect(html).toContain('rel="icon" href="/favicon.ico" sizes="any"')
  expect(html).not.toContain('favicon.svg')
  expect(html).toContain('rel="apple-touch-icon" href="/apple-touch-icon.png"')
  // PNG signature: the favicon is the raster badge, not the old drawn tile.
  expect(
    [...readFileSync('public/favicon.png').subarray(1, 4)]
      .map((c) => String.fromCharCode(c))
      .join(''),
  ).toBe('PNG')
})

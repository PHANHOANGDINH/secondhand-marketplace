// Contrast check for the theme tokens (WCAG 2.x). Usage: node scripts/contrast.mjs
// Fails (exit 1) when a pair is below its minimum: 4.5 for body text, 3 for large text and icons.
import process from 'node:process'

const channel = (value) => {
  const c = value / 255
  return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
}
const luminance = (hex) => {
  const [r, g, b] = hex
    .replace('#', '')
    .match(/../g)
    .map((part) => parseInt(part, 16))
  return 0.2126 * channel(r) + 0.7152 * channel(g) + 0.0722 * channel(b)
}
export const contrast = (a, b) => {
  const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
  return (hi + 0.05) / (lo + 0.05)
}

export const themes = {
  light: {
    bg: '#FBF8F3',
    surface: '#FFFFFF',
    surface2: '#F4EFE7',
    text: '#2B2B2E',
    muted: '#6B6660',
    brand: '#F26B3A',
    brandText: '#E25A26',
    primary: '#C2410C',
    onPrimary: '#FFFFFF',
    primarySoft: '#FDEBDF',
    primaryInk: '#9A3412',
    price: '#C2410C',
    danger: '#DC2626',
    dangerInk: '#B91C1C',
    onDanger: '#FFFFFF',
    success: '#15803D',
    successInk: '#166534',
    onSuccess: '#FFFFFF',
    band: '#F26B3A',
    bandInk: '#2B2B2E',
    bandInkMuted: '#3A2218',
    accent: '#FFE2D1',
    accentInk: '#7C2D12',
    inverse: '#2B2B2E',
    onInverse: '#FFFFFF',
    rating: '#B45309',
  },
  dark: {
    bg: '#17130F',
    surface: '#211C17',
    surface2: '#2B251F',
    text: '#F4EFE7',
    muted: '#A89F94',
    brand: '#F26B3A',
    brandText: '#F26B3A',
    primary: '#F26B3A',
    onPrimary: '#17130F',
    primarySoft: '#3A2418',
    primaryInk: '#FF8A5C',
    price: '#FF8A5C',
    danger: '#F87171',
    dangerInk: '#FCA5A5',
    onDanger: '#17130F',
    success: '#4ADE80',
    successInk: '#86EFAC',
    onSuccess: '#17130F',
    band: '#2B251F',
    bandInk: '#F4EFE7',
    bandInkMuted: '#A89F94',
    accent: '#3A2418',
    accentInk: '#FFB38F',
    inverse: '#F26B3A',
    onInverse: '#17130F',
    rating: '#F5B544',
  },
}

// [foreground, background, minimum, what]
const pairs = (t) => [
  [t.text, t.bg, 4.5, 'text on page'],
  [t.text, t.surface, 4.5, 'text on card'],
  [t.text, t.surface2, 4.5, 'text on muted panel'],
  [t.muted, t.bg, 4.5, 'muted on page'],
  [t.muted, t.surface, 4.5, 'muted on card'],
  [t.muted, t.surface2, 4.5, 'muted on muted panel'],
  [t.primary, t.surface, 4.5, 'link on card'],
  [t.primary, t.bg, 4.5, 'link on page'],
  [t.onPrimary, t.primary, 4.5, 'button text on primary'],
  [t.primaryInk, t.primarySoft, 4.5, 'text on primary-soft'],
  [t.price, t.surface, 4.5, 'price on card'],
  [t.dangerInk, t.surface, 4.5, 'error text on card'],
  [t.successInk, t.surface, 4.5, 'success text on card'],
  [t.bandInk, t.band, 4.5, 'header text on band'],
  [t.bandInkMuted, t.band, 4.5, 'secondary header link on band'],
  [t.onDanger, t.danger, 4.5, 'text on danger badge'],
  [t.onSuccess, t.success, 4.5, 'text on success badge'],
  [t.accentInk, t.accent, 4.5, 'accent badge'],
  [t.onInverse, t.inverse, 4.5, 'inverse pill'],
  [t.brandText, t.surface, 3, 'logo "ss" (large) on card'],
  [t.brandText, t.bg, 3, 'logo "ss" (large) on page'],
  ['#FFFFFF', t.brand, 3, 'white mark stroke on brand tile (icon)'],
  [t.rating, t.surface, 3, 'rating star (icon)'],
]

let failed = 0
for (const [name, tokens] of Object.entries(themes)) {
  for (const [fg, bg, min, what] of pairs(tokens)) {
    const ratio = contrast(fg, bg)
    const ok = ratio >= min
    if (!ok) failed++
    console.log(
      `${ok ? 'ok  ' : 'FAIL'} ${name.padEnd(5)} ${ratio.toFixed(2).padStart(5)} ≥ ${min}  ${what} (${fg} on ${bg})`,
    )
  }
}
process.exit(failed ? 1 : 0)

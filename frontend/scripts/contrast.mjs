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
    bg: '#F8F8F7',
    surface: '#FFFFFF',
    surface2: '#F1F1EF',
    text: '#18181B',
    muted: '#63636B',
    brand: '#F26B3A',
    brandText: '#E25A26',
    primary: '#C2410C',
    onPrimary: '#FFFFFF',
    primarySoft: '#FFF1E8',
    primaryInk: '#9A3412',
    price: '#C2410C',
    danger: '#DC2626',
    dangerInk: '#B91C1C',
    onDanger: '#FFFFFF',
    success: '#15803D',
    successInk: '#166534',
    onSuccess: '#FFFFFF',
    band: '#FFFFFF',
    bandInk: '#18181B',
    bandInkMuted: '#63636B',
    accent: '#FFF1E8',
    accentInk: '#9A3412',
    inverse: '#18181B',
    onInverse: '#FFFFFF',
    rating: '#B45309',
  },
  dark: {
    bg: '#0F1012',
    surface: '#17181B',
    surface2: '#1F2024',
    text: '#F4F4F5',
    muted: '#A1A1AA',
    brand: '#F26B3A',
    brandText: '#F26B3A',
    primary: '#FB923C',
    onPrimary: '#18181B',
    primarySoft: '#2A1A10',
    primaryInk: '#FDBA74',
    price: '#FDBA74',
    danger: '#F87171',
    dangerInk: '#FCA5A5',
    onDanger: '#18181B',
    success: '#4ADE80',
    successInk: '#86EFAC',
    onSuccess: '#18181B',
    band: '#17181B',
    bandInk: '#F4F4F5',
    bandInkMuted: '#A1A1AA',
    accent: '#2A1A10',
    accentInk: '#FDBA74',
    inverse: '#FB923C',
    onInverse: '#18181B',
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

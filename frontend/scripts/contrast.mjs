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
    bg: '#F3F6F4',
    surface: '#FFFFFF',
    surface2: '#E8EFEB',
    text: '#12302A',
    muted: '#4A615B',
    brand: '#3A8A5C',
    brandText: '#2F7F52',
    primary: '#1F6F5C',
    onPrimary: '#FFFFFF',
    primarySoft: '#D7EFE6',
    primaryInk: '#155446',
    price: '#12302A',
    danger: '#DC2626',
    dangerInk: '#B91C1C',
    onDanger: '#FFFFFF',
    success: '#15803D',
    successInk: '#166534',
    onSuccess: '#FFFFFF',
    successSoft: '#E6F4EA',
    warningSoft: '#FEF3C7',
    warningInk: '#7A4306',
    band: '#F3F6F4',
    bandInk: '#12302A',
    bandInkMuted: '#4A615B',
    accent: '#D7EFE6',
    accentInk: '#155446',
    inverse: '#12302A',
    onInverse: '#FFFFFF',
    rating: '#B45309',
    field: '#12302A',
    onField: '#EAF5F0',
    onFieldMuted: '#B7D3C9',
    fieldEdge: '#FFFFFF',
  },
  dark: {
    bg: '#0B1714',
    surface: '#11211D',
    surface2: '#172C27',
    text: '#E3F1EC',
    muted: '#9CB9AF',
    brand: '#3A8A5C',
    brandText: '#6FBF8B',
    primary: '#5CC4A5',
    onPrimary: '#0B1714',
    primarySoft: '#17362E',
    primaryInk: '#8EE0C5',
    price: '#E3F1EC',
    danger: '#F87171',
    dangerInk: '#FCA5A5',
    onDanger: '#0B1714',
    success: '#4ADE80',
    successInk: '#86EFAC',
    onSuccess: '#0B1714',
    successSoft: '#16301F',
    warningSoft: '#3A2A0E',
    warningInk: '#F5B544',
    band: '#0B1714',
    bandInk: '#E3F1EC',
    bandInkMuted: '#9CB9AF',
    accent: '#17362E',
    accentInk: '#8EE0C5',
    inverse: '#5CC4A5',
    onInverse: '#0B1714',
    rating: '#F5B544',
    field: '#163A31',
    onField: '#E3F1EC',
    onFieldMuted: '#A9C9BE',
    fieldEdge: '#6F9A8D',
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
  [t.successInk, t.successSoft, 4.5, 'condition chip "Như mới"'],
  [t.warningInk, t.warningSoft, 4.5, 'condition chip "Đã sử dụng nhiều"'],
  [t.primaryInk, t.surface, 4.5, 'primary ink on card (chips on a card)'],
  [t.onDanger, t.danger, 4.5, 'text on danger badge'],
  [t.onSuccess, t.success, 4.5, 'text on success badge'],
  [t.accentInk, t.accent, 4.5, 'accent badge'],
  [t.onInverse, t.inverse, 4.5, 'inverse pill'],
  [t.onField, t.field, 4.5, 'text on the forest field'],
  [t.onFieldMuted, t.field, 4.5, 'secondary text on the forest field'],
  [t.fieldEdge, t.field, 3, 'edge of the search box on the forest field'],
  [t.brandText, t.surface, 3, 'logo "di" (large) on card'],
  [t.brandText, t.bg, 3, 'logo "di" (large) on page'],
  ['#FFFFFF', t.brand, 3, 'white icon on the brand colour'],
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

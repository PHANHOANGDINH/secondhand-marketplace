// Money as people type it in Vietnam: "1.500.000" (dot groups thousands), "1.500.000,5" (comma for the
// fraction). The API keeps its own form: "1500000" or "1500000.5", never more than two fraction digits.

const group = (digits) => digits.replace(/\B(?=(\d{3})+(?!\d))/g, '.')

/** API value ("1500000.50", 1500000) → text for the input ("1.500.000,5"). Empty stays empty. */
export function formatMoneyInput(value) {
  const text = value === null || value === undefined ? '' : String(value).trim()
  const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(text)
  if (!match) return text
  const fraction = (match[2] || '').replace(/0+$/, '')
  return group(match[1].replace(/^0+(?=\d)/, '')) + (fraction ? `,${fraction}` : '')
}

/**
 * What the person typed → { value, text }: value for the API, text to show (grouped, keeping a comma
 * they just typed). Dots and spaces are grouping, so they are dropped. One exception: a pasted
 * "1500000.50" keeps its decimal point — a grouped number never has 4+ digits before its first dot.
 */
export function parseMoneyInput(raw) {
  let text = String(raw ?? '').replace(/\s/g, '')
  if (/^\d{4,}\.\d{1,2}$/.test(text)) text = text.replace('.', ',')
  const comma = text.indexOf(',')
  const whole = (comma === -1 ? text : text.slice(0, comma))
    .replace(/\D/g, '')
    .replace(/^0+(?=\d)/, '')
    .slice(0, 13)
  const fraction =
    comma === -1
      ? ''
      : text
          .slice(comma + 1)
          .replace(/\D/g, '')
          .slice(0, 2)
  if (!whole && comma === -1) return { value: '', text: '' }
  const base = whole || '0'
  return {
    value: fraction ? `${base}.${fraction}` : base,
    text: group(base) + (comma === -1 ? '' : `,${fraction}`),
  }
}

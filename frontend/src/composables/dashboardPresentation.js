export function reportPreset(preset = '30', clock = new Date()) {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Ho_Chi_Minh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    })
      .formatToParts(clock)
      .filter((part) => part.type !== 'literal')
      .map((part) => [part.type, part.value]),
  )
  const to = `${parts.year}-${parts.month}-${parts.day}`
  const from = new Date(`${to}T00:00:00Z`)
  if (preset === 'month') from.setUTCDate(1)
  else from.setUTCDate(from.getUTCDate() - (preset === '7' ? 6 : 29))
  return { from: from.toISOString().slice(0, 10), to }
}

export { auctionStatusLabels as auctionLabels } from '../utils/labels'
export function amountCents(value) {
  const match = /^(\d+)(?:\.(\d{1,2}))?$/.exec(String(value))
  return match ? BigInt(match[1]) * 100n + BigInt((match[2] || '').padEnd(2, '0')) : null
}
export function centsAmount(value) {
  return `${value / 100n}.${String(value % 100n).padStart(2, '0')}`
}
export function parseBidInput(value) {
  const input = String(value).trim().replace(/\s/g, '')
  if (/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/.test(input))
    return input.replace(/\./g, '').replace(',', '.')
  return /^\d+(?:[.,]\d{1,2})?$/.test(input) ? input.replace(',', '.') : null
}
export function quickBid(minimum, increment) {
  const cents = amountCents(minimum)
  return cents === null ? '' : centsAmount(cents + BigInt(increment) * 100n)
}
export function bidIdentity(row) {
  return row.bidder_label || `Người mua #${row.user_id}`
}
export const bidTime = (value) => (value ? new Date(value).toLocaleString('vi-VN') : '—')

export function checkoutDeadlineLabel(value) {
  const date = new Date(value)
  return value && Number.isFinite(date.getTime())
    ? new Intl.DateTimeFormat('vi-VN', { dateStyle: 'medium', timeStyle: 'short' }).format(date)
    : '—'
}

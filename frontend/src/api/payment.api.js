import api from './axios'
import { getCsrfCookie } from './auth.api'
import { mutate } from './domain.api'
export const queryPayment = async (order) =>
  (await mutate('post', `orders/${order}/payments/zalopay/query`)).data
export async function retryPayment(order, key) {
  await getCsrfCookie()
  return (
    await api.post(
      `/api/v1/orders/${order}/payments/zalopay`,
      {},
      { headers: { 'Idempotency-Key': key } },
    )
  ).data.data
}
export function gatewayUrl(value) {
  try {
    const url = new URL(value)
    return url.protocol === 'https:' &&
      !url.username &&
      !url.password &&
      (!url.port || url.port === '443') &&
      /^(?:[a-z0-9-]+\.)*zalopay\.vn$/.test(url.hostname)
      ? url.href
      : null
  } catch {
    return null
  }
}
export function redirectToGateway(value) {
  const url = gatewayUrl(value)
  if (!url) throw new Error('Invalid payment URL')
  window.location.assign(url)
}

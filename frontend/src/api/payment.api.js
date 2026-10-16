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
/**
 * Opens the ZaloPay page in a new tab so this tab can wait on the result page: the sandbox does not always
 * send the buyer back. Returns 'tab' when the new tab opened, 'same' when the browser blocked it and this
 * tab went to ZaloPay instead (the old behaviour; ZaloPay's redirect then brings the buyer back).
 */
export function redirectToGateway(value) {
  const url = gatewayUrl(value)
  if (!url) throw new Error('Invalid payment URL')
  const opened = window.open(url, '_blank')
  if (opened) {
    // The gateway page must not be able to script or navigate this tab.
    opened.opener = null
    return 'tab'
  }
  window.location.assign(url)
  return 'same'
}
export const paymentResultPath = (orderId) => `/payment/result?order=${encodeURIComponent(orderId)}`

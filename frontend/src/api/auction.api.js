import { read, mutate } from './domain.api'
export const listAuctions = (params) => read('auctions', params)
export const listAuctionBids = (id, params) =>
  read(`auctions/${encodeURIComponent(id)}/bids`, params)
export const placeAuctionBid = async (id, amount) =>
  (await mutate('post', `auctions/${encodeURIComponent(id)}/bids`, { amount })).data
export const listSellerAuctions = (params) => read('seller/auctions', params)
export const getSellerAuction = async (id) =>
  (await read(`seller/auctions/${encodeURIComponent(id)}`)).data
export const createAuction = async (data) => (await mutate('post', 'seller/auctions', data)).data
export const cancelAuction = async (id) =>
  (await mutate('post', `seller/auctions/${encodeURIComponent(id)}/cancel`)).data
// "Bán ngay": end a live auction by accepting the highest bid the seller saw.
export const acceptAuctionBid = async (id, data) =>
  (await mutate('post', `seller/auctions/${encodeURIComponent(id)}/accept-bid`, data)).data
export const getAuction = async (id) => (await read(`auctions/${encodeURIComponent(id)}`)).data

export const getAuctionCheckout = async (id) =>
  (await read(`auctions/${encodeURIComponent(id)}/checkout`)).data
export async function checkoutAuction(id, data, key) {
  const [{ default: api }, { getCsrfCookie }] = await Promise.all([
    import('./axios'),
    import('./auth.api'),
  ])
  await getCsrfCookie()
  return (
    await api.post(`/api/v1/auctions/${encodeURIComponent(id)}/checkout`, data, {
      headers: { 'Idempotency-Key': key },
    })
  ).data.data
}

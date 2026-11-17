import { read, mutate } from './domain.api'
export const listShops = (params) => read('shops', params)
export const getShop = async (slug) => (await read(`shops/${encodeURIComponent(slug)}`)).data
export const getSellerShop = async () => (await read('seller/shop')).data
export const createShop = async (data) => (await mutate('post', 'shops', data)).data
export const updateShop = async (data) => (await mutate('patch', 'seller/shop', data)).data
export const submitShop = async () => (await mutate('post', 'seller/shop/submit')).data
export const listAdminShops = (params) => read('admin/shops', params)
export const getAdminShop = async (id) => (await read(`admin/shops/${id}`)).data
export const moderateShop = async (id, data) =>
  (await mutate('patch', `admin/shops/${id}/status`, data)).data
// Shop star ratings: the public list carries a `summary` (average, count, 1–5 distribution).
export const listShopReviews = (slug, params) => read(`shops/${encodeURIComponent(slug)}/reviews`, params)
export const getMyShopReview = async (slug) =>
  (await read(`shops/${encodeURIComponent(slug)}/reviews/mine`)).data
// One rating per completed order: POST rates the newest completed order not yet rated; PATCH edits one of mine.
export const createShopReview = (slug, data) =>
  mutate('post', `shops/${encodeURIComponent(slug)}/reviews`, data)
export const updateShopReview = (id, data) => mutate('patch', `shop-reviews/${id}`, data)
export async function uploadShopLogo(picture) {
  const data = new FormData()
  data.append('avatar', picture, 'logo.jpg')
  return (await mutate('post', 'seller/shop/logo', data)).data
}
export const removeShopLogo = async () => (await mutate('delete', 'seller/shop/logo')).data

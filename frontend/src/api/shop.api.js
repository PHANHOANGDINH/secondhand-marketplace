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
export const saveShopReview = (slug, data) =>
  mutate('put', `shops/${encodeURIComponent(slug)}/reviews`, data)

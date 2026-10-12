import { read, mutate } from './domain.api'
export const listWishlist = (params) => read('wishlist', params)
export const saveFavorite = (id) => mutate('post', `products/${encodeURIComponent(id)}/wishlist`)
export const removeFavorite = (id) =>
  mutate('delete', `products/${encodeURIComponent(id)}/wishlist`)

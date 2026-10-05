import { read, mutate } from './domain.api'
export const getCart = async () => (await read('cart')).data
export const addCartItem = async (productId, quantity) =>
  (await mutate('post', 'cart/items', { product_id: productId, quantity })).data
export const updateCartItem = async (id, quantity) =>
  (await mutate('patch', `cart/items/${id}`, { quantity })).data
export const removeCartItem = (id) => mutate('delete', `cart/items/${id}`)
export const clearCart = () => mutate('delete', 'cart')

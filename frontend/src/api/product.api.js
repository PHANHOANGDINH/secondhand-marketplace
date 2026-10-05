import { read, mutate } from './domain.api'
export const listProducts = (params) => read('products', params)
export const getProduct = async (slug) => (await read(`products/${encodeURIComponent(slug)}`)).data
export const listSellerProducts = (params) => read('seller/products', params)
export const getSellerProduct = async (id) => (await read(`seller/products/${id}`)).data
export const createProduct = async (data) => (await mutate('post', 'seller/products', data)).data
export const updateProduct = async (id, data) =>
  (await mutate('patch', `seller/products/${id}`, data)).data
export const deleteProduct = (id) => mutate('delete', `seller/products/${id}`)
export const productAction = async (id, action) =>
  (await mutate('post', `seller/products/${id}/${action}`)).data
export const listAdminProducts = (params) => read('admin/products', params)
export const getAdminProduct = async (id) => (await read(`admin/products/${id}`)).data
export const moderateProduct = async (id, data) =>
  (await mutate('patch', `admin/products/${id}/status`, data)).data
export async function uploadProductImages(id, files) {
  const data = new FormData()
  files.forEach((file) => data.append('images[]', file))
  return (await mutate('post', `seller/products/${id}/images`, data)).data
}
export const setPrimaryImage = async (id, image) =>
  (await mutate('patch', `seller/products/${id}/images/${image}`, { is_primary: true })).data
export const deleteProductImage = (id, image) =>
  mutate('delete', `seller/products/${id}/images/${image}`)
export const reorderProductImages = async (id, images) =>
  (await mutate('patch', `seller/products/${id}/images/reorder`, { image_ids: images })).data

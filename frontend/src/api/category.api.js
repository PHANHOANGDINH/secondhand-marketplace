import { read, mutate } from './domain.api'
export const listCategories = (params) => read('categories', params)
export const getCategory = async (slug) =>
  (await read(`categories/${encodeURIComponent(slug)}`)).data
export const listAdminCategories = (params) => read('admin/categories', params)
export const createCategory = async (data) => (await mutate('post', 'admin/categories', data)).data
export const updateCategory = async (id, data) =>
  (await mutate('patch', `admin/categories/${id}`, data)).data
export const deleteCategory = (id, reason) => mutate('delete', `admin/categories/${id}`, { reason })

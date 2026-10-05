import { read, mutate } from './domain.api'
export const listAddresses = (params) => read('addresses', params)
export const createAddress = async (data) => (await mutate('post', 'addresses', data)).data
export const updateAddress = async (id, data) =>
  (await mutate('patch', `addresses/${id}`, data)).data
export const deleteAddress = (id) => mutate('delete', `addresses/${id}`)
export const setDefaultAddress = async (id) =>
  (await mutate('patch', `addresses/${id}/default`)).data

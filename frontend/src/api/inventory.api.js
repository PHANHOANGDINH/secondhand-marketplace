import { read } from './domain.api'
export const getActionCenter = async () => (await read('seller/action-center')).data
export const listInventory = (params) => read('seller/inventory', params)

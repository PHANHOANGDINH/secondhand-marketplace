import { read } from './domain.api'
export const getSellerDashboard = async (params) => (await read('seller/dashboard', params)).data
export const getSellerRevenue = async (params) =>
  (await read('seller/dashboard/revenue', params)).data

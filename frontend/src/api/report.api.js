import { read, mutate } from './domain.api'
export const createReport = async (data) => (await mutate('post', 'reports', data)).data
export const listReports = (admin, params) => read(admin ? 'admin/reports' : 'my-reports', params)
export const getReport = async (admin, id) =>
  (await read(`${admin ? 'admin/reports' : 'my-reports'}/${id}`)).data
export const updateReportStatus = async (id, data) =>
  (await mutate('patch', `admin/reports/${id}/status`, data)).data

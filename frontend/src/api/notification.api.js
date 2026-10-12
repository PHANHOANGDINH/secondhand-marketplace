import { read, mutate } from './domain.api'
export const listNotifications = (params) => read('notifications', params)
export const getNotificationCount = async () => (await read('notifications/unread-count')).data
export const markNotificationRead = async (id) =>
  (await mutate('post', `notifications/${encodeURIComponent(id)}/read`)).data
export const markAllNotificationsRead = async () =>
  (await mutate('post', 'notifications/read-all')).data

import { read, mutate } from './domain.api'
export const listVideoComments = (id, params) =>
  read(`videos/${encodeURIComponent(id)}/comments`, params)
export const createVideoComment = async (id, body) =>
  (await mutate('post', `videos/${encodeURIComponent(id)}/comments`, { body })).data
export const hideVideoComment = (id) =>
  mutate('patch', `video-comments/${encodeURIComponent(id)}`, { status: 'HIDDEN' })

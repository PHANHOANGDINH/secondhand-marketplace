import api from './axios'
import { getCsrfCookie } from './auth.api'
import { read, mutate } from './domain.api'
export const listPublicVideos = (params) => read('videos', params)
export const getPublicVideo = async (id) => (await read(`videos/${encodeURIComponent(id)}`)).data
const path = (id) => `seller/products/${encodeURIComponent(id)}/videos`
export const listSellerVideos = (id) => read(path(id))
export const listProductVideos = (slug) => read(`products/${encodeURIComponent(slug)}/videos`)
export const updateVideo = (id, video, data) =>
  mutate('patch', `${path(id)}/${encodeURIComponent(video)}`, data)
export const deleteVideo = (id, video) =>
  mutate('delete', `${path(id)}/${encodeURIComponent(video)}`)
export async function uploadVideo(id, file, metadata, onProgress) {
  await getCsrfCookie()
  const data = new FormData()
  data.append('video', file)
  for (const [key, value] of Object.entries(metadata)) if (value) data.append(key, value)
  return (
    await api.post(`/api/v1/${path(id)}`, data, {
      onUploadProgress: (e) =>
        onProgress(e.total ? Math.min(100, Math.round((e.loaded / e.total) * 100)) : null),
    })
  ).data
}

import { read, mutate } from './domain.api'
export const getProfile = async () => (await read('profile')).data
export async function uploadAvatar(picture) {
  const data = new FormData()
  data.append('avatar', picture, 'avatar.jpg')
  return (await mutate('post', 'profile/avatar', data)).data
}
export const removeAvatar = async () => (await mutate('delete', 'profile/avatar')).data
/**
 * Any photo a phone can take becomes a small square JPEG before it is sent, so nobody meets a size limit.
 * Without the browser features for it the original goes up and the server decides.
 */
export async function shrinkPicture(file, side = 512) {
  if (typeof createImageBitmap !== 'function') return file
  let bitmap
  try {
    bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
  } catch {
    throw { response: { status: 422, data: { errors: { avatar: ['Không đọc được ảnh này. Hãy chọn một ảnh khác.'] } } } }
  }
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = side
  const crop = Math.min(bitmap.width, bitmap.height)
  const context = canvas.getContext('2d')
  context.fillStyle = '#fff'
  context.fillRect(0, 0, side, side)
  context.drawImage(bitmap, (bitmap.width - crop) / 2, (bitmap.height - crop) / 2, crop, crop, 0, 0, side, side)
  bitmap.close()
  return (await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9))) || file
}
export const updateProfile = async (data) => (await mutate('patch', 'profile', data)).data

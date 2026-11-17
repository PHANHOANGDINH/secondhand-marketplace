import { read, mutate } from './domain.api'
export const listProductReviews = (slug, params) =>
  read(`products/${encodeURIComponent(slug)}/reviews`, params)
export const getReviewEligibility = (slug, params) =>
  read(`products/${encodeURIComponent(slug)}/review-eligibility`, params)
export const createReview = async (itemId, data) =>
  (await mutate('post', `order-items/${itemId}/review`, data)).data
export const updateReview = async (id, data) => (await mutate('patch', `reviews/${id}`, data)).data
// One optional MP4/WebM video per review; uploading again replaces it.
export const REVIEW_VIDEO_MAX_BYTES = 50 * 1024 * 1024
export async function uploadReviewVideo(id, file) {
  const data = new FormData()
  data.append('video', file)
  return (await mutate('post', `reviews/${id}/video`, data)).data
}
export const removeReviewVideo = async (id) => (await mutate('delete', `reviews/${id}/video`)).data

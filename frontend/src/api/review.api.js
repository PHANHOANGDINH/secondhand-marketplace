import { read, mutate } from './domain.api'
export const listProductReviews = (slug, params) =>
  read(`products/${encodeURIComponent(slug)}/reviews`, params)
export const getReviewEligibility = (slug, params) =>
  read(`products/${encodeURIComponent(slug)}/review-eligibility`, params)
export const createReview = async (itemId, data) =>
  (await mutate('post', `order-items/${itemId}/review`, data)).data
export const updateReview = async (id, data) => (await mutate('patch', `reviews/${id}`, data)).data

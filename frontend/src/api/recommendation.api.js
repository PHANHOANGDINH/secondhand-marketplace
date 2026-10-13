import { read } from './domain.api'
export const listRecommendations = async (limit = 12) =>
  (await read('recommendations', { limit })).data
export const listSimilarProducts = async (slug, limit = 8) =>
  (await read(`products/${encodeURIComponent(slug)}/similar`, { limit })).data

const sorts = ['newest', 'oldest', 'price_asc', 'price_desc', 'rating', 'relevance']
const price = /^(0|[1-9][0-9]{0,12})(\.[0-9]{1,2})?$/
const decimal = (value) =>
  BigInt(value.split('.')[0]) * 100n + BigInt((value.split('.')[1] || '').padEnd(2, '0'))
export function normalizeDiscoveryQuery(query) {
  const normalized = {}
  for (const [key, value] of Object.entries(query)) {
    if (typeof value !== 'string' || !value.trim()) continue
    const text = value.trim()
    if (['q', 'search', 'keyword'].includes(key)) {
      if (!['q', 'search', 'keyword'].some((k) => normalized[k]))
        normalized[key] = text.slice(0, 100)
    } else if (['category', 'shop', 'page'].includes(key) && /^[1-9]\d{0,14}$/.test(text))
      normalized[key] = text
    else if (key === 'per_page' && /^\d+$/.test(text) && Number(text) >= 1 && Number(text) <= 100)
      normalized[key] = text
    else if (['min_price', 'max_price'].includes(key) && price.test(text)) normalized[key] = text
    else if (key === 'condition' && ['LIKE_NEW', 'GOOD', 'FAIR', 'POOR'].includes(text))
      normalized[key] = text
    else if (key === 'sort' && sorts.includes(text)) normalized[key] = text
    else if (key === 'has_video' && ['0', '1'].includes(text)) normalized[key] = text
    else if (key === 'province' && /^\d{2}$/.test(text)) normalized[key] = text
    else if (key === 'sale_type' && ['fixed', 'auction'].includes(text)) normalized[key] = text
    else if (key === 'quick-listing' && text === '1') normalized[key] = text
  }
  if (normalized.keyword) {
    normalized.search = normalized.keyword
    delete normalized.keyword
  }
  if (
    normalized.min_price &&
    normalized.max_price &&
    decimal(normalized.min_price) > decimal(normalized.max_price)
  )
    delete normalized.max_price
  return normalized
}

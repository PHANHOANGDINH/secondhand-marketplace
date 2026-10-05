import { listCategories } from './category.api'
import { listShops } from './shop.api'
async function pages(loader, params = {}) {
  const rows = []
  let page = 1,
    last
  do {
    const result = await loader({ ...params, page, per_page: 100 })
    rows.push(...result.data)
    last = result.meta.last_page
    page++
  } while (page <= last)
  return rows
}
export async function categoryOptions() {
  const result = []
  async function level(parent, prefix, depth) {
    const rows = await pages(listCategories, parent ? { parent_id: parent } : {})
    for (const row of rows) {
      result.push({ value: row.id, label: prefix + row.name })
      if (depth < 3) await level(row.id, prefix + '— ', depth + 1)
    }
  }
  await level(null, '', 1)
  return result
}
export async function shopOptions() {
  return (await pages(listShops)).map((shop) => ({ value: shop.id, label: shop.name }))
}

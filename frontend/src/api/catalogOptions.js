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
  // One request for the whole tree, then parents first with their children under them.
  const { data } = await listCategories({ all: 1 })
  const children = new Map()
  for (const row of data) {
    const parent = row.parent_id == null ? null : String(row.parent_id)
    children.set(parent, [...(children.get(parent) || []), row])
  }
  const result = []
  const level = (parent, prefix, depth) => {
    for (const row of children.get(parent) || []) {
      result.push({ value: row.id, label: prefix + row.name })
      if (depth < 3) level(String(row.id), prefix + '— ', depth + 1)
    }
  }
  level(null, '', 1)
  return result
}
export async function shopOptions() {
  return (await pages(listShops)).map((shop) => ({ value: shop.id, label: shop.name }))
}

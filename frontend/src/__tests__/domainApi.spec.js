import api from '../api/axios'
import { updateProfile } from '../api/profile.api'
import { deleteAddress } from '../api/address.api'
import { createShop, moderateShop } from '../api/shop.api'
import { deleteCategory, listCategories } from '../api/category.api'
beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'get').mockResolvedValue({ data: { data: [] } })
  vi.spyOn(api, 'request').mockResolvedValue({ data: { data: { id: '1' } } })
})
it('domain mutations reuse the session Axios instance and refresh CSRF first', async () => {
  const calls = []
  api.get.mockImplementation(async () => {
    calls.push('csrf')
    return {}
  })
  api.request.mockImplementation(async () => {
    calls.push('mutation')
    return { data: { data: { name: 'Updated' } } }
  })
  expect(await updateProfile({ name: 'Updated', phone: null })).toEqual({ name: 'Updated' })
  expect(calls).toEqual(['csrf', 'mutation'])
  expect(api.request).toHaveBeenCalledWith({
    method: 'patch',
    url: '/api/v1/profile',
    data: { name: 'Updated', phone: null },
  })
})
it('shop creation follows the existing contract and moderation includes optimistic state', async () => {
  await createShop({ name: 'Shop' })
  expect(api.request).toHaveBeenLastCalledWith({
    method: 'post',
    url: '/api/v1/shops',
    data: { name: 'Shop' },
  })
  const data = { expected_status: 'PENDING', status: 'ACTIVE', reason: 'Review' }
  await moderateShop('8', data)
  expect(api.request).toHaveBeenLastCalledWith({
    method: 'patch',
    url: '/api/v1/admin/shops/8/status',
    data,
  })
})
it('DELETE category carries reason in Axios data and address deletion uses its id', async () => {
  await deleteCategory('2', 'Unused')
  expect(api.request).toHaveBeenLastCalledWith({
    method: 'delete',
    url: '/api/v1/admin/categories/2',
    data: { reason: 'Unused' },
  })
  await deleteAddress('3')
  expect(api.request).toHaveBeenLastCalledWith({
    method: 'delete',
    url: '/api/v1/addresses/3',
    data: {},
  })
})
it('public categories sends bounded pagination options without needing CSRF', async () => {
  await listCategories({ parent_id: '2', page: 1, per_page: 20 })
  expect(api.get).toHaveBeenCalledExactlyOnceWith('/api/v1/categories', {
    params: { parent_id: '2', page: 1, per_page: 20 },
  })
  expect(api.request).not.toHaveBeenCalled()
})
it('a failed CSRF bootstrap does not send or retry the mutation', async () => {
  api.get.mockRejectedValue(new Error('offline'))
  await expect(updateProfile({ name: 'Updated' })).rejects.toThrow('offline')
  expect(api.request).not.toHaveBeenCalled()
})

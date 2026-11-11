import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createMemoryHistory, createRouter } from 'vue-router'
import NavDrawer from '../components/layout/NavDrawer.vue'
import * as catalog from '../api/catalogOptions'

// The drawer is a dialog teleported to <body>, so it is read from the document.
const open = async () => {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: { template: '<div />' } }] })
  await router.push('/')
  await router.isReady()
  const wrapper = mount(NavDrawer, { attachTo: document.body, global: { plugins: [router] } })
  await flushPromises()
  return wrapper
}

beforeEach(() => {
  vi.restoreAllMocks()
})

it('a failed load says so and "Tải lại" fetches the categories again', async () => {
  const load = vi.spyOn(catalog, 'categoryOptions').mockRejectedValueOnce(new Error('offline')).mockResolvedValueOnce([{ value: '1', label: 'Điện tử' }])
  const wrapper = await open()

  expect(document.body.textContent).toContain('Chưa tải được danh mục.')
  document.body.querySelector('[data-retry]').click()
  await flushPromises()

  expect(load).toHaveBeenCalledTimes(2)
  expect(document.body.textContent).toContain('Điện tử')
  expect(document.body.textContent).not.toContain('Chưa tải được')
  wrapper.unmount()
})

it('an empty list is not reported as a failure', async () => {
  vi.spyOn(catalog, 'categoryOptions').mockResolvedValue([])
  const wrapper = await open()

  expect(document.body.textContent).toContain('Chưa có danh mục.')
  expect(document.body.querySelector('[data-retry]')).toBeNull()
  wrapper.unmount()
})

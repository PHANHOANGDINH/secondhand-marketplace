import { expect, it } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia } from 'pinia'
import { createMemoryHistory, createRouter } from 'vue-router'
import StaticPageView from '../views/StaticPageView.vue'
import realRouter from '../router'

const paths = { help: '/help', safety: '/safety', contact: '/contact', about: '/about', terms: '/terms', privacy: '/privacy' }
const open = async (page) => {
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push(paths[page])
  await router.isReady()
  const wrapper = mount(StaticPageView, { props: { page }, global: { plugins: [createPinia(), router] } })
  await flushPromises()
  return wrapper
}

it.each(Object.keys(paths))('%s has one title, a menu of all six pages and marks itself as current', async (page) => {
  const wrapper = await open(page)

  expect(wrapper.findAll('h1')).toHaveLength(1)
  const menu = wrapper.get('nav[aria-label="Trang thông tin"]')
  expect(menu.findAll('a').map((a) => a.attributes('href'))).toEqual(Object.values(paths))
  expect(menu.findAll('[aria-current="page"]').map((a) => a.attributes('href'))).toEqual([paths[page]])
  expect(wrapper.findAll('article').length).toBeGreaterThan(1)
})

it.each(Object.keys(paths))('every link on %s leads to a real page', async (page) => {
  const wrapper = await open(page)

  for (const link of wrapper.findAll('a')) {
    expect(realRouter.resolve(link.attributes('href')).name).not.toBe('not-found')
  }
})

it('says what the app really does: listings appear at once, without review', async () => {
  for (const page of ['help', 'terms']) {
    const text = (await open(page)).text()
    expect(text).toContain('không chờ duyệt')
    expect(text).not.toMatch(/gửi duyệt|sau khi quản trị viên duyệt|kiểm duyệt trước/)
  }
})

it('the contact page is honest that there is no hotline and invents no address', async () => {
  const text = (await open('contact')).text()

  expect(text).toContain('không có tổng đài')
  expect(text).not.toMatch(/@|\b0\d{9}\b|hotline/i)
})

it('help and privacy cover the email verification', async () => {
  expect((await open('help')).text()).toContain('mã 6 số')
  expect((await open('privacy')).text()).toContain('mã xác minh')
})

it('on a narrow screen the menu row scrolls to the current page', async () => {
  const wrapper = await open('privacy')
  const row = wrapper.get('nav[aria-label="Trang thông tin"]').element
  Object.defineProperty(row, 'scrollWidth', { value: 900 })
  Object.defineProperty(row, 'clientWidth', { value: 300 })
  row.getBoundingClientRect = () => ({ left: 0 })
  const current = row.querySelector('[aria-current="page"]')
  current.getBoundingClientRect = () => ({ left: 700 })
  Object.defineProperty(current, 'offsetWidth', { value: 100 })

  await wrapper.setProps({ page: 'terms' })
  await flushPromises()

  expect(row.scrollLeft).toBe(600)
})

it('an unknown page falls back to help', async () => {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: { template: '<div />' } }] })
  await router.push('/help')
  await router.isReady()
  const wrapper = mount(StaticPageView, { props: { page: 'nope' }, global: { plugins: [createPinia(), router] } })

  expect(wrapper.get('h1').text()).toBe('Trợ giúp')
})

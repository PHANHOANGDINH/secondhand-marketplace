import { mount, flushPromises } from '@vue/test-utils'
import { createPinia } from 'pinia'
import HomeView from '../views/HomeView.vue'
import api from '../api/axios'

// The feed and the hero load their own data; these tests are about the page shell and the API check.
const mountHome = () =>
  mount(HomeView, {
    global: {
      plugins: [createPinia()],
      stubs: { RouterLink: true, HomeHero: true, HomeTabs: true },
    },
  })

beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'get')
})

describe('Home page API connection', () => {
  it('renders the project and successful API status', async () => {
    api.get.mockResolvedValue({ data: { status: 'ok' } })
    const wrapper = mountHome()
    expect(wrapper.text()).toContain('pass lại · Đồ cũ, giá trị mới')
    expect(wrapper.text()).toContain('Mua và bán đồ đã qua sử dụng')
    expect(
      wrapper.findAllComponents({ name: 'RouterLink' }).map((link) => link.attributes('to')),
    ).toEqual(['/products', '/shops'])
    expect(wrapper.findComponent({ name: 'HomeHero' }).exists()).toBe(true)
    expect(wrapper.findComponent({ name: 'HomeTabs' }).exists()).toBe(true)
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/api/v1/health')
    expect(wrapper.get('[role="status"]').text()).toContain('Kết nối cửa hàng: hoạt động')
  })

  it('shows a connection error and allows retry', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))
    const wrapper = mountHome()
    await flushPromises()
    expect(wrapper.get('[role="status"]').text()).toContain('Chưa kết nối')
    api.get.mockResolvedValueOnce({ data: { status: 'ok' } })
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="status"]').text()).toContain('Kết nối cửa hàng: hoạt động')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})

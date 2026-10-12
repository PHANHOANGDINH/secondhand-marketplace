import { mount, flushPromises } from '@vue/test-utils'
import HomeView from '../views/HomeView.vue'
import api from '../api/axios'

beforeEach(() => {
  vi.restoreAllMocks()
  vi.spyOn(api, 'get')
})

describe('Home page API connection', () => {
  it('renders the project and successful API status', async () => {
    api.get.mockResolvedValue({ data: { status: 'ok' } })
    const wrapper = mount(HomeView, { global: { stubs: { RouterLink: true, HomeDiscovery: true } } })
    expect(wrapper.text()).toContain('Secondhand · Sàn mua bán')
    expect(wrapper.text()).toContain('Mua và bán đồ đã qua sử dụng')
    expect(
      wrapper.findAllComponents({ name: 'RouterLink' }).map((link) => link.attributes('to')),
    ).toEqual(['/products', '/seller/shop', '/products', '/shops'])
    await flushPromises()
    expect(api.get).toHaveBeenCalledWith('/api/v1/health')
    expect(wrapper.get('[role="status"]').text()).toContain('Kết nối cửa hàng: hoạt động')
  })

  it('shows a connection error and allows retry', async () => {
    api.get.mockRejectedValueOnce(new Error('Network Error'))
    const wrapper = mount(HomeView, { global: { stubs: { RouterLink: true, HomeDiscovery: true } } })
    await flushPromises()
    expect(wrapper.get('[role="status"]').text()).toContain('Chưa kết nối')
    api.get.mockResolvedValueOnce({ data: { status: 'ok' } })
    await wrapper.get('button').trigger('click')
    await flushPromises()
    expect(wrapper.get('[role="status"]').text()).toContain('Kết nối cửa hàng: hoạt động')
    expect(wrapper.find('button').exists()).toBe(false)
  })
})

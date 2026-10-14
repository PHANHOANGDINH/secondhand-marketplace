import { mount } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import IconButton from '../components/ui/IconButton.vue'

it('an icon-only button keeps its words for screen readers and the tooltip, and passes attributes through', async () => {
  const onClick = vi.fn()
  const wrapper = mount(IconButton, {
    props: { icon: 'trash', label: 'Xóa' },
    attrs: { class: 'btn btn-outline-danger', 'data-action': 'delete', disabled: false, onClick },
  })
  const button = wrapper.get('button')
  expect(button.attributes('type')).toBe('button')
  expect(button.attributes('title')).toBe('Xóa')
  expect(button.classes()).toEqual(expect.arrayContaining(['btn', 'btn-outline-danger', 'icon-btn']))
  expect(button.attributes('data-action')).toBe('delete')
  expect(button.get('.visually-hidden').text()).toBe('Xóa')
  expect(button.get('svg').attributes('aria-hidden')).toBe('true')
  await button.trigger('click')
  expect(onClick).toHaveBeenCalledTimes(1)
})

it('a labelled one shows its words, and with "to" it is a link', async () => {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:any(.*)', component: { template: '<div />' } }] })
  const wrapper = mount(IconButton, {
    props: { icon: 'edit', label: 'Xem / Sửa', showLabel: true, to: '/seller/products/7/edit' },
    global: { plugins: [router] },
  })
  const link = wrapper.get('a')
  expect(link.attributes('href')).toBe('/seller/products/7/edit')
  expect(link.classes()).toContain('is-labelled')
  expect(link.get('.icon-btn-label').text()).toBe('Xem / Sửa')
  expect(link.find('.visually-hidden').exists()).toBe(false)
})

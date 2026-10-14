import { mount } from '@vue/test-utils'
import DetailSheet from '../components/ui/DetailSheet.vue'
import FilterSheet from '../components/ui/FilterSheet.vue'

const wrappers = []
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
  document.body.style.overflow = ''
})

it('the detail sheet opens over the list as a dialog and closes with the button, Escape or the backdrop', async () => {
  const wrapper = mount(DetailSheet, {
    props: { open: false, id: 'demo-detail', title: 'Tin đăng: Máy ảnh' },
    slots: { default: '<p data-inside>Nội dung chi tiết</p>' },
    attachTo: document.body,
  })
  wrappers.push(wrapper)
  expect(wrapper.find('[role="dialog"]').exists()).toBe(false)
  await wrapper.setProps({ open: true })
  const dialog = wrapper.get('[role="dialog"]')
  expect(dialog.attributes('aria-modal')).toBe('true')
  expect(wrapper.get(`#${dialog.attributes('aria-labelledby')}`).text()).toBe('Tin đăng: Máy ảnh')
  // Rendered in place, so the page's own controls stay inside its component.
  expect(wrapper.find('[data-inside]').exists()).toBe(true)
  expect(document.body.style.overflow).toBe('hidden')
  await wrapper.get('.detail-sheet-close').trigger('click')
  await dialog.trigger('keydown', { key: 'Escape' })
  await wrapper.get('.detail-sheet-backdrop').trigger('click')
  expect(wrapper.emitted('close')).toHaveLength(3)
  // A click inside the panel does not close it, and nothing closes while busy.
  await dialog.trigger('click')
  await wrapper.setProps({ busy: true })
  await wrapper.get('.detail-sheet-backdrop').trigger('click')
  expect(wrapper.emitted('close')).toHaveLength(3)
  await wrapper.setProps({ open: false, busy: false })
  expect(document.body.style.overflow).toBe('')
})

it('the filter card is folded, counts the filters that are on and lists them', async () => {
  const wrapper = mount(FilterSheet, {
    props: {
      active: [
        { label: 'Trạng thái', value: 'Chờ xem xét' },
        { label: 'Tìm', value: '' },
        { label: 'Danh mục', value: true },
        { label: 'Gian hàng', value: null },
      ],
    },
    slots: { default: '<form data-form><input name="search" /></form>' },
  })
  wrappers.push(wrapper)
  const details = wrapper.get('details')
  expect(details.attributes('open')).toBeUndefined()
  expect(wrapper.get('.filter-sheet-count').text()).toBe('2')
  expect(wrapper.findAll('.filter-sheet-chip').map((chip) => chip.text())).toEqual(['Trạng thái: Chờ xem xét', 'Danh mục'])
  // The page's own form stays in the document, so it keeps working while folded.
  expect(wrapper.find('[data-form] input[name="search"]').exists()).toBe(true)

  await wrapper.setProps({ active: [] })
  expect(wrapper.find('.filter-sheet-count').exists()).toBe(false)
  expect(wrapper.get('.filter-sheet-hint').text()).toContain('Chưa áp dụng bộ lọc nào')
})

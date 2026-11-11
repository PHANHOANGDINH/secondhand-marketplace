import { beforeEach, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import AvatarPicker from '../components/account/AvatarPicker.vue'
import UserAvatar from '../components/ui/UserAvatar.vue'
import { useAuthStore } from '../stores/auth'
import * as profileApi from '../api/profile.api'

const user = (extra = {}) => ({ id: '3', name: 'Trần Lan', role: 'USER', status: 'ACTIVE', avatar_url: null, ...extra })
const setup = (signedIn = user()) => {
  const pinia = createPinia()
  setActivePinia(pinia)
  const auth = useAuthStore()
  auth.user = signedIn
  return { auth, wrapper: mount(AvatarPicker, { global: { plugins: [pinia] } }) }
}
const choose = async (wrapper, file = new File(['x'], 'me.jpg', { type: 'image/jpeg' })) => {
  const input = wrapper.get('[data-avatar-input]')
  Object.defineProperty(input.element, 'files', { value: [file], configurable: true })
  await input.trigger('change')
  await flushPromises()
}

beforeEach(() => {
  vi.restoreAllMocks()
})

it('shows initials without a picture and the picture when there is one', async () => {
  const wrapper = mount(UserAvatar, { props: { name: 'Trần Lan' } })
  expect(wrapper.text()).toBe('TL')
  expect(wrapper.find('img').exists()).toBe(false)

  await wrapper.setProps({ url: 'http://x/a.jpg' })
  expect(wrapper.get('img').attributes('src')).toBe('http://x/a.jpg')
})

it('falls back to the initials when the picture fails to load, and tries again for a new picture', async () => {
  const wrapper = mount(UserAvatar, { props: { name: 'Trần Lan', url: 'http://x/gone.jpg' } })

  await wrapper.get('img').trigger('error')
  expect(wrapper.text()).toBe('TL')

  await wrapper.setProps({ url: 'http://x/new.jpg' })
  expect(wrapper.find('img').exists()).toBe(true)
})

it('choosing a picture shrinks it, saves at once and updates the signed-in user', async () => {
  const small = new Blob(['y'], { type: 'image/jpeg' })
  const shrink = vi.spyOn(profileApi, 'shrinkPicture').mockResolvedValue(small)
  const upload = vi.spyOn(profileApi, 'uploadAvatar').mockResolvedValue(user({ avatar_url: 'http://x/a.jpg' }))
  const { auth, wrapper } = setup()

  await choose(wrapper)

  expect(shrink).toHaveBeenCalledTimes(1)
  expect(upload).toHaveBeenCalledWith(small)
  expect(auth.user.avatar_url).toBe('http://x/a.jpg')
  expect(wrapper.text()).toContain('Đã đổi ảnh đại diện.')
  expect(wrapper.get('[data-avatar-pick]').text()).toBe('Đổi ảnh')
})

it('a picture the browser cannot read gives a plain message and changes nothing', async () => {
  vi.spyOn(profileApi, 'shrinkPicture').mockRejectedValue({
    response: { status: 422, data: { errors: { avatar: ['Không đọc được ảnh này.'] } } },
  })
  const upload = vi.spyOn(profileApi, 'uploadAvatar')
  const { auth, wrapper } = setup()

  await choose(wrapper)

  expect(upload).not.toHaveBeenCalled()
  expect(auth.user.avatar_url).toBeNull()
  expect(wrapper.text()).toContain('Không đọc được ảnh này.')
})

it('the remove button only appears with a picture and clears it', async () => {
  expect(setup().wrapper.find('[data-avatar-remove]').exists()).toBe(false)

  const remove = vi.spyOn(profileApi, 'removeAvatar').mockResolvedValue(user())
  const { auth, wrapper } = setup(user({ avatar_url: 'http://x/a.jpg' }))
  await wrapper.get('[data-avatar-remove]').trigger('click')
  await flushPromises()

  expect(remove).toHaveBeenCalledTimes(1)
  expect(auth.user.avatar_url).toBeNull()
  expect(wrapper.find('[data-avatar-remove]').exists()).toBe(false)
})

it('without canvas support the original file is sent and the server decides', async () => {
  const file = new File(['x'], 'me.jpg', { type: 'image/jpeg' })
  vi.stubGlobal('createImageBitmap', undefined)

  expect(await profileApi.shrinkPicture(file)).toBe(file)
  vi.unstubAllGlobals()
})

it('the whole category tree comes from one request, parents first', async () => {
  const api = await import('../api/category.api')
  const list = vi.spyOn(api, 'listCategories').mockResolvedValue({
    data: [
      { id: '1', name: 'Điện tử', parent_id: null },
      { id: '2', name: 'Thời trang', parent_id: null },
      { id: '3', name: 'Điện thoại', parent_id: '1' },
      { id: '4', name: 'iPhone', parent_id: '3' },
    ],
  })
  const { categoryOptions } = await import('../api/catalogOptions')

  expect(await categoryOptions()).toEqual([
    { value: '1', label: 'Điện tử' },
    { value: '3', label: '— Điện thoại' },
    { value: '4', label: '— — iPhone' },
    { value: '2', label: 'Thời trang' },
  ])
  expect(list).toHaveBeenCalledTimes(1)
  expect(list).toHaveBeenCalledWith({ all: 1 })
})

it('the shop picture uses the same picker: pick, save, tell the page', async () => {
  const PicturePicker = (await import('../components/account/PicturePicker.vue')).default
  const small = new Blob(['y'], { type: 'image/jpeg' })
  vi.spyOn(profileApi, 'shrinkPicture').mockResolvedValue(small)
  const saved = { id: '5', logo_url: 'http://x/shop.jpg' }
  const upload = vi.fn().mockResolvedValue(saved)
  const wrapper = mount(PicturePicker, {
    props: { name: 'Góc Cũ', url: '', title: 'Ảnh gian hàng', hint: 'Gợi ý', noun: 'ảnh gian hàng', upload, remove: vi.fn() },
  })

  await choose(wrapper)

  expect(upload).toHaveBeenCalledWith(small)
  expect(wrapper.emitted('changed')[0]).toEqual([saved])
  expect(wrapper.text()).toContain('Đã đổi ảnh gian hàng.')
})

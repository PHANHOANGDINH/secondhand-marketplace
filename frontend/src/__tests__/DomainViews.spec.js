import { mount, flushPromises } from '@vue/test-utils'
import { createRouter, createMemoryHistory } from 'vue-router'
import { createPinia } from 'pinia'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import ProfileView from '../views/ProfileView.vue'
import AddressesView from '../views/AddressesView.vue'
import SellerShopView from '../views/SellerShopView.vue'
import AdminCategoriesView from '../views/AdminCategoriesView.vue'
import AdminShopsView from '../views/AdminShopsView.vue'
import * as profileApi from '../api/profile.api'
import * as addressApi from '../api/address.api'
import * as shopApi from '../api/shop.api'
import * as categoryApi from '../api/category.api'
const user = {
  id: '7',
  name: 'Current User',
  email: 'user@example.test',
  phone: null,
  role: 'USER',
  status: 'ACTIVE',
}
const address = {
  id: '10',
  recipient_name: 'Recipient',
  phone: '0901234567',
  address_line: '12 Example',
  ward: 'Ward',
  district: null,
  province: 'City',
  country_code: 'VN',
  is_default: true,
}
const shop = {
  id: '20',
  name: 'Test Shop',
  slug: 'test-shop',
  status: 'PENDING',
  description: 'Used items',
  owner_id: '7',
  archived: false,
}
const category = {
  id: '30',
  name: 'Category',
  slug: 'category',
  description: null,
  parent_id: null,
  status: 'ACTIVE',
}
const collection = (data) => ({ data, meta: { current_page: 1, last_page: 1, total: data.length } })
const wrappers = []
it('address loading failure does not claim the address collection is empty', async () => {
  vi.spyOn(addressApi, 'listAddresses').mockRejectedValueOnce(new Error('Network'))
  const { wrapper } = await setup(AddressesView)
  expect(wrapper.get('[role="alert"]').exists()).toBe(true)
  expect(wrapper.text()).not.toContain('Chưa có địa chỉ.')
})
async function setup(component, role = 'USER') {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.user = { ...user, role }
  auth.initialized = true
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }],
  })
  await router.push('/admin/shops')
  const wrapper = mount(component, {
    global: {
      plugins: [pinia, router],
      stubs: { RouterLink: { props: ['to'], template: '<a :href="to"><slot /></a>' } },
    },
  })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, auth }
}
function button(wrapper, text) {
  return wrapper.findAll('button').find((node) => node.text() === text)
}
beforeEach(() => vi.restoreAllMocks())
afterEach(() => {
  for (const wrapper of wrappers.splice(0)) wrapper.unmount()
})
it('loads profile, submits only editable fields and updates the auth store', async () => {
  vi.spyOn(profileApi, 'getProfile').mockResolvedValue(user)
  vi.spyOn(profileApi, 'updateProfile').mockResolvedValue({
    ...user,
    name: 'Updated User',
    phone: '0901234567',
  })
  const { wrapper, auth } = await setup(ProfileView)
  expect(wrapper.get('input[name=name]').element.value).toBe(user.name)
  expect(wrapper.text()).toContain(user.email)
  expect(wrapper.find('input[name=email]').exists()).toBe(false)
  await wrapper.get('input[name=name]').setValue('Updated User')
  await wrapper.get('input[name=phone]').setValue('0901234567')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(profileApi.updateProfile).toHaveBeenCalledWith({
    name: 'Updated User',
    phone: '0901234567',
  })
  expect(auth.user.name).toBe('Updated User')
  expect(wrapper.text()).toContain('Đã cập nhật hồ sơ')
})
it('shows profile field validation and keeps current identity on failure', async () => {
  vi.spyOn(profileApi, 'getProfile').mockResolvedValue(user)
  vi.spyOn(profileApi, 'updateProfile').mockRejectedValue({
    response: { status: 422, data: { errors: { phone: ['Invalid phone'] } } },
  })
  const { wrapper, auth } = await setup(ProfileView)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('#phone-errors').text()).toContain('Số điện thoại không hợp lệ')
  expect(auth.user.name).toBe(user.name)
  expect(wrapper.get('button[type=submit]').attributes('disabled')).toBeUndefined()
})
it('loads addresses and creates a new address without an owner field', async () => {
  vi.spyOn(addressApi, 'listAddresses').mockResolvedValue(collection([address]))
  vi.spyOn(addressApi, 'createAddress').mockResolvedValue(address)
  const { wrapper } = await setup(AddressesView)
  expect(wrapper.text()).toContain('Mặc định')
  for (const key of ['recipient_name', 'phone', 'address_line', 'ward', 'province'])
    await wrapper.get(`input[name=${key}]`).setValue(address[key])
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(addressApi.createAddress).toHaveBeenCalledWith({
    recipient_name: address.recipient_name,
    phone: address.phone,
    address_line: address.address_line,
    ward: address.ward,
    district: null,
    province: address.province,
    country_code: 'VN',
    is_default: false,
  })
  expect(addressApi.listAddresses).toHaveBeenCalledTimes(2)
})
it('edits an address and uses its own resource id', async () => {
  vi.spyOn(addressApi, 'listAddresses').mockResolvedValue(collection([address]))
  vi.spyOn(addressApi, 'updateAddress').mockResolvedValue(address)
  const { wrapper } = await setup(AddressesView)
  await button(wrapper, 'Sửa').trigger('click')
  await wrapper.get('input[name=ward]').setValue('New ward')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(addressApi.updateAddress).toHaveBeenCalledWith(
    address.id,
    expect.objectContaining({ ward: 'New ward' }),
  )
})
it('sets default and deletes addresses then reloads server state', async () => {
  vi.spyOn(addressApi, 'listAddresses').mockResolvedValue(
    collection([{ ...address, is_default: false }]),
  )
  vi.spyOn(addressApi, 'setDefaultAddress').mockResolvedValue(address)
  vi.spyOn(addressApi, 'deleteAddress').mockResolvedValue(undefined)
  const { wrapper } = await setup(AddressesView)
  await button(wrapper, 'Đặt mặc định').trigger('click')
  await flushPromises()
  expect(addressApi.setDefaultAddress).toHaveBeenCalledWith('10')
  await button(wrapper, 'Xóa').trigger('click')
  expect(addressApi.deleteAddress).not.toHaveBeenCalled()
  await button(wrapper, 'Xác nhận xóa').trigger('click')
  await flushPromises()
  expect(addressApi.deleteAddress).toHaveBeenCalledWith('10')
  expect(addressApi.listAddresses).toHaveBeenCalledTimes(3)
})
it('shows address API errors without losing the entered form', async () => {
  vi.spyOn(addressApi, 'listAddresses').mockResolvedValue(collection([]))
  vi.spyOn(addressApi, 'createAddress').mockRejectedValue({
    response: { status: 422, data: { errors: { ward: ['Required ward'] } } },
  })
  const { wrapper } = await setup(AddressesView)
  await wrapper.get('input[name=recipient_name]').setValue('Retained')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(wrapper.get('#ward-errors').text()).toContain('Phường/xã là bắt buộc')
  expect(wrapper.get('input[name=recipient_name]').element.value).toBe('Retained')
})
it('offers shop creation on 404 and submits no privileged status or owner', async () => {
  vi.spyOn(shopApi, 'getSellerShop').mockRejectedValue({ response: { status: 404 } })
  vi.spyOn(shopApi, 'createShop').mockResolvedValue(shop)
  const { wrapper } = await setup(SellerShopView)
  expect(button(wrapper, 'Tạo gian hàng')).toBeTruthy()
  expect(wrapper.find('[name=status]').exists()).toBe(false)
  await wrapper.get('input[name=name]').setValue(shop.name)
  await wrapper.get('textarea[name=description]').setValue(shop.description)
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(shopApi.createShop).toHaveBeenCalledWith({
    name: shop.name,
    description: shop.description,
  })
  expect(wrapper.text()).toContain('Chờ duyệt')
})
it('does not interpret authentication failure as a missing shop', async () => {
  vi.spyOn(shopApi, 'getSellerShop').mockRejectedValue({ response: { status: 401 } })
  const { wrapper } = await setup(SellerShopView)
  expect(wrapper.find('form').exists()).toBe(false)
  expect(wrapper.get('[role=alert]').text()).toContain('đăng nhập')
})
it('shows existing shop status and updates only name and description', async () => {
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue(shop)
  vi.spyOn(shopApi, 'updateShop').mockResolvedValue(shop)
  const { wrapper } = await setup(SellerShopView)
  expect(wrapper.text()).toContain('Chờ duyệt')
  await wrapper.get('form').trigger('submit')
  await flushPromises()
  expect(shopApi.updateShop).toHaveBeenCalledWith({
    name: shop.name,
    description: shop.description,
  })
})
it('keeps suspended shop read-only and exposes rejected resubmission', async () => {
  vi.spyOn(shopApi, 'getSellerShop').mockResolvedValue({ ...shop, status: 'SUSPENDED' })
  const { wrapper } = await setup(SellerShopView)
  expect(wrapper.get('input[name=name]').attributes('disabled')).toBeDefined()
  expect(wrapper.find('button[type=submit]').exists()).toBe(false)
  shopApi.getSellerShop.mockResolvedValue({ ...shop, status: 'REJECTED' })
  vi.spyOn(shopApi, 'submitShop').mockResolvedValue(shop)
  await button(wrapper, 'Tải lại').trigger('click')
  await flushPromises()
  await button(wrapper, 'Gửi lại để xét duyệt').trigger('click')
  await flushPromises()
  expect(shopApi.submitShop).toHaveBeenCalledTimes(1)
  expect(wrapper.text()).toContain('Chờ duyệt')
})
it('admin guard waits for session and distinguishes guest, USER and ADMIN', async () => {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.initialized = true
  const guard = createAuthGuard(auth),
    target = { meta: { requiresAdmin: true } }
  expect(await guard(target)).toEqual({ name: 'login' })
  auth.user = user
  expect(await guard(target)).toEqual({ name: 'forbidden' })
  auth.user = { ...user, role: 'ADMIN' }
  expect(await guard(target)).toBe(true)
})
it('admin category create and edit send hierarchy, status and reason without slug', async () => {
  vi.spyOn(categoryApi, 'listAdminCategories').mockResolvedValue(collection([category]))
  vi.spyOn(categoryApi, 'createCategory').mockResolvedValue(category)
  vi.spyOn(categoryApi, 'updateCategory').mockResolvedValue(category)
  const { wrapper } = await setup(AdminCategoriesView, 'ADMIN')
  await wrapper.get('input[name=name]').setValue('New category')
  await wrapper.get('select[name=parent_id]').setValue(category.id)
  await wrapper.get('input[name=reason]').setValue('Organize catalog')
  await wrapper.get('[data-category-form]').trigger('submit')
  await flushPromises()
  expect(categoryApi.createCategory).toHaveBeenCalledWith({
    name: 'New category',
    description: null,
    parent_id: '30',
    status: 'ACTIVE',
    reason: 'Organize catalog',
  })
  await button(wrapper, 'Sửa').trigger('click')
  await wrapper.get('select[name=status]').setValue('INACTIVE')
  await wrapper.get('input[name=reason]').setValue('Temporarily hidden')
  await wrapper.get('[data-category-form]').trigger('submit')
  await flushPromises()
  expect(categoryApi.updateCategory).toHaveBeenCalledWith(
    '30',
    expect.objectContaining({ status: 'INACTIVE', parent_id: null, reason: 'Temporarily hidden' }),
  )
})
it('admin category deletion displays server conflict and retains confirmation', async () => {
  vi.spyOn(categoryApi, 'listAdminCategories').mockResolvedValue(collection([category]))
  vi.spyOn(categoryApi, 'deleteCategory').mockRejectedValue({
    response: { status: 409, data: { message: 'Category has children or products.' } },
  })
  const { wrapper } = await setup(AdminCategoriesView, 'ADMIN')
  await button(wrapper, 'Xóa').trigger('click')
  await wrapper.get('input[name=delete_reason]').setValue('Unused')
  await wrapper.get('[data-delete-category]').trigger('submit')
  await flushPromises()
  expect(categoryApi.deleteCategory).toHaveBeenCalledWith('30', 'Unused')
  expect(wrapper.get('[role=alert]').text()).toContain('Dữ liệu đã thay đổi')
  expect(wrapper.find('[data-delete-category]').exists()).toBe(true)
})
it('shop moderation presents allowed transitions and sends expected status and reason', async () => {
  const pending = { ...shop, owner_id: '8' }
  vi.spyOn(shopApi, 'listAdminShops').mockResolvedValue(collection([pending]))
  vi.spyOn(shopApi, 'getAdminShop').mockResolvedValue(pending)
  vi.spyOn(shopApi, 'moderateShop').mockResolvedValue({ ...pending, status: 'ACTIVE' })
  const { wrapper } = await setup(AdminShopsView, 'ADMIN')
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-target=SUSPENDED]').exists()).toBe(false)
  expect(wrapper.get('[data-target=ACTIVE]').attributes('disabled')).toBeDefined()
  await wrapper.get('textarea[name=reason]').setValue('Reviewed')
  await wrapper.get('[data-target=ACTIVE]').trigger('click')
  await flushPromises()
  expect(shopApi.moderateShop).toHaveBeenCalledWith('20', {
    expected_status: 'PENDING',
    status: 'ACTIVE',
    reason: 'Reviewed',
  })
  expect(wrapper.find('[data-target=REJECTED]').exists()).toBe(false)
  expect(wrapper.find('[data-target=SUSPENDED]').exists()).toBe(true)
})
it('admin UI does not offer self moderation', async () => {
  vi.spyOn(shopApi, 'listAdminShops').mockResolvedValue(collection([shop]))
  vi.spyOn(shopApi, 'getAdminShop').mockResolvedValue(shop)
  const { wrapper } = await setup(AdminShopsView, 'ADMIN')
  await button(wrapper, 'Xem').trigger('click')
  await flushPromises()
  expect(wrapper.find('[data-target]').exists()).toBe(false)
})

it('profile cancel restores saved fields without an API write', async () => {
  vi.spyOn(profileApi, 'getProfile').mockResolvedValue(user)
  vi.spyOn(profileApi, 'updateProfile')
  const { wrapper } = await setup(ProfileView)
  await wrapper.get('input[name=name]').setValue('Unsaved name')
  await wrapper.get('input[name=phone]').setValue('0909999999')
  await button(wrapper, 'Hủy thay đổi').trigger('click')
  expect(wrapper.get('input[name=name]').element.value).toBe(user.name)
  expect(wrapper.get('input[name=phone]').element.value).toBe('')
  expect(profileApi.updateProfile).not.toHaveBeenCalled()
})

import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { createPinia } from 'pinia'
import { createRouter, createMemoryHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { provideMessaging } from '../composables/useMessaging'
import ChatDrawer from '../components/chat/ChatDrawer.vue'
import ChatLauncher from '../components/chat/ChatLauncher.vue'
import ChatSellerButton from '../components/chat/ChatSellerButton.vue'
import * as api from '../api/messaging.api'
import axios from '../api/axios'

const conversation = {
  id: '7',
  partner: { id: '2', name: 'Seller' },
  product: { id: '9', name: 'Máy ảnh', slug: 'camera', price: '500000' },
  product_unavailable: false,
  unread_count: 2,
  last_message: null,
}
const message = (id, body = 'Xin chào', senderId = '2') => ({
  id: String(id),
  body,
  sender_id: senderId,
  created_at: '2026-10-07T08:00:00Z',
})
const result = (data, next = null) => ({ data, links: { next }, unread_total: 2 })
const wrappers = []
let hidden
beforeEach(() => {
  vi.restoreAllMocks()
  vi.useFakeTimers()
  hidden = vi.spyOn(document, 'hidden', 'get').mockReturnValue(false)
  vi.spyOn(api, 'listConversations').mockResolvedValue(result([structuredClone(conversation)]))
  vi.spyOn(api, 'listMessages').mockResolvedValue(result([message(2), message(1)]))
  vi.spyOn(api, 'markConversationRead').mockImplementation(async (_id, checkpoint) => ({
    last_read_message_id: checkpoint,
    unread_count: 0,
  }))
  vi.spyOn(api, 'startConversation').mockResolvedValue(structuredClone(conversation))
  vi.spyOn(api, 'sendMessage').mockResolvedValue(message(3, 'Hello', '1'))
})
afterEach(() => {
  wrappers.splice(0).forEach((wrapper) => wrapper.unmount())
  vi.useRealTimers()
  vi.restoreAllMocks()
})
async function setup({ authenticated = true, path = '/products/camera' } = {}) {
  const pinia = createPinia(),
    auth = useAuthStore(pinia)
  auth.initialized = true
  auth.user = authenticated ? { id: '1', role: 'USER', status: 'ACTIVE' } : null
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/login', name: 'login', component: { template: '<div />' } },
      { path: '/:pathMatch(.*)*', component: { template: '<div />' } },
    ],
  })
  await router.push(path)
  let chat
  const Host = defineComponent({
    components: { ChatDrawer, ChatLauncher, ChatSellerButton },
    setup() {
      chat = provideMessaging()
      return { product: { id: '9' } }
    },
    template: '<ChatLauncher /><ChatSellerButton :product="product" /><ChatDrawer />',
  })
  const wrapper = mount(Host, { attachTo: document.body, global: { plugins: [pinia, router] } })
  wrappers.push(wrapper)
  await flushPromises()
  return { wrapper, chat, auth, router }
}
it('anonymous messaging returns through login safely and makes no conversation API requests', async () => {
  const { wrapper, router } = await setup({ authenticated: false })
  expect(wrapper.find('.chat-launcher').exists()).toBe(false)
  await wrapper.findAll('button')[0].trigger('click')
  await flushPromises()
  expect(router.currentRoute.value.name).toBe('login')
  expect(router.currentRoute.value.query.redirect).toBe('/products/camera?chat=1')
  expect(api.startConversation).not.toHaveBeenCalled()
  expect(api.listConversations).not.toHaveBeenCalled()
})
it('an authenticated product return automatically opens the seller chat and removes the return marker', async () => {
  const { router, chat } = await setup({ path: '/products/camera?chat=1' })
  expect(api.startConversation).toHaveBeenCalledWith('9')
  expect(router.currentRoute.value.fullPath).toBe('/products/camera')
  expect(chat.active.value.id).toBe('7')
  expect(chat.opened.value).toBe(true)
})
it('opens the drawer with unread counts, chronological plain-text bubbles and a read checkpoint', async () => {
  api.listMessages.mockResolvedValue(
    result([message(2, 'My reply', '1'), message(1, '<img src=x onerror=alert(1)>')]),
  )
  const { wrapper, chat } = await setup()
  expect(wrapper.get('.chat-launcher .badge').text()).toBe('2')
  await wrapper.get('.chat-launcher').trigger('click')
  await flushPromises()
  await chat.selectConversation(chat.conversations.value[0])
  await flushPromises()
  const drawer = document.querySelector('[role=dialog]')
  expect(drawer.getAttribute('aria-modal')).toBe('true')
  expect([...drawer.querySelectorAll('.chat-bubble p')].map((node) => node.textContent)).toEqual([
    '<img src=x onerror=alert(1)>',
    'My reply',
  ])
  expect(drawer.querySelector('.chat-bubble img')).toBeNull()
  expect(drawer.querySelector('.chat-bubble.mine').textContent).toContain('My reply')
  expect(api.markConversationRead).toHaveBeenCalledWith('7', '2')
  expect(chat.unreadTotal.value).toBe(0)
  expect(drawer.querySelector('a').getAttribute('href')).toBe('/products/camera')
})
it('uses message ID cursors to load older history and poll new messages without duplicates', async () => {
  const { chat } = await setup()
  api.listMessages.mockResolvedValueOnce(result([message(4), message(3)], 'older'))
  await chat.selectConversation(chat.conversations.value[0])
  expect(chat.hasOlder.value).toBe(true)
  api.listMessages.mockResolvedValueOnce(result([message(2), message(1)]))
  await chat.refreshMessages('older')
  expect(api.listMessages).toHaveBeenLastCalledWith('7', { per_page: 30, before_id: '3' })
  api.listMessages.mockResolvedValueOnce(result([message(5)]))
  await vi.advanceTimersByTimeAsync(4000)
  expect(api.listMessages).toHaveBeenLastCalledWith('7', { per_page: 30, after_id: '4' })
  expect(chat.messages.value.map((row) => row.id)).toEqual(['1', '2', '3', '4', '5'])
  expect(api.markConversationRead).toHaveBeenLastCalledWith('7', '5')
})
it('sends trimmed text once, disables duplicate submission and preserves drafts on validation failure', async () => {
  const { chat } = await setup()
  await chat.selectConversation(chat.conversations.value[0])
  let finish
  api.sendMessage.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve
      }),
  )
  chat.draft.value = '  Hello  '
  const request = chat.send()
  await chat.send()
  expect(api.sendMessage).toHaveBeenCalledTimes(1)
  expect(api.sendMessage).toHaveBeenCalledWith('7', 'Hello')
  api.listMessages.mockResolvedValueOnce(result([message(3, 'Hello', '1')]))
  finish(message(3, 'Hello', '1'))
  await request
  expect(chat.draft.value).toBe('')
  expect(chat.messages.value.at(-1).body).toBe('Hello')
  api.sendMessage.mockRejectedValueOnce({
    response: { status: 422, data: { errors: { body: ['Invalid message'] } } },
  })
  chat.draft.value = 'Keep my draft'
  await chat.send()
  expect(chat.draft.value).toBe('Keep my draft')
  expect(chat.error.value).toBe('Nội dung không hợp lệ. Vui lòng kiểm tra lại.')
  expect(chat.sending.value).toBe(false)
})
it('pauses polling while the document is hidden, resumes when visible and cleans timers on unmount', async () => {
  const { wrapper, chat } = await setup()
  await chat.selectConversation(chat.conversations.value[0])
  hidden.mockReturnValue(true)
  document.dispatchEvent(new Event('visibilitychange'))
  const calls = api.listMessages.mock.calls.length
  await vi.advanceTimersByTimeAsync(12000)
  expect(api.listMessages).toHaveBeenCalledTimes(calls)
  hidden.mockReturnValue(false)
  document.dispatchEvent(new Event('visibilitychange'))
  await flushPromises()
  expect(api.listMessages).toHaveBeenCalledTimes(calls + 1)
  wrapper.unmount()
  const lists = api.listConversations.mock.calls.length
  await vi.advanceTimersByTimeAsync(12000)
  expect(api.listConversations).toHaveBeenCalledTimes(lists)
  expect(vi.getTimerCount()).toBe(0)
})
it('closes on navigation or logout and never renders stale conversation responses after account changes', async () => {
  const { chat, auth, router } = await setup()
  let finish
  api.listMessages.mockImplementationOnce(
    () =>
      new Promise((resolve) => {
        finish = resolve
      }),
  )
  const request = chat.selectConversation(chat.conversations.value[0])
  auth.user = null
  await flushPromises()
  finish(result([message(99, 'Private response')]))
  await request
  expect(chat.messages.value).toEqual([])
  expect(chat.opened.value).toBe(false)
  expect(chat.conversations.value).toEqual([])
  expect(vi.getTimerCount()).toBe(0)
  auth.user = { id: '1', role: 'USER' }
  await flushPromises()
  await chat.open()
  await router.push('/products')
  expect(chat.opened.value).toBe(false)
})
it('restores focus on Escape and exposes an accessible mobile back-to-conversations action', async () => {
  const { wrapper, chat } = await setup()
  wrapper.get('.chat-launcher').element.focus()
  await wrapper.get('.chat-launcher').trigger('click')
  await flushPromises()
  expect(document.activeElement.getAttribute('aria-label')).toBe('Đóng tin nhắn')
  await chat.selectConversation(chat.conversations.value[0])
  await flushPromises()
  const back = document.querySelector('.chat-list-back')
  expect(back.textContent).toContain('Hội thoại')
  back.click()
  await flushPromises()
  expect(chat.active.value).toBeNull()
  document
    .querySelector('[role=dialog]')
    .dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))
  await flushPromises()
  expect(document.querySelector('[role=dialog]')).toBeNull()
  expect(document.activeElement).toBe(wrapper.get('.chat-launcher').element)
  expect(document.body.style.overflow).toBe('')
})
it('shows a failed conversation load without a contradictory empty state and supports retry', async () => {
  api.listConversations.mockRejectedValueOnce(new Error('Offline'))
  const { chat } = await setup()
  chat.opened.value = true
  await flushPromises()
  expect(document.querySelector('.chat-conversations [role=alert]')).not.toBeNull()
  expect(document.querySelector('.chat-conversations .buyer-empty')).toBeNull()
  await chat.refreshList()
  expect(chat.listError.value).toBe('')
  expect(chat.conversations.value).toHaveLength(1)
})
it('messaging writes use the existing cookie CSRF helper', async () => {
  vi.restoreAllMocks()
  vi.spyOn(axios, 'get').mockResolvedValue({})
  vi.spyOn(axios, 'request').mockResolvedValue({ data: { data: message(3) } })
  await api.sendMessage('7', 'Hello')
  expect(axios.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  expect(axios.request).toHaveBeenCalledWith({
    method: 'post',
    url: '/api/v1/conversations/7/messages',
    data: { body: 'Hello' },
  })
})

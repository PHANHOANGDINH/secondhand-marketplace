import { createPinia, setActivePinia } from 'pinia'
import api from '../api/axios'
import { useAuthStore } from '../stores/auth'
import { createAuthGuard } from '../router/authGuard'
import { authError } from '../api/auth.api'
const user = {
  id: '7',
  name: 'Test User',
  email: 'user@example.test',
  role: 'USER',
  status: 'ACTIVE',
}
beforeEach(() => {
  setActivePinia(createPinia())
  vi.restoreAllMocks()
  vi.spyOn(api, 'get')
  vi.spyOn(api, 'post')
})
it('gets CSRF before login and sets current user without persisted storage', async () => {
  const calls = []
  api.get.mockImplementation(async (url) => {
    calls.push(url)
    return {}
  })
  api.post.mockImplementation(async (url) => {
    calls.push(url)
    return { data: { data: user } }
  })
  const store = useAuthStore()
  await store.login({ email: user.email, password: 'test-only-input' })
  expect(calls).toEqual(['/sanctum/csrf-cookie', '/api/v1/auth/login'])
  expect(store.user).toEqual(user)
  expect(store.isAuthenticated).toBe(true)
  expect(store.loading).toBe(false)
  expect(localStorage.length).toBe(0)
  expect(sessionStorage.length).toBe(0)
})
it('gets CSRF before registration and stays signed out until the address is verified', async () => {
  api.get.mockResolvedValue({})
  api.post.mockResolvedValue({ data: { data: user } })
  const store = useAuthStore()
  await store.register({ name: user.name, email: user.email })
  expect(api.get).toHaveBeenCalledWith('/sanctum/csrf-cookie')
  expect(api.post).toHaveBeenCalledWith('/api/v1/auth/register', {
    name: user.name,
    email: user.email,
  })
  expect(store.user).toBeNull()
  expect(store.isAuthenticated).toBe(false)
})
it('failed login remains guest and resets loading', async () => {
  api.get.mockResolvedValue({})
  api.post.mockRejectedValue({ response: { status: 422 } })
  const store = useAuthStore()
  await expect(store.login({})).rejects.toMatchObject({ response: { status: 422 } })
  expect(store.user).toBeNull()
  expect(store.loading).toBe(false)
})
it('restores current user once even with concurrent initialization', async () => {
  api.get.mockResolvedValue({ data: { data: user } })
  const store = useAuthStore()
  await Promise.all([store.initialize(), store.initialize()])
  await store.initialize()
  expect(api.get).toHaveBeenCalledTimes(1)
  expect(api.get).toHaveBeenCalledWith('/api/v1/auth/me')
  expect(store.user).toEqual(user)
})
it('me 401 initializes guest without recursive calls', async () => {
  api.get.mockRejectedValue({ response: { status: 401 } })
  const store = useAuthStore()
  await store.initialize()
  expect(store.user).toBeNull()
  expect(store.initialized).toBe(true)
  expect(store.initializationError).toBe('')
  expect(api.get).toHaveBeenCalledTimes(1)
  expect(api.post).not.toHaveBeenCalled()
})
it('logout clears user after successful server invalidation', async () => {
  api.get.mockResolvedValue({})
  api.post.mockResolvedValue({ status: 204 })
  const store = useAuthStore()
  store.user = user
  await store.logout()
  expect(api.post).toHaveBeenCalledWith('/api/v1/auth/logout')
  expect(store.user).toBeNull()
})
it('failed logout preserves authenticated state and allows explicit retry', async () => {
  api.get.mockResolvedValue({})
  api.post.mockRejectedValue({ response: { status: 419 } })
  const store = useAuthStore()
  store.user = user
  await expect(store.logout()).rejects.toMatchObject({ response: { status: 419 } })
  expect(store.user).toEqual(user)
  expect(api.post).toHaveBeenCalledTimes(1)
  expect(authError({ response: { status: 419 } })).toContain('Phiên bảo mật')
})
it('network errors are visible without repeated me calls', async () => {
  api.get.mockRejectedValue(new Error('offline'))
  const store = useAuthStore()
  await store.initialize()
  expect(store.initializationError).not.toBe('')
  expect(api.get).toHaveBeenCalledTimes(1)
})
it('guest guard waits for initialization then redirects account to login', async () => {
  api.get.mockRejectedValue({ response: { status: 401 } })
  const store = useAuthStore()
  expect(await createAuthGuard(store)({ meta: { requiresAuth: true } })).toEqual({ name: 'login' })
  expect(store.initialized).toBe(true)
})
it('guest guard preserves a protected payment result destination', async () => {
  api.get.mockRejectedValue({ response: { status: 401 } })
  const store = useAuthStore()
  expect(
    await createAuthGuard(store)({
      meta: { requiresAuth: true },
      fullPath: '/payment/result?order=7',
    }),
  ).toEqual({
    name: 'login',
    query: { redirect: '/payment/result?order=7' },
  })
})
it('authenticated guard redirects guest-only routes and allows account', async () => {
  api.get.mockResolvedValue({ data: { data: user } })
  const guard = createAuthGuard(useAuthStore())
  expect(await guard({ meta: { guestOnly: true } })).toEqual({ name: 'home' })
  expect(await guard({ meta: { requiresAuth: true } })).toBe(true)
})
it('CSRF failure does not send login or retry automatically', async () => {
  api.get.mockRejectedValue(new Error('offline'))
  await expect(useAuthStore().login({})).rejects.toThrow('offline')
  expect(api.post).not.toHaveBeenCalled()
  expect(api.get).toHaveBeenCalledTimes(1)
})
it('suspended session stays blocked but exposes an explicit logout path', async () => {
  api.get.mockRejectedValueOnce({ response: { status: 403 } })
  const store = useAuthStore()
  await store.initialize()
  expect(store.user).toBeNull()
  expect(store.sessionBlocked).toBe(true)
  expect(store.initializationError).not.toBe('')
  api.get.mockResolvedValue({})
  api.post.mockResolvedValue({ status: 204 })
  await store.logout()
  expect(store.sessionBlocked).toBe(false)
})

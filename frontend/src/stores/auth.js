import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import * as authApi from '../api/auth.api'
export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const initialized = ref(false)
  const loading = ref(false)
  const initializationError = ref('')
  const sessionBlocked = ref(false)
  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'ADMIN')
  let initialization = null
  async function fetchUser() {
    try {
      user.value = await authApi.me()
      sessionBlocked.value = false
    } catch (error) {
      if ([401, 403].includes(error.response?.status)) {
        user.value = null
        sessionBlocked.value = error.response.status === 403
        if (error.response.status === 403) initializationError.value = authApi.authError(error)
      } else {
        throw error
      }
    }
    return user.value
  }
  async function initialize() {
    if (initialized.value) return
    if (initialization) return initialization
    loading.value = true
    initialization = (async () => {
      try {
        await fetchUser()
      } catch (error) {
        initializationError.value = authApi.authError(error)
      } finally {
        initialized.value = true
        loading.value = false
      }
    })()
    return initialization
  }
  async function authenticate(action, data) {
    loading.value = true
    initializationError.value = ''
    try {
      user.value = await action(data)
      sessionBlocked.value = false
      initialized.value = true
    } catch (error) {
      user.value = null
      throw error
    } finally {
      loading.value = false
    }
  }
  const login = (data) => authenticate(authApi.login, data)
  async function register(data) {
    loading.value = true
    try {
      return await authApi.register(data)
    } finally {
      loading.value = false
    }
  }
  async function logout() {
    loading.value = true
    try {
      await authApi.logout()
    } catch (error) {
      if (error.response?.status !== 401) throw error
    } finally {
      loading.value = false
    }
    user.value = null
    sessionBlocked.value = false
    initialized.value = true
    initializationError.value = ''
  }
  return {
    user,
    initialized,
    loading,
    initializationError,
    sessionBlocked,
    isAuthenticated,
    isAdmin,
    initialize,
    fetchUser,
    login,
    register,
    logout,
  }
})

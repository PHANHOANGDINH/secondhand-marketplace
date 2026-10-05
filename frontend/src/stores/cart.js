import { computed, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import * as cartApi from '../api/cart.api'
import { domainError } from '../api/domain.api'
export const useCartStore = defineStore('cart', () => {
  const auth = useAuthStore()
  const data = ref(null),
    loading = ref(false),
    writing = ref(false),
    error = ref('')
  const busy = computed(() => loading.value || writing.value)
  const count = computed(() => data.value?.total_quantity ?? null)
  let revision = 0,
    pendingLoad = null
  function reset() {
    revision++
    data.value = null
    loading.value = false
    writing.value = false
    error.value = ''
    pendingLoad = null
  }
  watch(() => auth.user?.id ?? null, reset, { flush: 'sync' })
  async function load() {
    if (!auth.isAuthenticated) {
      reset()
      return null
    }
    if (writing.value) return data.value
    if (pendingLoad) return pendingLoad
    const ticket = ++revision
    loading.value = true
    error.value = ''
    pendingLoad = (async () => {
      try {
        const result = await cartApi.getCart()
        if (ticket === revision) data.value = result
        return ticket === revision ? result : null
      } catch (failure) {
        if (ticket === revision) {
          data.value = null
          error.value = domainError(failure)
        }
        return null
      } finally {
        if (ticket === revision) {
          loading.value = false
          pendingLoad = null
        }
      }
    })()
    return pendingLoad
  }
  async function refreshAfterWrite(ticket) {
    try {
      const result = await cartApi.getCart()
      if (ticket === revision) data.value = result
    } catch {
      if (ticket === revision) {
        data.value = null
        error.value =
          'Chưa tải lại được giỏ hàng. Hãy tải lại để kiểm tra kết quả trước khi thêm lần nữa.'
      }
    }
  }
  async function change(action) {
    if (!auth.isAuthenticated) throw new Error('Authentication required')
    if (writing.value) throw new Error('A cart update is already running')
    const ticket = ++revision
    pendingLoad = null
    loading.value = false
    writing.value = true
    error.value = ''
    try {
      const result = await action()
      if (ticket === revision) await refreshAfterWrite(ticket)
      return ticket === revision ? (result ?? true) : null
    } catch (failure) {
      if (ticket !== revision) return null
      if (failure.response?.status === 409) await refreshAfterWrite(ticket)
      if ([401, 403].includes(failure.response?.status)) data.value = null
      throw failure
    } finally {
      if (ticket === revision) writing.value = false
    }
  }
  const add = (productId, quantity) => change(() => cartApi.addCartItem(productId, quantity))
  const update = (id, quantity) => change(() => cartApi.updateCartItem(id, quantity))
  const remove = (id) => change(() => cartApi.removeCartItem(id))
  const clear = () => change(() => cartApi.clearCart())
  return { data, loading, writing, busy, count, error, load, add, update, remove, clear, reset }
})

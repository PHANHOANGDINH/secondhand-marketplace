import { reactive, ref, watch } from 'vue'
import { defineStore } from 'pinia'
import { useAuthStore } from './auth'
import { saveFavorite, removeFavorite } from '../api/wishlist.api'
import { domainError } from '../api/domain.api'
export const useWishlistStore = defineStore('wishlist', () => {
  const auth = useAuthStore()
  const states = reactive({}),
    pending = reactive({}),
    errors = reactive({}),
    revision = ref(0)
  let generation = 0
  watch(
    () => auth.user?.id,
    () => {
      ++generation
      for (const map of [states, pending, errors])
        for (const key of Object.keys(map)) delete map[key]
      ++revision.value
    },
    { flush: 'sync' },
  )
  function hydrate(product) {
    if (auth.isAuthenticated && product?.id && !Object.hasOwn(states, product.id))
      states[product.id] = Boolean(product.is_wishlisted)
  }
  async function toggle(product) {
    const id = product.id,
      current = generation
    if (!auth.isAuthenticated || pending[id]) return
    pending[id] = true
    errors[id] = ''
    try {
      const result = await (states[id] ? removeFavorite(id) : saveFavorite(id))
      if (current !== generation) return
      states[id] = result.data.is_wishlisted
      ++revision.value
    } catch (error) {
      if (current === generation) errors[id] = domainError(error)
    } finally {
      if (current === generation) pending[id] = false
    }
  }
  return { states, pending, errors, revision, hydrate, toggle }
})

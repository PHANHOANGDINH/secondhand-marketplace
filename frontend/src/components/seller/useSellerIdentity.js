import { inject, watch } from 'vue'
export const sellerIdentityKey = Symbol('seller-shop-presentation')
// Display only metadata already loaded by the current page; no additional requests.
export function useSellerIdentity(source) {
  const identity = inject(sellerIdentityKey, null)
  watch(
    source,
    (shop) => {
      if (identity) identity.value = shop ? { name: shop.name, status: shop.status } : null
    },
    { immediate: true },
  )
}

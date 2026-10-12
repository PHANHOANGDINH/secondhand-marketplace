<script setup>
import { watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
defineProps({ iconOnly: Boolean })
const auth = useAuthStore(),
  cart = useCartStore()
watch(
  () => auth.user?.id,
  () => cart.load(),
  { immediate: true },
)
</script>
<template>
  <RouterLink
    to="/cart"
    :class="{ 'cart-icon-link': iconOnly }"
    :aria-label="iconOnly ? `Giỏ hàng, ${cart.count ?? 'chưa tải'} sản phẩm` : undefined"
    :title="cart.error || 'Xem giỏ hàng'"
    ><svg
      v-if="iconOnly"
      aria-hidden="true"
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
    >
      <path d="M3 3h2l3 12h10l3-9H6m4 14h0m7 0h0" />
      <circle cx="10" cy="20" r="1" />
      <circle cx="17" cy="20" r="1" /></svg
    ><span :class="{ 'visually-hidden': iconOnly }">Giỏ hàng</span>
    <span class="badge text-bg-secondary" data-cart-count>{{ cart.count ?? '—' }}</span></RouterLink
  >
</template>

<script setup>
import { watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
const auth = useAuthStore(),
  cart = useCartStore()
watch(
  () => auth.user?.id,
  () => cart.load(),
  { immediate: true },
)
</script>
<template>
  <RouterLink to="/cart" :title="cart.error || 'Xem giỏ hàng'"
    >Giỏ hàng
    <span class="badge text-bg-secondary" data-cart-count>{{ cart.count ?? '—' }}</span></RouterLink
  >
</template>

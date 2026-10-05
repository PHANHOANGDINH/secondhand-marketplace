<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { useFeedback } from '../composables/useFeedback'
import DomainFeedback from './DomainFeedback.vue'
const props = defineProps({ product: { type: Object, required: true } })
const auth = useAuthStore(),
  cart = useCartStore(),
  router = useRouter(),
  quantity = ref(1)
const { busy, error, errors, success, run } = useFeedback()
async function add() {
  if (!auth.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: `/products/${props.product.slug}` } })
    return
  }
  await run(() => cart.add(props.product.id, quantity.value), 'Đã thêm sản phẩm vào giỏ hàng.')
}
</script>
<template>
  <form class="border-top pt-3" @submit.prevent="add">
    <DomainFeedback :busy="busy" :error="error || cart.error" :success="success" />
    <div v-for="(messages, key) in errors" :key="key" class="text-danger">
      {{ messages.join(' ') }}
    </div>
    <label for="add-cart-quantity" class="form-label">Số lượng</label>
    <div class="d-flex gap-2">
      <input
        id="add-cart-quantity"
        v-model.number="quantity"
        name="quantity"
        type="number"
        min="1"
        :max="Math.min(99, product.stock_quantity)"
        step="1"
        required
        class="form-control"
        style="max-width: 100px"
        :disabled="busy || cart.writing || auth.loading"
      />
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="busy || cart.writing || auth.loading"
      >
        Thêm vào giỏ
      </button>
    </div>
    <RouterLink v-if="success" to="/cart" class="d-inline-block mt-2">Xem giỏ hàng</RouterLink>
  </form>
</template>

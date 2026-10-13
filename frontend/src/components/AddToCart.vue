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
  if (props.product.auction) return
  if (!auth.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: `/products/${props.product.slug}` } })
    return
  }
  await run(() => cart.add(props.product.id, quantity.value), 'Đã thêm sản phẩm vào giỏ hàng.')
}
// Buy now = the normal cart and checkout, narrowed to this one cart line. Nothing else is ordered.
async function buyNow() {
  if (props.product.auction) return
  if (!auth.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: `/products/${props.product.slug}` } })
    return
  }
  await run(async () => {
    await cart.add(props.product.id, quantity.value)
    const line = cart.data?.items?.find(
      (item) => String(item.product?.id) === String(props.product.id),
    )
    if (line) await router.push({ path: '/checkout', query: { items: String(line.id) } })
    else await router.push('/cart')
  })
}
</script>
<template>
  <div v-if="product.auction" class="border-top pt-3">
    <p class="text-muted">Sản phẩm đang dành cho đấu giá.</p>
    <RouterLink :to="`/auctions/${product.auction.id}`" class="btn btn-primary"
      >Xem phiên đấu giá</RouterLink
    >
  </div>
  <form v-else class="border-top pt-3" @submit.prevent="add">
    <DomainFeedback :busy="busy" :error="error || cart.error" :success="success" />
    <div v-for="(messages, key) in errors" :key="key" class="text-danger">
      {{ messages.join(' ') }}
    </div>
    <label for="add-cart-quantity" class="form-label">Số lượng</label>
    <div class="d-flex gap-2 flex-wrap">
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
        :disabled="busy || cart.writing || auth.loading || product.stock_quantity < 1"
      />
      <button
        type="submit"
        class="btn btn-primary"
        :disabled="busy || cart.writing || auth.loading || product.stock_quantity < 1"
      >
        {{ product.stock_quantity < 1 ? 'Tạm hết hàng' : 'Thêm vào giỏ' }}
      </button>
      <button
        v-if="product.stock_quantity >= 1"
        type="button"
        class="btn btn-outline-primary"
        data-buy-now
        :disabled="busy || cart.writing || auth.loading"
        @click="buyNow"
      >
        Mua ngay
      </button>
    </div>
    <RouterLink v-if="success" to="/cart" class="d-inline-block mt-2">Xem giỏ hàng</RouterLink>
  </form>
</template>

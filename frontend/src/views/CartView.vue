<script setup>
import { computed, onMounted, reactive, watch } from 'vue'
import { useCartStore } from '../stores/cart'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import ProductPhoto from '../components/ProductPhoto.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
const cart = useCartStore(),
  quantities = reactive({})
const { busy, error, errors, success, run } = useFeedback()
const disabled = computed(() => busy.value || cart.busy)
const groups = computed(() => {
  const grouped = new Map()
  for (const item of cart.data?.items || []) {
    const id = item.shop?.id ?? 'unavailable'
    if (!grouped.has(id)) grouped.set(id, { id, shop: item.shop, items: [] })
    grouped.get(id).items.push(item)
  }
  return Array.from(grouped.values())
})
const reasons = {
  PRODUCT_UNAVAILABLE: 'Sản phẩm hiện không còn khả dụng. Bạn có thể xóa khỏi giỏ.',
  INSUFFICIENT_STOCK: 'Tồn kho hiện tại không đủ. Hãy giảm số lượng hoặc xóa sản phẩm.',
  OWN_SHOP: 'Bạn không thể mua sản phẩm của gian hàng mình.',
}
watch(
  () => cart.data,
  (value) => {
    for (const key of Object.keys(quantities)) delete quantities[key]
    for (const item of value?.items || []) quantities[item.id] = item.quantity
  },
  { immediate: true },
)
const update = (item) =>
  run(() => cart.update(item.id, quantities[item.id]), 'Đã cập nhật số lượng.')
const remove = (item) => run(() => cart.remove(item.id), 'Đã xóa sản phẩm khỏi giỏ.')
const clear = () => run(() => cart.clear(), 'Đã xóa toàn bộ sản phẩm trong giỏ.')
onMounted(() => cart.load())
</script>
<template>
  <section>
    <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
      <h1 class="h3">Giỏ hàng</h1>
      <RouterLink to="/products">Tiếp tục xem sản phẩm</RouterLink>
    </div>
    <DomainFeedback :busy="disabled" :error="error || cart.error" :success="success" />
    <div v-for="(messages, key) in errors" :key="key" class="text-danger">
      {{ messages.join(' ') }}
    </div>
    <button class="btn btn-outline-secondary mb-3" :disabled="disabled" @click="cart.load">
      Tải lại giỏ hàng
    </button>
    <template v-if="cart.data">
      <p v-if="!cart.data.items.length" class="alert alert-info">Giỏ hàng đang trống.</p>
      <template v-else>
        <p v-if="cart.data.has_unavailable_items" class="alert alert-warning">
          Một số sản phẩm chưa thể mua. Giỏ hàng vẫn giữ các sản phẩm này để bạn kiểm tra hoặc xóa.
        </p>
        <section v-for="group in groups" :key="group.id" :data-shop="group.id" class="card mb-3">
          <h2 class="h5 card-header">{{ group.shop?.name || 'Gian hàng không còn khả dụng' }}</h2>
          <article
            v-for="item in group.items"
            :key="item.id"
            :data-cart-item="item.id"
            class="card-body border-bottom"
          >
            <div class="row g-3 align-items-center">
              <div class="col-3 col-md-2">
                <ProductPhoto
                  :src="item.primary_image?.url"
                  :alt="item.product?.name || 'Sản phẩm'"
                  style="height: 100px"
                />
              </div>
              <div class="col-9 col-md-4">
                <RouterLink v-if="item.product?.is_public" :to="`/products/${item.product.slug}`">{{
                  item.product.name
                }}</RouterLink
                ><strong v-else>{{ item.product?.name || 'Sản phẩm không còn khả dụng' }}</strong>
                <p class="mb-1">Đơn giá hiện tại: {{ formatPrice(item.unit_price) }}</p>
                <p v-if="!item.available" class="text-danger mb-1" role="alert">
                  {{ reasons[item.unavailable_reason] || 'Sản phẩm không còn khả dụng.' }}
                </p>
              </div>
              <form class="col-md-3" @submit.prevent="update(item)">
                <label :for="`quantity-${item.id}`" class="form-label"
                  >Số lượng (tối đa {{ item.max_quantity }})</label
                >
                <div class="d-flex gap-2">
                  <input
                    :id="`quantity-${item.id}`"
                    v-model.number="quantities[item.id]"
                    type="number"
                    name="quantity"
                    min="1"
                    :max="item.max_quantity"
                    step="1"
                    required
                    class="form-control"
                    :disabled="disabled || !item.max_quantity"
                  /><button
                    type="submit"
                    class="btn btn-outline-primary"
                    :disabled="disabled || !item.max_quantity"
                  >
                    Cập nhật
                  </button>
                </div>
              </form>
              <div class="col-md-3">
                <p class="fw-bold mb-2">{{ formatPrice(item.subtotal) }}</p>
                <button
                  class="btn btn-outline-danger btn-sm"
                  :disabled="disabled"
                  @click="remove(item)"
                >
                  Xóa
                </button>
              </div>
            </div>
          </article>
        </section>
        <div class="d-flex flex-wrap justify-content-between gap-3">
          <button
            class="btn btn-outline-danger align-self-start"
            :disabled="disabled"
            @click="clear"
          >
            Xóa toàn bộ giỏ
          </button>
          <div>
            <p class="h5" data-cart-total>Tổng tạm tính: {{ formatPrice(cart.data.subtotal) }}</p>
            <p v-if="cart.data.has_unavailable_items" data-available-total>
              Phần hiện khả dụng: {{ formatPrice(cart.data.available_subtotal) }}
            </p>
          </div>
        </div>
        <RouterLink
          v-if="!disabled && !cart.error && !cart.data.has_unavailable_items"
          to="/checkout"
          class="btn btn-primary mt-3"
          >Đặt hàng</RouterLink
        >
        <p class="text-secondary mt-3">
          Tạm tính theo giá hiện tại, gồm cả sản phẩm chưa khả dụng. Giỏ hàng không giữ tồn kho; giá
          và tồn kho sẽ được kiểm tra lại khi đặt hàng.
        </p>
      </template>
    </template>
  </section>
</template>

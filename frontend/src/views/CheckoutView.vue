<script setup>
import { computed, onMounted, ref } from 'vue'
import * as paymentApi from '../api/payment.api'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { listAddresses } from '../api/address.api'
import { checkout } from '../api/order.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
const router = useRouter(),
  auth = useAuthStore(),
  cart = useCartStore()
const addresses = ref([]),
  addressId = ref(''),
  paymentMethod = ref('COD'),
  channel = ref(''),
  attempt = ref(null)
const { busy, error, errors, run } = useFeedback()
const storageKey = `checkout-attempt:${auth.user?.id}`
try {
  const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null')
  if (
    saved?.key &&
    saved?.payload?.address_id &&
    ['COD', 'ZALOPAY'].includes(saved.payload.payment_method)
  ) {
    attempt.value = saved
    addressId.value = saved.payload.address_id
    paymentMethod.value = saved.payload.payment_method
    const methods = saved.payload.preferred_payment_method || []
    channel.value =
      methods.length === 2 && methods.includes('domestic_card') && methods.includes('account')
        ? 'domestic'
        : methods.length === 1
          ? methods[0]
          : ''
  }
} catch {
  /* Storage may be unavailable. */
}
function persist(value) {
  attempt.value = value
  try {
    if (value) sessionStorage.setItem(storageKey, JSON.stringify(value))
    else sessionStorage.removeItem(storageKey)
  } catch {
    /* Keep retry key in memory. */
  }
}
const groups = computed(() => {
  const map = new Map()
  for (const item of cart.data?.items || []) {
    const id = item.shop?.id
    if (!map.has(id)) map.set(id, { shop: item.shop, items: [] })
    map.get(id).items.push(item)
  }
  return [...map.values()]
})
const canSubmit = computed(
  () =>
    !busy.value &&
    !cart.busy &&
    (attempt.value ||
      (addressId.value && cart.data?.items.length && !cart.data.has_unavailable_items)),
)
async function load() {
  await run(async () => {
    await cart.load()
    addresses.value = (await listAddresses({ per_page: 100 })).data
    if (!attempt.value)
      addressId.value = (addresses.value.find((a) => a.is_default) || addresses.value[0])?.id || ''
  })
}
async function submit() {
  if (!canSubmit.value) return
  await run(async () => {
    const buyerId = auth.user?.id
    if (!attempt.value)
      persist({
        key: crypto.randomUUID(),
        payload: {
          address_id: addressId.value,
          payment_method: paymentMethod.value,
          ...(paymentMethod.value === 'ZALOPAY'
            ? {
                preferred_payment_method:
                  channel.value === 'domestic'
                    ? ['domestic_card', 'account']
                    : channel.value
                      ? [channel.value]
                      : [],
              }
            : {}),
        },
      })
    let order
    try {
      order = await checkout(attempt.value.payload, attempt.value.key)
    } catch (failure) {
      if (failure.response?.status && failure.response.status < 500) persist(null)
      await cart.load()
      throw failure
    }
    persist(null)
    if (auth.user?.id !== buyerId) return
    cart.reset()
    await cart.load()
    const online = order.payments?.at(-1)
    if (
      order.payment_method === 'ZALOPAY' &&
      online?.status === 'PENDING' &&
      online.order_url &&
      !order.reconciliation_required
    )
      paymentApi.redirectToGateway(online.order_url)
    else await router.push(`/orders/${order.id}`)
  })
}
onMounted(load)
</script>
<template>
  <section>
    <h1 class="h3">Đặt hàng</h1>
    <DomainFeedback :busy="busy || cart.busy" :error="error || cart.error" />
    <p v-for="(messages, field) in errors" :key="field" class="text-danger">
      {{ messages.join(' ') }}
    </p>
    <p v-if="attempt" class="alert alert-warning">
      Có yêu cầu chưa nhận được kết quả. Bấm kiểm tra lại để lấy đúng đơn đã tạo; địa chỉ được giữ
      nguyên.
    </p>
    <p v-if="cart.data?.has_unavailable_items" class="alert alert-warning">
      Giỏ có sản phẩm không thể mua. Hãy sửa giỏ trước khi đặt toàn bộ đơn.
    </p>
    <p v-if="cart.data && !cart.data.items.length" class="alert alert-info">Giỏ hàng đang trống.</p>
    <form @submit.prevent="submit">
      <label for="checkout-address" class="form-label">Địa chỉ giao hàng</label>
      <select
        id="checkout-address"
        v-model="addressId"
        class="form-select mb-3"
        :disabled="busy || !!attempt"
        :required="!attempt"
      >
        <option value="">Chọn địa chỉ</option>
        <option v-for="address in addresses" :key="address.id" :value="address.id">
          {{ address.recipient_name }} — {{ address.address_line }}, {{ address.province }}
        </option>
      </select>
      <RouterLink to="/account/addresses">Quản lý địa chỉ</RouterLink>
      <section v-for="group in groups" :key="group.shop?.id" class="card my-3" data-checkout-shop>
        <h2 class="h5 card-header">{{ group.shop?.name }}</h2>
        <div class="card-body">
          <p v-for="item in group.items" :key="item.id">
            {{ item.product?.name }} × {{ item.quantity }} — {{ formatPrice(item.subtotal) }}
          </p>
        </div>
      </section>
      <label for="checkout-payment" class="form-label">Phương thức thanh toán</label>
      <select
        id="checkout-payment"
        v-model="paymentMethod"
        class="form-select mb-3"
        :disabled="busy || !!attempt"
      >
        <option value="COD">Thanh toán khi nhận hàng (COD)</option>
        <option value="ZALOPAY">ZaloPay Online</option>
      </select>
      <template v-if="paymentMethod === 'ZALOPAY'">
        <label for="checkout-channel" class="form-label">Kênh ưu tiên tại ZaloPay</label>
        <select
          id="checkout-channel"
          v-model="channel"
          class="form-select mb-3"
          :disabled="busy || !!attempt"
        >
          <option value="">Tất cả kênh</option>
          <option value="international_card">Thẻ quốc tế</option>
          <option value="domestic">Thẻ / tài khoản ngân hàng nội địa</option>
          <option value="zalopay_wallet">Ví ZaloPay</option>
          <option value="vietqr">VietQR</option>
        </select>
        <p>
          Bạn nhập thông tin thanh toán tại cổng ZaloPay. Cửa hàng không lưu thông tin thẻ hoặc tài
          khoản ngân hàng.
        </p>
      </template>
      <p>Phí giao hàng: 0 ₫. Giảm giá: 0 ₫.</p>
      <p class="h5" data-checkout-total>Tổng tạm tính: {{ formatPrice(cart.data?.subtotal) }}</p>
      <p class="text-secondary">
        Giá và tồn kho được kiểm tra lại khi xác nhận. Đơn hàng lưu giá tại thời điểm đặt.
      </p>
      <button type="submit" class="btn btn-primary" :disabled="!canSubmit">
        {{ attempt ? 'Kiểm tra lại yêu cầu' : 'Xác nhận đặt hàng' }}
      </button>
      <RouterLink to="/cart" class="ms-3">Về giỏ hàng</RouterLink>
    </form>
  </section>
</template>

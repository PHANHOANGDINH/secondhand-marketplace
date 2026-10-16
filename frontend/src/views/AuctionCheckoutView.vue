<script setup>
import { localizeValidationErrors } from '../utils/validationLabels'
import { computed, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { getAuctionCheckout, checkoutAuction } from '../api/auction.api'
import { paymentResultPath, redirectToGateway } from '../api/payment.api'
import { domainError } from '../api/domain.api'
import { formatPrice } from '../composables/productPresentation'
import { checkoutDeadlineLabel } from '../composables/auctionPresentation'
import { useAuctionCountdown } from '../composables/useAuctionCountdown'
import ProductPhoto from '../components/ProductPhoto.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore()
const data = ref(null),
  loading = ref(false),
  busy = ref(false),
  error = ref(''),
  errors = ref({}),
  forbidden = ref(false)
const addressId = ref(''),
  method = ref('COD'),
  attempt = ref(null)
let generation = 0
const storageKey = computed(() => `auction-checkout:${auth.user?.id}:${route.params.id}`)
function persist(value) {
  attempt.value = value
  try {
    if (value) sessionStorage.setItem(storageKey.value, JSON.stringify(value))
    else sessionStorage.removeItem(storageKey.value)
  } catch {
    /* Retry remains possible in memory. */
  }
}
const deadlineClock = computed(() => ({
  status: data.value?.auction.settlement_state === 'AWAITING_CHECKOUT' ? 'LIVE' : 'ENDED',
  ends_at: data.value?.checkout_deadline,
  server_time: data.value?.auction.server_time,
}))
const { remaining, text } = useAuctionCountdown(deadlineClock)
const expired = computed(
  () =>
    data.value?.auction.settlement_state === 'CHECKOUT_EXPIRED' ||
    (data.value?.auction.settlement_state === 'AWAITING_CHECKOUT' && remaining.value <= 0),
)
const canSubmit = computed(
  () =>
    !!data.value &&
    !data.value.winner_order &&
    !busy.value &&
    !loading.value &&
    (!!attempt.value || (!expired.value && data.value.product_available && !!addressId.value)),
)
async function load() {
  const g = ++generation,
    id = route.params.id
  busy.value = false
  data.value = null
  error.value = ''
  errors.value = {}
  forbidden.value = false
  attempt.value = null
  loading.value = true
  try {
    const response = await getAuctionCheckout(id)
    if (g !== generation) return
    data.value = response
    addressId.value =
      (response.addresses.find((a) => a.is_default) || response.addresses[0])?.id || ''
    method.value = 'COD'
    try {
      const saved = JSON.parse(sessionStorage.getItem(storageKey.value) || 'null')
      if (
        saved?.key &&
        saved?.payload?.address_id &&
        response.payment_methods.includes(saved.payload.payment_method)
      ) {
        attempt.value = saved
        addressId.value = saved.payload.address_id
        method.value = saved.payload.payment_method
      }
    } catch {
      /* Ignore unavailable storage. */
    }
  } catch (e) {
    if (g !== generation) return
    forbidden.value = e.response?.status === 403
    error.value = domainError(e)
  } finally {
    if (g === generation) loading.value = false
  }
}
async function submit() {
  if (!canSubmit.value) return
  const g = generation,
    buyerId = auth.user?.id,
    id = route.params.id
  busy.value = true
  error.value = ''
  errors.value = {}
  if (!attempt.value)
    persist({
      key: crypto.randomUUID(),
      payload: { address_id: addressId.value, payment_method: method.value },
    })
  try {
    const order = await checkoutAuction(id, attempt.value.payload, attempt.value.key)
    if (g !== generation || auth.user?.id !== buyerId) return
    persist(null)
    data.value.winner_order = order
    const payment = order.payments?.at(-1)
    if (
      order.payment_method === 'ZALOPAY' &&
      payment?.status === 'PENDING' &&
      payment.order_url &&
      !order.reconciliation_required
    ) {
      if (redirectToGateway(payment.order_url) === 'tab')
        await router.push(paymentResultPath(order.id))
    } else await router.push(`/orders/${order.id}`)
  } catch (e) {
    if (g !== generation) return
    if (e.response?.status && e.response.status < 500) persist(null)
    error.value = domainError(e)
    errors.value = localizeValidationErrors(e.response?.data?.errors)
  } finally {
    if (g === generation) busy.value = false
  }
}
watch(() => [route.params.id, auth.user?.id], load, { immediate: true })
onUnmounted(() => {
  generation++
})
</script>
<template>
  <section class="buyer-flow">
    <RouterLink to="/auctions" class="buyer-back-link">← Sàn đấu giá</RouterLink>
    <PageHeader
      title="Hoàn tất mua hàng"
      eyebrow="Thắng đấu giá"
      description="Mua một sản phẩm với giá thắng được xác nhận bởi máy chủ."
    />
    <DomainFeedback :busy="loading || busy" :error="error" />
    <p v-if="forbidden" class="alert alert-warning" role="status">
      Chỉ người thắng phiên đấu giá mới được hoàn tất mua hàng.
    </p>
    <template v-if="data">
      <section v-if="data.winner_order" class="buyer-panel" data-existing-auction-order>
        <h2>Đơn thắng đấu giá đã được tạo</h2>
        <p>Bạn có thể xem tiến trình, thanh toán hoặc thử lại ZaloPay trong đơn hàng hiện có.</p>
        <RouterLink :to="`/orders/${data.winner_order.id}`" class="btn btn-primary"
          >Xem đơn hàng {{ data.winner_order.order_code }}</RouterLink
        >
      </section>
      <form v-else class="buyer-two-column" @submit.prevent="submit">
        <div class="buyer-checkout-panels">
          <section class="buyer-panel">
            <h2>Bạn đã thắng phiên đấu giá</h2>
            <ProductPhoto
              :src="data.product?.primary_image?.url || data.product?.images?.[0]?.url"
              :alt="data.product?.name"
              style="max-height: 240px; width: 100%"
            />
            <h3 class="h5 mt-3">{{ data.product?.name || data.auction.product?.name }}</h3>
            <p>Số lượng: <strong>1</strong></p>
            <p class="buyer-summary-total">Giá thắng: {{ formatPrice(data.winning_price) }}</p>
            <p>
              Hạn hoàn tất:
              <time :datetime="data.checkout_deadline">{{
                checkoutDeadlineLabel(data.checkout_deadline)
              }}</time>
            </p>
            <p v-if="!expired" role="status">Còn {{ text }}</p>
            <p v-else class="alert alert-warning" data-auction-expired role="status">
              Quyền mua đã hết hạn. Phiên đấu giá không tự chuyển cho người ra giá tiếp theo.
            </p>
            <p v-if="!data.product_available" class="alert alert-warning">
              Sản phẩm hoặc gian hàng hiện không thể giao hàng. Vui lòng kiểm tra lại sau.
            </p>
          </section>
          <section v-if="!expired" class="buyer-panel">
            <h2>Địa chỉ giao hàng</h2>
            <label for="auction-address" class="form-label">Chọn địa chỉ</label>
            <select
              id="auction-address"
              v-model="addressId"
              class="form-select"
              required
              :disabled="busy || !!attempt"
            >
              <option value="">Chọn địa chỉ</option>
              <option v-for="address in data.addresses" :key="address.id" :value="address.id">
                {{ address.recipient_name }} — {{ address.address_line }}, {{ address.province }}
              </option>
            </select>
            <p v-if="errors.address_id" class="text-danger" role="alert">
              {{ errors.address_id.join(' ') }}
            </p>
            <RouterLink to="/account/addresses" class="d-inline-block mt-3"
              >Quản lý địa chỉ</RouterLink
            >
          </section>
        </div>
        <aside class="buyer-panel buyer-sticky-summary">
          <h2>Xác nhận đơn mua</h2>
          <label for="auction-payment" class="form-label">Phương thức thanh toán</label>
          <select
            id="auction-payment"
            v-model="method"
            class="form-select mb-3"
            :disabled="busy || !!attempt || expired"
          >
            <option v-for="payment in data.payment_methods" :key="payment" :value="payment">
              {{
                payment === 'COD' ? 'Thanh toán khi nhận hàng' : 'Thanh toán trực tuyến qua ZaloPay'
              }}
            </option>
          </select>
          <p v-if="method === 'ZALOPAY'" class="small">
            Thông tin thanh toán được nhập tại cổng ZaloPay. Quay lại đơn hàng nếu cần kiểm tra hoặc
            thử thanh toán lại.
          </p>
          <p>Phí giao hàng: 0 ₫. Giảm giá: 0 ₫.</p>
          <p class="buyer-summary-total">Tổng: {{ formatPrice(data.winning_price) }}</p>
          <p v-if="attempt" class="alert alert-warning">
            Yêu cầu trước chưa có kết quả. Kiểm tra lại để lấy đúng đơn, tránh đặt trùng.
          </p>
          <p v-for="(messages, field) in errors" :key="field" class="text-danger" role="alert">
            {{ messages.join(' ') }}
          </p>
          <button class="btn btn-primary w-100" :disabled="!canSubmit" :aria-busy="busy">
            {{ busy ? 'Đang xử lý…' : attempt ? 'Kiểm tra lại yêu cầu' : 'Xác nhận đặt hàng' }}
          </button>
          <button
            v-if="expired || error"
            type="button"
            class="btn btn-outline-secondary w-100 mt-3"
            :disabled="busy"
            @click="load"
          >
            Kiểm tra trạng thái
          </button>
        </aside>
      </form>
    </template>
  </section>
</template>

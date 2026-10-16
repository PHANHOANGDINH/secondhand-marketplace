<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getOrder } from '../api/order.api'
import { queryPayment } from '../api/payment.api'
import { domainError } from '../api/domain.api'
import PaymentAttempts from '../components/PaymentAttempts.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import PageHeader from '../components/ui/PageHeader.vue'
// After ZaloPay sends the buyer back, the status comes only from the server: it asks ZaloPay itself while
// the payment is still pending. The page re-asks every few seconds for about 30 s, then offers a manual check.
const POLL_EVERY_MS = 3000
const POLL_FOR_MS = 30000
const route = useRoute(),
  order = ref(null)
const busy = ref(false),
  error = ref(''),
  // checking: still asking; settled: a final answer; waiting: gave up for now, "Kiểm tra lại" asks again.
  phase = ref('checking')
let revision = 0
const settled = (value) =>
  value.payment_method !== 'ZALOPAY' ||
  value.reconciliation_required ||
  ['PAID', 'FAILED', 'CANCELLED', 'REFUNDED'].includes(value.payment_status)
const pause = (ms, current) =>
  new Promise((resolve) => setTimeout(() => resolve(current === revision), ms))
// ZaloPay may append its own parameters to the redirect ("?order=57?amount=…"): only the leading id counts.
const orderId = () => {
  const match = /^([1-9][0-9]{0,18})(?![0-9])/.exec(
    typeof route.query.order === 'string' ? route.query.order : '',
  )
  return match ? match[1] : null
}
async function poll(id, current) {
  const started = Date.now()
  phase.value = 'checking'
  for (;;) {
    const verified = await queryPayment(id)
    if (current !== revision) return
    order.value = verified
    if (settled(verified)) {
      phase.value = 'settled'
      return
    }
    if (
      Date.now() - started + POLL_EVERY_MS > POLL_FOR_MS ||
      !(await pause(POLL_EVERY_MS, current))
    )
      break
  }
  if (current === revision) phase.value = 'waiting'
}
async function load() {
  const current = ++revision
  order.value = null
  busy.value = true
  error.value = ''
  phase.value = 'checking'
  const id = orderId()
  if (!id) {
    error.value = 'Mã đơn hàng bị thiếu hoặc không hợp lệ. Hãy mở đơn từ danh sách đơn mua.'
    busy.value = false
    phase.value = 'settled'
    return
  }
  try {
    const saved = await getOrder(id)
    if (current !== revision) return
    order.value = saved
    busy.value = false
    if (saved.payment_method === 'ZALOPAY') await poll(id, current)
    else phase.value = 'settled'
  } catch (failure) {
    if (current === revision) {
      error.value = domainError(failure)
      phase.value = 'waiting'
    }
  } finally {
    if (current === revision) busy.value = false
  }
}
async function checkAgain() {
  const id = orderId()
  if (!id || phase.value === 'checking') return
  const current = ++revision
  error.value = ''
  try {
    await poll(id, current)
  } catch (failure) {
    if (current === revision) {
      error.value = domainError(failure)
      phase.value = 'waiting'
    }
  }
}
const checking = computed(() => phase.value === 'checking' && order.value && !settled(order.value))
function updated(verified) {
  if (verified.id === order.value?.id) order.value = verified
}
// Back from the ZaloPay tab (or the phone woke up): ask again at once if the result is still open.
function returned() {
  if (document.visibilityState === 'hidden' || busy.value || phase.value === 'checking') return
  if (order.value && !settled(order.value)) checkAgain()
}
watch(() => route.query.order, load, { immediate: true })
onMounted(() => {
  document.addEventListener('visibilitychange', returned)
  window.addEventListener('focus', returned)
})
onBeforeUnmount(() => {
  revision++
  document.removeEventListener('visibilitychange', returned)
  window.removeEventListener('focus', returned)
})
</script>
<template>
  <section class="buyer-flow buyer-payment-result">
    <PageHeader
      title="Kết quả thanh toán"
      description="Theo dõi kết quả đã được máy chủ xác minh cho đơn mua của bạn."
    />
    <DomainFeedback :busy="busy" :error="error" />
    <RouterLink v-if="!order && !busy" to="/orders">Về đơn mua của tôi</RouterLink>
    <p class="buyer-summary-note">
      Trạng thái được lấy từ máy chủ. Thông tin trên đường dẫn chuyển về không xác nhận thanh toán.
    </p>
    <template v-if="order"
      ><div
        class="buyer-result-hero"
        :class="{
          'buyer-result-success': order.payment_status === 'PAID' && !order.reconciliation_required,
          'buyer-result-failure':
            ['FAILED', 'CANCELLED'].includes(order.payment_status) &&
            !order.reconciliation_required,
        }"
        data-verified-result
      >
        <span class="buyer-result-icon" aria-hidden="true">{{
          order.reconciliation_required
            ? '?'
            : order.payment_status === 'PAID'
              ? '✓'
              : ['FAILED', 'CANCELLED'].includes(order.payment_status)
                ? '!'
                : '…'
        }}</span>
        <h2 v-if="checking" role="status">Đang xác nhận thanh toán…</h2>
        <h2 v-else-if="order.reconciliation_required">Giao dịch cần đối soát</h2>
        <h2 v-else-if="order.payment_status === 'PAID'">Đã thanh toán</h2>
        <h2 v-else-if="order.payment_status === 'FAILED'">Thanh toán không thành công</h2>
        <h2 v-else-if="order.payment_status === 'CANCELLED'">Thanh toán đã hủy</h2>
        <h2 v-else-if="order.payment_status === 'REFUNDED'">Đã hoàn tiền</h2>
        <h2 v-else-if="order.payment_method === 'COD'">Đơn hàng đã được tạo</h2>
        <h2 v-else>Đang chờ kết quả thanh toán</h2>
        <p>Đơn {{ order.order_code }}</p>
        <p v-if="checking" class="small mb-0">Đang hỏi ZaloPay, vui lòng đợi trong giây lát.</p>
        <template v-if="phase === 'waiting' && !settled(order)">
          <p class="small">
            ZaloPay chưa trả kết quả. Nếu bạn đang thanh toán ở thẻ ZaloPay, hoàn tất rồi quay lại
            đây: trang sẽ tự kiểm tra lại.
          </p>
          <button
            type="button"
            class="btn btn-outline-primary"
            data-check-again
            @click="checkAgain"
          >
            Kiểm tra lại
          </button>
        </template>
      </div>
      <PaymentAttempts :key="order.id" :order="order" @changed="updated" />
      <div class="d-flex flex-wrap gap-2">
        <RouterLink :to="`/orders/${order.id}`" class="btn btn-primary">Xem đơn hàng</RouterLink
        ><RouterLink to="/products" class="btn btn-outline-secondary">Tiếp tục khám phá</RouterLink>
      </div>
    </template>
  </section>
</template>

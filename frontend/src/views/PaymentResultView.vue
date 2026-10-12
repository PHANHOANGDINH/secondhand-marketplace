<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getOrder } from '../api/order.api'
import { queryPayment } from '../api/payment.api'
import { domainError } from '../api/domain.api'
import PaymentAttempts from '../components/PaymentAttempts.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import PageHeader from '../components/ui/PageHeader.vue'
const route = useRoute(),
  order = ref(null)
const busy = ref(false),
  error = ref('')
let revision = 0
async function load() {
  const current = ++revision
  order.value = null
  busy.value = true
  error.value = ''
  const id = route.query.order
  if (typeof id !== 'string' || !/^[1-9][0-9]*$/.test(id)) {
    error.value = 'Mã đơn hàng bị thiếu hoặc không hợp lệ. Hãy mở đơn từ danh sách đơn mua.'
    busy.value = false
    return
  }
  try {
    const saved = await getOrder(id)
    if (current !== revision) return
    order.value = saved
    if (saved.payment_method === 'ZALOPAY') {
      const verified = await queryPayment(id)
      if (current === revision) order.value = verified
    }
  } catch (failure) {
    if (current === revision) error.value = domainError(failure)
  } finally {
    if (current === revision) busy.value = false
  }
}
function updated(verified) {
  if (verified.id === order.value?.id) order.value = verified
}
watch(() => route.query.order, load, { immediate: true })
onBeforeUnmount(() => revision++)
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
        <h2 v-if="order.reconciliation_required">Giao dịch cần đối soát</h2>
        <h2 v-else-if="order.payment_status === 'PAID'">Đã thanh toán</h2>
        <h2 v-else-if="order.payment_status === 'FAILED'">Thanh toán không thành công</h2>
        <h2 v-else-if="order.payment_status === 'CANCELLED'">Thanh toán đã hủy</h2>
        <h2 v-else-if="order.payment_status === 'REFUNDED'">Đã hoàn tiền</h2>
        <h2 v-else-if="order.payment_method === 'COD'">Đơn hàng đã được tạo</h2>
        <h2 v-else>Đang chờ kết quả thanh toán</h2>
        <p>Đơn {{ order.order_code }}</p>
      </div>
      <PaymentAttempts :key="order.id" :order="order" @changed="updated" />
      <div class="d-flex flex-wrap gap-2">
        <RouterLink :to="`/orders/${order.id}`" class="btn btn-primary">Xem đơn hàng</RouterLink
        ><RouterLink to="/products" class="btn btn-outline-secondary">Tiếp tục khám phá</RouterLink>
      </div>
    </template>
  </section>
</template>

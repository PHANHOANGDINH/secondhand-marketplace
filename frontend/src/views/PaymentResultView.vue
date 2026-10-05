<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getOrder } from '../api/order.api'
import { queryPayment } from '../api/payment.api'
import { domainError } from '../api/domain.api'
import PaymentAttempts from '../components/PaymentAttempts.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
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
  <section>
    <h1 class="h3">Kết quả thanh toán</h1>
    <DomainFeedback :busy="busy" :error="error" />
    <RouterLink v-if="!order && !busy" to="/orders">Về đơn mua của tôi</RouterLink>
    <p>
      Trạng thái được lấy từ máy chủ. Thông tin trên đường dẫn chuyển về không xác nhận thanh toán.
    </p>
    <template v-if="order"
      ><p>Đơn {{ order.order_code }}</p>
      <PaymentAttempts :key="order.id" :order="order" @changed="updated" />
      <RouterLink :to="`/orders/${order.id}`">Xem đơn hàng</RouterLink>
    </template>
  </section>
</template>

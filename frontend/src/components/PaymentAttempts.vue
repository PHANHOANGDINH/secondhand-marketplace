<script setup>
import { getStatusLabel } from '../utils/labels'
import { computed, ref } from 'vue'
import * as api from '../api/payment.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import DomainFeedback from './DomainFeedback.vue'
const props = defineProps({ order: { type: Object, required: true } })
const emit = defineEmits(['changed'])
const { busy, error, run } = useFeedback()
const retryKey = ref(null)
const latest = computed(() => props.order.payments?.at(-1))
const url = computed(
  () =>
    !props.order.reconciliation_required &&
    latest.value?.status === 'PENDING' &&
    api.gatewayUrl(latest.value?.order_url),
)
const query = () => run(async () => emit('changed', await api.queryPayment(props.order.id)))
async function retry() {
  await run(async () => {
    retryKey.value ||= crypto.randomUUID()
    const order = await api.retryPayment(props.order.id, retryKey.value)
    retryKey.value = null
    emit('changed', order)
    const payment = order.payments?.at(-1)
    if (payment?.status === 'PENDING' && payment.order_url && !order.reconciliation_required)
      api.redirectToGateway(payment.order_url)
  })
}
</script>
<template>
  <section v-if="order.payment_method === 'ZALOPAY'" class="card my-3" data-payments>
    <div class="card-body">
      <h2 class="h5">Thanh toán trực tuyến qua ZaloPay</h2>
      <DomainFeedback :busy="busy" :error="error" />
      <p v-if="order.reconciliation_required" class="alert alert-warning">
        Giao dịch cần đối soát. Không thanh toán thêm; vui lòng liên hệ hỗ trợ.
      </p>
      <p v-for="payment in order.payments" :key="payment.id" data-payment-attempt>
        Lần {{ payment.attempt_number }}: {{ getStatusLabel(payment.status, 'payment') }} —
        {{ formatPrice(payment.amount) }}
      </p>
      <p v-if="latest?.status === 'PENDING'" class="text-secondary">
        Chỉ trạng thái đã được máy chủ xác minh mới xác nhận thanh toán. Nếu chưa rõ kết quả, kiểm
        tra trước khi thử lại.
      </p>
      <a v-if="url" :href="url" class="btn btn-primary me-2" rel="noreferrer" data-resume-payment
        >Tiếp tục tại ZaloPay</a
      >
      <button
        class="btn btn-outline-secondary me-2"
        :disabled="busy"
        data-query-payment
        @click="query"
      >
        Kiểm tra thanh toán
      </button>
      <button
        v-if="order.can_retry_payment && !order.reconciliation_required"
        class="btn btn-primary"
        :disabled="busy"
        data-retry-payment
        @click="retry"
      >
        Thử thanh toán lại
      </button>
    </div>
  </section>
</template>

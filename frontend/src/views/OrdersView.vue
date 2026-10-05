<script setup>
import { onMounted, ref, watch } from 'vue'
import * as api from '../api/order.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import { orderStatuses } from '../composables/orderPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
const props = defineProps({ seller: Boolean })
const result = ref(null),
  status = ref('')
const { busy, error, run } = useFeedback()
async function load(page = 1) {
  await run(async () => {
    result.value = await (props.seller ? api.listSellerOrders : api.listOrders)({
      page,
      per_page: 20,
      ...(props.seller && status.value ? { status: status.value } : {}),
    })
  })
}
watch(
  () => props.seller,
  () => {
    result.value = null
    status.value = ''
    load()
  },
)
onMounted(() => load())
</script>
<template>
  <section>
    <h1 class="h3">{{ seller ? 'Đơn hàng của gian hàng' : 'Đơn mua của tôi' }}</h1>
    <DomainFeedback :busy="busy" :error="error" />
    <form v-if="seller" class="d-flex gap-2 my-3" @submit.prevent="load()">
      <select v-model="status" aria-label="Trạng thái đơn" class="form-select">
        <option value="">Mọi trạng thái</option>
        <option
          v-for="key in [
            'PENDING',
            'CONFIRMED',
            'PROCESSING',
            'SHIPPING',
            'COMPLETED',
            'CANCELLED',
          ]"
          :key="key"
          :value="key"
        >
          {{ orderStatuses[key] }}
        </option></select
      ><button class="btn btn-outline-primary" :disabled="busy">Lọc</button>
    </form>
    <p v-if="result && !result.data.length" class="alert alert-info">Chưa có đơn hàng.</p>
    <article v-for="order in result?.data || []" :key="order.id" class="card mb-3">
      <div class="card-body">
        <RouterLink :to="`${seller ? '/seller' : ''}/orders/${order.id}`">{{
          order.order_code
        }}</RouterLink>
        <p>
          {{ orderStatuses[order.status || order.overall_status] }} —
          {{ formatPrice(order.total_amount) }}
        </p>
        <p v-if="seller">{{ order.shop.name }}</p>
        <p v-else>{{ order.shop_orders.length }} gian hàng</p>
      </div>
    </article>
    <CollectionPager :meta="result?.meta" :busy="busy" @page="load" />
  </section>
</template>

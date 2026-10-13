<script setup>
import { useSellerIdentity } from '../components/seller/useSellerIdentity'
import { useRoute } from 'vue-router'
import { onMounted, ref, watch } from 'vue'
import * as api from '../api/order.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import { orderStatuses } from '../composables/orderPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
const route = useRoute() || { query: {}, fullPath: '' }
const actionRequired = ref(route.query.action_required === '1')
const props = defineProps({ seller: Boolean })
const result = ref(null),
  status = ref(typeof route.query.status === 'string' ? route.query.status : '')
// Buyer tabs use the list endpoint's existing overall_status filter.
const overallStatus = ref('')
const buyerTabs = ['', 'PENDING', 'IN_PROGRESS', 'COMPLETED', 'PARTIALLY_COMPLETED', 'CANCELLED']
function chooseTab(value) {
  overallStatus.value = value
  load()
}
const { busy, error, run } = useFeedback()
async function load(page = 1) {
  await run(async () => {
    result.value = await (props.seller ? api.listSellerOrders : api.listOrders)({
      page,
      per_page: 20,
      ...(props.seller && actionRequired.value ? { action_required: 1 } : {}),
      ...(props.seller && status.value ? { status: status.value } : {}),
      ...(!props.seller && overallStatus.value ? { overall_status: overallStatus.value } : {}),
    })
  })
}
watch(
  () => props.seller,
  () => {
    result.value = null
    status.value = ''
    overallStatus.value = ''
    load()
  },
)
watch(
  () => route.fullPath,
  () => {
    if (props.seller) {
      actionRequired.value = route.query.action_required === '1'
      status.value = typeof route.query.status === 'string' ? route.query.status : ''
      load()
    }
  },
)
onMounted(() => load())
useSellerIdentity(() => (props.seller ? result.value?.data[0]?.shop : null))
</script>
<template>
  <section :class="{ 'buyer-flow': !seller }">
    <PageHeader
      v-if="seller"
      title="Đơn hàng của gian hàng"
      eyebrow="Không gian người bán"
      description="Theo dõi đơn mới và quản lý từng bước giao hàng."
    />
    <PageHeader
      v-else
      title="Đơn mua của tôi"
      description="Theo dõi hành trình của những món đồ bạn đã chọn."
      ><RouterLink to="/products" class="btn btn-outline-primary"
        >Khám phá sản phẩm</RouterLink
      ></PageHeader
    >
    <DomainFeedback :busy="busy" :error="error" />
    <button v-if="error" class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load()">
      {{ seller ? 'Tải lại đơn bán' : 'Tải lại đơn mua' }}
    </button>
    <div v-if="!seller" class="order-tabs" role="group" aria-label="Lọc đơn theo trạng thái">
      <button
        v-for="tab in buyerTabs"
        :key="tab"
        type="button"
        class="order-tab"
        :class="{ 'is-active': overallStatus === tab }"
        :aria-pressed="overallStatus === tab"
        :disabled="busy"
        @click="chooseTab(tab)"
      >
        {{ tab ? orderStatuses[tab] : 'Tất cả' }}
      </button>
    </div>
    <label v-if="seller" class="d-flex gap-2 align-items-center mt-3"
      ><input v-model="actionRequired" type="checkbox" @change="load()" /> Chỉ đơn cần xử lý</label
    >
    <form v-if="seller" class="d-flex gap-2 my-3 seller-toolbar" @submit.prevent="load()">
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
      ><button class="btn btn-outline-secondary" :disabled="busy">Lọc</button>
    </form>
    <template v-if="result && !result.data.length && !busy && !error"
      ><EmptyState
        v-if="seller"
        title="Chưa có đơn hàng."
        description="Đơn bán sẽ xuất hiện khi người mua đặt hàng tại gian hàng." />
      <EmptyState
        v-else
        title="Chưa có đơn hàng."
        description="Các đơn mua của bạn sẽ xuất hiện ở đây sau khi đặt hàng."
    /></template>
    <article
      v-for="order in result?.data || []"
      :key="order.id"
      class="card mb-3"
      :class="{ 'buyer-order-card': !seller, 'seller-order-card': seller }"
    >
      <div class="card-body">
        <div>
          <RouterLink :to="`${seller ? '/seller' : ''}/orders/${order.id}`">{{
            order.order_code
          }}</RouterLink>
          <p v-if="seller">
            <template v-if="order.buyer?.name">{{ order.buyer.name }}</template>
            <template v-else-if="order.shipping_address?.recipient_name"
              >Người nhận: {{ order.shipping_address.recipient_name }}</template
            >
            <template v-else>{{ order.shop.name }}</template
            ><template v-if="order.created_at">
              &middot; {{ new Date(order.created_at).toLocaleDateString('vi-VN') }}</template
            >
          </p>
          <p v-else>{{ order.shop_orders.length }} gian hàng</p>
        </div>
        <div v-if="seller">
          <StatusBadge :status="order.status" :label="orderStatuses[order.status]" />
          <p class="seller-order-value">{{ formatPrice(order.total_amount) }}</p>
        </div>
        <div v-else>
          <StatusBadge
            :status="order.status || order.overall_status"
            :label="orderStatuses[order.status || order.overall_status]"
          />
          <p class="buyer-order-price">{{ formatPrice(order.total_amount) }}</p>
        </div>
      </div>
    </article>
    <CollectionPager :meta="result?.meta" :busy="busy" @page="load" />
  </section>
</template>

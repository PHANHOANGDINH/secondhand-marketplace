<script setup>
import { formatCancellationReason, getPaymentMethodLabel } from '../utils/labels'
import { useSellerIdentity } from '../components/seller/useSellerIdentity'
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import * as api from '../api/order.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import { orderStatuses, nextOrderStatus, paymentStatuses } from '../composables/orderPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
import OrderItems from '../components/OrderItems.vue'
import PaymentAttempts from '../components/PaymentAttempts.vue'
import OrderShipping from '../components/OrderShipping.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
const props = defineProps({ seller: Boolean }),
  route = useRoute()
const order = ref(null),
  reasons = reactive({}),
  categories = reactive({}),
  collected = ref(false)
const { busy, error, errors, success, run } = useFeedback()
const parts = computed(() =>
  order.value ? (props.seller ? [order.value] : order.value.shop_orders) : [],
)
async function read() {
  order.value = await (props.seller ? api.getSellerOrder : api.getOrder)(route.params.id)
}
const load = () => run(read)
function reviewed(id, review) {
  for (const part of parts.value) {
    const item = part.items.find((item) => item.id === id)
    if (item) {
      item.review = review
      item.can_review = false
      item.can_edit_review = true
    }
  }
}
function canCancel(part) {
  if (part.can_cancel_payment === false) return false
  return props.seller
    ? part.can_update && ['PENDING', 'CONFIRMED', 'PROCESSING'].includes(part.status)
    : ['PENDING', 'CONFIRMED'].includes(part.status)
}
async function action(part, target) {
  await run(async () => {
    try {
      if (props.seller) {
        order.value = await api.updateSellerOrder(part.id, {
          expected_status: part.status,
          status: target,
          ...(target === 'CANCELLED'
            ? { reason: reasons[part.id], failure_category: categories[part.id] || 'UNFULFILLABLE' }
            : {}),
          ...(target === 'COMPLETED' && part.payment_method !== 'ZALOPAY'
            ? { cod_collected: collected.value }
            : {}),
        })
      } else {
        order.value = await api.cancelShopOrder(order.value.id, part.id, {
          expected_status: part.status,
          reason: reasons[part.id],
        })
      }
      collected.value = false
    } catch (failure) {
      if (failure.response?.status === 409) {
        try {
          await read()
        } catch {
          /* Preserve original conflict. */
        }
      }
      throw failure
    }
  }, 'Đã cập nhật đơn hàng.')
}
watch(
  () => [route.params.id, props.seller],
  () => {
    order.value = null
    load()
  },
)
onMounted(load)
useSellerIdentity(() => (props.seller ? order.value?.shop : null))
</script>
<template>
  <section :class="{ 'buyer-flow': !seller }">
    <PageHeader
      v-if="seller"
      :title="`Chi tiết đơn ${order?.order_code || ''}`"
      eyebrow="Không gian người bán"
      description="Thông tin giao hàng, sản phẩm và các bước xử lý đơn."
      ><RouterLink to="/seller/orders" class="btn btn-outline-secondary"
        >← Đơn bán</RouterLink
      ></PageHeader
    >
    <PageHeader
      v-else
      title="Chi tiết đơn mua"
      :description="
        order
          ? `Mã đơn: ${order.order_code}`
          : 'Thông tin giao hàng, thanh toán và sản phẩm trong đơn mua.'
      "
      ><RouterLink to="/orders" class="btn btn-outline-secondary"
        >← Đơn mua của tôi</RouterLink
      ></PageHeader
    >
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <p v-for="(messages, field) in errors" :key="field" class="text-danger">
      {{ messages.join(' ') }}
    </p>
    <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load">Tải lại</button>
    <template v-if="order"
      ><OrderShipping :address="order.shipping_address" />
      <div v-if="!seller" class="buyer-order-overview">
        <p>
          <StatusBadge
            :status="order.overall_status"
            :label="orderStatuses[order.overall_status]"
          />
          {{ getPaymentMethodLabel(order.payment_method || 'COD') }}:
          <StatusBadge
            :status="order.payment_status"
            :label="paymentStatuses[order.payment_status]"
          />
        </p>
        <p>
          Tổng đơn gốc: {{ formatPrice(order.total_amount) }} · Còn phải thu:
          {{ formatPrice(order.amount_due) }}
          <template v-if="order.payment_method !== 'ZALOPAY'">
            · Đã thu: {{ formatPrice(order.cod_collected_amount) }}
          </template>
        </p>
      </div>
      <PaymentAttempts v-if="!seller" :order="order" @changed="order = $event" />
      <section v-for="part in parts" :key="part.id" class="card mb-3" :data-shop-order="part.id">
        <div class="card-body">
          <h2 class="h5">{{ part.shop.name }}</h2>
          <p v-if="seller">
            <StatusBadge :status="part.status" :label="orderStatuses[part.status]" />
            <strong class="ms-2">{{ formatPrice(part.total_amount) }}</strong>
          </p>
          <p v-else>
            <StatusBadge :status="part.status" :label="orderStatuses[part.status]" />
            <strong class="ms-2">{{ formatPrice(part.total_amount) }}</strong>
          </p>
          <p v-if="part.cancellation_reason">Lý do hủy: {{ formatCancellationReason(part.cancellation_reason) }}</p>
          <OrderItems
            :items="part.items"
            :allow-reviews="!seller"
            :seller="seller"
            @reviewed="reviewed"
          />
          <div
            v-if="
              seller &&
              part.can_update &&
              part.payment_ready !== false &&
              nextOrderStatus[part.status]
            "
            class="my-3 seller-next-action"
          >
            <label
              v-if="part.status === 'SHIPPING' && part.payment_method !== 'ZALOPAY'"
              class="d-block mb-2"
              ><input v-model="collected" type="checkbox" data-cod-collected /> Đã giao và thu đủ
              {{ formatPrice(part.total_amount) }} khi nhận hàng</label
            >
            <button
              class="btn btn-primary"
              data-next-status
              :disabled="
                busy ||
                (part.status === 'SHIPPING' && part.payment_method !== 'ZALOPAY' && !collected)
              "
              @click="action(part, nextOrderStatus[part.status])"
            >
              Chuyển sang {{ orderStatuses[nextOrderStatus[part.status]] }}
            </button>
          </div>
          <form v-if="canCancel(part)" class="mt-3" @submit.prevent="action(part, 'CANCELLED')">
            <label :for="`reason-${part.id}`" class="form-label">Lý do hủy</label
            ><input
              :id="`reason-${part.id}`"
              v-model="reasons[part.id]"
              name="reason"
              required
              maxlength="900"
              class="form-control mb-2"
            /><select
              v-if="seller"
              v-model="categories[part.id]"
              aria-label="Loại lý do hủy"
              class="form-select mb-2"
            >
              <option :value="undefined">Không giao được</option>
              <option value="OUT_OF_STOCK">Hết hàng</option>
              <option value="DAMAGED">Hư hỏng</option>
              <option value="UNFULFILLABLE">Không giao được</option></select
            ><button class="btn btn-outline-danger" :disabled="busy">Hủy phần đơn này</button>
          </form>
        </div>
      </section></template
    >
  </section>
</template>

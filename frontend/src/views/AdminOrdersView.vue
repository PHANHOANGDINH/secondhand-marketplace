<script setup>
import { getPaymentMethodLabel } from '../utils/labels'
import AuctionOrderSource from '../components/auction/AuctionOrderSource.vue'
import AdminRecordHeader from '../components/admin/AdminRecordHeader.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'

import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import * as api from '../api/admin.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import { orderStatuses } from '../composables/orderPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
const auth = useAuthStore(),
  route = useRoute(),
  rows = ref([]),
  meta = ref(null),
  selected = ref(null)
const filters = reactive({ overall_status: '', shop_order_status: '', buyer: '', from: '', to: '' })
const { busy, error, errors, run } = useFeedback()
const overall = ['PENDING', 'IN_PROGRESS', 'COMPLETED', 'PARTIALLY_COMPLETED', 'CANCELLED'],
  parts = ['PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPING', 'COMPLETED', 'CANCELLED']
const load = (page = 1) =>
  run(async () => {
    rows.value = []
    meta.value = null
    if (!auth.isAdmin) return
    const actor = auth.user.id
    const params = { page, per_page: 20 }
    for (const [key, value] of Object.entries(filters)) if (value !== '') params[key] = value
    const result = await api.listAdminOrders(params)
    if (auth.isAdmin && auth.user.id === actor) {
      rows.value = result.data
      meta.value = result.meta
    }
  })
const show = (id) =>
  run(async () => {
    selected.value = null
    if (!auth.isAdmin) return
    const actor = auth.user.id
    const result = await api.getAdminOrder(id)
    if (auth.isAdmin && auth.user.id === actor) selected.value = result
  })
onMounted(async () => {
  await load()
  if (/^\d+$/.test(String(route.query.order || ''))) await show(String(route.query.order))
})
</script>
<template>
  <section v-if="auth.isAdmin">
    <PageHeader
      title="Tổng quan đơn hàng"
      eyebrow="Quản trị · Sàn mua bán"
      description="Tra cứu lịch sử mua bán và xem chi tiết từng gian hàng trong đơn."
    />
    <p class="text-secondary">
      Chỉ xem thông tin đã ghi nhận khi đặt hàng. Bộ lọc ngày áp dụng ngày tạo đơn theo giờ Việt
      Nam, tối đa 366 ngày.
    </p>
    <form class="row g-2 align-items-end my-3 admin-toolbar" @submit.prevent="load()">
      <label class="col-sm-6 col-xl-3"
        >Trạng thái toàn đơn<select
          v-model="filters.overall_status"
          name="overall_status"
          class="form-select"
        >
          <option value="">Tất cả</option>
          <option v-for="s in overall" :key="s" :value="s">{{ orderStatuses[s] }}</option>
        </select></label
      >
      <label class="col-sm-6 col-xl-3"
        >Trạng thái theo gian hàng<select
          v-model="filters.shop_order_status"
          name="shop_order_status"
          class="form-select"
        >
          <option value="">Tất cả</option>
          <option v-for="s in parts" :key="s" :value="s">{{ orderStatuses[s] }}</option>
        </select></label
      >
      <label class="col-sm-6 col-xl-2"
        >Mã người mua<input
          v-model="filters.buyer"
          name="buyer"
          type="number"
          min="1"
          class="form-control" /></label
      ><label class="col-sm-6 col-xl-2"
        >Từ ngày<input
          v-model="filters.from"
          name="from"
          type="date"
          :required="!!filters.to"
          min="2000-01-01"
          max="9998-12-31"
          class="form-control" /></label
      ><label class="col-sm-6 col-xl-2"
        >Đến ngày<input
          v-model="filters.to"
          name="to"
          type="date"
          :required="!!filters.from"
          :min="filters.from || '2000-01-01'"
          max="9998-12-31"
          class="form-control"
      /></label>
      <div><button class="btn btn-outline-secondary" :disabled="busy">Lọc / Tải lại</button></div>
    </form>
    <DomainFeedback :busy="busy" :error="error" />
    <p v-for="(messages, key) in errors" :key="key" class="text-danger">{{ messages.join(' ') }}</p>
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Chưa có đơn hàng phù hợp."
      description="Điều chỉnh bộ lọc để xem đơn hàng khác."
    />
    <div v-if="rows.length" class="table-responsive admin-table-wrap">
      <table class="table admin-table">
        <thead>
          <tr>
            <th scope="col">Mã đơn / ngày tạo</th>
            <th scope="col">Người mua</th>
            <th scope="col">Trạng thái</th>
            <th scope="col">Giá trị đơn</th>
            <th scope="col">Chi tiết</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="order in rows"
            :key="order.id"
            :data-order="order.id"
            :class="{ 'is-selected': selected?.id === order.id }"
          >
            <td>
              <strong>{{ order.order_code }}</strong
              ><small v-if="order.created_at" class="d-block">{{
                new Date(order.created_at).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' })
              }}</small
              ><small v-if="order.shop_orders" class="d-block"
                >{{ order.shop_orders.length }} gian hàng</small
              >
            </td>
            <td data-label="Người mua">{{ order.buyer.name }}</td>
            <td data-label="Trạng thái">
              <StatusBadge
                :status="order.overall_status"
                :label="orderStatuses[order.overall_status]"
              />
            </td>
            <td data-label="Giá trị đơn">{{ formatPrice(order.total_amount) }}</td>
            <td>
              <button
                class="btn btn-outline-primary btn-sm"
                :disabled="busy"
                :aria-expanded="selected?.id === order.id"
                @click="show(order.id)"
              >
                Xem
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
    <section v-if="selected" class="admin-panel mt-4" data-order-detail>
      <AdminRecordHeader :title="selected.order_code" />
      <div class="admin-order-summary">
        <div>
          <p class="mb-2">Người mua: {{ selected.buyer.name }}</p>
          <StatusBadge
            :status="selected.overall_status"
            :label="orderStatuses[selected.overall_status]"
          />
        </div>
        <div>
          <small class="text-secondary">Tổng đơn</small
          ><strong>{{ formatPrice(selected.total_amount) }}</strong>
        </div>
      </div>
      <h3 class="h6">Đơn theo gian hàng · {{ selected.shop_orders.length }} gian hàng</h3>
      <p class="text-secondary">
        Mỗi gian hàng xử lý phần đơn của mình; trạng thái toàn đơn tổng hợp các phần này.
      </p>
      <article v-for="part in selected.shop_orders" :key="part.id" class="admin-shop-order">
        <h3>
          {{ part.shop.name }}
          <span v-if="part.shop.archived" class="text-secondary">(Đã lưu trữ)</span>
        </h3>
        <p>
          <StatusBadge :status="part.status" :label="orderStatuses[part.status]" /><strong
            class="ms-2"
            >{{ formatPrice(part.total_amount) }}</strong
          >
        </p>
        <h4 class="h6">Sản phẩm đã đặt</h4>
        <ul>
          <li v-for="item in part.items || []" :key="item.id">
            <AuctionOrderSource :auction-id="item.auction_id" admin />
            <strong>{{ item.product_name }}</strong
            ><br />{{ item.quantity }} × {{ formatPrice(item.product_price) }} =
            {{ formatPrice(item.subtotal) }}
          </li>
        </ul>
        <p v-if="!part.items?.length" class="text-secondary">
          Không có sản phẩm trong phần đơn này.
        </p>
        <p v-if="part.completed_at" class="text-secondary">
          Hoàn tất:
          {{
            new Date(part.completed_at).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' })
          }}
        </p>
      </article>
      <section class="admin-shop-order">
        <h3>Thanh toán</h3>
        <p v-if="selected.payment_method">Phương thức: {{ getPaymentMethodLabel(selected.payment_method) }}</p>
        <p v-if="selected.payment_status">
          Trạng thái: <StatusBadge :status="selected.payment_status" domain="payment" />
        </p>
        <template v-if="selected.payments?.length"
          ><p v-for="payment in selected.payments" :key="payment.id">
            <StatusBadge :status="payment.status" domain="payment" /> {{ getPaymentMethodLabel(payment.method || payment.provider)
            }}<span v-if="payment.amount"> · {{ formatPrice(payment.amount) }}</span>
          </p></template
        >
        <p v-else class="text-secondary mb-0">
          Chi tiết giao dịch thanh toán không được cung cấp trong dữ liệu quản trị hiện tại.
        </p>
      </section>
      <button class="btn btn-outline-secondary mt-2" :disabled="busy" @click="show(selected.id)">
        Tải lại chi tiết
      </button>
    </section>
  </section>
</template>

<script setup>
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
    <h1 class="h3">Tổng quan đơn hàng</h1>
    <p class="text-secondary">
      Chỉ xem lịch sử và snapshot; không thay đổi quy trình xử lý của gian hàng. Mặc định toàn bộ
      thời gian; bộ lọc ngày áp dụng ngày tạo đơn theo giờ Việt Nam (tối đa366 ngày).
    </p>
    <form class="row g-2 align-items-end my-3" @submit.prevent="load()">
      <label class="col-md-3"
        >Trạng thái toàn đơn<select
          v-model="filters.overall_status"
          name="overall_status"
          class="form-select"
        >
          <option value="">Tất cả</option>
          <option v-for="s in overall" :key="s" :value="s">{{ orderStatuses[s] }}</option>
        </select></label
      ><label class="col-md-3"
        >Trạng thái phần đơn<select
          v-model="filters.shop_order_status"
          name="shop_order_status"
          class="form-select"
        >
          <option value="">Tất cả</option>
          <option v-for="s in parts" :key="s" :value="s">{{ orderStatuses[s] }}</option>
        </select></label
      ><label class="col-md-2"
        >ID người mua<input
          v-model="filters.buyer"
          name="buyer"
          type="number"
          min="1"
          class="form-control" /></label
      ><label class="col-md-2"
        >Từ ngày<input
          v-model="filters.from"
          name="from"
          type="date"
          :required="!!filters.to"
          min="2000-01-01"
          max="9998-12-31"
          class="form-control" /></label
      ><label class="col-md-2"
        >Đến ngày<input
          v-model="filters.to"
          name="to"
          type="date"
          :required="!!filters.from"
          :min="filters.from || '2000-01-01'"
          max="9998-12-31"
          class="form-control"
      /></label>
      <div><button class="btn btn-primary" :disabled="busy">Lọc / Tải lại</button></div>
    </form>
    <DomainFeedback :busy="busy" :error="error" />
    <p v-for="(messages, key) in errors" :key="key" class="text-danger">{{ messages.join(' ') }}</p>
    <p v-if="!busy && !error && !rows.length">Chưa có đơn hàng phù hợp.</p>
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>Mã đơn</th>
            <th>Người mua</th>
            <th>Trạng thái</th>
            <th>Tổng snapshot</th>
            <th>Chi tiết</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in rows" :key="order.id" :data-order="order.id">
            <td>{{ order.order_code }}</td>
            <td>{{ order.buyer.name }}</td>
            <td>{{ orderStatuses[order.overall_status] }}</td>
            <td>{{ formatPrice(order.total_amount) }}</td>
            <td>
              <button
                class="btn btn-outline-primary btn-sm"
                :disabled="busy"
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
    <section v-if="selected" class="card card-body mt-3" data-order-detail>
      <h2 class="h5">{{ selected.order_code }} · {{ orderStatuses[selected.overall_status] }}</h2>
      <p>
        Người mua: {{ selected.buyer.name }} · Tổng snapshot:
        {{ formatPrice(selected.total_amount) }}
      </p>
      <article v-for="part in selected.shop_orders" :key="part.id" class="border rounded p-3 my-2">
        <h3 class="h6">{{ part.shop.name }} <span v-if="part.shop.archived">(Đã lưu trữ)</span></h3>
        <p>{{ orderStatuses[part.status] }} · {{ formatPrice(part.total_amount) }}</p>
        <ul>
          <li v-for="item in part.items || []" :key="item.id">
            {{ item.product_name }} · {{ item.quantity }} × {{ formatPrice(item.product_price) }} =
            {{ formatPrice(item.subtotal) }}
          </li>
        </ul>
        <p v-if="part.completed_at">
          Hoàn tất:
          {{
            new Date(part.completed_at).toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' })
          }}
        </p>
      </article>
      <button
        class="btn btn-outline-secondary align-self-start"
        :disabled="busy"
        @click="show(selected.id)"
      >
        Tải lại chi tiết
      </button>
    </section>
  </section>
</template>

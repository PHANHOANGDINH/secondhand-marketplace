<script setup>
import { onMounted, ref } from 'vue'
import { getAdminDashboard } from '../api/admin.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import { orderStatuses } from '../composables/orderPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
const auth = useAuthStore(),
  data = ref(null)
const { busy, error, run } = useFeedback()
const metrics = [
  ['total_users', 'Tổng người dùng'],
  ['active_users', 'Người dùng hoạt động'],
  ['total_shops', 'Tổng gian hàng'],
  ['active_shops', 'Gian hàng hoạt động'],
  ['pending_shops', 'Gian hàng chờ duyệt'],
  ['total_products', 'Tổng sản phẩm'],
  ['active_products', 'Sản phẩm ACTIVE'],
  ['pending_products', 'Sản phẩm chờ duyệt'],
  ['total_orders', 'Tổng đơn mua'],
  ['completed_orders', 'Đơn hoàn tất toàn bộ'],
  ['cancelled_orders', 'Đơn hủy toàn bộ'],
]
const load = () =>
  run(async () => {
    data.value = null
    if (!auth.isAdmin) return
    const actor = auth.user.id
    const result = await getAdminDashboard()
    if (auth.isAdmin && auth.user.id === actor) data.value = result
  })
onMounted(load)
</script>
<template>
  <section v-if="auth.isAdmin">
    <h1 class="h3">Tổng quan quản trị</h1>
    <p class="text-secondary">
      Số liệu toàn thời gian. Người dùng, gian hàng và sản phẩm không gồm bản ghi đã xóa. Đơn hàng
      giữ toàn bộ lịch sử.
    </p>
    <button class="btn btn-outline-primary mb-3" :disabled="busy" @click="load">Tải lại</button
    ><DomainFeedback :busy="busy" :error="error" />
    <template v-if="data"
      ><div class="row g-3">
        <div v-for="[field, label] in metrics" :key="field" class="col-6 col-lg-3">
          <div class="card card-body">
            <span>{{ label }}</span
            ><strong class="h3" :data-metric="field">{{ data[field] }}</strong>
          </div>
        </div>
      </div>
      <section class="card card-body my-3">
        <h2 class="h5">Doanh thu hàng hóa đã hoàn tất</h2>
        <strong class="h3" data-total-revenue>{{ formatPrice(data.total_revenue) }}</strong>
        <p>
          Tổng tiền hàng của các phần đơn COMPLETED, không gồm phí giao hàng hoặc các lần thanh
          toán. Đây không phải doanh thu hoa hồng của nền tảng. Đơn hoàn tất một phần vẫn đóng góp
          tiền hàng của phần COMPLETED.
        </p>
      </section>
      <h2 class="h5">5 đơn mới nhất</h2>
      <p v-if="!data.recent_orders.length">Chưa có đơn hàng.</p>
      <ul class="list-group mb-3">
        <li
          v-for="order in data.recent_orders"
          :key="order.id"
          class="list-group-item"
          data-recent-order
        >
          <RouterLink :to="{ path: '/admin/orders', query: { order: order.id } }">{{
            order.order_code
          }}</RouterLink>
          · {{ order.buyer.name }} · {{ orderStatuses[order.overall_status] }} ·
          {{ formatPrice(order.total_amount) }}
        </li>
      </ul>
      <h2 class="h5">5 người dùng mới nhất</h2>
      <p v-if="!data.recent_users.length">Chưa có người dùng.</p>
      <ul class="list-group">
        <li
          v-for="user in data.recent_users"
          :key="user.id"
          class="list-group-item"
          data-recent-user
        >
          {{ user.name }} · {{ user.role }} · {{ user.status }}
        </li>
      </ul>
    </template>
  </section>
</template>

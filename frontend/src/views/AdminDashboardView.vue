<script setup>
import { getRoleLabel } from '../utils/labels'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LoadingState from '../components/ui/LoadingState.vue'
import { computed, onMounted, ref } from 'vue'
import { getAdminDashboard } from '../api/admin.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import { orderStatuses } from '../composables/orderPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
import RevenueChart from '../components/RevenueChart.vue'
const auth = useAuthStore(),
  data = ref(null)
const { busy, error, run } = useFeedback()
const metrics = [
  ['total_users', 'Tổng người dùng'],
  ['active_users', 'Người dùng hoạt động'],
  ['total_shops', 'Tổng gian hàng'],
  ['active_shops', 'Gian hàng hoạt động'],
  ['pending_shops', 'Gian hàng chờ duyệt (cũ)'],
  ['total_products', 'Tổng sản phẩm'],
  ['active_products', 'Sản phẩm đang hoạt động'],
  ['pending_products', 'Tin cần xem xét'],
  ['total_orders', 'Tổng đơn mua'],
  ['completed_orders', 'Đơn hoàn tất toàn bộ'],
  ['cancelled_orders', 'Đơn hủy toàn bộ'],
]
const revenueSeries = computed(() =>
  (data.value?.revenue_by_month ?? []).map((row) => {
    const [year, month] = row.period.split('-')
    return { ...row, period: `T${Number(month)}/${year}` }
  }),
)
const hasRecentRevenue = computed(() => revenueSeries.value.some((row) => row.completed_orders > 0))
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
    <PageHeader
      title="Tổng quan quản trị"
      eyebrow="Quản trị · Sàn mua bán"
      description="Số liệu toàn thời gian về người dùng, gian hàng và hoạt động mua bán."
      ><button class="btn btn-outline-primary" :disabled="busy" @click="load">
        Tải lại
      </button></PageHeader
    >
    <DomainFeedback :error="error" /><LoadingState
      layout="summary"
      v-if="busy"
      label="Đang tải tổng quan quản trị…"
    />
    <p class="text-secondary">
      Người dùng, gian hàng và sản phẩm không gồm bản ghi đã xóa. Đơn hàng giữ toàn bộ lịch sử.
    </p>
    <template v-if="data">
      <div class="row g-3 mb-4">
        <div v-for="[field, label] in metrics" :key="field" class="col-6 col-md-4 col-xl-3">
          <div class="card card-body admin-kpi">
            <span>{{ label }}</span
            ><strong :data-metric="field">{{ data[field] }}</strong>
          </div>
        </div>
      </div>
      <section class="admin-panel admin-revenue">
        <h2>Doanh thu hàng hóa đã hoàn tất</h2>
        <strong data-total-revenue>{{ formatPrice(data.total_revenue) }}</strong>
        <p class="mb-0">
          Tổng tiền hàng của các phần đơn đã hoàn thành, không gồm phí giao hàng hoặc các lần thanh
          toán. Đây không phải doanh thu hoa hồng của nền tảng. Đơn hoàn tất một phần vẫn đóng góp
          tiền hàng của phần đã hoàn thành.
        </p>
        <h3 class="h6 mt-4 mb-3">Theo tháng hoàn tất, 6 tháng gần nhất (giờ Việt Nam)</h3>
        <p v-if="!hasRecentRevenue" class="alert alert-info" data-no-recent-revenue>
          Chưa có đơn hoàn tất trong 6 tháng gần nhất.
        </p>
        <RevenueChart :series="revenueSeries" />
      </section>
      <div class="row g-4">
        <div class="col-xl-7">
          <section class="admin-panel">
            <h2>5 đơn mới nhất</h2>
            <EmptyState
              v-if="!data.recent_orders.length"
              title="Chưa có đơn hàng."
              description="Các đơn mua gần đây sẽ xuất hiện tại đây."
            />
            <div v-else class="admin-activity">
              <article v-for="order in data.recent_orders" :key="order.id" data-recent-order>
                <div>
                  <RouterLink :to="{ path: '/admin/orders', query: { order: order.id } }">{{
                    order.order_code
                  }}</RouterLink>
                  <p>{{ order.buyer.name }}</p>
                  <p>
                    <StatusBadge
                      :status="order.overall_status"
                      :label="orderStatuses[order.overall_status]"
                    />
                  </p>
                </div>
                <strong>{{ formatPrice(order.total_amount) }}</strong>
              </article>
            </div>
          </section>
        </div>
        <div class="col-xl-5">
          <section class="admin-panel">
            <h2>5 người dùng mới nhất</h2>
            <EmptyState v-if="!data.recent_users.length" title="Chưa có người dùng." />
            <div v-else class="admin-activity">
              <article v-for="user in data.recent_users" :key="user.id" data-recent-user>
                <div>
                  <strong>{{ user.name }}</strong>
                  <p>{{ getRoleLabel(user.role) }}</p>
                </div>
                <StatusBadge :status="user.status" />
              </article>
            </div>
          </section>
        </div>
      </div>
    </template>
  </section>
</template>

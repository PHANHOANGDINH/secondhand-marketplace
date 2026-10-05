<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { getSellerDashboard, getSellerRevenue } from '../api/sellerDashboard.api'
import { reportPreset } from '../composables/dashboardPresentation'
import { formatPrice } from '../composables/productPresentation'
import { orderStatuses } from '../composables/orderPresentation'
import { domainError } from '../api/domain.api'
import DomainFeedback from '../components/DomainFeedback.vue'
import RevenueChart from '../components/RevenueChart.vue'
const auth = useAuthStore(),
  preset = ref('30'),
  filters = reactive({ ...reportPreset(), group_by: 'day' })
const dashboard = ref(null),
  revenue = ref(null),
  busy = ref(false),
  error = ref(''),
  errors = ref({}),
  noShop = ref(false)
let sequence = 0
const productCards = computed(() => [
  ['total_products', 'Tổng sản phẩm'],
  ['active_products', 'Đang bán'],
  ['pending_products', 'Chờ duyệt'],
  ['sold_products', 'Đã bán hết'],
])
const orderCards = [
  ['total_orders', 'Tổng đơn'],
  ['pending_orders', 'Chờ xác nhận'],
  ['completed_orders', 'Đã hoàn tất'],
  ['cancelled_orders', 'Đã hủy'],
]
async function load() {
  const ticket = ++sequence
  dashboard.value = null
  revenue.value = null
  error.value = ''
  errors.value = {}
  noShop.value = false
  if (!auth.isAuthenticated) {
    busy.value = false
    return
  }
  busy.value = true
  const range = { from: filters.from, to: filters.to }
  try {
    const [stats, chart] = await Promise.all([
      getSellerDashboard(range),
      getSellerRevenue({ ...range, group_by: filters.group_by }),
    ])
    if (ticket === sequence) {
      dashboard.value = stats
      revenue.value = chart
    }
  } catch (failure) {
    if (ticket === sequence) {
      noShop.value = failure.response?.status === 404
      error.value = noShop.value ? 'Không tìm thấy gian hàng của bạn.' : domainError(failure)
      errors.value = failure.response?.data?.errors || {}
    }
  } finally {
    if (ticket === sequence) busy.value = false
  }
}
function selectPreset() {
  if (preset.value === 'custom') return
  Object.assign(filters, reportPreset(preset.value))
  load()
}
function submit() {
  preset.value = 'custom'
  load()
}
watch(() => auth.user?.id, load)
onMounted(load)
onBeforeUnmount(() => {
  sequence++
})
</script>
<template>
  <section>
    <h1 class="h3">Thống kê gian hàng</h1>
    <p v-if="dashboard" class="text-secondary">{{ dashboard.shop.name }}</p>
    <DomainFeedback :busy="busy" :error="error" />
    <p v-for="(messages, field) in errors" :key="field" class="text-danger">
      {{ messages.join(' ') }}
    </p>
    <p v-if="noShop"><RouterLink to="/seller/shop">Tạo hoặc kiểm tra gian hàng</RouterLink></p>
    <form class="row g-2 align-items-end mb-4" @submit.prevent="submit">
      <div class="col-md-3">
        <label for="dashboard-preset" class="form-label">Khoảng thời gian</label
        ><select
          id="dashboard-preset"
          v-model="preset"
          :disabled="busy"
          class="form-select"
          @change="selectPreset"
        >
          <option value="7">7 ngày</option>
          <option value="30">30 ngày</option>
          <option value="month">Tháng hiện tại</option>
          <option value="custom">Tùy chọn</option>
        </select>
      </div>
      <div class="col-md-3">
        <label for="dashboard-from" class="form-label">Từ ngày</label
        ><input
          id="dashboard-from"
          v-model="filters.from"
          name="from"
          type="date"
          min="2000-01-01"
          max="9998-12-31"
          required
          :disabled="busy"
          class="form-control"
        />
      </div>
      <div class="col-md-3">
        <label for="dashboard-to" class="form-label">Đến ngày</label
        ><input
          id="dashboard-to"
          v-model="filters.to"
          name="to"
          type="date"
          :min="filters.from"
          max="9998-12-31"
          required
          :disabled="busy"
          class="form-control"
        />
      </div>
      <div class="col-md-2">
        <label for="dashboard-group" class="form-label">Nhóm doanh thu</label
        ><select
          id="dashboard-group"
          v-model="filters.group_by"
          :disabled="busy"
          class="form-select"
        >
          <option value="day">Theo ngày</option>
          <option value="month">Theo tháng</option>
        </select>
      </div>
      <div class="col-md-1"><button class="btn btn-primary" :disabled="busy">Xem</button></div>
    </form>
    <p class="text-secondary">
      Theo giờ Việt Nam; tối đa 366 ngày. Sản phẩm là tồn hiện tại, đơn hàng tính theo ngày tạo,
      doanh thu tính theo ngày hoàn tất.
    </p>
    <template v-if="dashboard && revenue">
      <h2 class="h5">Sản phẩm hiện tại</h2>
      <div class="row g-3 mb-3">
        <div v-for="[field, label] in productCards" :key="field" class="col-6 col-lg-3">
          <div class="card card-body">
            <span>{{ label }}</span
            ><strong class="h3 mb-0" :data-metric="field">{{ dashboard[field] }}</strong>
          </div>
        </div>
      </div>
      <p class="text-secondary">
        Nháp: {{ dashboard.product_counts.DRAFT }} · Đang ẩn:
        {{ dashboard.product_counts.HIDDEN }} · Từ chối: {{ dashboard.product_counts.REJECTED }}.
        Không gồm sản phẩm đã xóa.
      </p>
      <h2 class="h5">Đơn được tạo trong kỳ</h2>
      <div class="row g-3 mb-3">
        <div v-for="[field, label] in orderCards" :key="field" class="col-6 col-lg-3">
          <div class="card card-body">
            <span>{{ label }}</span
            ><strong class="h3 mb-0" :data-metric="field">{{ dashboard[field] }}</strong>
          </div>
        </div>
      </div>
      <p class="text-secondary">
        Đã xác nhận: {{ dashboard.order_counts.CONFIRMED }} · Đang chuẩn bị:
        {{ dashboard.order_counts.PROCESSING }} · Đang giao: {{ dashboard.order_counts.SHIPPING }}
      </p>
      <section class="card card-body mb-4">
        <h2 class="h5">Doanh thu từ đơn hoàn tất trong kỳ</h2>
        <strong class="h3" data-total-revenue>{{ formatPrice(revenue.total_revenue) }}</strong>
        <p>
          {{ revenue.range.from }} → {{ revenue.range.to }} · {{ revenue.completed_orders }} đơn
          hoàn tất
        </p>
        <p class="text-secondary">
          Tổng tiền hàng của các phần đơn đã hoàn tất, không gồm phí giao hàng. Chưa áp dụng hoa
          hồng.
        </p>
        <p v-if="!revenue.completed_orders" class="alert alert-info">
          Chưa có đơn hoàn tất trong khoảng thời gian này.
        </p>
        <RevenueChart :series="revenue.series" />
      </section>
      <h2 class="h5">5 đơn mới nhất được tạo trong kỳ</h2>
      <p v-if="!dashboard.recent_orders.length" class="alert alert-info">
        Chưa có đơn hàng trong khoảng thời gian này.
      </p>
      <div v-else class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Mã đơn</th>
              <th>Người mua</th>
              <th>Tổng tiền</th>
              <th>Trạng thái</th>
              <th>Ngày tạo (giờ Việt Nam)</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in dashboard.recent_orders" :key="order.id" data-recent-order>
              <td>
                <RouterLink :to="`/seller/orders/${order.id}`">{{ order.code }}</RouterLink>
              </td>
              <td>{{ order.buyer.name }}</td>
              <td>{{ formatPrice(order.total_amount) }}</td>
              <td>{{ orderStatuses[order.status] }}</td>
              <td>
                {{
                  new Date(order.created_at).toLocaleString('vi-VN', {
                    timeZone: 'Asia/Ho_Chi_Minh',
                  })
                }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </section>
</template>

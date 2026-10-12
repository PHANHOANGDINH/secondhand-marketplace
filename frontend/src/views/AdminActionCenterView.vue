<script setup>
import { computed, onMounted, ref } from 'vue'
import { getAdminActionCenter } from '../api/admin.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import { reportTypes } from '../utils/reportPresentation'
import PageHeader from '../components/ui/PageHeader.vue'
import LoadingState from '../components/ui/LoadingState.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
const auth = useAuthStore(),
  data = ref(null)
const { busy, error, run } = useFeedback()
// Every card leads to the list that is already filtered to the counted records.
const cards = computed(() => [
  ['shops_pending', 'Gian hàng chờ duyệt', 'Hồ sơ gian hàng mới hoặc gửi lại.', '/admin/shops?status=PENDING'],
  ['products_pending', 'Sản phẩm chờ duyệt', 'Tin đăng đang chờ kiểm duyệt.', '/admin/products?status=PENDING'],
  ['reports_pending', 'Báo cáo chờ xử lý', 'Báo cáo chưa có người tiếp nhận.', '/admin/reports?status=PENDING'],
  ['reports_in_review', 'Báo cáo đang xem xét', 'Báo cáo đã tiếp nhận, chưa kết luận.', '/admin/reports?status=IN_REVIEW'],
  [
    'video_comments_reported',
    'Bình luận video bị báo cáo',
    'Số bình luận có báo cáo đang mở.',
    '/admin/reports?target_type=VIDEO_COMMENT',
  ],
  [
    'users_flagged',
    'Tài khoản cần chú ý',
    data.value
      ? `Từ ${data.value.flagged_min_reports} báo cáo mở trong ${data.value.flagged_window_days} ngày.`
      : '',
    '/admin/users?flagged=1',
  ],
  [
    'payments_reconciliation',
    'Thanh toán cần đối soát',
    'Tiền đã về nhưng đơn không còn nhận được.',
    '/admin/payments?reconciliation=open',
  ],
  [
    'auctions_awaiting_checkout',
    'Đấu giá chờ người thắng thanh toán',
    'Phiên đã kết thúc, còn trong hạn hoàn tất đơn.',
    '/admin/auctions?settlement=AWAITING_CHECKOUT',
  ],
  ['auctions_overdue', 'Đấu giá quá hạn chưa kết thúc', 'Dấu hiệu tiến trình định kỳ đã dừng.', '/admin/auctions?overdue=1'],
])
const load = () =>
  run(async () => {
    if (!auth.isAdmin) return
    const actor = auth.user.id
    const result = await getAdminActionCenter()
    if (auth.isAdmin && auth.user.id === actor) data.value = result
  })
const time = (value) => (value ? new Date(value).toLocaleString('vi-VN') : '—')
onMounted(load)
</script>
<template>
  <section v-if="auth.isAdmin" class="action-center">
    <PageHeader
      title="Việc cần xử lý"
      eyebrow="Quản trị · Sàn mua bán"
      description="Các hạng mục đang chờ quản trị viên. Chọn một thẻ để mở danh sách đã lọc sẵn."
      ><button class="btn btn-outline-primary" :disabled="busy" @click="load">
        {{ busy ? 'Đang làm mới…' : 'Làm mới' }}
      </button></PageHeader
    >
    <DomainFeedback :error="error" />
    <LoadingState v-if="busy && !data" layout="summary" label="Đang tải việc cần xử lý…" />
    <template v-if="data">
      <p v-if="data.auctions_overdue > 0" class="alert alert-danger" role="alert" data-overdue-banner>
        Có phiên đấu giá quá hạn chưa được kết thúc. Kiểm tra tiến trình schedule:work.
      </p>
      <div class="action-grid">
        <RouterLink
          v-for="[field, label, hint, to] in cards"
          :key="field"
          :to="to"
          class="card card-body admin-kpi action-card"
          :class="{ 'action-card-alert': data[field] > 0 }"
          :data-card="field"
          :aria-label="`${label}: ${data[field]}`"
        >
          <span>{{ label }}</span>
          <strong :data-count="field">{{ data[field] }}</strong>
          <small>{{ hint }}</small>
        </RouterLink>
      </div>
      <section class="admin-panel mt-4">
        <h2>Báo cáo đang mở theo loại nội dung</h2>
        <p class="mb-2">
          Tổng cộng <strong data-count="reports_open">{{ data.reports_open }}</strong> báo cáo đang mở.
        </p>
        <ul class="action-breakdown">
          <li v-for="(label, type) in reportTypes" :key="type">
            <RouterLink :to="`/admin/reports?target_type=${type}`">{{ label }}</RouterLink
            >: <strong :data-target-count="type">{{ data.reports_by_target[type] ?? 0 }}</strong>
          </li>
        </ul>
      </section>
      <p class="text-secondary mt-3 mb-0">
        Cập nhật lúc {{ time(data.server_time) }} theo giờ máy chủ.
        <RouterLink to="/admin/dashboard">Xem số liệu tổng quan</RouterLink>
      </p>
    </template>
  </section>
</template>
<style scoped>
.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
  gap: 1rem;
}
.action-card {
  text-decoration: none;
  color: inherit;
  border-left: 0.3rem solid var(--bs-border-color);
}
.action-card:focus-visible {
  outline: 3px solid var(--bs-primary);
  outline-offset: 2px;
}
.action-card small {
  color: var(--bs-secondary-color);
  margin-top: 0.35rem;
}
.action-card-alert {
  border-left-color: var(--bs-warning);
  background: var(--bs-warning-bg-subtle);
}
.action-card-alert strong {
  color: var(--bs-warning-text-emphasis);
}
.action-breakdown {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem 1.5rem;
  list-style: none;
  padding: 0;
  margin: 0;
}
@media (max-width: 575px) {
  .action-grid {
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
  }
}
</style>

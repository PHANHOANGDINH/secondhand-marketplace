<script setup>
import { onUnmounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { listNotifications } from '../api/notification.api'
import { domainError } from '../api/domain.api'
import { useNotifications, safeNotificationTarget } from '../composables/useNotifications'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import NotificationList from '../components/notifications/NotificationList.vue'
import '../styles/notifications.css'
const notifications = useNotifications(),
  router = useRouter()
const filter = ref('all'),
  rows = ref([]),
  loading = ref(false),
  error = ref(''),
  meta = ref(null)
let sequence = 0
async function load(page = 1) {
  const s = ++sequence
  loading.value = true
  error.value = ''
  try {
    const result = await listNotifications({ filter: filter.value, page, per_page: 20 })
    if (s !== sequence) return
    rows.value = result.data
    meta.value = result.meta
  } catch (e) {
    if (s === sequence) error.value = domainError(e)
  } finally {
    if (s === sequence) loading.value = false
  }
}
async function open(row) {
  if (await notifications.read(row)) await router.push(safeNotificationTarget(row.target_route))
}
watch([filter, () => notifications?.version.value], () => load(), { immediate: true })
onUnmounted(() => {
  sequence++
})
</script>
<template>
  <section class="buyer-flow notifications-page">
    <PageHeader
      title="Thông báo"
      description="Các cập nhật đấu giá và đơn hàng được lưu lại để bạn xem cả khi đã bỏ lỡ thông báo trực tiếp."
    />
    <div class="d-flex justify-content-between gap-3 flex-wrap align-items-end mb-4">
      <label for="notification-filter" class="form-label mb-0"
        >Hiển thị<select id="notification-filter" v-model="filter" class="form-select mt-2">
          <option value="all">Tất cả</option>
          <option value="unread">Chưa đọc</option>
          <option value="auction">Đấu giá</option>
          <option value="orders">Đơn hàng</option>
        </select></label
      >
      <button
        class="btn btn-outline-secondary"
        :disabled="!notifications?.unread.value || notifications.writing.value"
        @click="notifications.readAll"
      >
        Đánh dấu tất cả đã đọc
      </button>
    </div>
    <DomainFeedback :busy="loading" :error="error || notifications?.error.value" />
    <div v-if="!loading && !error" class="buyer-panel">
      <NotificationList
        :rows="rows"
        :busy="notifications?.writing.value"
        @open="open"
        @read="notifications.read"
      />
      <p v-if="!rows.length && !notifications?.error.value" class="text-muted mb-0">
        Chưa có thông báo trong mục này.
      </p>
    </div>
    <div v-if="meta && meta.last_page > 1" class="d-flex gap-3 justify-content-center mt-4">
      <button
        class="btn btn-outline-secondary"
        :disabled="loading || meta.current_page <= 1"
        @click="load(meta.current_page - 1)"
      >
        Trước</button
      ><span class="align-self-center">Trang {{ meta.current_page }} / {{ meta.last_page }}</span
      ><button
        class="btn btn-outline-secondary"
        :disabled="loading || meta.current_page >= meta.last_page"
        @click="load(meta.current_page + 1)"
      >
        Sau
      </button>
    </div>
  </section>
</template>

<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotifications, safeNotificationTarget } from '../../composables/useNotifications'
import DomainFeedback from '../DomainFeedback.vue'
import NotificationList from './NotificationList.vue'
import '../../styles/notifications.css'
const notifications = useNotifications(),
  route = useRoute(),
  router = useRouter()
const opened = ref(false),
  button = ref(null),
  panel = ref(null),
  position = ref({})
async function toggle() {
  if (opened.value) return close()
  const rect = button.value.getBoundingClientRect()
  position.value = {
    top: `${Math.max(8, Math.min(rect.bottom + 8, window.innerHeight - 320))}px`,
    right: `${Math.max(12, window.innerWidth - rect.right)}px`,
  }
  opened.value = true
  notifications.load()
  await nextTick()
  panel.value?.focus()
}
function close(focus = true) {
  opened.value = false
  if (focus) button.value?.focus()
}
function outside(e) {
  if (opened.value && !button.value?.contains(e.target) && !panel.value?.contains(e.target))
    close(false)
}
function resized() {
  if (opened.value) close(false)
}
async function open(row) {
  if (await notifications.read(row)) {
    close(false)
    await router.push(safeNotificationTarget(row.target_route))
  }
}
watch(
  () => route.fullPath,
  () => close(false),
)
onMounted(() => {
  document.addEventListener('pointerdown', outside)
  window.addEventListener('resize', resized)
})
onUnmounted(() => {
  document.removeEventListener('pointerdown', outside)
  window.removeEventListener('resize', resized)
})
</script>
<template>
  <div v-if="notifications" class="notification-bell">
    <button
      ref="button"
      type="button"
      class="notification-bell-button"
      :aria-label="`Thông báo, ${notifications.unread.value} chưa đọc`"
      :aria-expanded="opened"
      aria-haspopup="dialog"
      aria-controls="notification-panel"
      @click="toggle"
    >
      <svg
        aria-hidden="true"
        width="20"
        height="20"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
      >
        <path d="M6 8a6 6 0 0 1 12 0v6l2 3H4l2-3V8m4 12h4" />
      </svg>
      <span v-if="notifications.unread.value" class="notification-badge" aria-hidden="true">{{
        notifications.unreadLabel.value
      }}</span>
    </button>
    <Teleport to="body">
      <section
        v-if="opened"
        id="notification-panel"
        ref="panel"
        class="notification-panel"
        :style="position"
        role="dialog"
        aria-label="Thông báo"
        tabindex="-1"
        @keydown.esc.stop.prevent="close()"
      >
        <div class="notification-panel-header">
          <h2 class="h5 mb-0">Thông báo</h2>
          <button
            class="btn btn-sm btn-outline-secondary"
            aria-label="Đóng thông báo"
            @click="close()"
          >
            Đóng
          </button>
        </div>
        <DomainFeedback :busy="notifications.loading.value" :error="notifications.error.value" />
        <button
          class="btn btn-sm btn-link"
          :disabled="!notifications.unread.value || notifications.writing.value"
          @click="notifications.readAll"
        >
          Đánh dấu tất cả đã đọc
        </button>
        <NotificationList
          :rows="notifications.rows.value.slice(0, 8)"
          :busy="notifications.writing.value"
          @open="open"
          @read="notifications.read"
        />
        <p
          v-if="
            !notifications.loading.value &&
            !notifications.error.value &&
            !notifications.rows.value.length
          "
          class="p-3 text-muted"
        >
          Chưa có thông báo.
        </p>
        <RouterLink to="/notifications" class="notification-see-all" @click="close(false)"
          >Xem tất cả thông báo →</RouterLink
        >
      </section>
    </Teleport>
  </div>
</template>

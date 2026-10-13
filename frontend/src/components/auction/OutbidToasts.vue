<script setup>
import { onUnmounted, ref, watch } from 'vue'
import { useUserRealtime } from '../../composables/useUserRealtime'
import { useAuthStore } from '../../stores/auth'
import { useRouter } from 'vue-router'
import '../../styles/auction.css'
const { outbid } = useUserRealtime(),
  auth = useAuthStore(),
  router = useRouter(),
  notices = ref([])
const timers = new Map(),
  seen = new Set()
let serial = 0
function dismiss(id) {
  clearTimeout(timers.get(id))
  timers.delete(id)
  notices.value = notices.value.filter((n) => n.id !== id)
}
function pause(id) {
  clearTimeout(timers.get(id))
  timers.delete(id)
}
function resume(id) {
  pause(id)
  timers.set(
    id,
    setTimeout(() => dismiss(id), 12000),
  )
}
watch(outbid, (payload) => {
  if (
    !auth.isAuthenticated ||
    !payload ||
    !/^[1-9][0-9]*$/.test(String(payload.auction_id)) ||
    !payload.product?.name
  )
    return
  const key = `${payload.auction_id}:${payload.new_current_price}`
  if (seen.has(key)) return
  seen.add(key)
  if (seen.size > 100) seen.delete(seen.values().next().value)
  if (notices.value.length >= 3) dismiss(notices.value[0].id)
  const notice = { ...payload, id: ++serial }
  notices.value.push(notice)
  resume(notice.id)
})
watch(
  () => auth.user?.id,
  () => {
    notices.value.forEach((n) => pause(n.id))
    notices.value = []
    seen.clear()
  },
)
function returnToAuction(notice) {
  dismiss(notice.id)
  router.push(`/auctions/${notice.auction_id}`)
}
onUnmounted(() => {
  timers.forEach(clearTimeout)
  timers.clear()
})
</script>
<template>
  <aside class="auction-toasts theme-auction" aria-label="Thông báo đấu giá">
    <section
      v-for="notice in notices"
      :key="notice.id"
      class="auction-toast"
      @pointerenter="pause(notice.id)"
      @pointerleave="resume(notice.id)"
      @focusin="pause(notice.id)"
      @focusout="resume(notice.id)"
      @keydown.esc="dismiss(notice.id)"
    >
      <p role="status">
        Có người vừa trả giá cao hơn bạn cho <strong>{{ notice.product.name }}</strong
        >.
      </p>
      <div class="d-flex gap-2">
        <button class="btn btn-primary btn-sm" @click="returnToAuction(notice)">Ra giá lại</button
        ><button
          class="btn btn-outline-secondary btn-sm"
          aria-label="Đóng thông báo đấu giá"
          @click="dismiss(notice.id)"
        >
          Đóng
        </button>
      </div>
    </section>
  </aside>
</template>

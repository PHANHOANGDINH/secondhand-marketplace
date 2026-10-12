import { computed, onScopeDispose, ref, toValue, watch } from 'vue'
import { createAuctionClock } from './auctionTimeSync'
export function useAuctionCountdown(source, suppliedClock) {
  const clock = suppliedClock || createAuctionClock(),
    remaining = ref(0)
  let timer
  function tick() {
    const auction = toValue(source)
    remaining.value = ['LIVE', 'SCHEDULED'].includes(auction?.status)
      ? clock.remainingMs(auction.status === 'SCHEDULED' ? auction.starts_at : auction.ends_at)
      : 0
  }
  const stop = watch(
    () => {
      const a = toValue(source)
      return [a?.status, a?.starts_at, a?.ends_at, a?.server_time]
    },
    () => {
      clearInterval(timer)
      if (!suppliedClock) clock.sync(toValue(source)?.server_time)
      tick()
      if (['LIVE', 'SCHEDULED'].includes(toValue(source)?.status)) timer = setInterval(tick, 1000)
    },
    { immediate: true },
  )
  onScopeDispose(() => {
    stop()
    clearInterval(timer)
  })
  const text = computed(() => {
    const status = toValue(source)?.status
    if (status === 'ENDED') return 'Đã kết thúc'
    if (status === 'CANCELLED') return 'Đã huỷ'
    if (!remaining.value) return 'Chờ cập nhật từ máy chủ'
    const seconds = Math.ceil(remaining.value / 1000),
      days = Math.floor(seconds / 86400)
    const time = [Math.floor(seconds / 3600) % 24, Math.floor(seconds / 60) % 60, seconds % 60]
      .map((n) => String(n).padStart(2, '0'))
      .join(':')
    return `${days ? days + ' ngày ' : ''}${time}`
  })
  return {
    remaining,
    text,
    tick,
    urgent: computed(
      () => toValue(source)?.status === 'LIVE' && remaining.value > 0 && remaining.value < 60000,
    ),
    finalSeconds: computed(
      () => toValue(source)?.status === 'LIVE' && remaining.value > 0 && remaining.value <= 10000,
    ),
  }
}

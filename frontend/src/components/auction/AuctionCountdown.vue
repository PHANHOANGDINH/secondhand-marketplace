<script setup>
import { ref, watch } from 'vue'
import { useMotion } from '../../composables/useMotion'
import { useAuctionCountdown } from '../../composables/useAuctionCountdown'
const props = defineProps({
  auction: { type: Object, required: true },
  clock: { type: Object, default: undefined },
})
const { text, urgent, finalSeconds } = useAuctionCountdown(() => props.auction, props.clock)
const digits = ref(null)
const { countPulse } = useMotion()
// One beat per tick in the final seconds; purely visual.
watch(text, () => {
  if (finalSeconds.value) countPulse(digits.value)
})
</script>
<template>
  <div class="auction-timer" :class="{ 'is-urgent': urgent, 'is-final': finalSeconds }">
    <small>{{
      auction.status === 'SCHEDULED'
        ? 'Bắt đầu sau'
        : auction.status === 'LIVE'
          ? urgent
            ? 'Sắp kết thúc'
            : 'Thời gian còn lại'
          : 'Trạng thái phiên'
    }}</small
    ><strong ref="digits" role="timer" aria-live="off">{{ text }}</strong>
  </div>
</template>

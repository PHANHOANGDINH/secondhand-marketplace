<script setup>
import { computed } from 'vue'
import { orderStatuses } from '../composables/orderPresentation'
// Progress of one shop order, drawn only from the timestamps the API already returns.
const props = defineProps({ part: { type: Object, required: true } })
const time = (value) =>
  value
    ? new Date(value).toLocaleString('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      })
    : ''
const steps = computed(() => {
  const part = props.part
  const flow = [
    { key: 'PENDING', label: 'Đã đặt hàng', at: part.created_at },
    { key: 'CONFIRMED', label: orderStatuses.CONFIRMED, at: part.confirmed_at },
    { key: 'PROCESSING', label: orderStatuses.PROCESSING, at: part.processing_at },
    { key: 'SHIPPING', label: orderStatuses.SHIPPING, at: part.shipping_at },
    { key: 'COMPLETED', label: orderStatuses.COMPLETED, at: part.completed_at },
  ]
  const reached = flow.findIndex((step) => step.key === part.status)
  if (part.status !== 'CANCELLED')
    return flow.map((step, index) => ({ ...step, done: index <= reached, current: index === reached }))
  // A cancelled order shows the steps it really went through, then the cancellation.
  return [
    ...flow.filter((step, index) => index === 0 || step.at).map((step) => ({ ...step, done: true })),
    { key: 'CANCELLED', label: orderStatuses.CANCELLED, at: part.cancelled_at, done: true, current: true, cancelled: true },
  ]
})
</script>
<template>
  <ol class="order-timeline" aria-label="Tiến trình đơn hàng">
    <li
      v-for="step in steps"
      :key="step.key"
      :class="{ 'is-done': step.done, 'is-current': step.current, 'is-cancelled': step.cancelled }"
      :aria-current="step.current ? 'step' : undefined"
    >
      <span class="order-timeline-dot" aria-hidden="true"></span>
      <span class="order-timeline-label">{{ step.label }}</span>
      <time v-if="time(step.at)" :datetime="step.at">{{ time(step.at) }}</time>
    </li>
  </ol>
</template>
<style scoped>
.order-timeline {
  display: grid;
  grid-auto-flow: column;
  grid-auto-columns: minmax(0, 1fr);
  gap: var(--space-2);
  margin: var(--space-4) 0;
  padding: 0;
  list-style: none;
}
.order-timeline li {
  position: relative;
  display: grid;
  justify-items: start;
  gap: 0.2rem;
  padding-top: 1.4rem;
  color: var(--muted);
  font-size: 0.78rem;
  line-height: 1.4;
}
.order-timeline li::before {
  content: '';
  position: absolute;
  top: 6px;
  left: 0;
  right: calc(-1 * var(--space-2));
  height: 2px;
  background: var(--border);
}
.order-timeline li:last-child::before {
  display: none;
}
.order-timeline-dot {
  position: absolute;
  top: 0;
  left: 0;
  width: 14px;
  height: 14px;
  border: 2px solid var(--border);
  border-radius: 50%;
  background: var(--surface);
}
.order-timeline li.is-done {
  color: var(--text);
}
.order-timeline li.is-done::before {
  background: var(--primary);
}
.order-timeline li.is-current::before {
  background: var(--border);
}
.order-timeline li.is-done .order-timeline-dot {
  border-color: var(--primary);
  background: var(--primary);
}
.order-timeline li.is-current .order-timeline-label {
  font-weight: 700;
}
.order-timeline li.is-cancelled .order-timeline-dot {
  border-color: var(--danger);
  background: var(--danger);
}
.order-timeline li.is-cancelled .order-timeline-label {
  color: var(--danger-ink);
}
.order-timeline time {
  font-family: var(--font-money);
  font-size: 0.7rem;
  color: var(--muted);
}
/* Phones: a vertical list reads better than five narrow columns. */
@media (max-width: 575px) {
  .order-timeline {
    grid-auto-flow: row;
    gap: 0;
  }
  .order-timeline li {
    grid-template-columns: 1fr auto;
    align-items: baseline;
    padding: 0 0 var(--space-4) 1.6rem;
  }
  .order-timeline li::before {
    top: 14px;
    bottom: 0;
    left: 6px;
    right: auto;
    width: 2px;
    height: auto;
  }
  .order-timeline-dot {
    top: 2px;
  }
}
</style>

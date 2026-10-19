<script setup>
import { ref } from 'vue'
import AppIcon from './AppIcon.vue'
// Choose 1–5 stars: a radio group (arrow keys move, Space/Enter picks) with large touch targets.
const props = defineProps({
  modelValue: { type: Number, default: 0 },
  disabled: Boolean,
  label: { type: String, default: 'Số sao' },
})
const emit = defineEmits(['update:modelValue'])
const hover = ref(0)
const words = ['', 'Rất tệ', 'Tệ', 'Bình thường', 'Tốt', 'Tuyệt vời']
function keyboard(event) {
  const step = { ArrowRight: 1, ArrowUp: 1, ArrowLeft: -1, ArrowDown: -1 }[event.key]
  if (!step || props.disabled) return
  event.preventDefault()
  const next = Math.max(1, Math.min(5, (props.modelValue || 0) + step))
  emit('update:modelValue', next)
  event.currentTarget.querySelectorAll('button')[next - 1]?.focus()
}
</script>
<template>
  <div class="star-picker">
    <div
      class="star-picker-stars"
      role="radiogroup"
      :aria-label="label"
      @keydown="keyboard"
      @mouseleave="hover = 0"
    >
      <button
        v-for="star in 5"
        :key="star"
        type="button"
        role="radio"
        class="star-picker-star"
        :class="{ 'is-on': star <= (hover || modelValue) }"
        :aria-checked="modelValue === star"
        :aria-label="`${star} sao – ${words[star]}`"
        :tabindex="modelValue === star || (!modelValue && star === 1) ? 0 : -1"
        :disabled="disabled"
        :data-star="star"
        @mouseenter="hover = star"
        @click="emit('update:modelValue', star)"
      >
        <AppIcon name="star" :filled="star <= (hover || modelValue)" :size="32" />
      </button>
    </div>
    <span class="star-picker-word" aria-live="polite">{{
      words[hover || modelValue] || 'Chọn số sao'
    }}</span>
  </div>
</template>
<style scoped>
.star-picker {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 1rem;
}
.star-picker-stars {
  display: inline-flex;
  gap: 0.15rem;
}
.star-picker-star {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--border);
  transition:
    transform var(--transition-fast),
    color var(--transition-fast);
}
.star-picker-star.is-on {
  color: var(--rating);
}
.star-picker-star:hover:not(:disabled) {
  transform: scale(1.12);
}
.star-picker-star:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 1px;
}
.star-picker-word {
  color: var(--muted);
  font-weight: 600;
}
@media (prefers-reduced-motion: reduce) {
  .star-picker-star {
    transition: none;
  }
}
</style>

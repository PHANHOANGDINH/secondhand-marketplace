<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'
// Read-only stars: rounded to the nearest whole star, with the number for screen readers.
const props = defineProps({
  value: { type: Number, default: 0 },
  size: { type: Number, default: 16 },
})
const filled = computed(() => Math.max(0, Math.min(5, Math.round(props.value || 0))))
</script>
<template>
  <span class="star-rating" role="img" :aria-label="`${(value || 0).toFixed(1)} trên 5 sao`">
    <AppIcon
      v-for="star in 5"
      :key="star"
      name="star"
      :filled="star <= filled"
      :size="size"
      :class="{ 'is-on': star <= filled }"
    />
  </span>
</template>
<style scoped>
.star-rating {
  display: inline-flex;
  gap: 0.1rem;
  color: var(--border);
  vertical-align: middle;
}
.star-rating .is-on {
  color: var(--rating);
}
</style>

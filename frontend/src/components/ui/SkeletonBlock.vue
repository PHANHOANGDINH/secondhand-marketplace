<script setup>
defineProps({ shape: { type: String, default: 'line' } })
</script>
<template>
  <span class="ui-skeleton" :class="`ui-skeleton-${shape}`" aria-hidden="true"></span>
</template>
<style scoped>
.ui-skeleton {
  display: block;
  background: var(--surface-skeleton, #e9ecef);
  border-radius: var(--radius-sm, 0.5rem);
  animation: skeleton-breathe 1.6s ease-in-out infinite alternate;
}
.ui-skeleton-line {
  height: 0.8rem;
  width: 100%;
}
.ui-skeleton-image {
  aspect-ratio: 1;
  width: 100%;
}
.ui-skeleton-number {
  height: 2rem;
  width: 55%;
}
@keyframes skeleton-breathe {
  to {
    opacity: 0.5;
  }
}
@media (prefers-reduced-motion: reduce) {
  .ui-skeleton {
    animation: none;
  }
}
</style>

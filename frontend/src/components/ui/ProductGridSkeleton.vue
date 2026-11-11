<script setup>
// Placeholder for a page of product cards: same grid and same 4:5 photo frame as ProductCard,
// so nothing jumps when the real cards arrive.
defineProps({
  count: { type: Number, default: 8 },
  label: { type: String, default: 'Đang tải sản phẩm…' },
})
</script>
<template>
  <div
    class="product-grid-skeleton"
    role="status"
    aria-live="polite"
    :aria-label="label"
    aria-busy="true"
  >
    <span class="visually-hidden">{{ label }}</span>
    <div class="product-grid" aria-hidden="true">
      <div v-for="index in count" :key="index" class="product-skeleton">
        <span class="product-skeleton-photo"></span>
        <span class="product-skeleton-line is-price"></span>
        <span class="product-skeleton-line is-title"></span>
        <span class="product-skeleton-line is-title-short"></span>
        <span class="product-skeleton-line is-meta"></span>
      </div>
    </div>
  </div>
</template>
<style scoped>
.product-skeleton {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-2);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.product-skeleton > span {
  display: block;
  background: var(--surface-skeleton);
  animation: product-skeleton-breathe 1.6s ease-in-out infinite alternate;
}
.product-skeleton-photo {
  aspect-ratio: 4 / 5;
  margin-bottom: var(--space-1);
  border-radius: var(--radius-lg);
}
.product-skeleton-line {
  height: 0.9rem;
  border-radius: var(--radius-sm);
}
.product-skeleton-line.is-price {
  width: 40%;
  height: 1.15rem;
}
.product-skeleton-line.is-title {
  width: 90%;
}
.product-skeleton-line.is-title-short {
  width: 60%;
}
.product-skeleton-line.is-meta {
  width: 50%;
  height: 0.75rem;
}
@keyframes product-skeleton-breathe {
  to {
    opacity: 0.5;
  }
}
@media (prefers-reduced-motion: reduce) {
  .product-skeleton > span {
    animation: none;
  }
}
</style>

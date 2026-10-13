<script setup>
import ProductPhoto from './ProductPhoto.vue'
import FavoriteButton from './FavoriteButton.vue'
import AppIcon from './ui/AppIcon.vue'
import { formatPrice, conditionLabel } from '../composables/productPresentation'
import { getStatusLabel } from '../utils/labels'
defineProps({ product: { type: Object, required: true } })
</script>
<template>
  <article class="card h-100 marketplace-product-card" :data-product="product.id">
    <RouterLink
      :to="`/products/${product.slug}`"
      class="product-card-image"
      :aria-label="product.name"
      ><ProductPhoto
        :src="product.primary_image?.url"
        :alt="product.name"
        class="w-100"
        style="aspect-ratio: 1 / 1; height: auto"
    /></RouterLink>
    <div class="product-card-favorite"><FavoriteButton :product="product" compact /></div>
    <div class="card-body">
      <p v-if="product.reason_label" class="product-card-reason">{{ product.reason_label }}</p>
      <h2 class="h5">
        <RouterLink :to="`/products/${product.slug}`">{{ product.name }}</RouterLink>
      </h2>
      <p class="product-card-price">
        {{ formatPrice(product.auction?.current_price || product.price) }}
      </p>
      <RouterLink v-if="product.auction" :to="`/auctions/${product.auction.id}`" class="small mb-2"
        >{{ getStatusLabel(product.auction.status, 'auction') }} · Xem phiên</RouterLink
      >
      <p class="product-card-condition">{{ conditionLabel(product.condition) }}</p>
      <p v-if="product.review_count" class="product-card-rating">
        <AppIcon name="star" filled :size="14" /> {{ product.average_rating }} / 5 ·
        {{ product.review_count }} đánh giá
      </p>
      <RouterLink
        v-if="product.shop"
        class="product-card-shop"
        :to="`/shops/${product.shop.slug}`"
        >{{ product.shop.name }}</RouterLink
      >
    </div>
  </article>
</template>

<style scoped>
.marketplace-product-card {
  position: relative;
  padding: 0.5rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
  transition: border-color 0.2s ease;
}
.marketplace-product-card:hover,
.marketplace-product-card:focus-within {
  border-color: var(--primary);
}
.product-card-favorite {
  position: absolute;
  top: 1rem;
  right: 1rem;
  z-index: 1;
}
.product-card-image {
  display: block;
  aspect-ratio: 1 / 1;
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--surface-2);
}
.product-card-image :deep(img) {
  display: block;
  width: 100%;
  height: 100% !important;
  object-fit: cover !important;
  border-radius: 0 !important;
  background: transparent !important;
  transition: transform 0.3s ease;
}
.marketplace-product-card:hover .product-card-image :deep(img) {
  transform: scale(1.03);
}
.card-body {
  padding: 0.85rem 0.5rem 0.5rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.5rem;
}
.card-body > * {
  margin: 0;
}
.product-card-reason {
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--primary);
  overflow-wrap: anywhere;
}
h2 {
  font-size: 0.95rem;
  line-height: 1.45;
  font-weight: 600;
  overflow-wrap: anywhere;
}
h2 a {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  color: var(--text);
  text-decoration: none;
}
h2 a:hover {
  color: var(--primary);
}
.product-card-price {
  font-size: 1.1rem;
  font-weight: 800;
  color: var(--text);
}
.product-card-condition {
  padding: 0.2rem 0.6rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--surface-2);
  color: var(--text);
  font-size: 0.7rem;
  font-weight: 600;
}
.product-card-rating {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  font-size: 0.72rem;
  color: var(--muted);
}
.product-card-rating :deep(svg) {
  color: #b7791f;
}
.product-card-shop {
  width: 100%;
  margin-top: auto;
  padding-top: 0.6rem;
  border-top: 1px solid var(--border);
  font-size: 0.75rem;
  text-decoration: none;
  color: var(--muted);
  overflow-wrap: anywhere;
}
.product-card-shop:hover {
  color: var(--primary);
}
a:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
@media (prefers-reduced-motion: reduce) {
  .marketplace-product-card,
  .product-card-image :deep(img) {
    transition: none;
  }
  .marketplace-product-card:hover .product-card-image :deep(img) {
    transform: none;
  }
}
</style>

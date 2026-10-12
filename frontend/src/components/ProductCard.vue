<script setup>
import ProductPhoto from './ProductPhoto.vue'
import FavoriteButton from './FavoriteButton.vue'
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
        <span aria-hidden="true">★</span> {{ product.average_rating }} / 5 ·
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
.product-card-favorite { position: absolute; top: .8rem; right: .8rem; z-index: 1; }
.marketplace-product-card {
  position: relative;
  border-radius: var(--radius-lg);
  overflow: hidden;
  border-color: var(--bs-border-color);
  box-shadow: var(--shadow-card);
  transition:
    box-shadow 0.18s ease,
    border-color 0.18s ease;
}
.marketplace-product-card:hover,
.marketplace-product-card:focus-within {
  border-color: rgba(var(--bs-primary-rgb), 0.35);
  box-shadow: var(--shadow-hover);
}
.product-card-image {
  display: block;
  padding: 0.65rem;
  background: var(--bs-tertiary-bg);
}
.product-card-image :deep(img) {
  background: transparent !important;
  transition: transform 0.2s ease;
}
.marketplace-product-card:hover .product-card-image :deep(img) {
  transform: scale(1.04);
}
.card-body {
  padding: 1.1rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}
h2 {
  font-size: 0.95rem;
  line-height: 1.5;
  font-weight: 650;
  margin-bottom: 0.6rem;
  overflow-wrap: anywhere;
}
h2 a {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  overflow: hidden;
  color: var(--bs-body-color);
  text-decoration: none;
}
h2 a:hover {
  color: var(--bs-primary);
}
.product-card-price {
  font-size: 1.1rem;
  font-weight: 700;
  color: var(--bs-primary);
  margin-bottom: 0.6rem;
}
.product-card-condition {
  font-size: 0.7rem;
  color: var(--bs-secondary-color);
  margin-bottom: 0.75rem;
  background: var(--bs-tertiary-bg);
  padding: 0.25rem 0.5rem;
  border-radius: 0.4rem;
}
.product-card-rating {
  font-size: 0.7rem;
  color: var(--bs-secondary-color);
  margin-bottom: 0.8rem;
}
.product-card-rating span {
  color: var(--bs-warning-text-emphasis);
}
.product-card-shop {
  font-size: 0.75rem;
  text-decoration: none;
  color: var(--bs-secondary-color);
  margin-top: auto;
  padding-top: 0.85rem;
  border-top: 1px solid var(--bs-border-color);
  width: 100%;
  overflow-wrap: anywhere;
}
a:focus-visible {
  outline: 3px solid rgba(var(--bs-primary-rgb), 0.4);
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

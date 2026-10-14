<script setup>
import ProductPhoto from './ProductPhoto.vue'
import FavoriteButton from './FavoriteButton.vue'
import AppIcon from './ui/AppIcon.vue'
import {
  formatPrice,
  conditionLabel,
  timeAgo,
  auctionNote,
  voucherNote,
} from '../composables/productPresentation'
import { getStatusLabel } from '../utils/labels'
defineProps({ product: { type: Object, required: true } })
const rating = (product) => Number(product.average_rating).toFixed(1)
</script>
<template>
  <article class="card h-100 marketplace-product-card" :data-product="product.id">
    <div class="product-card-media">
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
      <span v-if="product.has_video" class="product-card-badge product-card-badge-video"
        ><AppIcon name="play" :size="14" />Có video</span
      >
      <RouterLink
        v-if="product.auction"
        :to="`/auctions/${product.auction.id}`"
        class="product-card-badge product-card-badge-auction"
        ><AppIcon name="gavel" :size="14" />{{ getStatusLabel(product.auction.status, 'auction') }}
        · Xem phiên</RouterLink
      >
      <div class="product-card-favorite"><FavoriteButton :product="product" compact /></div>
      <p
        v-if="timeAgo(product.published_at) || product.images?.length"
        class="product-card-overlay"
      >
        <time
          v-if="timeAgo(product.published_at)"
          class="product-card-time"
          :datetime="product.published_at"
          >{{ timeAgo(product.published_at) }}</time
        >
        <span
          v-if="product.images?.length"
          class="product-card-photos"
          :aria-label="`${product.images.length} ảnh`"
          ><AppIcon name="image" :size="13" />{{ product.images.length }}</span
        >
      </p>
    </div>
    <div class="card-body">
      <p v-if="product.reason_label" class="product-card-reason">{{ product.reason_label }}</p>
      <h2 class="h5">
        <RouterLink :to="`/products/${product.slug}`">{{ product.name }}</RouterLink>
      </h2>
      <p class="product-card-pricing">
        <span class="product-card-price">{{
          formatPrice(product.auction?.current_price || product.price)
        }}</span>
        <template v-if="product.discount_percent && !product.auction"
          ><s class="product-card-compare" :aria-label="`Giá trước khi giảm ${formatPrice(product.compare_at_price)}`">{{
            formatPrice(product.compare_at_price)
          }}</s
          ><span class="product-card-off">-{{ product.discount_percent }}%</span></template
        >
      </p>
      <ul v-if="product.auction || product.shop?.voucher" class="product-card-tags">
        <li v-if="product.auction" class="product-card-tag is-auction">
          <AppIcon name="gavel" :size="13" />{{ auctionNote(product.auction) }}
        </li>
        <li v-if="product.shop?.voucher" class="product-card-tag is-voucher">
          <AppIcon name="tag" :size="13" />{{ voucherNote(product.shop.voucher) }}
        </li>
      </ul>
      <p class="product-card-meta">
        <span class="product-card-condition">{{ conditionLabel(product.condition) }}</span>
        <RouterLink
          v-if="product.shop"
          class="product-card-shop"
          :to="`/shops/${product.shop.slug}`"
          >{{ product.shop.name }}</RouterLink
        >
        <span v-if="product.shop?.province_name" class="product-card-area">{{
          product.shop.province_name
        }}</span>
        <span
          v-if="product.review_count > 0"
          class="product-card-rating"
          :aria-label="`${rating(product)} trên 5, ${product.review_count} đánh giá`"
          ><AppIcon name="star" filled :size="13" />{{ rating(product) }} ({{
            product.review_count
          }})</span
        >
      </p>
    </div>
  </article>
</template>

<style scoped>
.marketplace-product-card {
  padding: 0.5rem;
  border: 1px solid transparent;
  border-radius: var(--radius-xl);
  background: var(--surface);
  transition: border-color var(--transition-fast);
}
.marketplace-product-card:hover,
.marketplace-product-card:focus-within {
  border-color: var(--border);
}
.product-card-media {
  position: relative;
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
  object-position: center;
  border-radius: 0 !important;
  background: transparent !important;
}
.product-card-favorite {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  z-index: 1;
}
/* Image-corner labels: video at the top, auction at the bottom. */
.product-card-badge {
  position: absolute;
  left: 0.5rem;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  max-width: calc(100% - 1rem);
  padding: 0.3rem 0.6rem;
  border-radius: var(--radius-pill);
  background: var(--text);
  color: #fff;
  font-size: 0.7rem;
  font-weight: 700;
  line-height: 1.3;
  text-decoration: none;
  transition: background-color var(--transition-fast);
}
.product-card-badge-video {
  top: 0.5rem;
  background: var(--accent);
  color: var(--accent-ink);
}
.product-card-badge-auction {
  top: 0.5rem;
}
.product-card-badge-video + .product-card-badge-auction {
  top: 2.4rem;
}
.product-card-badge-auction:hover {
  background: var(--primary);
  color: #fff;
}
.card-body {
  padding: 0.85rem 0.5rem 0.5rem;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.4rem;
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
.product-card-pricing {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  column-gap: 0.5rem;
  row-gap: 0.1rem;
}
.product-card-price {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--danger-ink);
}
.product-card-compare {
  font-size: 0.82rem;
  color: var(--muted);
}
.product-card-off {
  padding: 0.05rem 0.4rem;
  border-radius: var(--radius-pill);
  background: var(--danger);
  color: #fff;
  font-size: 0.72rem;
  font-weight: 800;
}
/* What is going on with the listing: an auction, a shop voucher. */
.product-card-tags {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  width: 100%;
  padding: 0;
  list-style: none;
}
.product-card-tag {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.55rem;
  border-radius: var(--radius-sm);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.35;
}
.product-card-tag :deep(svg) {
  flex: none;
}
.product-card-tag.is-auction {
  background: var(--auction-accent-soft);
  color: var(--auction-accent-ink);
}
.product-card-tag.is-voucher {
  background: var(--band-soft);
  color: var(--band-ink);
}
/* Bottom of the image: when it was posted and how many photos it has. */
.product-card-overlay {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin: 0;
  padding: 1.25rem 0.6rem 0.4rem;
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
  background: linear-gradient(to top, rgb(21 22 26 / 72%), rgb(21 22 26 / 0%));
  color: #fff;
  font-size: 0.7rem;
  font-weight: 600;
  line-height: 1.3;
  pointer-events: none;
}
.product-card-photos {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  margin-left: auto;
}
.product-card-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  column-gap: 0.4rem;
  margin-top: auto;
  padding-top: 0.25rem;
  font-size: 0.75rem;
  line-height: 1.5;
  color: var(--muted);
}
.product-card-meta > *:not(:last-child)::after {
  content: '·';
  margin-left: 0.4rem;
  color: var(--muted);
}
.product-card-shop {
  color: var(--muted);
  text-decoration: none;
  overflow-wrap: anywhere;
}
.product-card-shop:hover {
  color: var(--primary);
}
.product-card-rating {
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  white-space: nowrap;
}
.product-card-rating :deep(svg) {
  color: #b7791f;
}
a:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
@media (prefers-reduced-motion: reduce) {
  .marketplace-product-card,
  .product-card-badge {
    transition: none;
  }
}
</style>

<script setup>
import { computed } from 'vue'
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
const props = defineProps({ product: { type: Object, required: true } })
const rating = (product) => Number(product.average_rating).toFixed(1).replace('.', ',')
// Condition chip tone: the word is always shown, colour only helps.
const conditionTone = {
  LIKE_NEW: 'is-like-new',
  GOOD: 'is-good',
  FAIR: 'is-fair',
  POOR: 'is-poor',
}
// The public list only carries sellable items, but wishlists and old links can reach a sold one.
const soldLabel = computed(() => {
  if (props.product.status === 'SOLD') return 'Đã bán'
  return props.product.stock_quantity === 0 ? 'Hết hàng' : ''
})
const lowStock = computed(() => {
  const left = Number(props.product.stock_quantity)
  return Number.isInteger(left) && left > 1 && left <= 3 ? left : 0
})
</script>
<template>
  <article
    class="card h-100 marketplace-product-card"
    :class="{ 'is-sold': soldLabel }"
    :data-product="product.id"
  >
    <div class="product-card-media">
      <RouterLink
        :to="`/products/${product.slug}`"
        class="product-card-image"
        :aria-label="product.name"
        ><ProductPhoto :src="product.primary_image?.url" :alt="product.name" fit="cover"
      /></RouterLink>
      <RouterLink
        v-if="product.auction"
        :to="`/auctions/${product.auction.id}`"
        class="product-card-badge product-card-badge-auction"
        ><AppIcon name="gavel" :size="14" />Đấu giá<span class="visually-hidden">
          · {{ getStatusLabel(product.auction.status, 'auction') }} · Xem phiên</span
        ></RouterLink
      >
      <span v-else-if="product.has_video" class="product-card-badge product-card-badge-video"
        ><AppIcon name="play" :size="14" />Có video</span
      >
      <div class="product-card-favorite"><FavoriteButton :product="product" compact /></div>
      <p v-if="soldLabel" class="product-card-sold">
        <span>{{ soldLabel }}</span>
      </p>
      <p v-if="product.images?.length" class="product-card-overlay">
        <span class="product-card-photos" :aria-label="`${product.images.length} ảnh`"
          ><AppIcon name="image" :size="14" />{{ product.images.length }}</span
        >
      </p>
    </div>
    <div class="card-body">
      <p v-if="product.reason_label" class="product-card-reason">{{ product.reason_label }}</p>
      <p class="product-card-pricing">
        <span class="product-card-price">{{
          formatPrice(product.auction?.current_price || product.price)
        }}</span>
        <template v-if="product.discount_percent && !product.auction"
          ><s
            class="product-card-compare"
            :aria-label="`Giá trước khi giảm ${formatPrice(product.compare_at_price)}`"
            >{{ formatPrice(product.compare_at_price) }}</s
          ><span class="product-card-off">-{{ product.discount_percent }}%</span></template
        >
      </p>
      <h2 class="h5">
        <RouterLink :to="`/products/${product.slug}`">{{ product.name }}</RouterLink>
      </h2>
      <p class="product-card-chips">
        <span
          class="product-card-condition"
          :class="conditionTone[product.condition] || 'is-poor'"
          >{{ conditionLabel(product.condition) }}</span
        >
        <span v-if="lowStock" class="product-card-stock">Còn {{ lowStock }}</span>
      </p>
      <ul v-if="product.auction || product.shop?.voucher" class="product-card-tags">
        <li v-if="product.auction" class="product-card-tag is-auction">
          <AppIcon name="gavel" :size="14" />{{ auctionNote(product.auction) }}
        </li>
        <li v-if="product.shop?.voucher" class="product-card-tag is-voucher">
          <AppIcon name="tag" :size="14" />{{ voucherNote(product.shop.voucher) }}
        </li>
      </ul>
      <p
        v-if="product.shop?.province_name || timeAgo(product.published_at)"
        class="product-card-meta"
      >
        <span v-if="product.shop?.province_name" class="product-card-area">{{
          product.shop.province_name
        }}</span>
        <time
          v-if="timeAgo(product.published_at)"
          class="product-card-time"
          :datetime="product.published_at"
          >{{ timeAgo(product.published_at) }}</time
        >
      </p>
      <p v-if="product.shop || product.review_count > 0" class="product-card-seller">
        <span
          v-if="product.review_count > 0"
          class="product-card-rating"
          :aria-label="`${rating(product)} trên 5, ${product.review_count} đánh giá`"
          ><AppIcon name="star" filled :size="14" />{{ rating(product) }} ({{
            product.review_count
          }})</span
        >
        <RouterLink
          v-if="product.shop"
          class="product-card-shop"
          :to="`/shops/${product.shop.slug}`"
          >{{ product.shop.name }}</RouterLink
        >
      </p>
    </div>
  </article>
</template>

<style scoped>
.marketplace-product-card {
  padding: var(--space-2);
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
/* Photo first: a 4:5 frame, the picture fills it. */
.product-card-image {
  display: block;
  aspect-ratio: 4 / 5;
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--surface-2);
}
.product-card-image :deep(img) {
  transition: transform 0.3s ease;
}
.is-sold .product-card-image :deep(img) {
  filter: grayscale(0.6);
}
.product-card-favorite {
  position: absolute;
  top: var(--space-1);
  right: var(--space-1);
  z-index: 1;
}
/* One label on the image corner: the auction when there is one, else the video. */
.product-card-badge {
  position: absolute;
  top: var(--space-2);
  left: var(--space-2);
  z-index: 1;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  max-width: calc(100% - 4rem);
  white-space: nowrap;
  padding: var(--space-1) var(--space-2);
  border-radius: var(--radius-pill);
  background: var(--inverse);
  color: var(--on-inverse);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.3;
  text-decoration: none;
  transition: background-color var(--transition-fast);
}
.product-card-badge-video {
  background: var(--accent);
  color: var(--accent-ink);
}
/* The auction label is a link: its touch area is taller than the pill. */
.product-card-badge-auction::after {
  content: '';
  position: absolute;
  inset: -0.6rem -0.25rem;
}
.product-card-badge-auction:hover {
  background: var(--primary);
  color: var(--on-primary);
}
.product-card-sold {
  position: absolute;
  inset: 0;
  z-index: 1;
  display: grid;
  place-items: center;
  margin: 0;
  border-radius: var(--radius-lg);
  background: var(--scrim);
  pointer-events: none;
}
.product-card-sold span {
  padding: var(--space-1) var(--space-4);
  border-radius: var(--radius-pill);
  background: var(--inverse);
  color: var(--on-inverse);
  font-size: 0.85rem;
  font-weight: 700;
}
/* Bottom of the image: how many photos it has. */
.product-card-overlay {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 0;
  display: flex;
  align-items: center;
  margin: 0;
  padding: var(--space-5) var(--space-2) var(--space-2);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
  background: linear-gradient(to top, rgb(21 22 26 / 72%), rgb(21 22 26 / 0%));
  color: #fff;
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.3;
  pointer-events: none;
}
.product-card-photos {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
}
.card-body {
  padding: var(--space-3) var(--space-1) var(--space-1);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--space-2);
  min-width: 0;
}
.card-body > * {
  margin: 0;
}
.product-card-reason {
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--primary-ink);
  overflow-wrap: anywhere;
}
.product-card-pricing {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  column-gap: var(--space-2);
  row-gap: 0;
}
.product-card-price {
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1.2;
  color: var(--price);
  font-variant-numeric: tabular-nums;
}
.product-card-compare {
  font-size: 0.8rem;
  color: var(--muted);
}
.product-card-off {
  padding: 0 var(--space-2);
  border-radius: var(--radius-pill);
  background: var(--danger);
  color: var(--on-danger);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.5;
}
h2 {
  width: 100%;
  font-size: 0.95rem;
  line-height: 1.4;
  font-weight: 600;
  overflow-wrap: anywhere;
}
h2 a {
  display: -webkit-box;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  overflow: hidden;
  color: var(--text);
  text-decoration: none;
}
h2 a:hover {
  color: var(--primary);
}
/* Condition: a chip with the word, tinted by how worn the item is. */
.product-card-chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
}
.product-card-condition,
.product-card-stock {
  padding: 0 var(--space-2);
  border-radius: var(--radius-pill);
  font-size: 0.75rem;
  font-weight: 600;
  line-height: 1.6;
  white-space: nowrap;
}
.product-card-condition.is-like-new {
  background: var(--success-soft);
  color: var(--success-ink);
}
.product-card-condition.is-good {
  background: var(--primary-soft);
  color: var(--primary-ink);
}
.product-card-condition.is-fair {
  background: var(--warning-soft);
  color: var(--warning-ink);
}
.product-card-condition.is-poor {
  background: var(--surface-2);
  color: var(--muted);
}
.product-card-stock {
  background: var(--surface-2);
  color: var(--text);
}
/* What is going on with the listing: an auction, a shop voucher. */
.product-card-tags {
  display: flex;
  flex-direction: column;
  gap: var(--space-1);
  width: 100%;
  padding: 0;
  list-style: none;
}
.product-card-tag {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  padding: var(--space-1) var(--space-2);
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
/* "Area · time posted", one line. */
.product-card-meta {
  display: flex;
  align-items: baseline;
  gap: var(--space-1);
  width: 100%;
  min-width: 0;
  font-size: 0.75rem;
  line-height: 1.4;
  color: var(--muted);
  white-space: nowrap;
}
.product-card-area {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
}
.product-card-time {
  flex: none;
}
.product-card-area + .product-card-time::before {
  content: '·';
  margin-right: var(--space-1);
}
/* Seller line: stars (only with reviews) and the shop. The row is a 44px touch target; negative margins
   give the extra height back so the card does not grow. */
.card-body > .product-card-seller {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  width: 100%;
  min-width: 0;
  min-height: var(--control-height);
  margin: auto 0 calc(var(--space-2) * -1);
  font-size: 0.75rem;
  line-height: 1.4;
  color: var(--muted);
}
.product-card-rating {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  white-space: nowrap;
}
.product-card-rating :deep(svg) {
  color: var(--rating);
}
.product-card-shop {
  align-self: stretch;
  display: block;
  min-width: 0;
  overflow: hidden;
  color: var(--muted);
  line-height: var(--control-height);
  text-decoration: none;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.product-card-shop:hover {
  color: var(--primary);
}
a:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
@media (min-width: 768px) {
  .card-body {
    padding-inline: var(--space-2);
  }
  .product-card-price {
    font-size: 1.25rem;
  }
  h2 {
    font-size: 1rem;
  }
}
@media (hover: hover) and (prefers-reduced-motion: no-preference) {
  .marketplace-product-card:hover .product-card-image :deep(img) {
    transform: scale(1.03);
  }
}
@media (prefers-reduced-motion: reduce) {
  .marketplace-product-card,
  .product-card-badge,
  .product-card-image :deep(img) {
    transition: none;
  }
}
</style>

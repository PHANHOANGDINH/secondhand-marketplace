<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getProduct } from '../api/product.api'
import { domainError } from '../api/domain.api'
import {
  formatPrice,
  conditionLabel,
  timeAgo,
  auctionNote,
  voucherNote,
} from '../composables/productPresentation'
import AppIcon from '../components/ui/AppIcon.vue'
import ProductGallery from '../components/ProductGallery.vue'
import ProductReviews from '../components/ProductReviews.vue'
import ProductVideos from '../components/ProductVideos.vue'
import AddToCart from '../components/AddToCart.vue'
import ReportDialog from '../components/ReportDialog.vue'
import FavoriteButton from '../components/FavoriteButton.vue'
import ChatSellerButton from '../components/chat/ChatSellerButton.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import ProductSuggestions from '../components/ProductSuggestions.vue'
const route = useRoute(),
  product = ref(null),
  busy = ref(false),
  error = ref('')
let sequence = 0
async function load() {
  const current = ++sequence
  busy.value = true
  error.value = ''
  product.value = null
  try {
    const result = await getProduct(route.params.slug)
    if (current === sequence) product.value = result
  } catch (failure) {
    if (current === sequence) error.value = domainError(failure)
  } finally {
    if (current === sequence) busy.value = false
  }
}
watch(() => route.params.slug, load, { immediate: true })
// "tháng 10/2026" from the shop's creation date, when the API sends it.
const joined = computed(() => {
  const time = Date.parse(product.value?.shop?.joined_at || '')
  return Number.isNaN(time)
    ? ''
    : new Date(time).toLocaleDateString('vi-VN', { month: '2-digit', year: 'numeric' })
})
</script>
<template>
  <section class="buyer-flow product-detail">
    <RouterLink to="/products" class="buyer-back-link">← Danh sách sản phẩm</RouterLink
    ><DomainFeedback loading-layout="detail" :busy="busy" :error="error" /><button
      v-if="error"
      class="btn btn-outline-secondary"
      @click="load"
    >
      Tải lại
    </button>
    <div v-if="product" class="product-detail-grid">
      <div class="product-detail-main">
        <ProductGallery :images="product.images" :name="product.name" />
        <section class="product-block" aria-labelledby="product-description-title">
          <h2 id="product-description-title">Mô tả</h2>
          <p class="text-break buyer-product-description" style="white-space: pre-wrap">
            {{ product.description }}
          </p>
        </section>
        <section class="product-block" aria-labelledby="product-specs-title">
          <h2 id="product-specs-title">Thông tin chi tiết</h2>
          <dl class="product-specs">
            <div>
              <dt>Tình trạng</dt>
              <dd>{{ conditionLabel(product.condition) }}</dd>
            </div>
            <div v-if="product.category">
              <dt>Danh mục</dt>
              <dd>{{ product.category.name }}</dd>
            </div>
            <div>
              <dt>Số lượng còn</dt>
              <dd>{{ product.stock_quantity }}</dd>
            </div>
            <div v-if="timeAgo(product.published_at)">
              <dt>Đăng bán</dt>
              <dd>{{ timeAgo(product.published_at) }}</dd>
            </div>
          </dl>
        </section>
      </div>
      <aside class="product-detail-side buyer-product-info">
        <h1 class="h3">{{ product.name }}</h1>
        <p class="buyer-product-price">
          {{ formatPrice(product.auction?.current_price || product.price) }}
          <template v-if="product.discount_percent && !product.auction"
            ><s class="product-compare-price">{{ formatPrice(product.compare_at_price) }}</s
            ><span class="product-discount">-{{ product.discount_percent }}%</span></template
          >
        </p>
        <p v-if="product.auction" class="product-deal is-auction">{{ auctionNote(product.auction) }}</p>
        <p v-if="product.shop?.voucher" class="product-deal is-voucher">
          {{ voucherNote(product.shop.voucher) }} · nhắn người bán để nhận mã
        </p>
        <p class="buyer-product-meta">
          <span>{{ conditionLabel(product.condition) }}</span
          ><span>Còn {{ product.stock_quantity }} sản phẩm</span>
        </p>
        <p v-if="product.category" class="product-category-line">
          Danh mục:
          <RouterLink :to="{ path: '/products', query: { category: product.category.id } }">{{
            product.category.name
          }}</RouterLink>
        </p>
        <div class="product-buy-bar">
          <AddToCart :key="product.id" :product="product" />
        </div>
        <FavoriteButton :product="product" class="product-favorite" />
        <div class="product-seller">
          <p v-if="product.shop" class="buyer-shop-panel">
            <span class="product-seller-avatar" aria-hidden="true">{{
              product.shop.name.trim().charAt(0).toLocaleUpperCase('vi-VN')
            }}</span>
            <span
              >Gian hàng:
              <RouterLink :to="`/shops/${product.shop.slug}`">{{
                product.shop.name
              }}</RouterLink></span
            >
          </p>
          <p v-if="product.shop && (product.shop.rating_count > 0 || joined)" class="product-seller-stats">
            <span
              v-if="product.shop.rating_count > 0"
              :aria-label="`Gian hàng được đánh giá ${Number(product.shop.rating_avg).toFixed(1)} trên 5 từ ${product.shop.rating_count} lượt`"
              ><AppIcon name="star" filled :size="14" />{{
                Number(product.shop.rating_avg).toFixed(1)
              }}
              ({{ product.shop.rating_count }})</span
            >
            <span v-if="joined">Tham gia {{ joined }}</span>
          </p>
          <ChatSellerButton :key="`chat-${product.id}`" :product="product" />
        </div>
        <div class="product-safety" role="note">
          <AppIcon name="shield" :size="20" />
          <div>
            <strong>Lưu ý an toàn giao dịch</strong>
            <ul>
              <li>Chỉ thanh toán qua giỏ hàng trên sàn, không chuyển khoản riêng cho người bán.</li>
              <li>Kiểm tra kỹ tình trạng món đồ khi nhận hàng.</li>
              <li>Thấy dấu hiệu bất thường, hãy dùng nút báo cáo bên dưới.</li>
            </ul>
          </div>
        </div>
        <ReportDialog target-type="PRODUCT" :target-id="product.id" label="Báo cáo sản phẩm" />
      </aside>
    </div>
    <ProductVideos v-if="product" :key="`videos-${product.id}`" :slug="product.slug" />
    <ProductReviews v-if="product" :key="product.id" :product="product" @changed="load" />
    <ProductSuggestions v-if="product" title="Sản phẩm tương tự" :slug="product.slug" :limit="8" />
  </section>
</template>
<style scoped>
.product-detail-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  gap: var(--space-8);
  align-items: start;
  margin-top: var(--space-2);
}
.product-detail-main {
  display: grid;
  gap: var(--space-6);
  min-width: 0;
}
.product-detail-side {
  position: sticky;
  top: 5.5rem;
  display: grid;
  gap: var(--space-4);
  min-width: 0;
  padding: var(--space-6);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.product-detail-side > * {
  margin: 0;
}
.product-detail-side h1 {
  font-size: clamp(1.35rem, 2.2vw, 1.75rem);
  font-weight: 700;
  line-height: 1.3;
  letter-spacing: -0.02em;
}
.product-compare-price {
  margin-left: 0.6rem;
  font-size: 0.55em;
  font-weight: 500;
  color: var(--muted);
}
.product-discount {
  margin-left: 0.5rem;
  padding: 0.1rem 0.5rem;
  border-radius: var(--radius-pill);
  background: var(--danger);
  color: #fff;
  font-size: 0.45em;
  font-weight: 800;
  vertical-align: middle;
}
.product-deal {
  padding: 0.45rem 0.75rem;
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
  font-weight: 700;
}
.product-deal.is-auction {
  background: var(--auction-accent-soft);
  color: var(--auction-accent-ink);
}
.product-deal.is-voucher {
  background: var(--band-soft);
  color: var(--band-ink);
}
.product-detail-side .buyer-product-price {
  margin: 0;
  font-size: clamp(1.6rem, 3vw, 2.1rem);
  font-weight: 800;
  color: var(--text);
}
.product-detail-side .buyer-product-meta span {
  padding: 0.25rem 0.7rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--surface-2);
  color: var(--text);
  font-weight: 600;
}
.product-category-line {
  font-size: 0.85rem;
  color: var(--muted);
}
.product-buy-bar :deep(.border-top) {
  border-top: 0 !important;
  padding-top: 0 !important;
}
.product-buy-bar :deep(.btn-primary) {
  flex: 1;
  font-weight: 700;
}
.product-favorite :deep(.btn) {
  width: 100%;
}
.product-seller {
  display: grid;
  gap: var(--space-3);
  padding: var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface-2);
}
.product-seller .buyer-shop-panel {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
  font-size: 0.9rem;
}
.product-seller-avatar {
  flex: none;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--accent);
  color: var(--accent-ink);
  font-weight: 800;
}
.product-seller-stats {
  display: flex;
  flex-wrap: wrap;
  gap: 0.25rem 1rem;
  margin: 0;
  font-size: 0.82rem;
  color: var(--muted);
}
.product-seller-stats span {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
}
.product-seller-stats :deep(svg) {
  color: #b7791f;
}
.product-seller :deep(.btn) {
  width: 100%;
}
.product-safety {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-4);
  border: 1px solid var(--bs-success-border-subtle);
  border-radius: var(--radius-lg);
  background: var(--bs-success-bg-subtle);
  color: var(--success-ink);
  font-size: 0.82rem;
  line-height: 1.6;
}
.product-safety ul {
  margin: var(--space-2) 0 0;
  padding-left: 1.1rem;
  color: var(--text);
}
.product-block {
  padding: var(--space-6);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.product-block h2 {
  margin-bottom: var(--space-4);
  font-size: 1.15rem;
  font-weight: 800;
}
.product-block .buyer-product-description {
  margin: 0;
  padding: 0;
  color: var(--text);
}
.product-specs {
  display: grid;
  margin: 0;
}
.product-specs > div {
  display: grid;
  grid-template-columns: minmax(8rem, 1fr) 2fr;
  gap: var(--space-4);
  padding: var(--space-3) 0;
  border-top: 1px solid var(--border);
}
.product-specs > div:first-child {
  border-top: 0;
  padding-top: 0;
}
.product-specs dt {
  font-weight: 500;
  color: var(--muted);
}
.product-specs dd {
  margin: 0;
  font-weight: 600;
  overflow-wrap: anywhere;
}
@media (max-width: 991px) {
  .product-detail-grid {
    grid-template-columns: 1fr;
    gap: var(--space-6);
  }
  .product-detail-side {
    position: static;
    order: -1;
  }
  .product-detail-main {
    display: contents;
  }
  .product-detail-main :deep(.marketplace-gallery) {
    order: -2;
  }
}
/* Phones: the buy form stays reachable as a bar fixed to the bottom edge. */
@media (max-width: 767px) {
  .product-detail {
    padding-bottom: 7.5rem;
  }
  .product-detail-side {
    padding: var(--space-4);
  }
  .product-buy-bar {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 1010;
    padding: var(--space-3) var(--space-4) calc(var(--space-3) + env(safe-area-inset-bottom, 0px));
    border-top: 1px solid var(--border);
    background: var(--surface);
  }
  .product-buy-bar :deep(.form-label) {
    position: absolute;
    width: 1px;
    height: 1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
  }
  .product-buy-bar :deep(.alert),
  .product-buy-bar :deep(p) {
    margin-bottom: var(--space-2);
    font-size: 0.82rem;
  }
}
</style>

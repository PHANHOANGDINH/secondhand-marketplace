<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getProduct } from '../api/product.api'
import { domainError } from '../api/domain.api'
import { formatPrice, conditionLabel } from '../composables/productPresentation'
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
</script>
<template>
  <section class="buyer-flow">
    <RouterLink to="/products" class="buyer-back-link">← Danh sách sản phẩm</RouterLink
    ><DomainFeedback loading-layout="detail" :busy="busy" :error="error" /><button
      v-if="error"
      class="btn btn-outline-secondary"
      @click="load"
    >
      Tải lại
    </button>
    <div v-if="product" class="row g-4 mt-2">
      <div class="col-md-6"><ProductGallery :images="product.images" :name="product.name" /></div>
      <div class="col-md-6 buyer-product-info">
        <h1 class="h3">{{ product.name }}</h1>
        <p class="buyer-product-price">
          {{ formatPrice(product.auction?.current_price || product.price) }}
        </p>
        <p class="buyer-product-meta">
          <span>{{ conditionLabel(product.condition) }}</span
          ><span>Còn {{ product.stock_quantity }} sản phẩm</span>
        </p>
        <p v-if="product.category">
          Danh mục:
          <RouterLink :to="{ path: '/products', query: { category: product.category.id } }">{{
            product.category.name
          }}</RouterLink>
        </p>
        <p v-if="product.shop" class="buyer-shop-panel">
          Gian hàng:
          <RouterLink :to="`/shops/${product.shop.slug}`">{{ product.shop.name }}</RouterLink>
        </p>
        <p class="text-break buyer-product-description" style="white-space: pre-wrap">
          {{ product.description }}
        </p>
        <FavoriteButton :product="product" class="mb-3" />
        <AddToCart :key="product.id" :product="product" />
        <ReportDialog target-type="PRODUCT" :target-id="product.id" label="Báo cáo sản phẩm" />
        <ChatSellerButton :key="`chat-${product.id}`" :product="product" />
      </div>
    </div>
    <ProductVideos v-if="product" :key="`videos-${product.id}`" :slug="product.slug" />
    <ProductReviews v-if="product" :key="product.id" :product="product" @changed="load" />
    <ProductSuggestions v-if="product" title="Sản phẩm tương tự" :slug="product.slug" :limit="8" />
  </section>
</template>

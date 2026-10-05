<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getProduct } from '../api/product.api'
import { domainError } from '../api/domain.api'
import { formatPrice, conditionLabel } from '../composables/productPresentation'
import ProductGallery from '../components/ProductGallery.vue'
import ProductReviews from '../components/ProductReviews.vue'
import AddToCart from '../components/AddToCart.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
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
  <section>
    <RouterLink to="/products">← Danh sách sản phẩm</RouterLink
    ><DomainFeedback :busy="busy" :error="error" /><button
      v-if="error"
      class="btn btn-outline-secondary"
      @click="load"
    >
      Tải lại
    </button>
    <div v-if="product" class="row g-4 mt-2">
      <div class="col-md-6"><ProductGallery :images="product.images" :name="product.name" /></div>
      <div class="col-md-6">
        <h1 class="h3">{{ product.name }}</h1>
        <p class="h4">{{ formatPrice(product.price) }}</p>
        <p>{{ conditionLabel(product.condition) }} · Còn {{ product.stock_quantity }} sản phẩm</p>
        <p v-if="product.category">
          Danh mục:
          <RouterLink :to="{ path: '/products', query: { category: product.category.id } }">{{
            product.category.name
          }}</RouterLink>
        </p>
        <p v-if="product.shop">
          Gian hàng:
          <RouterLink :to="`/shops/${product.shop.slug}`">{{ product.shop.name }}</RouterLink>
        </p>
        <p class="text-break" style="white-space: pre-wrap">{{ product.description }}</p>
        <AddToCart :key="product.id" :product="product" />
      </div>
    </div>
    <ProductReviews v-if="product" :key="product.id" :product="product" @changed="load" />
  </section>
</template>

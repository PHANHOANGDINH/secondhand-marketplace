<script setup>
import { ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { listRecommendations, listSimilarProducts } from '../api/recommendation.api'
import { listProducts } from '../api/product.api'
import ProductCard from './ProductCard.vue'
import SkeletonState from './ui/SkeletonState.vue'
// Without a slug: personal recommendations. With a slug: products similar to that one.
// `latest` shows the newest public listings instead.
const props = defineProps({
  title: { type: String, required: true },
  slug: { type: String, default: '' },
  limit: { type: Number, default: 12 },
  latest: Boolean,
  moreTo: { type: [String, Object], default: null },
  moreLabel: { type: String, default: 'Xem tất cả' },
})
const auth = useAuthStore(),
  rows = ref([]),
  busy = ref(false)
const headingId = props.latest
  ? 'latest-products-title'
  : props.slug
    ? 'similar-products-title'
    : 'recommendations-title'
const fetchRows = async () =>
  props.latest
    ? (await listProducts({ sort: 'newest', per_page: props.limit })).data
    : props.slug
      ? listSimilarProducts(props.slug, props.limit)
      : listRecommendations(props.limit)
let sequence = 0
async function load() {
  const current = ++sequence
  busy.value = true
  rows.value = []
  try {
    const result = await fetchRows()
    if (current === sequence) rows.value = Array.isArray(result) ? result : []
  } catch {
    // Suggestions are optional: on failure the section simply stays hidden.
    if (current === sequence) rows.value = []
  } finally {
    if (current === sequence) busy.value = false
  }
}
watch(() => [props.slug, auth.user?.id], load, { immediate: true })
</script>
<template>
  <section
    v-if="busy || rows.length"
    class="product-suggestions"
    :aria-labelledby="headingId"
    :aria-busy="busy"
  >
    <header class="product-suggestions-header">
      <h2 :id="headingId" class="h4 product-suggestions-title">{{ title }}</h2>
      <RouterLink v-if="moreTo && rows.length" :to="moreTo" class="product-suggestions-more">{{
        moreLabel
      }}</RouterLink>
    </header>
    <SkeletonState v-if="busy" :count="4" :label="`Đang tải ${title.toLowerCase()}…`" />
    <div v-else class="row g-3">
      <div v-for="product in rows" :key="product.id" class="col-6 col-md-4 col-lg-3">
        <ProductCard :product="product" />
      </div>
    </div>
  </section>
</template>
<style scoped>
.product-suggestions {
  margin-block: clamp(1.5rem, 4vw, 3rem);
}
.product-suggestions-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}
.product-suggestions-title {
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.product-suggestions-more {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
}
</style>

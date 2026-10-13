<script setup>
import { nextTick, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { listRecommendations, listSimilarProducts } from '../api/recommendation.api'
import ProductCard from './ProductCard.vue'
import SkeletonState from './ui/SkeletonState.vue'
import { useMotion } from '../composables/useMotion'
// Without a slug: personal recommendations. With a slug: products similar to that one.
const props = defineProps({
  title: { type: String, required: true },
  slug: { type: String, default: '' },
  limit: { type: Number, default: 12 },
})
const auth = useAuthStore(),
  rows = ref([]),
  busy = ref(false),
  grid = ref(null)
const { fadeUp } = useMotion()
const headingId = props.slug ? 'similar-products-title' : 'recommendations-title'
let sequence = 0
async function load() {
  const current = ++sequence
  busy.value = true
  rows.value = []
  try {
    const result = await (props.slug
      ? listSimilarProducts(props.slug, props.limit)
      : listRecommendations(props.limit))
    if (current === sequence) rows.value = Array.isArray(result) ? result : []
  } catch {
    // Suggestions are optional: on failure the section simply stays hidden.
    if (current === sequence) rows.value = []
  } finally {
    if (current === sequence) {
      busy.value = false
      await nextTick()
      if (grid.value) fadeUp([...grid.value.children])
    }
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
    <h2 :id="headingId" class="h4 product-suggestions-title">{{ title }}</h2>
    <SkeletonState v-if="busy" :count="4" :label="`Đang tải ${title.toLowerCase()}…`" />
    <div v-else ref="grid" class="row g-3">
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
.product-suggestions-title {
  font-weight: 700;
  letter-spacing: -0.02em;
  margin-bottom: 1rem;
}
</style>

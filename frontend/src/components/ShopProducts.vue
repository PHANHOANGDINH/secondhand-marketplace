<script setup>
import { ref, watch } from 'vue'
import { listProducts } from '../api/product.api'
import { domainError } from '../api/domain.api'
import ProductCard from './ProductCard.vue'
import DomainFeedback from './DomainFeedback.vue'
import CollectionPager from './CollectionPager.vue'
const props = defineProps({ shopId: { type: String, required: true } })
const rows = ref([]),
  meta = ref(null),
  busy = ref(false),
  error = ref('')
let sequence = 0
async function load(page = 1) {
  const current = ++sequence
  busy.value = true
  error.value = ''
  rows.value = []
  try {
    const result = await listProducts({ shop: props.shopId, page })
    if (current === sequence) {
      rows.value = result.data
      meta.value = result.meta
    }
  } catch (failure) {
    if (current === sequence) error.value = domainError(failure)
  } finally {
    if (current === sequence) busy.value = false
  }
}
watch(
  () => props.shopId,
  () => load(),
  { immediate: true },
)
</script>
<template>
  <section class="mt-4">
    <h2 class="h4">Sản phẩm của gian hàng</h2>
    <DomainFeedback :busy="busy" :error="error" /><button
      v-if="error"
      class="btn btn-outline-secondary"
      @click="load()"
    >
      Thử lại
    </button>
    <p v-if="!busy && !error && !rows.length">Chưa có sản phẩm đang bán.</p>
    <div class="row g-3">
      <div v-for="product in rows" :key="product.id" class="col-sm-6 col-lg-3">
        <ProductCard :product="product" />
      </div>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
  </section>
</template>

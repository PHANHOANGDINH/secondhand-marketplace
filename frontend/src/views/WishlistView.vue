<script setup>
import IconButton from '../components/ui/IconButton.vue'
import { onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { listWishlist } from '../api/wishlist.api'
import { domainError } from '../api/domain.api'
import { useWishlistStore } from '../stores/wishlist'
import ProductCard from '../components/ProductCard.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
const route = useRoute(),
  router = useRouter(),
  wishlist = useWishlistStore()
const rows = ref([]),
  meta = ref(null),
  busy = ref(false),
  error = ref('')
let sequence = 0
async function load() {
  const current = ++sequence
  busy.value = true
  error.value = ''
  rows.value = []
  meta.value = null
  try {
    const result = await listWishlist({ page: route.query.page || '1', per_page: 20 })
    if (current !== sequence) return
    rows.value = result.data
    meta.value = result.meta
    if (!rows.value.length && Number(route.query.page) > 1) await router.replace('/wishlist')
  } catch (failure) {
    if (current === sequence) error.value = domainError(failure)
  } finally {
    if (current === sequence) busy.value = false
  }
}
watch(() => [route.query.page, wishlist.revision], load, { immediate: true })
onUnmounted(() => {
  ++sequence
})
</script>
<template>
  <section class="buyer-flow">
    <PageHeader
      title="Sản phẩm yêu thích"
      description="Những món đồ bạn đã lưu để xem lại. Việc lưu không giữ hàng hoặc giữ giá."
    />
    <IconButton class="btn btn-outline-secondary mb-4" :disabled="busy" @click="load" icon="refresh" label="Tải lại" />
    <DomainFeedback :error="error" />
    <div v-if="busy" class="row g-4" aria-label="Đang tải sản phẩm yêu thích" aria-busy="true">
      <div v-for="n in 8" :key="n" class="col-6 col-lg-3">
        <div class="card p-3 placeholder-glow" aria-hidden="true">
          <div class="placeholder w-100 rounded" style="aspect-ratio: 1" />
          <span class="placeholder col-8 mt-3" />
        </div>
      </div>
    </div>
    <EmptyState
      v-else-if="!error && !rows.length"
      title="Bạn chưa lưu sản phẩm nào."
      description="Lưu những món đồ bạn quan tâm để dễ dàng tìm lại."
      ><RouterLink class="btn btn-primary" to="/products">Khám phá sản phẩm</RouterLink></EmptyState
    >
    <div v-else-if="!error" class="row g-4">
      <div v-for="product in rows" :key="product.id" class="col-6 col-lg-3">
        <ProductCard :product="product" />
      </div>
    </div>
    <CollectionPager
      :meta="meta"
      :busy="busy"
      @page="(page) => router.push({ path: '/wishlist', query: { page: String(page) } })"
    />
  </section>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getShop } from '../api/shop.api'
import { domainError } from '../api/domain.api'
import DomainFeedback from '../components/DomainFeedback.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import ReportDialog from '../components/ReportDialog.vue'
import ShopProducts from '../components/ShopProducts.vue'
const route = useRoute(),
  shop = ref(null),
  busy = ref(false),
  error = ref('')
let requestId = 0
async function load(slug) {
  const current = ++requestId
  shop.value = null
  error.value = ''
  busy.value = true
  try {
    const result = await getShop(slug)
    if (current === requestId) shop.value = result
  } catch (failure) {
    if (current === requestId) error.value = domainError(failure)
  } finally {
    if (current === requestId) busy.value = false
  }
}
watch(() => route.params.slug, load, { immediate: true })
</script>
<template>
  <section class="buyer-flow">
    <RouterLink to="/shops" class="buyer-back-link">← Tất cả gian hàng</RouterLink>
    <DomainFeedback :busy="busy" :error="error" />
    <button v-if="error" class="btn btn-outline-secondary" @click="load(route.params.slug)">
      Tải lại
    </button>
    <template v-if="shop"
      ><PageHeader :title="shop.name" eyebrow="Gian hàng trên sàn mua bán" />
      <p class="public-shop-description">{{ shop.description }}</p>
      <ReportDialog
        target-type="SHOP"
        :target-id="shop.id"
        label="Báo cáo gian hàng"
        class="mb-3" />
      <ShopProducts :shop-id="shop.id"
    /></template>
  </section>
</template>

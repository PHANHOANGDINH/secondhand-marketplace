<script setup>
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { getShop } from '../api/shop.api'
import { domainError } from '../api/domain.api'
import DomainFeedback from '../components/DomainFeedback.vue'
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
  <section>
    <RouterLink to="/shops">← Tất cả gian hàng</RouterLink>
    <DomainFeedback :busy="busy" :error="error" />
    <button v-if="error" class="btn btn-outline-secondary" @click="load(route.params.slug)">
      Tải lại
    </button>
    <template v-if="shop"
      ><h1 class="h3 mt-3">{{ shop.name }}</h1>
      <p class="text-break" style="white-space: pre-wrap">{{ shop.description }}</p>
      <ShopProducts :shop-id="shop.id"
    /></template>
  </section>
</template>

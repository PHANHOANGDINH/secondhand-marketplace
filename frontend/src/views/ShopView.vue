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
      ><div class="shop-hero">
        <span class="shop-hero-avatar" aria-hidden="true">{{
          shop.name.trim().charAt(0).toLocaleUpperCase('vi-VN')
        }}</span>
        <div class="shop-hero-body">
          <PageHeader :title="shop.name" eyebrow="Gian hàng trên sàn mua bán" />
          <p class="public-shop-description">{{ shop.description }}</p>
          <ReportDialog target-type="SHOP" :target-id="shop.id" label="Báo cáo gian hàng" />
        </div>
      </div>
      <ShopProducts :shop-id="shop.id"
    /></template>
  </section>
</template>
<style scoped>
.shop-hero {
  display: flex;
  gap: var(--space-6);
  align-items: flex-start;
  padding: var(--space-6);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.shop-hero-avatar {
  flex: none;
  display: grid;
  place-items: center;
  width: 72px;
  height: 72px;
  border-radius: var(--radius-xl);
  background: var(--primary);
  color: var(--accent);
  font-size: 1.75rem;
  font-weight: 800;
}
.shop-hero-body {
  min-width: 0;
  flex: 1;
}
.shop-hero :deep(.buyer-page-header) {
  margin-bottom: var(--space-3);
  padding-bottom: 0;
  border-bottom: 0;
}
.shop-hero :deep(.buyer-page-header h1) {
  font-size: clamp(1.5rem, 3vw, 2.1rem);
}
.shop-hero .public-shop-description {
  margin-bottom: var(--space-4);
}
@media (max-width: 575px) {
  .shop-hero {
    flex-direction: column;
    gap: var(--space-4);
    padding: var(--space-4);
  }
  .shop-hero-avatar {
    width: 56px;
    height: 56px;
    font-size: 1.35rem;
  }
}
</style>

<script setup>
import { onMounted, ref } from 'vue'
import api from '../api/axios'
import HomeHero from '../components/home/HomeHero.vue'
import HomeLiveAuctions from '../components/home/HomeLiveAuctions.vue'
import HomeDiscovery from '../components/home/HomeDiscovery.vue'
import ProductSuggestions from '../components/ProductSuggestions.vue'
import { brandLine } from '../config/brand'

const status = ref('checking')
async function checkHealth() {
  status.value = 'checking'
  try {
    const { data } = await api.get('/api/v1/health')
    status.value = data.status === 'ok' ? 'ok' : 'error'
  } catch {
    status.value = 'error'
  }
}
onMounted(checkHealth)
</script>

<template>
  <h1 class="visually-hidden">{{ brandLine }}</h1>
  <HomeHero />
  <HomeLiveAuctions />
  <ProductSuggestions title="Gợi ý cho bạn" :limit="8" />
  <ProductSuggestions
    title="Tin mới đăng"
    latest
    :limit="8"
    more-to="/products"
    more-label="Xem tất cả tin"
  />
  <HomeDiscovery default-mode="video" />
  <section class="marketplace-entry" aria-labelledby="marketplace-entry-title">
    <div class="marketplace-entry-copy">
      <p class="marketplace-entry-eyebrow">Tiếp tục khám phá</p>
      <h2 id="marketplace-entry-title">Tìm món đồ dành cho bạn.</h2>
      <p>Mua và bán đồ đã qua sử dụng từ các gian hàng được kiểm duyệt.</p>
      <div class="d-flex flex-wrap gap-2 mb-4">
        <RouterLink to="/products" class="btn btn-primary">Xem sản phẩm</RouterLink>
        <RouterLink to="/shops" class="btn btn-outline-primary">Khám phá gian hàng</RouterLink>
      </div>
    </div>
    <p role="status" aria-live="polite">
      {{
        status === 'checking'
          ? 'Đang kiểm tra kết nối…'
          : status === 'ok'
            ? 'Kết nối cửa hàng: hoạt động'
            : 'Chưa kết nối được cửa hàng.'
      }}
    </p>
    <button v-if="status === 'error'" class="btn btn-outline-primary" @click="checkHealth">
      Thử lại
    </button>
  </section>
</template>

<style scoped>
.marketplace-entry {
  position: relative;
  padding: clamp(1.5rem, 4vw, 3rem);
  margin-bottom: 2rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.marketplace-entry-copy {
  max-width: 640px;
}
.marketplace-entry-eyebrow {
  font-size: 0.7rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  font-weight: 700;
  color: var(--bs-primary);
  margin-bottom: 1rem;
}
.marketplace-entry h2 {
  font-size: clamp(1.7rem, 3vw, 2.6rem);
  letter-spacing: -0.035em;
  font-weight: 700;
  margin-bottom: 1rem;
}
.marketplace-entry-copy > p:not(.marketplace-entry-eyebrow) {
  color: var(--bs-secondary-color);
  line-height: 1.75;
  margin-bottom: 1.5rem;
}
.marketplace-entry .btn {
  padding: 0.75rem 1.15rem;
  border-radius: 0.75rem;
  font-weight: 600;
}
.marketplace-entry [role='status'] {
  font-size: 0.8rem;
  color: var(--bs-secondary-color);
  margin-bottom: 0;
}
</style>

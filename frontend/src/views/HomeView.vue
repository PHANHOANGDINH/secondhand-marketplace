<script setup>
import { onMounted, ref } from 'vue'
import api from '../api/axios'
import HomeHero from '../components/home/HomeHero.vue'
import HomeTabs from '../components/home/HomeTabs.vue'
import { useFirstTime } from '../composables/useFirstTime'
import { areaPickerOpen } from '../composables/useArea'
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
// A signed-in user is asked for their area once, on the home page, after the welcome tour is out of the way.
const welcome = useFirstTime('welcome'),
  areaGuide = useFirstTime('area')
onMounted(() => {
  if (areaGuide.pending.value && !welcome.pending.value) {
    areaGuide.markSeen()
    areaPickerOpen.value = true
  }
})
</script>

<template>
  <h1 class="visually-hidden">{{ brandLine }}</h1>
  <HomeHero />
  <HomeTabs />
  <section class="marketplace-entry" aria-labelledby="marketplace-entry-title">
    <div class="marketplace-entry-copy">
      <h2 id="marketplace-entry-title">Tìm món đồ dành cho bạn.</h2>
      <p>Mua và bán đồ đã qua sử dụng từ các gian hàng được kiểm duyệt.</p>
      <div class="marketplace-entry-actions">
        <RouterLink to="/products" class="btn btn-primary">Xem sản phẩm</RouterLink>
        <RouterLink to="/shops" class="btn btn-outline-primary">Khám phá gian hàng</RouterLink>
      </div>
    </div>
    <div class="marketplace-entry-status">
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
    </div>
  </section>
</template>

<style scoped>
/* A slim closing band: where to go next, not a second hero. */
.marketplace-entry {
  display: grid;
  gap: var(--space-4);
  padding: var(--space-5);
  margin-bottom: var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.marketplace-entry h2 {
  margin: 0 0 var(--space-2);
  font-family: var(--font-display);
  font-size: 1.3rem;
  font-weight: 600;
  line-height: 1.25;
}
.marketplace-entry-copy > p {
  margin: 0 0 var(--space-4);
  max-width: 52ch;
  color: var(--muted);
  line-height: 1.6;
}
.marketplace-entry-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
.marketplace-entry .btn {
  min-height: var(--control-height);
  display: inline-flex;
  align-items: center;
  padding-inline: var(--space-5);
  border-radius: var(--radius-pill);
  font-weight: 600;
}
.marketplace-entry-status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
  font-size: var(--font-size-small);
  color: var(--muted);
}
.marketplace-entry-status p {
  margin: 0;
}
@media (max-width: 575px) {
  .marketplace-entry-actions .btn {
    flex: 1 1 100%;
    justify-content: center;
  }
}
@media (min-width: 768px) {
  .marketplace-entry {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: end;
    padding: var(--space-8);
  }
  .marketplace-entry h2 {
    font-size: 1.5rem;
  }
}
</style>

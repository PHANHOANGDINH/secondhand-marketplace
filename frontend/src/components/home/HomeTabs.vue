<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useArea } from '../../composables/useArea'
import { listRecommendations } from '../../api/recommendation.api'
import { listProducts } from '../../api/product.api'
import ProductCard from '../ProductCard.vue'
import ProductGridSkeleton from '../ui/ProductGridSkeleton.vue'
import EmptyState from '../ui/EmptyState.vue'
import HomeDiscovery from './HomeDiscovery.vue'
import HomeAuctions from './HomeAuctions.vue'
// Home feed: four tabs over the existing sources. The two product tabs follow the chosen area;
// video and auction reuse the discovery component (theater, pager and deep links included).
const auth = useAuthStore(),
  route = useRoute(),
  router = useRouter()
const { code, label } = useArea()
const tabs = [
  ['foryou', 'Dành cho bạn'],
  ['latest', 'Mới nhất'],
  ['video', 'Video'],
  ['auction', 'Đấu giá'],
]
const fromRoute = () => (route.query.video || route.query.discovery === 'video' ? 'video' : '')
const tab = ref(fromRoute() || 'foryou'),
  rows = ref([]),
  busy = ref(false),
  failed = ref(false)
const products = computed(() => tab.value === 'foryou' || tab.value === 'latest')
const more = computed(() => ({
  path: '/products',
  query: { sort: 'newest', ...(code.value ? { province: code.value } : {}) },
}))
let sequence = 0
async function load() {
  if (!products.value) return
  const current = ++sequence
  busy.value = true
  failed.value = false
  rows.value = []
  const area = code.value ? { province: code.value } : {}
  try {
    const result =
      tab.value === 'foryou'
        ? await listRecommendations(12, area)
        : (await listProducts({ sort: 'newest', per_page: 12, ...area })).data
    if (current === sequence) rows.value = Array.isArray(result) ? result : []
  } catch {
    if (current === sequence) failed.value = true
  } finally {
    if (current === sequence) busy.value = false
  }
}
function choose(next) {
  tab.value = next
  // Leaving the video tab also leaves its deep link.
  if (next !== 'video' && (route.query.video || route.query.discovery)) {
    const query = { ...route.query }
    delete query.video
    delete query.discovery
    router.replace({ path: route.path, query })
  }
}
watch(() => [tab.value, code.value, auth.user?.id], load, { immediate: true })
watch(
  () => [route.query.video, route.query.discovery],
  () => {
    if (fromRoute()) tab.value = 'video'
  },
)
</script>
<template>
  <section class="home-tabs" aria-labelledby="home-tabs-title">
    <header class="home-section-head">
      <h2 id="home-tabs-title">Tin mới đăng</h2>
      <span v-if="code && products" class="home-tabs-area">Khu vực: {{ label }}</span>
    </header>
    <div class="home-tabs-bar" role="group" aria-label="Loại tin đăng">
      <button
        v-for="[key, text] in tabs"
        :key="key"
        type="button"
        class="home-tab"
        :class="{ 'is-active': tab === key }"
        :aria-pressed="tab === key"
        :data-home-tab="key"
        @click="choose(key)"
      >
        {{ text }}
      </button>
    </div>
    <template v-if="products">
      <ProductGridSkeleton v-if="busy" :count="8" label="Đang tải tin đăng…" />
      <div v-else-if="failed" role="alert" class="alert alert-danger home-failed">
        Không tải được tin đăng.
        <button type="button" class="btn btn-outline-secondary ms-2" @click="load">Tải lại</button>
      </div>
      <EmptyState
        v-else-if="!rows.length"
        :title="code ? `Chưa có tin đăng ở ${label}` : 'Chưa có tin đăng'"
        description="Thử chọn khu vực khác hoặc xem tin toàn quốc."
      />
      <template v-else>
        <div class="product-grid home-grid">
          <ProductCard v-for="product in rows" :key="product.id" :product="product" />
        </div>
        <p class="home-more">
          <RouterLink :to="more" class="btn btn-outline-primary">Xem thêm</RouterLink>
        </p>
      </template>
    </template>
    <HomeDiscovery v-else :key="tab" :default-mode="tab" hide-switch />
  </section>
  <HomeAuctions />
</template>
<style scoped>
.home-tabs {
  margin-bottom: var(--space-8);
}
.home-section-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-2);
}
.home-section-head h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.3rem;
  font-weight: 600;
  line-height: 1.25;
}
.home-tabs-area {
  color: var(--muted);
  font-size: var(--font-size-small);
  text-align: right;
}
/* Tabs are filter chips; the row scrolls sideways on a narrow phone. */
.home-tabs-bar {
  display: flex;
  gap: var(--space-2);
  margin-bottom: var(--space-3);
  overflow-x: auto;
  scrollbar-width: none;
}
.home-tabs-bar::-webkit-scrollbar {
  display: none;
}
.home-tab {
  flex: none;
  min-height: var(--control-height);
  padding: 0 var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--surface);
  color: var(--text);
  font-size: var(--font-size-body);
  font-weight: 600;
  white-space: nowrap;
  transition:
    background-color var(--transition-fast),
    color var(--transition-fast);
}
.home-tab:hover {
  background: var(--surface-2);
}
.home-tab.is-active {
  border-color: var(--primary);
  background: var(--primary);
  color: var(--on-primary);
}
.home-tab:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.home-more {
  margin: var(--space-5) 0 0;
  text-align: center;
}
.home-more .btn {
  min-width: 12rem;
  min-height: var(--control-height);
  border-radius: var(--radius-pill);
}
.home-failed .btn {
  min-height: var(--control-height);
}
.home-tabs :deep(.home-discovery) {
  margin: 0;
  padding: 0;
  border: 0;
  background: transparent;
}
@media (min-width: 768px) {
  .home-section-head h2 {
    font-size: 1.5rem;
  }
  .home-tabs {
    margin-bottom: var(--space-10);
  }
}
/* On phones the feed is eight cards (four rows) and "Xem thêm" leads to the rest. */
@media (max-width: 767px) {
  .home-grid > :nth-child(n + 9) {
    display: none;
  }
}
@media (prefers-reduced-motion: reduce) {
  .home-tab {
    transition: none;
  }
}
</style>

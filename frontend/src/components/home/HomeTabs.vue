<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useArea } from '../../composables/useArea'
import { listRecommendations } from '../../api/recommendation.api'
import { listProducts } from '../../api/product.api'
import ProductCard from '../ProductCard.vue'
import SkeletonState from '../ui/SkeletonState.vue'
import EmptyState from '../ui/EmptyState.vue'
import HomeDiscovery from './HomeDiscovery.vue'
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
    <h2 id="home-tabs-title" class="visually-hidden">Tin đăng</h2>
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
      <span v-if="code && products" class="home-tabs-area">Khu vực: {{ label }}</span>
    </div>
    <template v-if="products">
      <SkeletonState v-if="busy" :count="6" label="Đang tải tin đăng…" />
      <p v-else-if="failed" role="alert" class="alert alert-danger">
        Không tải được tin đăng.
        <button type="button" class="btn btn-sm btn-outline-secondary ms-2" @click="load">Tải lại</button>
      </p>
      <EmptyState
        v-else-if="!rows.length"
        :title="code ? `Chưa có tin đăng ở ${label}` : 'Chưa có tin đăng'"
        description="Thử chọn khu vực khác hoặc xem tin toàn quốc."
      />
      <template v-else>
        <div class="home-grid">
          <ProductCard v-for="product in rows" :key="product.id" :product="product" />
        </div>
        <p class="home-more">
          <RouterLink :to="more" class="btn btn-outline-primary">Xem thêm</RouterLink>
        </p>
      </template>
    </template>
    <HomeDiscovery v-else :key="tab" :default-mode="tab" hide-switch />
  </section>
</template>
<style scoped>
.home-tabs {
  margin-bottom: var(--space-10);
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.home-tabs-bar {
  display: flex;
  align-items: center;
  gap: var(--space-5);
  margin-bottom: var(--space-4);
  overflow-x: auto;
  scrollbar-width: none;
}
.home-tabs-bar::-webkit-scrollbar {
  display: none;
}
.home-tab {
  flex: none;
  min-height: 48px;
  padding: 0 var(--space-1);
  border: 0;
  background: transparent;
  color: #9a9ba3;
  font-size: 1.3rem;
  font-weight: 700;
  white-space: nowrap;
  transition: color var(--transition-fast);
}
.home-tab:hover {
  color: var(--text);
}
.home-tab.is-active {
  color: var(--text);
}
.home-tab:focus-visible {
  outline: var(--focus-ring);
  outline-offset: -3px;
}
.home-tabs-area {
  margin-left: auto;
  padding-right: var(--space-2);
  color: var(--muted);
  font-size: 0.82rem;
  white-space: nowrap;
}
.home-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-5);
}
.home-more {
  margin: var(--space-5) 0 var(--space-2);
  text-align: center;
}
.home-tabs :deep(.home-discovery) {
  margin: 0;
  padding: 0;
  border: 0;
}
@media (max-width: 1199px) {
  .home-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 767px) {
  .home-tabs {
    padding: var(--space-3);
  }
  .home-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-2);
  }
}
</style>

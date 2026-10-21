<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as productsApi from '../api/product.api'
import { getSellerShop } from '../api/shop.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import {
  formatPrice,
  conditionLabel,
  sellerActions,
  actionLabels,
  actionIcons,
  labelledActions,
} from '../composables/productPresentation'
import IconButton from '../components/ui/IconButton.vue'
import InventoryBadge from '../components/seller/InventoryBadge.vue'
import AuctionLockNotice from '../components/seller/AuctionLockNotice.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import ProductPhoto from '../components/ProductPhoto.vue'

// Each tab is one list filter; `count` names the matching key of the counters endpoint.
const tabs = [
  { key: 'active', label: 'Đang hiển thị', count: 'ACTIVE', filter: { status: 'ACTIVE' } },
  { key: 'sold', label: 'Hết hàng', count: 'SOLD', filter: { status: 'SOLD' } },
  { key: 'rejected', label: 'Bị gỡ', count: 'REJECTED', filter: { status: 'REJECTED' } },
  { key: 'draft', label: 'Tin nháp', count: 'DRAFT', filter: { status: 'DRAFT' } },
  { key: 'pending', label: 'Chờ xem xét', count: 'PENDING', filter: { status: 'PENDING' } },
  { key: 'hidden', label: 'Đã ẩn', count: 'HIDDEN', filter: { status: 'HIDDEN' } },
  { key: 'auction', label: 'Đang đấu giá', count: 'AUCTION', filter: { auction: 1 } },
]
const shortcuts = [
  ['/seller/vouchers', 'Voucher', 'tag'],
  ['/seller/auctions', 'Đấu giá', 'gavel'],
  ['/seller/orders', 'Đơn bán', 'box'],
]
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore()
const rows = ref([]),
  meta = ref(null),
  shop = ref(null),
  noShop = ref(false),
  counts = ref(null),
  search = ref('')
const { busy, error, errors, success, run } = useFeedback()
const tab = computed(() => tabs.find((item) => item.key === route.query.tab) || tabs[0])
const page = computed(() => Math.max(1, Number.parseInt(route.query.page, 10) || 1))
const term = computed(() => (typeof route.query.q === 'string' ? route.query.q.trim() : ''))
const initials = computed(() => (auth.user?.name || '?').trim().charAt(0).toUpperCase())
const canPost = computed(() => ['PENDING', 'ACTIVE'].includes(shop.value?.status))
const go = (query) => router.replace({ query: { ...route.query, page: undefined, ...query } })

// The counters are a convenience: the list never waits for them and the tabs work without them.
function loadCounts() {
  productsApi
    .getSellerProductCounts()
    .then((data) => (counts.value = data))
    .catch(() => (counts.value = null))
}
async function refresh() {
  const params = { ...tab.value.filter, page: page.value, search: term.value || undefined }
  let result = await productsApi.listSellerProducts(params)
  if (page.value > result.meta.last_page)
    result = await productsApi.listSellerProducts({ ...params, page: result.meta.last_page })
  rows.value = result.data
  meta.value = result.meta
}
const load = () =>
  run(async () => {
    if (!shop.value && !noShop.value)
      shop.value = await getSellerShop().catch((failure) => {
        if (failure?.response?.status !== 404) throw failure
        noShop.value = true
        return null
      })
    if (noShop.value) return
    loadCounts()
    await refresh()
  })
const action = (product, target) =>
  run(async () => {
    if (target === 'delete') await productsApi.deleteProduct(product.id)
    else await productsApi.productAction(product.id, target)
    loadCounts()
    await refresh()
  }, 'Đã cập nhật tin đăng.')

watch(
  () => [tab.value.key, page.value, term.value],
  () => {
    search.value = term.value
    load()
  },
)
onMounted(() => {
  search.value = term.value
  load()
})
</script>
<template>
  <section class="my-ads">
    <nav class="my-ads-breadcrumb" aria-label="Đường dẫn">
      <RouterLink to="/">Trang chủ</RouterLink>
      <AppIcon name="chevronRight" :size="14" />
      <span aria-current="page">Quản lý tin</span>
    </nav>
    <div class="my-ads-head">
      <h1 class="h4 m-0">Quản lý tin đăng</h1>
      <ul class="my-ads-shortcuts" aria-label="Lối tắt người bán">
        <li v-for="[to, label, icon] in shortcuts" :key="to">
          <RouterLink :to="to"><AppIcon :name="icon" :size="18" />{{ label }}</RouterLink>
        </li>
      </ul>
    </div>
    <div class="my-ads-profile">
      <span class="my-ads-avatar" aria-hidden="true">{{ initials }}</span>
      <div class="my-ads-who">
        <strong>{{ auth.user?.name }}</strong>
        <span v-if="shop" class="my-ads-shop"
          >{{ shop.name }} <StatusBadge :status="shop.status"
        /></span>
        <RouterLink v-else-if="noShop" to="/seller/shop" class="btn btn-sm btn-outline-primary" data-create-shop
          >+ Tạo cửa hàng</RouterLink
        >
      </div>
      <form class="my-ads-search" role="search" @submit.prevent="go({ q: search.trim() || undefined })">
        <label class="visually-hidden" for="my-ads-search">Tìm tin đăng của bạn</label>
        <input
          id="my-ads-search"
          v-model="search"
          type="search"
          name="search"
          class="form-control"
          maxlength="100"
          placeholder="Tìm tin đăng của bạn…"
        />
        <button class="btn btn-outline-secondary" :disabled="busy">Tìm</button>
      </form>
    </div>
    <div class="my-ads-tabs" role="group" aria-label="Lọc tin đăng theo trạng thái">
      <button
        v-for="item in tabs"
        :key="item.key"
        type="button"
        class="my-ads-tab"
        :class="{ 'is-active': tab.key === item.key }"
        :aria-pressed="tab.key === item.key"
        :data-tab="item.key"
        @click="go({ tab: item.key })"
      >
        {{ item.label }} <span v-if="counts" class="my-ads-count">({{ counts[item.count] ?? 0 }})</span>
      </button>
    </div>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div v-for="(messages, key) in errors" :key="key" class="text-danger">
      {{ messages.join(' ') }}
    </div>
    <div v-if="!busy && !error && !rows.length" class="my-ads-empty" role="status">
      <svg viewBox="0 0 160 120" width="160" height="120" aria-hidden="true">
        <rect x="26" y="34" width="84" height="64" rx="8" fill="var(--primary-soft)" stroke="var(--primary)" stroke-width="3" />
        <path d="M26 54h84" stroke="var(--primary)" stroke-width="3" />
        <path d="M40 70h40M40 82h26" stroke="var(--primary)" stroke-width="3" stroke-linecap="round" opacity=".55" />
        <circle cx="112" cy="46" r="20" fill="var(--surface)" stroke="var(--text-muted)" stroke-width="4" />
        <path d="M126 61l14 15" stroke="var(--text-muted)" stroke-width="5" stroke-linecap="round" />
        <path d="M105 46h14" stroke="var(--text-muted)" stroke-width="3" stroke-linecap="round" />
      </svg>
      <h2 class="h5">Không tìm thấy tin đăng</h2>
      <p v-if="noShop">Tạo cửa hàng để bắt đầu đăng tin.</p>
      <p v-else-if="term">Không có tin nào khớp “{{ term }}” trong mục này.</p>
      <p v-else>Bạn chưa có tin nào ở mục “{{ tab.label }}”.</p>
      <RouterLink :to="canPost ? '/seller/products/create' : '/seller/shop'" class="btn btn-primary" data-post
        >Đăng tin</RouterLink
      >
    </div>
    <ul v-if="rows.length" class="my-ads-list">
      <li v-for="product in rows" :key="product.id" class="my-ad" :data-product="product.id">
        <ProductPhoto :src="product.primary_image?.url" :alt="product.name" class="my-ad-photo" />
        <div class="my-ad-body">
          <strong class="my-ad-name">{{ product.name }}</strong>
          <span class="my-ad-price">{{ formatPrice(product.price) }}</span>
          <span class="my-ad-meta"
            ><InventoryBadge :product="product" /> <StatusBadge :status="product.status" domain="product" />
            <small
              >{{ conditionLabel(product.condition) }} · Đăng ngày
              {{ new Date(product.created_at).toLocaleDateString('vi-VN') }}</small
            ></span
          >
          <AuctionLockNotice :product="product" compact />
        </div>
        <div class="my-ad-actions">
          <IconButton
            :to="`/seller/products/${product.id}/edit`"
            class="btn btn-sm btn-outline-primary"
            :icon="product.can_edit && !product.auction_locked ? 'edit' : 'eye'"
            :label="product.can_edit && !product.auction_locked ? 'Xem / Sửa' : 'Xem'"
          />
          <IconButton
            v-for="target in sellerActions(product)"
            :key="target"
            :data-action="target"
            :class="['btn', 'btn-sm', target === 'delete' ? 'btn-outline-danger' : 'btn-outline-secondary']"
            :icon="actionIcons[target]"
            :label="actionLabels[target]"
            :show-label="labelledActions.includes(target)"
            :disabled="busy"
            @click="action(product, target)"
          />
        </div>
      </li>
    </ul>
    <CollectionPager :meta="meta" :busy="busy" @page="(target) => go({ page: target > 1 ? target : undefined })" />
  </section>
</template>
<style scoped>
.my-ads {
  max-width: 1040px;
  margin-inline: auto;
}
.my-ads-breadcrumb {
  display: flex;
  align-items: center;
  gap: var(--space-1);
  margin-bottom: var(--space-3);
  color: var(--text-muted);
  font-size: 0.85rem;
}
.my-ads-head,
.my-ads-profile {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}
.my-ads-head {
  justify-content: space-between;
}
.my-ads-shortcuts {
  display: flex;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}
.my-ads-shortcuts a {
  display: inline-flex;
  align-items: center;
  gap: var(--space-1);
  min-height: 44px;
  padding: 0 var(--space-3);
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  transition: border-color var(--transition-fast);
}
.my-ads-shortcuts a:hover {
  border-color: var(--primary);
  color: var(--primary);
}
.my-ads-profile {
  padding: var(--space-3) var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  background: var(--surface);
}
.my-ads-avatar {
  display: grid;
  flex: none;
  place-items: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--primary-soft);
  color: var(--primary);
  font-weight: 700;
}
.my-ads-who {
  display: grid;
  gap: 2px;
  min-width: 0;
  justify-items: start;
}
.my-ads-shop {
  color: var(--text-muted);
  font-size: 0.85rem;
}
.my-ads-search {
  display: flex;
  flex: 1 1 260px;
  gap: var(--space-2);
  margin-left: auto;
  max-width: 420px;
}
.my-ads-tabs {
  /* The status tabs stay under the header while the list scrolls. */
  position: sticky;
  top: 68px;
  z-index: 2;
  display: flex;
  gap: var(--space-1);
  margin-bottom: var(--space-3);
  background: var(--bg);
  border-bottom: 1px solid var(--border);
  overflow-x: auto;
  scrollbar-width: none;
}
.my-ads-tabs::-webkit-scrollbar {
  display: none;
}
.my-ads-tab {
  flex: none;
  min-height: 44px;
  padding: 0 var(--space-3);
  border: 0;
  border-bottom: 3px solid transparent;
  background: none;
  color: var(--text-muted);
  font-size: 0.8rem;
  font-weight: 700;
  text-transform: none;
  white-space: nowrap;
  transition:
    color var(--transition-fast),
    border-color var(--transition-fast);
}
.my-ads-tab:hover,
.my-ads-tab.is-active {
  color: var(--primary);
}
.my-ads-tab.is-active {
  border-bottom-color: var(--primary);
}
.my-ads-tab:focus-visible {
  outline: var(--focus-ring);
  outline-offset: -2px;
}
.my-ads-empty {
  display: grid;
  justify-items: center;
  gap: var(--space-2);
  padding: var(--space-8) var(--space-4);
  color: var(--text-muted);
  text-align: center;
}
.my-ads-empty h2 {
  margin: 0;
  color: var(--text);
}
.my-ads-list {
  display: grid;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}
.my-ad {
  display: grid;
  grid-template-columns: 72px minmax(0, 1fr) auto;
  gap: var(--space-3);
  align-items: center;
  padding: var(--space-2) var(--space-3);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  background: var(--surface);
}
.my-ad-photo {
  width: 72px;
  height: 72px;
  border-radius: var(--radius-sm);
  object-fit: cover;
}
.my-ad-body {
  display: grid;
  gap: var(--space-1);
  min-width: 0;
}
.my-ad-name {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.my-ad-price {
  color: var(--price);
  font-weight: 700;
}
.my-ad-meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2);
  color: var(--text-muted);
}
.my-ad-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: var(--space-2);
  max-width: 260px;
}
@media (max-width: 991px) {
  .my-ads-tabs {
    position: static;
  }
}
@media (max-width: 640px) {
  .my-ads-search {
    flex-basis: 100%;
    max-width: none;
  }
  .my-ad {
    grid-template-columns: 72px minmax(0, 1fr);
  }
  .my-ad-photo {
    width: 72px;
    height: 72px;
  }
  .my-ad-actions {
    grid-column: 1 / -1;
    justify-content: flex-start;
    max-width: none;
  }
}
</style>

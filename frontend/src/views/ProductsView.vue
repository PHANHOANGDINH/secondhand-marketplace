<script setup>
import IconButton from '../components/ui/IconButton.vue'
import { localizeValidationErrors } from '../utils/validationLabels'

import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { listProducts } from '../api/product.api'
import { categoryOptions, shopOptions } from '../api/catalogOptions'
import { domainError } from '../api/domain.api'
import { conditions } from '../composables/productPresentation'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import ProductCard from '../components/ProductCard.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ProductSearch from '../components/ProductSearch.vue'
import ProductFilters from '../components/ProductFilters.vue'
import AccessibleDialog from '../components/ui/AccessibleDialog.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import ProductGridSkeleton from '../components/ui/ProductGridSkeleton.vue'
import { normalizeDiscoveryQuery } from '../utils/discoveryQuery'
import { useArea } from '../composables/useArea'
const route = useRoute(),
  router = useRouter()
const form = reactive({
  search: '',
  category: '',
  condition: '',
  min_price: '',
  max_price: '',
  shop: '',
  has_video: '',
  sale_type: '',
  province: '',
  sort: 'newest',
})
const rows = ref([]),
  meta = ref(null),
  busy = ref(false),
  error = ref(''),
  errors = ref({}),
  categories = ref([]),
  shops = ref([]),
  optionError = ref('')
const drawer = ref(false)
const { provinces } = useArea()
const keywordKey = computed(() => (typeof route.query.q === 'string' ? 'q' : 'search'))
const sorts = [
  { value: 'newest', label: 'Mới nhất' },
  { value: 'oldest', label: 'Cũ nhất' },
  { value: 'rating', label: 'Đánh giá cao nhất' },
  { value: 'relevance', label: 'Phù hợp nhất' },
  { value: 'price_asc', label: 'Giá tăng dần' },
  { value: 'price_desc', label: 'Giá giảm dần' },
]
let sequence = 0
async function load() {
  const current = ++sequence
  const normalized = normalizeDiscoveryQuery(route.query)
  if (JSON.stringify(normalized) !== JSON.stringify(route.query)) {
    await router.replace({ path: '/products', query: normalized })
    return
  }
  for (const key of Object.keys(form))
    form[key] =
      typeof route.query[key === 'search' ? keywordKey.value : key] === 'string'
        ? route.query[key === 'search' ? keywordKey.value : key]
        : key === 'sort'
          ? 'newest'
          : ''
  const params = {
    ...Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')),
    page: route.query.page || '1',
  }
  if (keywordKey.value === 'q') {
    params.q = params.search
    delete params.search
  }
  if (route.query.per_page) params.per_page = route.query.per_page
  busy.value = true
  error.value = ''
  errors.value = {}
  rows.value = []
  meta.value = null
  try {
    const result = await listProducts(params)
    if (current === sequence) {
      rows.value = result.data
      meta.value = result.meta
    }
  } catch (failure) {
    if (current === sequence) {
      error.value = domainError(failure)
      errors.value = localizeValidationErrors(failure.response?.data?.errors)
    }
  } finally {
    if (current === sequence) busy.value = false
  }
}
async function apply() {
  const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''))
  if (keywordKey.value === 'q') {
    query.q = query.search
    delete query.search
  }
  if (route.query['quick-listing']) query['quick-listing'] = route.query['quick-listing']
  drawer.value = false
  await router.push({ path: '/products', query: normalizeDiscoveryQuery(query) })
}
const page = (value) =>
  router.push({ path: '/products', query: { ...route.query, page: String(value) } })
watch(() => route.query, load, { immediate: true })
onUnmounted(() => {
  ++sequence
})
onMounted(async () => {
  try {
    ;[categories.value, shops.value] = await Promise.all([categoryOptions(), shopOptions()])
  } catch {
    optionError.value = 'Chưa tải được lựa chọn bộ lọc. Vui lòng tải lại trang.'
  }
})

// Filters that are on, shown as chips that can be removed one by one (page, sort and the dialog flag are not filters).
const notFilters = ['page', 'sort', 'quick-listing', 'per_page']
const filterNames = {
  search: 'Tìm kiếm',
  q: 'Tìm kiếm',
  has_video: 'Video',
  sale_type: 'Hình thức bán',
  category: 'Danh mục',
  condition: 'Tình trạng',
  min_price: 'Giá từ',
  max_price: 'Giá đến',
  shop: 'Gian hàng',
  province: 'Khu vực',
}
const optionLabel = (list, value) =>
  list.find((option) => String(option.value) === String(value))?.label || value
function filterValue(key, value) {
  if (key === 'category') return optionLabel(categories.value, value)
  if (key === 'shop') return optionLabel(shops.value, value)
  if (key === 'province') return provinces.value.find((row) => row.code === value)?.name || value
  if (key === 'condition')
    return conditions.find((option) => option.value === value)?.label || 'Không xác định'
  if (key === 'has_video') return value === '1' ? 'Có video' : 'Không có video'
  if (key === 'sale_type') return value === 'auction' ? 'Đấu giá' : 'Bán thường'
  return value
}
const chips = computed(() =>
  Object.entries(route.query)
    .filter(([key]) => !notFilters.includes(key))
    .map(([key, value]) => ({
      key,
      text: `${filterNames[key] || 'Bộ lọc'}: ${filterValue(key, value)}`,
    })),
)
// The dialog button counts the detailed filters, not the search words (those are in the search box).
const detailCount = computed(
  () => chips.value.filter((chip) => !['search', 'q'].includes(chip.key)).length,
)
function removeChip(key) {
  const query = { ...route.query }
  delete query[key]
  delete query.page
  router.push({ path: '/products', query })
}
const summary = computed(() =>
  busy.value
    ? 'Đang tìm sản phẩm…'
    : meta.value
      ? `${new Intl.NumberFormat('vi-VN').format(meta.value.total)} sản phẩm phù hợp`
      : '',
)
</script>
<template>
  <section class="buyer-flow products-page">
    <PageHeader
      title="Sản phẩm thanh lý"
      description="Đồ đã qua sử dụng còn giá trị, tìm theo nhu cầu, tình trạng và ngân sách."
    >
      <p v-if="summary" class="products-count" role="status">{{ summary }}</p>
    </PageHeader>
    <p v-if="optionError" role="alert" class="alert alert-warning">{{ optionError }}</p>
    <form class="products-toolbar" @submit.prevent="apply">
      <div class="products-search-row">
        <ProductSearch v-model="form.search" @submit="apply" />
        <button type="submit" class="btn btn-primary products-submit" :disabled="busy">
          <AppIcon name="search" :size="18" /><span>Tìm kiếm</span>
        </button>
      </div>
      <div class="products-tools-row">
        <button
          type="button"
          class="btn btn-outline-secondary mobile-filters"
          aria-controls="discovery-dialog"
          :aria-expanded="drawer"
          @click="drawer = true"
        >
          <AppIcon name="sliders" :size="18" /> Bộ lọc<span v-if="detailCount" class="products-badge"
            ><span class="visually-hidden"> đang bật: </span>{{ detailCount }}</span
          >
        </button>
        <DomainField
          v-model="form.sort"
          name="sort"
          label="Sắp xếp"
          type="select"
          :options="sorts"
          :errors="errors.sort"
        />
        <IconButton
          type="button"
          class="btn btn-outline-secondary products-refresh"
          :disabled="busy"
          icon="refresh"
          label="Tải lại"
          @click="load"
        />
      </div>
      <p v-if="errors.q || errors.search" role="alert" class="text-danger small mb-0">
        {{ (errors.q || errors.search)[0] }}
      </p>
    </form>
    <AccessibleDialog
      v-if="drawer"
      id="discovery-dialog"
      class="ui-sheet"
      title="Lọc sản phẩm"
      @dismiss="drawer = false"
    >
      <form @submit.prevent="apply">
        <ProductFilters
          :model-value="form"
          @update:model-value="(value) => Object.assign(form, value)"
          :categories="categories"
          :shops="shops"
          :errors="errors"
        />
        <div class="d-flex gap-2 mt-3">
          <RouterLink to="/products" class="btn btn-outline-secondary" @click="drawer = false"
            >Xóa bộ lọc</RouterLink
          >
          <button class="btn btn-primary flex-grow-1" type="submit">Áp dụng bộ lọc</button>
        </div>
      </form>
    </AccessibleDialog>
    <div class="products-layout">
      <aside v-if="!drawer" class="products-sidebar" aria-label="Bộ lọc sản phẩm">
        <form class="products-filters" @submit.prevent="apply">
          <h2 class="products-filters-title"><AppIcon name="sliders" :size="18" /> Bộ lọc</h2>
          <ProductFilters
            :model-value="form"
            @update:model-value="(value) => Object.assign(form, value)"
            :categories="categories"
            :shops="shops"
            :errors="errors"
          />
          <button class="btn btn-primary w-100" type="submit" :disabled="busy">
            Áp dụng bộ lọc
          </button>
          <RouterLink to="/products" class="btn btn-outline-secondary w-100">Xóa bộ lọc</RouterLink>
        </form>
      </aside>
      <div class="products-results">
        <div v-if="chips.length" class="buyer-filter-active" aria-label="Bộ lọc đang áp dụng">
          <button
            v-for="chip in chips"
            :key="chip.key"
            type="button"
            class="products-chip"
            :aria-label="`Bỏ bộ lọc ${chip.text}`"
            @click="removeChip(chip.key)"
          >
            <bdi class="products-chip-text">{{ chip.text }}</bdi
            ><AppIcon name="close" :size="14" />
          </button>
          <RouterLink v-if="chips.length > 1" to="/products" class="products-clear"
            >Xóa tất cả</RouterLink
          >
        </div>
        <DomainFeedback :error="error" />
        <button v-if="error" type="button" class="btn btn-outline-primary mb-3" @click="load">
          Tải lại
        </button>
        <ProductGridSkeleton v-if="busy" :count="8" label="Đang tải sản phẩm" />
        <EmptyState
          v-if="!busy && !error && !rows.length"
          title="Không tìm thấy sản phẩm phù hợp"
          description="Thử một từ khóa khác hoặc mở rộng bộ lọc để tìm thêm món đồ phù hợp."
          ><RouterLink to="/products" class="btn btn-outline-primary"
            >Xem tất cả sản phẩm</RouterLink
          ></EmptyState
        >
        <div v-if="rows.length" class="product-grid">
          <ProductCard v-for="product in rows" :key="product.id" :product="product" />
        </div>
        <CollectionPager :meta="meta" :busy="busy" @page="page" />
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Page header: the title, then how many items match. */
.products-page :deep(.buyer-page-header) {
  align-items: flex-end;
  gap: var(--space-2) var(--space-6);
  margin-bottom: var(--space-4);
}
.products-page :deep(.buyer-page-header h1) {
  margin-bottom: var(--space-1);
  font-family: var(--font-display);
  font-size: 1.6rem;
  font-weight: 600;
  line-height: 1.2;
  letter-spacing: -0.01em;
}
.products-page :deep(.buyer-page-description) {
  font-size: var(--font-size-body);
  line-height: 1.5;
}
.products-count {
  margin: 0;
  font-weight: 600;
  color: var(--text);
  font-variant-numeric: tabular-nums;
}
/* Search and sort stay on top; on phones filters open in a sheet. */
.products-toolbar {
  display: grid;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
  padding: var(--space-3);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.products-search-row {
  display: flex;
  gap: var(--space-2);
}
.products-search-row :deep(.product-search) {
  flex: 1;
}
.products-search-row :deep(input[type='search']) {
  min-height: var(--control-height);
  border-radius: var(--radius-pill);
}
.products-submit {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  flex: none;
  min-height: var(--control-height);
  border-radius: var(--radius-pill);
}
.products-submit span {
  display: none;
}
.products-tools-row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: end;
  gap: var(--space-2);
}
.products-tools-row :deep(.mb-3) {
  min-width: 0;
  margin: 0 !important;
}
.products-tools-row :deep(.form-label) {
  margin: 0 0 var(--space-1);
  font-size: var(--font-size-small);
  font-weight: 600;
  color: var(--muted);
}
.products-tools-row :deep(.form-select) {
  min-height: var(--control-height);
  border-radius: var(--radius-pill);
}
.mobile-filters {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  min-height: var(--control-height);
  border-radius: var(--radius-pill);
}
.products-badge {
  display: inline-grid;
  place-items: center;
  min-width: 1.4rem;
  height: 1.4rem;
  padding: 0 var(--space-1);
  border-radius: var(--radius-pill);
  background: var(--primary);
  color: var(--on-primary);
  font-size: 0.75rem;
  font-weight: 700;
}
.products-refresh {
  display: none !important;
  min-width: var(--control-height);
  min-height: var(--control-height);
  border-radius: var(--radius-pill);
}
/* Removable chips: the whole chip is the button (44px tall touch area). */
.buyer-filter-active {
  align-items: center;
  margin-bottom: var(--space-3);
}
.products-chip {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  max-width: 100%;
  min-height: var(--control-height);
  padding: 0 var(--space-3) 0 var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--primary-soft);
  color: var(--primary-ink);
  font-size: var(--font-size-small);
  font-weight: 600;
  text-align: left;
  transition: border-color var(--transition-fast);
}
.products-chip-text {
  display: block;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.products-chip :deep(svg) {
  flex: none;
}
.products-chip:hover {
  border-color: var(--primary);
}
.products-chip:focus-visible,
.products-clear:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.products-clear {
  display: inline-flex;
  align-items: center;
  min-height: var(--control-height);
  padding: 0 var(--space-2);
  font-size: var(--font-size-small);
  font-weight: 600;
}
.products-layout {
  display: block;
}
.products-sidebar {
  display: none;
}
.products-results {
  min-width: 0;
}
@media (min-width: 576px) {
  .products-submit span {
    display: inline;
  }
}
@media (min-width: 768px) {
  .products-page :deep(.buyer-page-header h1) {
    font-size: 2rem;
  }
  .products-toolbar {
    padding: var(--space-4);
  }
}
/* Desktop: filters become a column beside the results. */
@media (min-width: 992px) {
  .products-layout {
    display: grid;
    grid-template-columns: 17.5rem minmax(0, 1fr);
    gap: var(--space-6);
    align-items: start;
  }
  .products-sidebar {
    display: block;
  }
  .mobile-filters {
    display: none;
  }
  .products-refresh {
    display: inline-flex !important;
  }
  .products-toolbar {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
  }
  .products-tools-row {
    grid-template-columns: auto auto;
    align-items: center;
  }
  .products-tools-row :deep(.mb-3) {
    grid-column: 2;
    display: flex;
    align-items: center;
    gap: var(--space-2);
  }
  .products-tools-row :deep(.form-label) {
    margin: 0;
  }
  .products-tools-row :deep(.form-select) {
    width: 14rem;
  }
  .products-filters {
    display: grid;
    gap: var(--space-3);
    padding: var(--space-4);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    background: var(--surface);
  }
  .products-filters-title {
    display: flex;
    align-items: center;
    gap: var(--space-2);
    margin: 0;
    font-family: var(--font-ui);
    font-size: var(--font-size-section);
    font-weight: 600;
  }
  .products-filters :deep(.filter-chips) {
    flex-direction: column;
    flex-wrap: nowrap;
  }
  .products-filters :deep(.filter-chip) {
    flex: none;
    width: 100%;
  }
  .products-filters :deep(.filter-chip .form-select),
  .products-filters :deep(.filter-chip .form-control) {
    width: 100%;
    max-width: none;
  }
}
</style>

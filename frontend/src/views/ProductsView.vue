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
import { normalizeDiscoveryQuery } from '../utils/discoveryQuery'
import { useArea } from '../composables/useArea'
import FilterSheet from '../components/ui/FilterSheet.vue'
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
// Which detailed filters are on (search and sort stay visible above the card).
const filterNames = {
  category: 'Danh mục',
  condition: 'Tình trạng',
  shop: 'Gian hàng',
  min_price: 'Giá từ',
  max_price: 'Giá đến',
  has_video: 'Video',
  sale_type: 'Hình thức bán',
  province: 'Khu vực',
}
const activeFilters = computed(() =>
  Object.keys(filterNames)
    .filter((key) => route.query[key] !== undefined && route.query[key] !== '')
    .map((key) => ({ label: filterNames[key], value: true })),
)
</script>
<template>
  <section class="buyer-flow">
    <PageHeader
      title="Sản phẩm thanh lý"
      description="Khám phá những món đồ còn giá trị. Tìm theo nhu cầu, tình trạng và ngân sách của bạn."
    />
    <p v-if="optionError" role="alert" class="alert alert-warning">{{ optionError }}</p>
    <form class="my-3 buyer-filter discovery-toolbar" @submit.prevent="apply">
      <div class="discovery-top">
        <ProductSearch v-model="form.search" @submit="apply" />
        <DomainField
          v-model="form.sort"
          name="sort"
          label="Sắp xếp"
          type="select"
          :options="sorts"
          :errors="errors.sort"
        />
        <button type="submit" class="btn btn-primary" :disabled="busy">Tìm / Lọc</button>
        <button
          type="button"
          class="btn btn-outline-secondary mobile-filters"
          aria-controls="discovery-dialog"
          :aria-expanded="drawer"
          @click="drawer = true"
        >
          <AppIcon name="sliders" :size="18" /> Bộ lọc
        </button>
      </div>
      <p v-if="errors.q || errors.search" role="alert" class="text-danger small">
        {{ (errors.q || errors.search)[0] }}
      </p>
      <div v-if="!drawer" class="desktop-filters">
        <FilterSheet :active="activeFilters">
          <ProductFilters
            :model-value="form"
            @update:model-value="(value) => Object.assign(form, value)"
            :categories="categories"
            :shops="shops"
            :errors="errors"
          />
        </FilterSheet>
      </div>
      <div class="discovery-summary d-flex flex-wrap align-items-center gap-2 mt-3">
        <p class="discovery-count mb-0 me-auto" role="status">
          {{
            busy
              ? 'Đang tìm sản phẩm…'
              : meta
                ? `${meta.total} sản phẩm phù hợp`
                : 'Khám phá sản phẩm'
          }}
        </p>
        <RouterLink to="/products" class="btn btn-outline-secondary">Xóa bộ lọc</RouterLink>
        <IconButton type="button" class="btn btn-outline-secondary" :disabled="busy" @click="load" icon="refresh" label="Tải lại" />
      </div>
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
        <button class="btn btn-primary mt-3 w-100" type="submit">Áp dụng bộ lọc</button>
      </form>
    </AccessibleDialog>
    <div
      v-if="
        Object.keys(route.query).some(
          (key) =>
            key !== 'page' && key !== 'sort' && key !== 'quick-listing' && key !== 'per_page',
        )
      "
      class="buyer-filter-active"
      aria-label="Bộ lọc đang áp dụng"
    >
      <template v-for="(value, key) in route.query" :key="key"
        ><span
          v-if="key !== 'page' && key !== 'sort' && key !== 'quick-listing' && key !== 'per_page'"
          >{{
            {
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
            }[key] || 'Bộ lọc'
          }}:
          {{
            key === 'category'
              ? categories.find((option) => String(option.value) === String(value))?.label || value
              : key === 'shop'
                ? shops.find((option) => String(option.value) === String(value))?.label || value
                : key === 'province'
                  ? provinces.find((row) => row.code === value)?.name || value
                : key === 'condition'
                  ? conditions.find((option) => option.value === value)?.label || 'Không xác định'
                  : key === 'has_video'
                    ? value === '1'
                      ? 'Có video'
                      : 'Không có video'
                    : key === 'sale_type'
                      ? value === 'auction'
                        ? 'Đấu giá'
                        : 'Bán thường'
                      : value
          }}</span
        ></template
      >
    </div>
    <DomainFeedback :error="error" />
    <div v-if="busy" class="row g-4" aria-label="Đang tải sản phẩm" aria-busy="true">
      <div v-for="index in 8" :key="index" class="col-6 col-lg-3">
        <div class="card p-3 placeholder-glow" aria-hidden="true">
          <div class="placeholder rounded w-100" style="aspect-ratio: 1" />
          <span class="placeholder col-9 mt-3" /><span class="placeholder col-6 mt-2" />
        </div>
      </div>
    </div>
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Không tìm thấy sản phẩm phù hợp"
      description="Thử một từ khóa khác hoặc mở rộng bộ lọc để tìm thêm món đồ phù hợp."
      ><RouterLink to="/products" class="btn btn-outline-primary"
        >Xem tất cả sản phẩm</RouterLink
      ></EmptyState
    >
    <div class="row g-4">
      <div v-for="product in rows" :key="product.id" class="col-6 col-lg-3">
        <ProductCard :product="product" />
      </div>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="page" />
  </section>
</template>

<style scoped>
.discovery-top {
  display: flex;
  align-items: end;
  gap: 1rem;
  flex-wrap: wrap;
}
.discovery-top :deep(.product-search) {
  min-width: 200px;
}
.discovery-toolbar {
  padding: var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.discovery-top :deep(.mb-3) {
  margin-bottom: 0 !important;
}
.desktop-filters {
  margin-top: var(--space-4);
  padding-top: var(--space-4);
  border-top: 1px solid var(--border);
}
.discovery-summary {
  padding-top: var(--space-3);
  border-top: 1px solid var(--border);
}
.discovery-count {
  font-weight: 700;
  color: var(--text);
}
.mobile-filters {
  display: none;
}
@media (max-width: 767px) {
  .desktop-filters {
    display: none;
  }
  .mobile-filters {
    display: inline-flex;
  }
  .discovery-top :deep(.product-search) {
    flex-basis: 100%;
  }
  .discovery-top {
    gap: 0.65rem;
  }
}
</style>

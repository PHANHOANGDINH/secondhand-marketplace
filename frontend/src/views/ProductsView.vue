<script setup>
import { onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { listProducts } from '../api/product.api'
import { categoryOptions, shopOptions } from '../api/catalogOptions'
import { domainError } from '../api/domain.api'
import { conditions } from '../composables/productPresentation'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import ProductCard from '../components/ProductCard.vue'
const route = useRoute(),
  router = useRouter()
const form = reactive({
  search: '',
  category: '',
  condition: '',
  min_price: '',
  max_price: '',
  shop: '',
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
const all = [{ value: '', label: 'Tất cả' }]
const sorts = [
  { value: 'newest', label: 'Mới nhất' },
  { value: 'price_asc', label: 'Giá tăng dần' },
  { value: 'price_desc', label: 'Giá giảm dần' },
]
let sequence = 0
async function load() {
  const current = ++sequence
  for (const key of Object.keys(form))
    form[key] =
      typeof route.query[key] === 'string' ? route.query[key] : key === 'sort' ? 'newest' : ''
  const params = {
    ...Object.fromEntries(Object.entries(form).filter(([, value]) => value !== '')),
    page: route.query.page || '1',
  }
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
      errors.value = failure.response?.data?.errors || {}
    }
  } finally {
    if (current === sequence) busy.value = false
  }
}
async function apply() {
  const query = Object.fromEntries(Object.entries(form).filter(([, value]) => value !== ''))
  await router.push({ path: '/products', query })
}
const page = (value) =>
  router.push({ path: '/products', query: { ...route.query, page: String(value) } })
watch(() => route.query, load, { immediate: true })
onMounted(async () => {
  try {
    ;[categories.value, shops.value] = await Promise.all([categoryOptions(), shopOptions()])
  } catch {
    optionError.value = 'Chưa tải được lựa chọn bộ lọc. Vui lòng tải lại trang.'
  }
})
</script>
<template>
  <section>
    <h1 class="h3">Sản phẩm thanh lý</h1>
    <p v-if="optionError" role="alert" class="alert alert-warning">{{ optionError }}</p>
    <form class="row g-2 my-3" @submit.prevent="apply">
      <div class="col-md-6">
        <DomainField
          v-model="form.search"
          name="search"
          label="Tìm theo tên hoặc mô tả"
          :maxlength="100"
          :errors="errors.search"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="form.category"
          name="category"
          label="Danh mục"
          type="select"
          :options="[...all, ...categories]"
          :errors="errors.category"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="form.condition"
          name="condition"
          label="Tình trạng"
          type="select"
          :options="[...all, ...conditions]"
          :errors="errors.condition"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="form.min_price"
          name="min_price"
          label="Giá từ (VND)"
          :errors="errors.min_price"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="form.max_price"
          name="max_price"
          label="Giá đến (VND)"
          :errors="errors.max_price"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="form.shop"
          name="shop"
          label="Gian hàng"
          type="select"
          :options="[...all, ...shops]"
          :errors="errors.shop"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="form.sort"
          name="sort"
          label="Sắp xếp"
          type="select"
          :options="sorts"
          :errors="errors.sort"
        />
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary" :disabled="busy">Tìm / Lọc</button
        ><RouterLink to="/products" class="btn btn-outline-secondary">Xóa bộ lọc</RouterLink
        ><button type="button" class="btn btn-outline-secondary" :disabled="busy" @click="load">
          Tải lại
        </button>
      </div>
    </form>
    <DomainFeedback :busy="busy" :error="error" />
    <p v-if="!busy && !error && !rows.length">Không tìm thấy sản phẩm.</p>
    <div class="row g-3">
      <div v-for="product in rows" :key="product.id" class="col-sm-6 col-lg-3">
        <ProductCard :product="product" />
      </div>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="page" />
  </section>
</template>

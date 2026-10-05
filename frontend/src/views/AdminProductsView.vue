<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import * as productsApi from '../api/product.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice, conditionLabel, statuses } from '../composables/productPresentation'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import ProductGallery from '../components/ProductGallery.vue'
const filters = reactive({ search: '', status: '', shop: '', category: '' })
const rows = ref([]),
  meta = ref(null),
  page = ref(1),
  selected = ref(null),
  reason = ref('')
const { busy, error, errors, success, run } = useFeedback()
const transitions = { PENDING: ['ACTIVE', 'REJECTED'], ACTIVE: ['HIDDEN'], SOLD: ['HIDDEN'] }
const actions = computed(() =>
  selected.value?.can_moderate ? transitions[selected.value.status] || [] : [],
)
const labels = { ACTIVE: 'Duyệt', REJECTED: 'Từ chối', HIDDEN: 'Ẩn sản phẩm' }
async function refresh() {
  const params = {
    ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
    page: page.value,
  }
  let result = await productsApi.listAdminProducts(params)
  if (page.value > result.meta.last_page) {
    page.value = result.meta.last_page
    result = await productsApi.listAdminProducts({ ...params, page: page.value })
  }
  rows.value = result.data
  meta.value = result.meta
}
const load = (target = 1) =>
  run(async () => {
    page.value = target
    await refresh()
  })
const show = (id) =>
  run(async () => {
    selected.value = await productsApi.getAdminProduct(id)
    reason.value = ''
  })
const moderate = (status) =>
  run(async () => {
    selected.value = await productsApi.moderateProduct(selected.value.id, {
      expected_status: selected.value.status,
      status,
      reason: reason.value,
    })
    reason.value = ''
    await refresh()
  }, 'Đã ghi nhận kiểm duyệt và audit.')
onMounted(() => load())
</script>
<template>
  <section>
    <h1 class="h3">Kiểm duyệt sản phẩm</h1>
    <form class="row g-2 my-3" @submit.prevent="load()">
      <div class="col-md-4">
        <DomainField
          v-model="filters.search"
          name="search"
          label="Tìm kiếm"
          :maxlength="100"
          :errors="errors.search"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="filters.status"
          name="status_filter"
          label="Trạng thái"
          type="select"
          :options="[
            { value: '', label: 'Tất cả' },
            ...statuses.map((value) => ({ value, label: value })),
          ]"
          :errors="errors.status"
        />
      </div>
      <div class="col-md-2">
        <DomainField
          v-model="filters.shop"
          name="shop_filter"
          label="Mã gian hàng"
          :errors="errors.shop"
        />
      </div>
      <div class="col-md-3">
        <DomainField
          v-model="filters.category"
          name="category_filter"
          label="Mã danh mục"
          :errors="errors.category"
        />
      </div>
      <div><button class="btn btn-primary" :disabled="busy">Lọc / Tải lại</button></div>
    </form>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div
      v-for="key in ['images', 'product', 'stock_quantity', 'category_id', 'expected_status']"
      :key="key"
      class="text-danger"
    >
      <div v-for="message in errors[key] || []" :key="message">{{ message }}</div>
    </div>
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Sản phẩm</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in rows" :key="product.id">
                <td>
                  {{ product.name
                  }}<small class="d-block"
                    >{{ product.shop?.name }} · {{ product.category?.name }}</small
                  ><span v-if="product.archived" class="badge text-bg-secondary">Đã xóa mềm</span>
                </td>
                <td>{{ product.status }}</td>
                <td>
                  <button
                    class="btn btn-sm btn-outline-primary"
                    :disabled="busy"
                    @click="show(product.id)"
                  >
                    Xem
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="!busy && !error && !rows.length">Không có sản phẩm phù hợp.</p>
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
      <aside v-if="selected" class="col-lg-6">
        <h2 class="h4">{{ selected.name }}</h2>
        <p>
          {{ selected.status }} · {{ formatPrice(selected.price) }} ·
          {{ conditionLabel(selected.condition) }} · Tồn {{ selected.stock_quantity }}
        </p>
        <p>{{ selected.shop?.name }} / {{ selected.category?.name }}</p>
        <ProductGallery :images="selected.images" :name="selected.name" />
        <p class="text-break mt-3" style="white-space: pre-wrap">{{ selected.description }}</p>
        <p v-if="selected.moderation_reason">Lý do gần nhất: {{ selected.moderation_reason }}</p>
        <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="show(selected.id)">
          Tải lại chi tiết</button
        ><template v-if="actions.length"
          ><DomainField
            v-model="reason"
            name="reason"
            label="Lý do kiểm duyệt"
            type="textarea"
            required
            :maxlength="1000"
            :errors="errors.reason"
          />
          <div class="d-flex gap-2">
            <button
              v-for="target in actions"
              :key="target"
              :data-target="target"
              class="btn btn-outline-primary"
              :disabled="busy || !reason.trim()"
              @click="moderate(target)"
            >
              {{ labels[target] }}
            </button>
          </div></template
        >
        <p v-else>Không có thao tác kiểm duyệt hợp lệ.</p>
      </aside>
    </div>
  </section>
</template>

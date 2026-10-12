<script setup>
import InventoryBadge from '../components/seller/InventoryBadge.vue'
import { getStatusLabel } from '../utils/labels'
import { useSellerIdentity } from '../components/seller/useSellerIdentity'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'

import { onMounted, ref } from 'vue'
import * as productsApi from '../api/product.api'
import { getSellerShop } from '../api/shop.api'
import { useFeedback } from '../composables/useFeedback'
import {
  formatPrice,
  conditionLabel,
  statuses,
  sellerActions,
  actionLabels,
} from '../composables/productPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import ProductPhoto from '../components/ProductPhoto.vue'
const rows = ref([]),
  meta = ref(null),
  shop = ref(null),
  page = ref(1),
  status = ref(''),
  search = ref('')
const { busy, error, errors, success, run } = useFeedback()
async function refresh() {
  let result = await productsApi.listSellerProducts({
    page: page.value,
    status: status.value || undefined,
    search: search.value || undefined,
  })
  if (page.value > result.meta.last_page) {
    page.value = result.meta.last_page
    result = await productsApi.listSellerProducts({
      page: page.value,
      status: status.value || undefined,
      search: search.value || undefined,
    })
  }
  rows.value = result.data
  meta.value = result.meta
}
const load = (target = 1) =>
  run(async () => {
    page.value = target
    shop.value = await getSellerShop()
    await refresh()
  })
const action = (product, target) =>
  run(async () => {
    if (target === 'delete') await productsApi.deleteProduct(product.id)
    else await productsApi.productAction(product.id, target)
    await refresh()
  }, 'Đã cập nhật sản phẩm.')
onMounted(() => load())
useSellerIdentity(() => shop.value)
</script>
<template>
  <section>
    <PageHeader
      title="Sản phẩm của tôi"
      eyebrow="Không gian người bán"
      :description="
        shop
          ? `Quản lý sản phẩm của ${shop.name}.`
          : 'Quản lý nội dung, hình ảnh và trạng thái sản phẩm.'
      "
      ><RouterLink
        v-if="['PENDING', 'ACTIVE'].includes(shop?.status)"
        to="/seller/products/create"
        class="btn btn-primary"
        >+ Thêm sản phẩm</RouterLink
      ></PageHeader
    >
    <RouterLink to="/seller/inventory" class="btn btn-outline-secondary mb-3"
      >Quản lý tồn kho</RouterLink
    >
    <RouterLink v-if="!shop && !busy" to="/seller/shop">Xem / tạo gian hàng</RouterLink>
    <p v-if="shop && shop.status !== 'ACTIVE'" class="alert alert-info">
      Gian hàng {{ getStatusLabel(shop.status) }}. Chỉ gian hàng đang hoạt động được gửi sản phẩm để
      duyệt.
    </p>
    <form class="d-flex gap-2 flex-wrap my-3 seller-toolbar" @submit.prevent="load()">
      <label class="flex-grow-1"
        >Tìm kiếm<input
          v-model="search"
          name="search"
          class="form-control"
          maxlength="100" /></label
      ><label
        >Trạng thái<select v-model="status" name="status_filter" class="form-select">
          <option value="">Tất cả</option>
          <option v-for="state in statuses" :key="state" :value="state">
            {{ getStatusLabel(state) }}
          </option>
        </select></label
      ><button class="btn btn-outline-secondary align-self-end" :disabled="busy">
        Lọc / Tải lại
      </button>
    </form>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div v-for="(messages, key) in errors" :key="key" class="text-danger">
      {{ messages.join(' ') }}
    </div>
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Chưa có sản phẩm."
      description="Thêm sản phẩm hoặc điều chỉnh bộ lọc để xem kết quả."
    />
    <div v-if="rows.length" class="table-responsive seller-table-wrap">
      <table class="table align-middle seller-table">
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th>Giá / tồn kho</th>
            <th>Trạng thái</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in rows" :key="product.id" :data-product="product.id">
            <td>
              <ProductPhoto
                :src="product.primary_image?.url"
                :alt="product.name"
                style="width: 70px; height: 60px"
              /><strong class="d-block">{{ product.name }}</strong
              ><small>{{ conditionLabel(product.condition) }}</small
              ><small class="d-block">{{
                new Date(product.created_at).toLocaleDateString('vi-VN')
              }}</small>
            </td>
            <td data-label="Giá / tồn kho">
              {{ formatPrice(product.price) }}<br /><InventoryBadge :product="product" />
            </td>
            <td data-label="Trạng thái"><StatusBadge :status="product.status" /></td>
            <td data-label="Thao tác">
              <div class="d-flex gap-2 flex-wrap">
                <RouterLink
                  :to="`/seller/products/${product.id}/edit`"
                  class="btn btn-sm btn-outline-primary"
                  >{{ product.can_edit ? 'Xem / Sửa' : 'Xem' }}</RouterLink
                ><button
                  v-for="target in sellerActions(product)"
                  :key="target"
                  :data-action="target"
                  class="btn btn-sm btn-outline-secondary"
                  :disabled="busy"
                  @click="action(product, target)"
                >
                  {{ actionLabels[target] }}
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
  </section>
</template>

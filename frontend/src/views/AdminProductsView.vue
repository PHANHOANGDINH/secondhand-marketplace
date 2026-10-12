<script setup>
import { useRoute } from 'vue-router'
const route = useRoute()
import { getStatusLabel } from '../utils/labels'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import AdminRecordHeader from '../components/admin/AdminRecordHeader.vue'
import ProductPhoto from '../components/ProductPhoto.vue'
import { computed, onMounted, watch, reactive, ref } from 'vue'
import * as productsApi from '../api/product.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice, conditionLabel, statuses } from '../composables/productPresentation'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import ProductGallery from '../components/ProductGallery.vue'
// The Action Center links here with the list already filtered.
const filters = reactive({
  search: '',
  status: statuses.includes(route.query.status) ? route.query.status : '',
  shop: '',
  category: '',
})
const rows = ref([]),
  meta = ref(null),
  page = ref(1),
  selected = ref(null),
  reason = ref(''),
  auctionConflict = ref(null)
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
    auctionConflict.value = null
    selected.value = await productsApi.getAdminProduct(id)
    reason.value = ''
  })
const moderate = (status) =>
  run(async () => {
    auctionConflict.value = null
    try {
      selected.value = await productsApi.moderateProduct(selected.value.id, {
        expected_status: selected.value.status,
        status,
        reason: reason.value,
      })
    } catch (failure) {
      // A running auction blocks hiding; point the Admin at the auction instead of a generic conflict.
      const auctionId = String(failure.response?.data?.auction_id ?? '')
      if (failure.response?.status === 409 && /^[1-9]\d*$/.test(auctionId))
        auctionConflict.value = auctionId
      throw failure
    }
    reason.value = ''
    await refresh()
  }, 'Đã ghi nhận kiểm duyệt và nhật ký quản lý.')
async function inspectTarget(id) {
  if (/^[1-9]\d*$/.test(String(id || ''))) await show(id)
}
watch(() => route.query.inspect, inspectTarget)
onMounted(async () => {
  await load()
  await inspectTarget(route.query.inspect)
})
</script>
<template>
  <section>
    <PageHeader
      title="Kiểm duyệt sản phẩm"
      eyebrow="Quản trị · Sàn mua bán"
      description="Đối chiếu hình ảnh, thông tin sản phẩm và trạng thái kiểm duyệt."
    />
    <form class="row g-2 my-3 admin-toolbar" @submit.prevent="load()">
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
            ...statuses.map((value) => ({ value, label: getStatusLabel(value) })),
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
      <div><button class="btn btn-outline-secondary" :disabled="busy">Lọc / Tải lại</button></div>
    </form>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div
      v-for="key in ['images', 'product', 'stock_quantity', 'category_id', 'expected_status']"
      :key="key"
      class="text-danger"
    >
      <div v-for="message in errors[key] || []" :key="message">{{ message }}</div>
    </div>
    <div class="row g-4 admin-split">
      <div class="col-lg-6">
        <div class="table-responsive admin-table-wrap">
          <table class="table align-middle admin-table">
            <thead>
              <tr>
                <th scope="col">Sản phẩm</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="product in rows"
                :key="product.id"
                :data-product="product.id"
                :class="{ 'is-selected': selected?.id === product.id }"
              >
                <td>
                  <div class="admin-product-summary">
                    <ProductPhoto
                      :src="product.primary_image?.url"
                      :alt="product.name"
                      style="width: 52px; height: 52px"
                    />
                    <div>
                      <strong>{{ product.name }}</strong
                      ><small class="d-block"
                        >{{ product.shop?.name }} · {{ product.category?.name }}</small
                      ><small class="d-block"
                        >{{ formatPrice(product.price) }} · Tồn {{ product.stock_quantity }}</small
                      ><span v-if="product.archived" class="badge text-bg-secondary"
                        >Đã lưu trữ</span
                      >
                    </div>
                  </div>
                </td>
                <td data-label="Trạng thái"><StatusBadge :status="product.status" /></td>
                <td>
                  <button
                    class="btn btn-sm btn-outline-primary"
                    :disabled="busy"
                    :aria-expanded="selected?.id === product.id"
                    @click="show(product.id)"
                  >
                    Xem
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState
          v-if="!busy && !error && !rows.length"
          title="Không có sản phẩm phù hợp."
          description="Thử thay đổi bộ lọc."
        />
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
      <aside v-if="selected" class="col-lg-6">
        <div class="admin-detail">
          <AdminRecordHeader :title="selected.name" :status="selected.status" />
          <p>
            {{ formatPrice(selected.price) }} · {{ conditionLabel(selected.condition) }} · Tồn
            {{ selected.stock_quantity }}
          </p>
          <p>{{ selected.shop?.name }} / {{ selected.category?.name }}</p>
          <ProductGallery :images="selected.images" :name="selected.name" />
          <p class="text-break mt-3" style="white-space: pre-wrap">{{ selected.description }}</p>
          <p v-if="selected.moderation_reason">Lý do gần nhất: {{ selected.moderation_reason }}</p>
          <button
            class="btn btn-outline-secondary mb-3"
            :disabled="busy"
            @click="show(selected.id)"
          >
            Tải lại chi tiết
          </button>
          <p v-if="auctionConflict" class="alert alert-warning" role="alert" data-auction-conflict>
            Sản phẩm đang có phiên đấu giá. Hãy hủy phiên trước khi ẩn.
            <RouterLink :to="{ path: '/admin/auctions', query: { auction: auctionConflict } }"
              >Mở phiên đấu giá số {{ auctionConflict }}</RouterLink
            >
          </p>
          <div v-if="actions.length" class="admin-moderation">
            <p class="admin-moderation-title">Xác nhận kiểm duyệt</p>
            <p class="text-secondary">Ghi rõ lý do trước khi thay đổi trạng thái sản phẩm.</p>
            <DomainField
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
            </div>
          </div>
          <p v-else>Không có thao tác kiểm duyệt hợp lệ.</p>
        </div>
      </aside>
      <aside v-else class="col-lg-6"><EmptyState title="Chọn sản phẩm để xem chi tiết" /></aside>
    </div>
  </section>
</template>

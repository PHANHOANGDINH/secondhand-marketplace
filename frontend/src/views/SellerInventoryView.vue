<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { listInventory } from '../api/inventory.api'
import { updateProduct } from '../api/product.api'
import { useFeedback } from '../composables/useFeedback'
import { domainError } from '../api/domain.api'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import { useSellerIdentity } from '../components/seller/useSellerIdentity'
import InventoryBadge from '../components/seller/InventoryBadge.vue'
import AuctionLockNotice from '../components/seller/AuctionLockNotice.vue'
const route = useRoute(),
  router = useRouter()
const result = ref(null),
  filter = ref('all'),
  sort = ref('updated'),
  search = ref('')
const editing = ref(null),
  quantity = ref(''),
  saving = ref(false),
  stockError = ref(''),
  success = ref('')
const { busy, error } = useFeedback()
useSellerIdentity(() =>
  result.value?.data[0]
    ? { ...result.value.data[0].shop, status: result.value.data[0].shop_status }
    : null,
)
let sequence = 0
const filters = [
  ['all', 'Tất cả'],
  ['low_stock', 'Sắp hết hàng'],
  ['out_of_stock', 'Hết hàng'],
  ['active', 'Đang bán'],
  ['pending', 'Chờ duyệt'],
  ['hidden', 'Đã ẩn'],
  ['rejected', 'Bị từ chối'],
]
const sorts = [
  ['updated', 'Vừa cập nhật'],
  ['newest', 'Mới nhất'],
  ['stock_asc', 'Tồn kho tăng dần'],
  ['stock_desc', 'Tồn kho giảm dần'],
]
const validQuantity = computed(
  () => /^\d+$/.test(String(quantity.value)) && Number(quantity.value) <= 4294967295,
)
async function load() {
  const ticket = ++sequence
  busy.value = true
  error.value = ''
  result.value = null
  filter.value = filters.some(([v]) => v === route.query.filter) ? route.query.filter : 'all'
  sort.value = sorts.some(([v]) => v === route.query.sort) ? route.query.sort : 'updated'
  search.value = typeof route.query.search === 'string' ? route.query.search : ''
  try {
    const response = await listInventory({
      filter: filter.value,
      sort: sort.value,
      ...(search.value ? { search: search.value } : {}),
      page: /^\d+$/.test(String(route.query.page)) ? Number(route.query.page) : 1,
    })
    if (ticket === sequence) result.value = response
  } catch (failure) {
    if (ticket === sequence) error.value = domainError(failure)
  } finally {
    if (ticket === sequence) busy.value = false
  }
}
function apply(page = 1) {
  router.push({
    path: '/seller/inventory',
    query: {
      filter: filter.value,
      sort: sort.value,
      ...(search.value.trim() ? { search: search.value.trim() } : {}),
      ...(page > 1 ? { page: String(page) } : {}),
    },
  })
}
async function edit(product) {
  editing.value = product
  quantity.value = String(product.stock_quantity)
  stockError.value = ''
  success.value = ''
  await nextTick()
  document.getElementById('inventory-quantity')?.focus()
}
function reloadStock() {
  editing.value = null
  load()
}
async function save() {
  if (saving.value || !editing.value) return
  if (!validQuantity.value) {
    stockError.value = 'Tồn kho phải là số nguyên từ 0 đến 4.294.967.295.'
    return
  }
  saving.value = true
  stockError.value = ''
  success.value = ''
  try {
    const previousStatus = editing.value.status
    const updated = await updateProduct(editing.value.id, {
      stock_quantity: Number(quantity.value),
      expected_updated_at: editing.value.updated_at,
    })
    editing.value = null
    success.value =
      updated.status === 'PENDING'
        ? 'Đã cập nhật tồn kho. Sản phẩm được chuyển sang chờ duyệt theo quy định hiện có.'
        : previousStatus === 'SOLD' && updated.status === 'ACTIVE'
          ? 'Đã cập nhật tồn kho. Sản phẩm đã mở bán lại.'
          : 'Đã cập nhật tồn kho.'
    await load()
  } catch (failure) {
    const message = failure.response?.data?.message
    stockError.value =
      message === 'Sản phẩm đang được giữ cho đấu giá.'
        ? message
        : failure.response?.status === 409
          ? 'Sản phẩm đã thay đổi. Đóng biểu mẫu và tải lại trước khi cập nhật tồn kho.'
          : failure.response?.status === 422
            ? 'Tồn kho không hợp lệ. Vui lòng nhập số nguyên không âm.'
            : domainError(failure)
  } finally {
    saving.value = false
  }
}
watch(
  () => route.fullPath,
  () => {
    editing.value = null
    success.value = ''
    load()
  },
  { immediate: true },
)
onBeforeUnmount(() => sequence++)
const time = (value) => (value ? new Date(value).toLocaleString('vi-VN') : '—')
</script>
<template>
  <section>
    <PageHeader
      title="Quản lý tồn kho"
      eyebrow="Không gian người bán"
      description="Theo dõi số lượng và cập nhật tồn kho của gian hàng."
      ><RouterLink to="/my-ads" class="btn btn-outline-secondary"
        >Quản lý sản phẩm</RouterLink
      ></PageHeader
    >
    <form class="seller-toolbar inventory-toolbar mb-3" @submit.prevent="apply()">
      <input
        v-model="search"
        class="form-control"
        type="search"
        maxlength="200"
        aria-label="Tìm tên sản phẩm"
        placeholder="Tìm tên sản phẩm"
      />
      <select v-model="filter" class="form-select" aria-label="Lọc tồn kho">
        <option v-for="[value, label] in filters" :key="value" :value="value">{{ label }}</option>
      </select>
      <select v-model="sort" class="form-select" aria-label="Sắp xếp tồn kho">
        <option v-for="[value, label] in sorts" :key="value" :value="value">{{ label }}</option>
      </select>
      <button class="btn btn-outline-secondary" :disabled="busy">Áp dụng</button>
    </form>
    <DomainFeedback :busy="busy" :error="error" />
    <p v-if="success" class="alert alert-success" role="status">{{ success }}</p>
    <button v-if="error" class="btn btn-outline-secondary" :disabled="busy" @click="load">
      Tải lại tồn kho
    </button>
    <p v-if="result" class="text-muted small">
      Ngưỡng sắp hết: {{ result.low_stock_threshold }}. Cảnh báo tồn kho chỉ tính sản phẩm đang bán
      hoặc đã bán hết; sản phẩm ẩn và chờ duyệt được theo dõi riêng.
    </p>
    <form v-if="editing" class="card mb-3" aria-label="Cập nhật tồn kho" @submit.prevent="save">
      <div class="card-body">
        <h2 class="h5">Cập nhật tồn kho</h2>
        <p>{{ editing.name }}</p>
        <label for="inventory-quantity" class="form-label">Số lượng mới</label
        ><input
          id="inventory-quantity"
          v-model="quantity"
          class="form-control"
          type="number"
          min="0"
          max="4294967295"
          step="1"
          required
          :disabled="saving"
          :aria-invalid="!!stockError"
          aria-describedby="inventory-help inventory-error"
        />
        <p id="inventory-help" class="small text-muted mt-2">
          Chỉ thay đổi tồn kho thì sản phẩm đang bán vẫn tiếp tục được bán. Sản phẩm đã bán hết sẽ mở
          bán lại ngay nếu đã được duyệt và không có chỉnh sửa nào chưa duyệt; nếu không sẽ chuyển sang
          chờ duyệt.
        </p>
        <p id="inventory-error" v-if="stockError" class="text-danger" role="alert">
          {{ stockError }}
        </p>
        <div class="d-flex gap-2">
          <button class="btn btn-primary" :disabled="saving || !validQuantity">
            {{ saving ? 'Đang lưu…' : 'Lưu tồn kho' }}</button
          ><button
            type="button"
            class="btn btn-outline-secondary"
            :disabled="saving"
            @click="editing = null"
          >
            Đóng</button
          ><button
            v-if="stockError"
            type="button"
            class="btn btn-outline-secondary"
            :disabled="saving"
            @click="reloadStock"
          >
            Tải lại
          </button>
        </div>
      </div>
    </form>
    <EmptyState
      v-if="result && !result.data.length && !busy && !error"
      title="Không có sản phẩm phù hợp"
      description="Thử thay đổi bộ lọc hoặc tên sản phẩm."
    />
    <div v-if="result?.data.length && !error" class="table-responsive">
      <table class="table align-middle inventory-table">
        <thead>
          <tr>
            <th>Sản phẩm</th>
            <th>Trạng thái</th>
            <th>Tồn kho</th>
            <th>Cập nhật</th>
            <th>Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="product in result.data" :key="product.id">
            <td data-label="Sản phẩm">
              <div class="d-flex gap-2 align-items-center">
                <img
                  v-if="product.primary_image?.url || product.images?.[0]?.url"
                  :src="product.primary_image?.url || product.images[0].url"
                  alt=""
                  width="48"
                  height="48"
                  class="rounded object-fit-cover"
                  loading="lazy"
                /><strong>{{ product.name }}</strong>
              </div>
            </td>
            <td data-label="Trạng thái"><StatusBadge :status="product.status" /></td>
            <td data-label="Tồn kho"><InventoryBadge :product="product" /></td>
            <td data-label="Cập nhật" class="small">{{ time(product.updated_at) }}</td>
            <td data-label="Thao tác">
              <button
                v-if="product.stock_editable"
                class="btn btn-outline-primary btn-sm"
                :disabled="saving"
                :aria-label="`Cập nhật tồn kho: ${product.name}`"
                @click="edit(product)"
              >
                Cập nhật tồn kho
              </button>
              <AuctionLockNotice v-else-if="product.auction_locked" :product="product" compact />
              <p v-else class="small text-muted mb-0">{{ product.stock_edit_reason }}</p>
              <RouterLink :to="`/seller/products/${product.id}/edit`" class="d-block small mt-2"
                >Xem sản phẩm</RouterLink
              >
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <CollectionPager
      v-if="result && !error"
      :meta="result.meta"
      :disabled="busy || saving"
      @page="apply"
    />
  </section>
</template>
<style scoped>
.inventory-toolbar {
  display: grid;
  grid-template-columns: minmax(12rem, 2fr) minmax(9rem, 1fr) minmax(10rem, 1fr) auto;
  gap: 0.75rem;
  align-items: center;
}
@media (max-width: 1023px) {
  .inventory-toolbar {
    grid-template-columns: 1fr 1fr;
  }
}
@media (max-width: 575px) {
  .inventory-toolbar {
    grid-template-columns: 1fr;
  }
}
.inventory-table td {
  max-width: 24rem;
  overflow-wrap: anywhere;
}
@media (max-width: 767px) {
  .inventory-table thead {
    display: none;
  }
  .inventory-table,
  .inventory-table tbody,
  .inventory-table tr,
  .inventory-table td {
    display: block;
    width: 100%;
    max-width: none;
  }
  .inventory-table tr {
    border: 1px solid var(--bs-border-color);
    border-radius: 1rem;
    padding: 0.75rem;
    margin-bottom: 1rem;
  }
  .inventory-table td {
    border: 0;
    padding: 0.5rem 0;
  }
  .inventory-table td::before {
    content: attr(data-label);
    display: block;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
    margin-bottom: 0.25rem;
  }
}
</style>

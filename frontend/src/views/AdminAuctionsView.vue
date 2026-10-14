<script setup>
import { computed, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as api from '../api/admin.api'
import { domainError } from '../api/domain.api'
import { useAuthStore } from '../stores/auth'
import { formatPrice } from '../composables/productPresentation'
import { auctionStatusLabels, getSettlementLabel, settlementStatusLabels } from '../utils/labels'
import { localizeValidationErrors } from '../utils/validationLabels'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LoadingState from '../components/ui/LoadingState.vue'
import AccessibleDialog from '../components/ui/AccessibleDialog.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import FilterSheet from '../components/ui/FilterSheet.vue'
const auth = useAuthStore(),
  route = useRoute(),
  router = useRouter()
const filters = reactive({ status: '', settlement: '', overdue: false })
const rows = ref([]),
  meta = ref(null),
  busy = ref(false),
  error = ref(''),
  success = ref('')
const target = ref(null),
  reason = ref(''),
  saving = ref(false),
  dialogError = ref(''),
  fieldErrors = ref({})
let sequence = 0,
  disposed = false
const highlighted = computed(() =>
  /^[1-9]\d*$/.test(String(route.query.auction || '')) ? String(route.query.auction) : null,
)
const time = (value) => (value ? new Date(value).toLocaleString('vi-VN') : '—')
async function load() {
  if (!auth.isAdmin) return
  const ticket = ++sequence
  filters.status = Object.hasOwn(auctionStatusLabels, route.query.status) ? route.query.status : ''
  filters.settlement = Object.hasOwn(settlementStatusLabels, route.query.settlement)
    ? route.query.settlement
    : ''
  filters.overdue = route.query.overdue === '1'
  busy.value = true
  error.value = ''
  try {
    const result = await api.listAdminAuctions({
      ...(filters.status ? { status: filters.status } : {}),
      ...(filters.settlement ? { settlement: filters.settlement } : {}),
      ...(filters.overdue ? { overdue: 1 } : {}),
      ...(highlighted.value ? { auction: highlighted.value } : {}),
      page: /^[1-9]\d*$/.test(String(route.query.page)) ? Number(route.query.page) : 1,
    })
    if (disposed || ticket !== sequence) return
    rows.value = result.data
    meta.value = result.meta
  } catch (failure) {
    if (!disposed && ticket === sequence) {
      rows.value = []
      meta.value = null
      error.value = domainError(failure)
    }
  } finally {
    if (!disposed && ticket === sequence) busy.value = false
  }
}
function apply(page = 1) {
  router.push({
    path: '/admin/auctions',
    query: {
      ...(filters.status ? { status: filters.status } : {}),
      ...(filters.settlement ? { settlement: filters.settlement } : {}),
      ...(filters.overdue ? { overdue: '1' } : {}),
      ...(page > 1 ? { page: String(page) } : {}),
    },
  })
}
function open(row) {
  target.value = row
  reason.value = ''
  dialogError.value = ''
  fieldErrors.value = {}
  success.value = ''
}
function close() {
  if (!saving.value) target.value = null
}
async function cancel() {
  if (saving.value || !target.value || !reason.value.trim()) return
  const row = target.value
  saving.value = true
  dialogError.value = ''
  fieldErrors.value = {}
  try {
    await api.cancelAdminAuction(row.id, {
      reason: reason.value.trim(),
      expected_status: row.status,
    })
    if (disposed) return
    target.value = null
    success.value = `Đã hủy phiên đấu giá ${row.product?.name || ''}. Người bán và người đã đặt giá được thông báo.`
    await load()
  } catch (failure) {
    if (disposed) return
    fieldErrors.value = localizeValidationErrors(failure.response?.data?.errors)
    dialogError.value =
      failure.response?.status === 409
        ? 'Phiên đã thay đổi trạng thái hoặc không còn hủy được. Đóng hộp thoại và tải lại danh sách.'
        : domainError(failure)
  } finally {
    if (!disposed) saving.value = false
  }
}
watch(() => route.fullPath, load, { immediate: true })
onUnmounted(() => {
  disposed = true
  sequence++
})
const activeFilters = computed(() => [
  { label: 'Trạng thái', value: auctionStatusLabels[filters.status] },
  { label: 'Kết quả', value: settlementStatusLabels[filters.settlement] },
  { label: 'Quá hạn', value: filters.overdue ? 'Có' : '' },
])
</script>
<template>
  <section v-if="auth.isAdmin" class="admin-auctions">
    <PageHeader
      title="Phiên đấu giá"
      eyebrow="Quản trị · Đấu giá"
      description="Theo dõi mọi phiên đấu giá và dừng phiên vi phạm, kể cả khi đã có người đặt giá."
    />
    <FilterSheet :active="activeFilters">
      <form class="row g-2 align-items-end admin-toolbar" @submit.prevent="apply()">
        <label class="col-sm-6 col-lg-3"
          >Trạng thái phiên<select v-model="filters.status" name="status" class="form-select">
            <option value="">Tất cả</option>
            <option v-for="(label, value) in auctionStatusLabels" :key="value" :value="value">
              {{ label }}
            </option>
          </select></label
        >
        <label class="col-sm-6 col-lg-3"
          >Kết quả sau phiên<select
            v-model="filters.settlement"
            name="settlement"
            class="form-select"
          >
            <option value="">Tất cả</option>
            <option v-for="(label, value) in settlementStatusLabels" :key="value" :value="value">
              {{ label }}
            </option>
          </select></label
        >
        <div class="col-sm-6 col-lg-3">
          <div class="form-check mb-2">
            <input
              id="auction-overdue"
              v-model="filters.overdue"
              class="form-check-input"
              type="checkbox"
              name="overdue"
            /><label class="form-check-label" for="auction-overdue"
              >Chỉ phiên quá hạn chưa kết thúc</label
            >
          </div>
        </div>
        <div class="col-sm-6 col-lg-3 d-flex gap-2">
          <button class="btn btn-primary" :disabled="busy">Lọc</button
          ><button type="button" class="btn btn-outline-secondary" :disabled="busy" @click="load">
            Tải lại
          </button>
        </div>
      </form>
    </FilterSheet>
    <p v-if="highlighted" class="alert alert-info" data-highlight-note>
      Đang hiển thị phiên đấu giá số {{ highlighted }}.
      <RouterLink to="/admin/auctions">Xem tất cả phiên</RouterLink>
    </p>
    <DomainFeedback :error="error" :success="success" />
    <LoadingState v-if="busy && !rows.length" layout="table" label="Đang tải phiên đấu giá…" />
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Không có phiên đấu giá phù hợp"
      description="Thử thay đổi bộ lọc."
    />
    <div v-if="rows.length" class="table-responsive admin-table-wrap">
      <table class="table align-middle admin-table auctions-table">
        <caption class="visually-hidden">
          Danh sách phiên đấu giá
        </caption>
        <thead>
          <tr>
            <th scope="col">Sản phẩm</th>
            <th scope="col">Trạng thái</th>
            <th scope="col">Giá hiện tại</th>
            <th scope="col">Lượt đặt giá</th>
            <th scope="col">Kết thúc</th>
            <th scope="col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id" :data-auction="row.id">
            <td data-label="Sản phẩm">
              <strong>{{ row.product?.name || `Phiên số ${row.id}` }}</strong
              ><small class="d-block">Người bán: {{ row.seller.name }}</small>
            </td>
            <td data-label="Trạng thái">
              <StatusBadge :status="row.status" domain="auction" />
              <small v-if="row.status === 'ENDED'" class="d-block">{{
                getSettlementLabel(row.settlement_state)
              }}</small>
              <small v-if="row.overdue" class="d-block text-danger" data-overdue
                >Quá hạn, chưa được kết thúc</small
              >
            </td>
            <td data-label="Giá hiện tại">{{ formatPrice(row.current_price) }}</td>
            <td data-label="Lượt đặt giá">{{ row.bid_count }}</td>
            <td data-label="Kết thúc">
              <time :datetime="row.ends_at">{{ time(row.ends_at) }}</time>
            </td>
            <td data-label="Thao tác">
              <button
                v-if="row.can_cancel"
                type="button"
                class="btn btn-sm btn-outline-danger"
                :data-cancel="row.id"
                :aria-label="`Hủy phiên đấu giá ${row.product?.name || row.id}`"
                @click="open(row)"
              >
                Hủy phiên
              </button>
              <span v-else class="text-secondary small">{{
                ['SCHEDULED', 'LIVE'].includes(row.status)
                  ? 'Bạn là người bán của phiên này'
                  : 'Không còn thao tác'
              }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="apply" />
    <AccessibleDialog
      v-if="target"
      id="cancel-auction-dialog"
      title="Hủy phiên đấu giá"
      :description="target.product?.name || `Phiên số ${target.id}`"
      :busy="saving"
      @dismiss="close"
    >
      <form id="cancel-auction-form" @submit.prevent="cancel">
        <p class="alert alert-warning" data-bid-warning>
          <template v-if="target.bid_count > 0"
            >Phiên đang có <strong>{{ target.bid_count }}</strong> lượt đặt giá. Các lượt đặt giá
            được giữ trong lịch sử nhưng sẽ không còn hiệu lực.</template
          ><template v-else>Phiên chưa có lượt đặt giá nào.</template>
          Người bán và mọi người đã đặt giá sẽ nhận thông báo kèm lý do bên dưới. Thao tác này không
          thể hoàn tác.
        </p>
        <label for="cancel-auction-reason" class="form-label"
          >Lý do hủy (hiển thị cho người bán và người đặt giá) <span aria-hidden="true">*</span></label
        ><textarea
          id="cancel-auction-reason"
          v-model="reason"
          name="reason"
          class="form-control"
          rows="3"
          maxlength="1000"
          required
          :disabled="saving"
          :aria-invalid="!!fieldErrors.reason"
        />
        <div v-for="message in fieldErrors.reason || []" :key="message" class="text-danger">
          {{ message }}
        </div>
        <p v-if="dialogError" class="text-danger mt-2" role="alert">{{ dialogError }}</p>
      </form>
      <template #footer>
        <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="close">
          Giữ phiên</button
        ><button
          type="submit"
          form="cancel-auction-form"
          class="btn btn-danger"
          :disabled="saving || !reason.trim()"
        >
          {{ saving ? 'Đang hủy…' : 'Xác nhận hủy phiên' }}
        </button>
      </template>
    </AccessibleDialog>
  </section>
</template>
<style scoped>
.admin-auctions {
  min-width: 0;
}
.auctions-table td {
  overflow-wrap: anywhere;
}
@media (max-width: 767px) {
  .auctions-table thead {
    display: none;
  }
  .auctions-table,
  .auctions-table tbody,
  .auctions-table tr,
  .auctions-table td {
    display: block;
    width: 100%;
  }
  .auctions-table tr {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.75rem;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
  }
  .auctions-table td {
    border: 0;
    padding: 0.35rem 0;
  }
  .auctions-table td::before {
    content: attr(data-label);
    display: block;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
  }
}
</style>

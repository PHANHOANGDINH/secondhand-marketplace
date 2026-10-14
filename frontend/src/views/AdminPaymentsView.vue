<script setup>
import IconButton from '../components/ui/IconButton.vue'
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as api from '../api/admin.api'
import { domainError } from '../api/domain.api'
import { useAuthStore } from '../stores/auth'
import { formatPrice } from '../composables/productPresentation'
import {
  getPaymentMethodLabel,
  getReconciliationOutcomeLabel,
  getStatusLabel,
} from '../utils/labels'
import { localizeValidationErrors } from '../utils/validationLabels'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LoadingState from '../components/ui/LoadingState.vue'
import AccessibleDialog from '../components/ui/AccessibleDialog.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import FilterSheet from '../components/ui/FilterSheet.vue'
import DetailSheet from '../components/ui/DetailSheet.vue'
const auth = useAuthStore(),
  route = useRoute(),
  router = useRouter()
const filters = [
  ['open', 'Chưa đối soát'],
  ['resolved', 'Đã đối soát'],
  ['all', 'Tất cả'],
]
const filter = ref('open'),
  rows = ref([]),
  meta = ref(null),
  busy = ref(false),
  error = ref(''),
  success = ref(''),
  selected = ref(null)
const dialog = ref(null),
  outcome = ref('REFUNDED_OUTSIDE'),
  note = ref(''),
  step = ref('form'),
  saving = ref(false),
  dialogError = ref(''),
  fieldErrors = ref({})
let sequence = 0,
  disposed = false
const time = (value) => (value ? new Date(value).toLocaleString('vi-VN') : '—')
const queueLabel = (row) => (row.reconciliation_open ? 'Chưa đối soát' : 'Đã đối soát')
const canSubmit = computed(() => !saving.value && note.value.trim().length > 0)
async function load() {
  if (!auth.isAdmin) return
  const ticket = ++sequence
  filter.value = filters.some(([value]) => value === route.query.reconciliation)
    ? route.query.reconciliation
    : route.query.reconciliation === '1'
      ? 'open'
      : 'open'
  busy.value = true
  error.value = ''
  try {
    const result = await api.listAdminPayments({
      reconciliation: filter.value,
      page: /^[1-9]\d*$/.test(String(route.query.page)) ? Number(route.query.page) : 1,
    })
    if (disposed || ticket !== sequence) return
    rows.value = result.data
    meta.value = result.meta
    if (selected.value)
      selected.value = result.data.find((row) => row.id === selected.value.id) || selected.value
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
    path: '/admin/payments',
    query: { reconciliation: filter.value, ...(page > 1 ? { page: String(page) } : {}) },
  })
}
async function show(row) {
  success.value = ''
  selected.value = row
  try {
    const detail = await api.getAdminPayment(row.id)
    if (!disposed && selected.value?.id === row.id) selected.value = detail
  } catch (failure) {
    if (!disposed) error.value = domainError(failure)
  }
}
function open(row) {
  dialog.value = row
  outcome.value = 'REFUNDED_OUTSIDE'
  note.value = ''
  step.value = 'form'
  dialogError.value = ''
  fieldErrors.value = {}
}
function close() {
  if (!saving.value) dialog.value = null
}
async function submit() {
  if (!canSubmit.value || !dialog.value) return
  // Recording a refund is irreversible here, so it always takes a second, explicit confirmation.
  if (outcome.value === 'REFUNDED_OUTSIDE' && step.value === 'form') {
    step.value = 'confirm'
    await nextTick()
    document.getElementById('reconciliation-confirm')?.focus()
    return
  }
  const target = dialog.value
  saving.value = true
  dialogError.value = ''
  fieldErrors.value = {}
  try {
    const updated = await api.resolveReconciliation(target.id, {
      outcome: outcome.value,
      note: note.value.trim(),
      expected_status: target.status,
    })
    if (disposed) return
    dialog.value = null
    // Refresh the open details; do not pop another panel up after the dialog closes.
    if (selected.value?.id === updated.id) selected.value = updated
    success.value = `Đã ghi nhận đối soát: ${getReconciliationOutcomeLabel(outcome.value)}.`
    await load()
  } catch (failure) {
    if (disposed) return
    step.value = 'form'
    fieldErrors.value = localizeValidationErrors(failure.response?.data?.errors)
    dialogError.value =
      failure.response?.status === 409
        ? outcome.value === 'KEEP_PAID'
          ? 'Không thể giữ khoản thanh toán này hoặc giao dịch đã thay đổi. Tải lại và kiểm tra trước khi thử lại.'
          : 'Giao dịch đã thay đổi hoặc đã được đối soát. Vui lòng tải lại.'
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
  { label: 'Tình trạng', value: filters.find(([value]) => value === filter.value)?.[1] },
])
</script>
<template>
  <section v-if="auth.isAdmin" class="admin-payments">
    <PageHeader
      title="Đối soát thanh toán"
      eyebrow="Quản trị · Thanh toán trực tuyến"
      description="Các khoản tiền đã được xác minh về nhưng đơn hàng không còn nhận được hoặc bị thừa. Hệ thống chỉ ghi nhận quyết định, không tự chuyển tiền."
    />
    <FilterSheet :active="activeFilters">
      <form class="d-flex flex-wrap gap-2 align-items-end admin-toolbar" @submit.prevent="apply()">
        <label
          >Tình trạng đối soát<select
            v-model="filter"
            name="reconciliation"
            class="form-select"
            @change="apply()"
          >
            <option v-for="[value, label] in filters" :key="value" :value="value">
              {{ label }}
            </option>
          </select></label
        >
        <IconButton type="button" class="btn btn-outline-secondary" :disabled="busy" @click="load" icon="refresh" label="Tải lại" />
      </form>
    </FilterSheet>
    <DomainFeedback :error="error" :success="success" />
    <LoadingState v-if="busy && !rows.length" layout="table" label="Đang tải danh sách đối soát…" />
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Không có giao dịch phù hợp"
      description="Không có khoản thanh toán nào ở tình trạng đã chọn."
    />
    <div>
      <div v-if="rows.length">
        <div class="table-responsive admin-table-wrap">
          <table class="table align-middle admin-table payments-table">
            <caption class="visually-hidden">
              Danh sách thanh toán cần đối soát
            </caption>
            <thead>
              <tr>
                <th scope="col">Đơn hàng</th>
                <th scope="col">Số tiền</th>
                <th scope="col">Thanh toán</th>
                <th scope="col">Đối soát</th>
                <th scope="col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                :data-payment="row.id"
                :class="{ 'is-selected': selected?.id === row.id }"
              >
                <td data-label="Đơn hàng">
                  <strong>{{ row.order.order_code }}</strong
                  ><small class="d-block"
                    >{{ row.order.buyer.name }} · lần {{ row.attempt_number }}</small
                  >
                </td>
                <td data-label="Số tiền">{{ formatPrice(row.amount) }}</td>
                <td data-label="Thanh toán">
                  <StatusBadge :status="row.status" domain="payment" />
                </td>
                <td data-label="Đối soát">{{ queueLabel(row) }}</td>
                <td data-label="Thao tác">
                  <div class="d-flex flex-wrap gap-2">
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-primary"
                      :aria-label="`Xem chi tiết thanh toán của đơn ${row.order.order_code}`"
                      @click="show(row)"
                    >
                      Chi tiết</button
                    ><button
                      v-if="row.can_resolve"
                      type="button"
                      class="btn btn-sm btn-primary"
                      :data-resolve="row.id"
                      @click="open(row)"
                    >
                      Xử lý đối soát
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <CollectionPager :meta="meta" :busy="busy" @page="apply" />
      </div>
      <DetailSheet
        id="admin-payment-detail"
        :open="!!selected"
        :title="selected ? `Thanh toán đơn ${selected.order.order_code}` : 'Chi tiết thanh toán'"
        :busy="busy"
        @close="selected = null"
      >
        <div v-if="selected" class="admin-detail" data-payment-detail>
          <h2 class="h5">Thanh toán của đơn {{ selected.order.order_code }}</h2>
          <dl class="payment-facts">
            <dt>Phương thức</dt>
            <dd>{{ getPaymentMethodLabel(selected.method) }}</dd>
            <dt>Trạng thái thanh toán</dt>
            <dd><StatusBadge :status="selected.status" domain="payment" /></dd>
            <dt>Số tiền</dt>
            <dd>{{ formatPrice(selected.amount) }}</dd>
            <dt>Mã giao dịch của sàn</dt>
            <dd class="text-break">{{ selected.transaction_reference }}</dd>
            <dt>Mã giao dịch cổng thanh toán</dt>
            <dd class="text-break">{{ selected.gateway_transaction_id || '—' }}</dd>
            <dt>Trạng thái đơn hàng</dt>
            <dd>{{ getStatusLabel(selected.order.overall_status, 'order') }}</dd>
            <dt>Người mua</dt>
            <dd>{{ selected.order.buyer.name }}</dd>
            <dt>Tạo lúc</dt>
            <dd>{{ time(selected.created_at) }}</dd>
          </dl>
          <p v-if="selected.order.has_other_paid_payment" class="alert alert-warning">
            Đơn này đã có một khoản thanh toán thành công khác: khoản này là tiền thừa.
          </p>
          <template v-if="!selected.reconciliation_open">
            <p class="mb-1">
              <strong>Đã đối soát</strong> lúc {{ time(selected.reconciliation_resolved_at) }}
            </p>
            <p v-if="selected.reconciliation_resolved_by" class="mb-1">
              Người xử lý: {{ selected.reconciliation_resolved_by.name }}
            </p>
            <p class="payment-note"><strong>Ghi chú:</strong> {{ selected.reconciliation_note }}</p>
          </template>
          <button
            v-else-if="selected.can_resolve"
            type="button"
            class="btn btn-primary"
            @click="open(selected)"
          >
            Xử lý đối soát
          </button>
          <p v-else class="text-secondary mb-0">
            Bạn không thể tự đối soát khoản thanh toán thuộc đơn hàng của chính mình.
          </p>
        </div>
      </DetailSheet>
    </div>
    <AccessibleDialog
      v-if="dialog"
      id="reconciliation-dialog"
      title="Xử lý đối soát"
      :description="`Đơn ${dialog.order.order_code} · ${formatPrice(dialog.amount)}`"
      :busy="saving"
      @dismiss="close"
    >
      <form id="reconciliation-form" @submit.prevent="submit">
        <fieldset :disabled="saving || step === 'confirm'" class="mb-3">
          <legend class="h6">Kết quả đối soát</legend>
          <div class="form-check">
            <input
              id="outcome-refunded"
              v-model="outcome"
              class="form-check-input"
              type="radio"
              name="outcome"
              value="REFUNDED_OUTSIDE"
            /><label class="form-check-label" for="outcome-refunded"
              >{{ getReconciliationOutcomeLabel('REFUNDED_OUTSIDE') }}
              <small class="d-block text-secondary"
                >Bạn đã hoàn tiền cho người mua qua cổng thanh toán hoặc kênh khác. Thanh toán được
                ghi là đã hoàn tiền.</small
              ></label
            >
          </div>
          <div class="form-check mt-2">
            <input
              id="outcome-keep"
              v-model="outcome"
              class="form-check-input"
              type="radio"
              name="outcome"
              value="KEEP_PAID"
              :disabled="!dialog.can_keep_paid"
            /><label class="form-check-label" for="outcome-keep"
              >{{ getReconciliationOutcomeLabel('KEEP_PAID') }}
              <small class="d-block text-secondary">{{
                dialog.can_keep_paid
                  ? 'Tiền hợp lệ và đơn còn hiệu lực. Thanh toán được ghi là đã thanh toán để người bán tiếp tục xử lý đơn.'
                  : 'Không khả dụng: đơn đã bị hủy, đã có khoản thanh toán khác hoặc số tiền không khớp.'
              }}</small></label
            >
          </div>
          <div v-for="message in fieldErrors.outcome || []" :key="message" class="text-danger">
            {{ message }}
          </div>
          <label for="reconciliation-note" class="form-label mt-3"
            >Ghi chú đối soát <span aria-hidden="true">*</span></label
          ><textarea
            id="reconciliation-note"
            v-model="note"
            name="note"
            class="form-control"
            rows="3"
            maxlength="1000"
            required
            :aria-invalid="!!fieldErrors.note"
            aria-describedby="reconciliation-note-help"
          />
          <p id="reconciliation-note-help" class="form-text">
            Bắt buộc. Ghi mã hoàn tiền, kênh xử lý hoặc căn cứ giữ khoản thanh toán.
          </p>
          <div v-for="message in fieldErrors.note || []" :key="message" class="text-danger">
            {{ message }}
          </div>
        </fieldset>
        <div v-if="step === 'confirm'" class="alert alert-warning" data-confirm-step>
          <p class="mb-2">
            <strong>Xác nhận lần cuối.</strong> Hệ thống không tự hoàn tiền. Chỉ tiếp tục khi bạn đã
            hoàn {{ formatPrice(dialog.amount) }} cho người mua ở bên ngoài hệ thống. Thao tác này
            không thể hoàn tác.
          </p>
        </div>
        <p v-if="dialogError" class="text-danger" role="alert">{{ dialogError }}</p>
      </form>
      <template #footer>
        <button
          v-if="step === 'confirm'"
          type="button"
          class="btn btn-outline-secondary"
          :disabled="saving"
          @click="step = 'form'"
        >
          Quay lại
        </button>
        <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="close">
          Hủy bỏ
        </button>
        <button
          id="reconciliation-confirm"
          type="submit"
          form="reconciliation-form"
          :class="step === 'confirm' ? 'btn btn-danger' : 'btn btn-primary'"
          :disabled="!canSubmit"
        >
          {{
            saving
              ? 'Đang lưu…'
              : step === 'confirm'
                ? 'Tôi đã hoàn tiền, ghi nhận'
                : outcome === 'REFUNDED_OUTSIDE'
                  ? 'Tiếp tục'
                  : 'Ghi nhận đối soát'
          }}
        </button>
      </template>
    </AccessibleDialog>
  </section>
</template>
<style scoped>
.admin-payments {
  min-width: 0;
}
.payment-facts {
  display: grid;
  grid-template-columns: minmax(8rem, 40%) 1fr;
  gap: 0.35rem 1rem;
}
.payment-facts dt {
  font-weight: 600;
  color: var(--bs-secondary-color);
}
.payment-facts dd {
  margin: 0;
}
.payment-note {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
@media (max-width: 767px) {
  .payments-table thead {
    display: none;
  }
  .payments-table,
  .payments-table tbody,
  .payments-table tr,
  .payments-table td {
    display: block;
    width: 100%;
  }
  .payments-table tr {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.75rem;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
  }
  .payments-table td {
    border: 0;
    padding: 0.35rem 0;
  }
  .payments-table td::before {
    content: attr(data-label);
    display: block;
    font-size: 0.75rem;
    color: var(--bs-secondary-color);
  }
  .payment-facts {
    grid-template-columns: 1fr;
  }
}
</style>

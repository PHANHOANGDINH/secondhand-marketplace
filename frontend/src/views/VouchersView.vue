<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import * as api from '../api/voucher.api'
import { domainError } from '../api/domain.api'
import { localizeValidationErrors } from '../utils/validationLabels'
import { formatPrice } from '../composables/productPresentation'
import PageHeader from '../components/ui/PageHeader.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import AccessibleDialog from '../components/ui/AccessibleDialog.vue'
import CollectionPager from '../components/CollectionPager.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
// One screen for both areas: a seller manages the vouchers of their shop,
// an admin manages platform vouchers and can read shop vouchers.
const props = defineProps({ admin: Boolean })
const rows = ref([]),
  meta = ref(null),
  busy = ref(false),
  error = ref(''),
  success = ref(''),
  scope = ref(''),
  editing = ref(null),
  saving = ref(false),
  formError = ref(''),
  formErrors = ref({})
const blank = () => ({
  code: '',
  type: 'FIXED',
  value: '',
  min_order: '0',
  max_discount: '',
  usage_limit: '',
  per_user_limit: '',
  starts_at: '',
  ends_at: '',
})
const form = reactive(blank())
const stateLabels = {
  ACTIVE: 'Đang áp dụng',
  INACTIVE: 'Đã tắt',
  SCHEDULED: 'Chưa bắt đầu',
  EXPIRED: 'Hết hạn',
  EXHAUSTED: 'Hết lượt',
}
const stateTones = {
  ACTIVE: 'success',
  INACTIVE: 'neutral',
  SCHEDULED: 'primary',
  EXPIRED: 'neutral',
  EXHAUSTED: 'warning',
}
const locked = computed(() => Boolean(editing.value?.has_redemptions))
const date = (value) =>
  new Date(value).toLocaleString('vi-VN', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
// <input type="datetime-local"> works in local time without a zone.
const toLocal = (iso) => {
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}
const worth = (voucher) =>
  voucher.type === 'PERCENT'
    ? `Giảm ${Number(voucher.value)}%${voucher.max_discount ? `, tối đa ${formatPrice(voucher.max_discount)}` : ''}`
    : `Giảm ${formatPrice(voucher.value)}`
async function load(page = 1) {
  busy.value = true
  error.value = ''
  try {
    const result = await api.listVouchers(props.admin, {
      page,
      per_page: 20,
      ...(props.admin && scope.value ? { scope: scope.value } : {}),
    })
    rows.value = result.data
    meta.value = result.meta
  } catch (failure) {
    error.value = domainError(failure)
  } finally {
    busy.value = false
  }
}
function open(voucher = null) {
  editing.value = voucher || {}
  formError.value = ''
  formErrors.value = {}
  Object.assign(
    form,
    voucher
      ? {
          code: voucher.code,
          type: voucher.type,
          value: String(Number(voucher.value)),
          min_order: String(Number(voucher.min_order)),
          max_discount: voucher.max_discount ? String(Number(voucher.max_discount)) : '',
          usage_limit: voucher.usage_limit ?? '',
          per_user_limit: voucher.per_user_limit ?? '',
          starts_at: toLocal(voucher.starts_at),
          ends_at: toLocal(voucher.ends_at),
        }
      : blank(),
  )
}
async function save() {
  if (saving.value) return
  saving.value = true
  formError.value = ''
  formErrors.value = {}
  const limit = (value) => (value === '' || value === null ? null : Number(value))
  // A used voucher only accepts its end date and total limit.
  const body = locked.value
    ? { ends_at: new Date(form.ends_at).toISOString(), usage_limit: limit(form.usage_limit) }
    : {
        code: form.code.trim().toUpperCase(),
        type: form.type,
        value: String(form.value).trim(),
        min_order: String(form.min_order || '0').trim(),
        max_discount:
          form.type === 'PERCENT' && String(form.max_discount).trim() !== ''
            ? String(form.max_discount).trim()
            : null,
        usage_limit: limit(form.usage_limit),
        per_user_limit: limit(form.per_user_limit),
        starts_at: form.starts_at ? new Date(form.starts_at).toISOString() : '',
        ends_at: form.ends_at ? new Date(form.ends_at).toISOString() : '',
      }
  try {
    if (editing.value.id) await api.updateVoucher(props.admin, editing.value.id, body)
    else await api.createVoucher(props.admin, body)
    success.value = editing.value.id ? 'Đã lưu mã giảm giá.' : 'Đã tạo mã giảm giá.'
    editing.value = null
    await load(meta.value?.current_page || 1)
  } catch (failure) {
    formError.value = domainError(failure)
    formErrors.value = localizeValidationErrors(failure.response?.data?.errors) || {}
  } finally {
    saving.value = false
  }
}
async function toggle(voucher) {
  if (busy.value) return
  error.value = ''
  success.value = ''
  busy.value = true
  try {
    if (voucher.status === 'ACTIVE') await api.deactivateVoucher(props.admin, voucher.id)
    else
      await api.updateVoucher(props.admin, voucher.id, {
        status: 'ACTIVE',
        expected_status: voucher.status,
      })
    success.value = voucher.status === 'ACTIVE' ? 'Đã tắt mã.' : 'Đã bật lại mã.'
  } catch (failure) {
    error.value = domainError(failure)
  } finally {
    busy.value = false
  }
  await load(meta.value?.current_page || 1)
}
function filter(value) {
  scope.value = value
  load()
}
onMounted(() => load())
</script>
<template>
  <section>
    <PageHeader
      title="Mã giảm giá"
      :eyebrow="admin ? 'Quản trị · Sàn mua bán' : 'Không gian người bán'"
      :description="
        admin
          ? 'Mã toàn sàn do sàn chịu chi phí. Mã của gian hàng chỉ xem, không sửa tại đây.'
          : 'Mã chỉ áp dụng cho sản phẩm của gian hàng bạn; phần giảm do gian hàng chịu.'
      "
      ><button type="button" class="btn btn-primary" data-voucher-create @click="open()">
        + Tạo mã
      </button></PageHeader
    >
    <div v-if="admin" class="order-tabs" role="group" aria-label="Lọc theo phạm vi mã">
      <button
        v-for="[value, label] in [
          ['', 'Tất cả'],
          ['PLATFORM', 'Mã toàn sàn'],
          ['SHOP', 'Mã gian hàng'],
        ]"
        :key="value"
        type="button"
        class="order-tab"
        :class="{ 'is-active': scope === value }"
        :aria-pressed="scope === value"
        @click="filter(value)"
      >
        {{ label }}
      </button>
    </div>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Chưa có mã giảm giá."
      description="Tạo mã đầu tiên để người mua nhập khi đặt hàng."
    />
    <div v-if="rows.length" :class="admin ? 'admin-table-wrap' : 'seller-table-wrap'">
      <table class="table" :class="admin ? 'admin-table' : 'seller-table'">
        <thead>
          <tr>
            <th scope="col">Mã</th>
            <th scope="col">Mức giảm</th>
            <th scope="col">Điều kiện</th>
            <th scope="col">Đã dùng / giới hạn</th>
            <th scope="col">Thời gian</th>
            <th scope="col">Trạng thái</th>
            <th scope="col">Thao tác</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="voucher in rows" :key="voucher.id" :data-voucher="voucher.id">
            <td data-label="Mã">
              <strong class="voucher-code">{{ voucher.code }}</strong>
              <div class="small text-muted">
                {{ voucher.scope === 'PLATFORM' ? 'Toàn sàn' : voucher.shop?.name || 'Gian hàng' }}
              </div>
            </td>
            <td data-label="Mức giảm">{{ worth(voucher) }}</td>
            <td data-label="Điều kiện">
              Đơn từ {{ formatPrice(voucher.min_order) }}
              <div v-if="voucher.per_user_limit" class="small text-muted">
                Mỗi người {{ voucher.per_user_limit }} lượt
              </div>
            </td>
            <td data-label="Đã dùng / giới hạn" data-voucher-usage>
              {{ voucher.used_count }} / {{ voucher.usage_limit ?? 'không giới hạn' }}
            </td>
            <td data-label="Thời gian" class="small">
              {{ date(voucher.starts_at) }}<br />→ {{ date(voucher.ends_at) }}
            </td>
            <td data-label="Trạng thái">
              <span class="buyer-status" :class="`buyer-status-${stateTones[voucher.state]}`">{{
                stateLabels[voucher.state] || voucher.state
              }}</span>
            </td>
            <td data-label="Thao tác">
              <div v-if="voucher.can_manage" class="d-flex gap-2 flex-wrap">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary"
                  data-voucher-edit
                  @click="open(voucher)"
                >
                  Sửa
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary"
                  data-voucher-toggle
                  :disabled="busy"
                  @click="toggle(voucher)"
                >
                  {{ voucher.status === 'ACTIVE' ? 'Tắt mã' : 'Bật lại' }}
                </button>
              </div>
              <span v-else class="small text-muted">Chỉ xem</span>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
    <AccessibleDialog
      v-if="editing"
      id="voucher-dialog"
      class="voucher-dialog"
      :title="editing.id ? `Sửa mã ${editing.code}` : 'Tạo mã giảm giá'"
      :description="
        locked
          ? 'Mã đã có người dùng: chỉ đổi được ngày kết thúc và giới hạn lượt dùng.'
          : admin
            ? 'Mã toàn sàn, áp dụng cho cả đơn sau khi trừ mã của gian hàng.'
            : 'Mã áp dụng cho phần đơn thuộc gian hàng của bạn.'
      "
      :busy="saving"
      @dismiss="editing = null"
    >
      <form id="voucher-form" class="voucher-form" novalidate @submit.prevent="save">
        <div v-if="formError" role="alert" class="alert alert-danger">{{ formError }}</div>
        <div class="voucher-field">
          <label for="voucher-code" class="form-label">Mã (4–20 ký tự A–Z, 0–9)</label>
          <input
            id="voucher-code"
            v-model="form.code"
            class="form-control voucher-code"
            maxlength="20"
            autocomplete="off"
            :disabled="locked"
            :aria-invalid="formErrors.code ? 'true' : undefined"
          />
          <div v-if="formErrors.code" class="invalid-feedback d-block">
            {{ formErrors.code.join(' ') }}
          </div>
        </div>
        <div class="voucher-field">
          <label for="voucher-type" class="form-label">Loại giảm</label>
          <select id="voucher-type" v-model="form.type" class="form-select" :disabled="locked">
            <option value="FIXED">Số tiền cố định</option>
            <option value="PERCENT">Phần trăm</option>
          </select>
        </div>
        <div class="voucher-field">
          <label for="voucher-value" class="form-label">{{
            form.type === 'PERCENT' ? 'Phần trăm giảm (1–100)' : 'Số tiền giảm (₫)'
          }}</label>
          <input
            id="voucher-value"
            v-model="form.value"
            class="form-control"
            inputmode="decimal"
            :disabled="locked"
            :aria-invalid="formErrors.value ? 'true' : undefined"
          />
          <div v-if="formErrors.value" class="invalid-feedback d-block">
            {{ formErrors.value.join(' ') }}
          </div>
        </div>
        <div v-if="form.type === 'PERCENT'" class="voucher-field">
          <label for="voucher-max" class="form-label">Giảm tối đa (₫, bỏ trống nếu không giới hạn)</label>
          <input
            id="voucher-max"
            v-model="form.max_discount"
            class="form-control"
            inputmode="decimal"
            :disabled="locked"
          />
          <div v-if="formErrors.max_discount" class="invalid-feedback d-block">
            {{ formErrors.max_discount.join(' ') }}
          </div>
        </div>
        <div class="voucher-field">
          <label for="voucher-min" class="form-label">Đơn tối thiểu (₫)</label>
          <input
            id="voucher-min"
            v-model="form.min_order"
            class="form-control"
            inputmode="decimal"
            :disabled="locked"
          />
          <div v-if="formErrors.min_order" class="invalid-feedback d-block">
            {{ formErrors.min_order.join(' ') }}
          </div>
        </div>
        <div class="voucher-field">
          <label for="voucher-limit" class="form-label">Tổng lượt dùng (bỏ trống = không giới hạn)</label>
          <input
            id="voucher-limit"
            v-model="form.usage_limit"
            class="form-control"
            type="number"
            min="1"
            step="1"
          />
          <div v-if="formErrors.usage_limit" class="invalid-feedback d-block">
            {{ formErrors.usage_limit.join(' ') }}
          </div>
        </div>
        <div class="voucher-field">
          <label for="voucher-per-user" class="form-label">Lượt dùng mỗi người (bỏ trống = không giới hạn)</label>
          <input
            id="voucher-per-user"
            v-model="form.per_user_limit"
            class="form-control"
            type="number"
            min="1"
            step="1"
            :disabled="locked"
          />
        </div>
        <div class="voucher-field">
          <label for="voucher-start" class="form-label">Bắt đầu</label>
          <input
            id="voucher-start"
            v-model="form.starts_at"
            class="form-control"
            type="datetime-local"
            :disabled="locked"
          />
          <div v-if="formErrors.starts_at" class="invalid-feedback d-block">
            {{ formErrors.starts_at.join(' ') }}
          </div>
        </div>
        <div class="voucher-field">
          <label for="voucher-end" class="form-label">Kết thúc</label>
          <input id="voucher-end" v-model="form.ends_at" class="form-control" type="datetime-local" />
          <div v-if="formErrors.ends_at" class="invalid-feedback d-block">
            {{ formErrors.ends_at.join(' ') }}
          </div>
        </div>
      </form>
      <template #footer>
        <div class="d-flex justify-content-end gap-2">
          <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="editing = null">
            Hủy
          </button>
          <button type="submit" form="voucher-form" class="btn btn-primary" :disabled="saving">
            {{ saving ? 'Đang lưu…' : 'Lưu mã' }}
          </button>
        </div>
      </template>
    </AccessibleDialog>
  </section>
</template>
<style scoped>
.voucher-code {
  font-family: var(--font-money);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.voucher-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-4);
}
.voucher-form > .alert,
.voucher-form > .voucher-field:first-of-type {
  grid-column: 1 / -1;
}
@media (max-width: 575px) {
  .voucher-form {
    grid-template-columns: 1fr;
  }
}
</style>
<style>
.ui-dialog-backdrop.voucher-dialog .ui-dialog {
  width: min(44rem, 100%);
}
</style>

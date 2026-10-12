<script setup>
import { computed, nextTick, onUnmounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import * as api from '../api/report.api'
import { hideVideoComment } from '../api/videoComment.api'
import { domainError } from '../api/domain.api'
import {
  reportTypes,
  reportReasons,
  reportStatuses,
  reportActions,
  reportDate,
} from '../utils/reportPresentation'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
const props = defineProps({ admin: Boolean })
const auth = useAuthStore(),
  route = useRoute(),
  router = useRouter()
const rows = ref([]),
  meta = ref(null),
  busy = ref(false),
  error = ref(''),
  selected = ref(null),
  detailBusy = ref(false),
  actionBusy = ref(false),
  actionError = ref(''),
  success = ref(''),
  note = ref(''),
  status = ref(''),
  confirmHide = ref(false),
  detailHeading = ref(null)
// The Action Center links here with the list already filtered.
const filters = reactive({
  status: Object.hasOwn(reportStatuses, route.query.status) ? route.query.status : '',
  target_type: Object.hasOwn(reportTypes, route.query.target_type) ? route.query.target_type : '',
  reason: '',
  sort: 'newest',
  from: '',
  to: '',
})
const actions = computed(() => reportActions[selected.value?.status] || [])
// Admins never rule on reports against their own shop, product or comment.
const canModerate = computed(() => props.admin && selected.value?.can_moderate !== false)
// Keeping the current status is a note-only edit; it is always offered first for closed reports.
const choices = computed(() =>
  selected.value ? [...actions.value, selected.value.status] : [],
)
const noteOnly = computed(() => status.value === selected.value?.status)
const canHide = computed(
  () =>
    canModerate.value &&
    selected.value?.target.type === 'VIDEO_COMMENT' &&
    selected.value.target.available &&
    selected.value.target.status === 'PUBLISHED',
)
let sequence = 0,
  detailSequence = 0,
  disposed = false
const allowed = () => auth.isAuthenticated && (!props.admin || auth.isAdmin)
async function load(page = 1) {
  if (!allowed()) return
  const current = ++sequence,
    owner = auth.user.id
  busy.value = true
  error.value = ''
  rows.value = []
  meta.value = null
  try {
    const result = await api.listReports(props.admin, {
      page,
      per_page: 20,
      ...Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== '')),
    })
    if (!disposed && current === sequence && auth.user?.id === owner) {
      rows.value = result.data
      meta.value = result.meta
    }
  } catch (failure) {
    if (!disposed && current === sequence && auth.user?.id === owner)
      error.value = domainError(failure)
  } finally {
    if (!disposed && current === sequence) busy.value = false
  }
}
async function show(id) {
  if (!allowed()) return
  const current = ++detailSequence,
    owner = auth.user.id
  detailBusy.value = true
  selected.value = null
  actionError.value = ''
  success.value = ''
  confirmHide.value = false
  try {
    const result = await api.getReport(props.admin, id)
    if (!disposed && current === detailSequence && auth.user?.id === owner) {
      selected.value = result
      note.value = result.admin_note || ''
      status.value = reportActions[result.status]?.[0] || result.status
      await nextTick()
      detailHeading.value?.focus()
    }
  } catch (failure) {
    if (!disposed && current === detailSequence && auth.user?.id === owner)
      actionError.value = domainError(failure)
  } finally {
    if (!disposed && current === detailSequence) detailBusy.value = false
  }
}
async function processReport() {
  if (!auth.isAdmin || !canModerate.value || actionBusy.value || !choices.value.includes(status.value))
    return
  const owner = auth.user.id,
    id = selected.value.id,
    onlyNote = noteOnly.value
  actionBusy.value = true
  actionError.value = ''
  success.value = ''
  try {
    const result = await api.updateReportStatus(id, {
      expected_status: selected.value.status,
      status: status.value,
      admin_note: note.value.trim() || null,
    })
    if (!disposed && auth.user?.id === owner && selected.value?.id === id) {
      selected.value = result
      note.value = result.admin_note || ''
      status.value = reportActions[result.status]?.[0] || result.status
      success.value = onlyNote ? 'Đã lưu ghi chú nội bộ.' : 'Đã cập nhật kết quả xử lý báo cáo.'
      await load(meta.value?.current_page || 1)
    }
  } catch (failure) {
    if (!disposed && auth.user?.id === owner)
      actionError.value =
        failure.response?.status === 409
          ? 'Trạng thái đã thay đổi. Vui lòng tải lại báo cáo trước khi xử lý.'
          : domainError(failure)
  } finally {
    if (!disposed) actionBusy.value = false
  }
}
async function hideComment() {
  if (!canHide.value || actionBusy.value) return
  const owner = auth.user.id,
    id = selected.value.id
  actionBusy.value = true
  actionError.value = ''
  success.value = ''
  try {
    await hideVideoComment(selected.value.target.id)
    if (!disposed && auth.user?.id === owner && selected.value?.id === id) {
      const updated = await api.getReport(true, id)
      if (disposed || auth.user?.id !== owner || selected.value?.id !== id) return
      selected.value = updated
      success.value = 'Đã ẩn bình luận. Trạng thái báo cáo chưa thay đổi.'
      confirmHide.value = false
    }
  } catch (failure) {
    if (!disposed && auth.user?.id === owner) actionError.value = domainError(failure)
  } finally {
    if (!disposed) actionBusy.value = false
  }
}
watch(
  () => auth.user?.id,
  () => {
    sequence++
    detailSequence++
    rows.value = []
    selected.value = null
    error.value = actionError.value = success.value = ''
    busy.value = detailBusy.value = false
    if (allowed()) load()
  },
  { immediate: true },
)
watch(
  () => route.query.report,
  (id) => {
    if (/^[1-9]\d*$/.test(String(id || ''))) show(id)
  },
  { immediate: true },
)
onUnmounted(() => {
  disposed = true
  sequence++
  detailSequence++
})
function openReport(id) {
  router.replace({ query: { ...route.query, report: id } })
  if (String(route.query.report) === String(id)) show(id)
}
</script>
<template>
  <section v-if="allowed()" class="reports-view">
    <PageHeader
      :title="admin ? 'Báo cáo vi phạm' : 'Báo cáo của tôi'"
      :eyebrow="admin ? 'Quản trị · An toàn sàn mua bán' : 'Tài khoản · An toàn sàn mua bán'"
      description="Báo cáo được xem xét riêng; nội dung chỉ thay đổi khi quản trị viên thực hiện kiểm duyệt."
    />
    <form class="row g-3 my-3" @submit.prevent="load()">
      <label class="col-sm-6 col-lg-3"
        >Trạng thái<select v-model="filters.status" name="status" class="form-select">
          <option value="">Tất cả trạng thái</option>
          <option v-for="(label, value) in reportStatuses" :key="value" :value="value">
            {{ label }}
          </option>
        </select></label
      >
      <label class="col-sm-6 col-lg-3"
        >Loại nội dung<select v-model="filters.target_type" name="target_type" class="form-select">
          <option value="">Tất cả nội dung</option>
          <option v-for="(label, value) in reportTypes" :key="value" :value="value">
            {{ label }}
          </option>
        </select></label
      >
      <label class="col-sm-6 col-lg-3"
        >Lý do<select v-model="filters.reason" name="reason" class="form-select">
          <option value="">Tất cả lý do</option>
          <option v-for="(label, value) in reportReasons" :key="value" :value="value">
            {{ label }}
          </option>
        </select></label
      >
      <label class="col-sm-6 col-lg-3"
        >Thứ tự<select v-model="filters.sort" name="sort" class="form-select">
          <option value="newest">Mới nhất</option>
          <option value="oldest">Cũ nhất</option>
        </select></label
      >
      <label v-if="admin" class="col-sm-6 col-lg-3"
        >Từ ngày<input v-model="filters.from" name="from" type="date" class="form-control"
      /></label>
      <label v-if="admin" class="col-sm-6 col-lg-3"
        >Đến ngày<input
          v-model="filters.to"
          name="to"
          type="date"
          class="form-control"
          :min="filters.from || undefined"
      /></label>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" :disabled="busy">Lọc báo cáo</button
        ><button
          type="button"
          class="btn btn-outline-secondary"
          :disabled="busy"
          @click="load(meta?.current_page || 1)"
        >
          Tải lại
        </button>
      </div>
    </form>
    <DomainFeedback :busy="busy" :error="error" />
    <p v-if="meta && !busy && !error" class="text-muted">{{ meta.total }} báo cáo trong kết quả</p>
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Chưa có báo cáo"
      description="Báo cáo sẽ xuất hiện tại đây sau khi được gửi. Thử thay đổi bộ lọc nếu cần."
    />
    <div v-if="rows.length" class="table-responsive border rounded-3">
      <table class="table align-middle mb-0">
        <caption class="visually-hidden">
          Danh sách báo cáo
        </caption>
        <thead>
          <tr>
            <th scope="col">Nội dung</th>
            <th v-if="admin" scope="col">Người gửi</th>
            <th scope="col">Lý do</th>
            <th scope="col">Trạng thái</th>
            <th scope="col">Ngày gửi</th>
            <th scope="col">Chi tiết</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in rows" :key="row.id">
            <td>
              <small class="text-muted">{{ row.target.label }}</small>
              <p class="mb-0 report-target-name">{{ row.target.name }}</p>
            </td>
            <td v-if="admin">{{ row.reporter?.name || 'Tài khoản không còn tồn tại' }}</td>
            <td>{{ row.reason_label }}</td>
            <td><StatusBadge :status="row.status" :label="row.status_label" /></td>
            <td>
              <time :datetime="row.created_at">{{ reportDate(row.created_at) }}</time>
            </td>
            <td>
              <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                :disabled="actionBusy"
                :aria-label="`Xem báo cáo số ${row.id}`"
                @click="openReport(row.id)"
              >
                Xem
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
    <DomainFeedback :busy="detailBusy" :error="actionError" :success="success" />
    <button
      v-if="actionError && route.query.report"
      class="btn btn-outline-secondary"
      @click="show(route.query.report)"
    >
      Tải lại báo cáo
    </button>
    <article v-if="selected" class="card mt-4">
      <div class="card-body p-4">
        <h2 ref="detailHeading" tabindex="-1" class="h4">Báo cáo số {{ selected.id }}</h2>
        <StatusBadge :status="selected.status" :label="selected.status_label" />
        <p class="mt-3">
          <strong>{{ selected.target.label }}:</strong> {{ selected.target.name }}
        </p>
        <p><strong>Lý do:</strong> {{ selected.reason_label }}</p>
        <p v-if="selected.description" class="report-text">
          <strong>Mô tả:</strong> {{ selected.description }}
        </p>
        <p class="text-muted">
          Gửi {{ reportDate(selected.created_at) }} · Xử lý {{ reportDate(selected.handled_at) }}
        </p>
        <p v-if="!selected.target.available" class="alert alert-secondary">
          Nội dung không còn truy cập được. Báo cáo vẫn được lưu để xem xét.
        </p>
        <template v-if="admin">
          <p v-if="selected.target.status">
            Trạng thái nội dung: <StatusBadge :status="selected.target.status" />
            <span v-if="selected.target.deleted" class="text-muted">· Đã xóa</span>
          </p>
          <p v-if="selected.target.available" class="text-muted">
            {{
              selected.target.public_visible
                ? 'Nội dung đang công khai.'
                : 'Nội dung hiện không công khai.'
            }}
          </p>
          <div v-if="selected.target.context" class="border rounded-3 p-3 mb-3">
            <p v-if="selected.target.context.description" class="report-text">
              {{ selected.target.context.description }}
            </p>
            <p v-if="selected.target.context.shop">
              Gian hàng: {{ selected.target.context.shop.name }}
              <StatusBadge :status="selected.target.context.shop.status" />
            </p>
            <p v-if="selected.target.context.owner">
              Chủ gian hàng: {{ selected.target.context.owner.name }}
            </p>
            <p v-if="selected.target.context.author">
              Tác giả: {{ selected.target.context.author.name }}
            </p>
            <p v-if="selected.target.context.body" class="report-text">
              {{ selected.target.context.body }}
            </p>
            <RouterLink
              v-if="selected.target.context.product"
              :to="selected.target.context.product.route"
              >{{ selected.target.context.product.name }} → Kiểm duyệt sản phẩm</RouterLink
            >
          </div>
          <RouterLink
            v-if="selected.target.route"
            :to="selected.target.route"
            class="btn btn-outline-secondary mb-3"
            >Mở kiểm duyệt
            {{ selected.target.type === 'PRODUCT' ? 'sản phẩm' : 'gian hàng' }}</RouterLink
          >
          <div v-if="canHide" class="mb-3">
            <button v-if="!confirmHide" class="btn btn-outline-danger" @click="confirmHide = true">
              Ẩn bình luận
            </button>
            <div v-else role="group" aria-label="Xác nhận ẩn bình luận">
              <p>Ẩn bình luận này? Trạng thái báo cáo sẽ không tự thay đổi.</p>
              <button class="btn btn-danger me-2" :disabled="actionBusy" @click="hideComment">
                Xác nhận ẩn</button
              ><button
                class="btn btn-outline-secondary"
                :disabled="actionBusy"
                @click="confirmHide = false"
              >
                Giữ lại
              </button>
            </div>
          </div>
          <p class="small text-muted">
            Xử lý báo cáo không tự ẩn nội dung. Thao tác kiểm duyệt ở trên dùng quy tắc hiện có.
          </p>
          <p v-if="!canModerate" class="alert alert-secondary" data-own-report>
            Báo cáo này nhắm vào nội dung của chính bạn nên bạn không thể xử lý. Một quản trị viên
            khác sẽ tiếp nhận.
          </p>
          <form v-if="canModerate" class="border-top pt-3" @submit.prevent="processReport">
            <label for="report-next-status" class="form-label">Bước xử lý tiếp theo</label
            ><select
              id="report-next-status"
              v-model="status"
              name="next_status"
              class="form-select mb-3"
              :disabled="actionBusy"
              required
            >
              <option v-for="value in choices" :key="value" :value="value">
                {{
                  value === selected.status
                    ? `Giữ "${reportStatuses[value]}" — chỉ lưu ghi chú`
                    : reportStatuses[value]
                }}
              </option>
            </select>
            <label for="report-admin-note" class="form-label"
              >Ghi chú nội bộ (không hiển thị cho người báo cáo)</label
            ><textarea
              id="report-admin-note"
              v-model="note"
              name="admin_note"
              class="form-control mb-3"
              rows="3"
              maxlength="1000"
              :disabled="actionBusy"
            /><button class="btn btn-primary" :disabled="actionBusy">
              {{ actionBusy ? 'Đang lưu…' : noteOnly ? 'Lưu ghi chú' : 'Xác nhận xử lý' }}
            </button>
          </form>
          <p v-else-if="selected.admin_note" class="report-text">
            <strong>Ghi chú nội bộ:</strong> {{ selected.admin_note }}
          </p>
          <p v-if="selected.handler" class="text-muted mt-3">
            Người xử lý: {{ selected.handler.name }}
          </p>
        </template>
        <RouterLink
          v-else-if="selected.target.route"
          :to="selected.target.route"
          class="btn btn-outline-secondary"
          >Xem nội dung</RouterLink
        >
      </div>
    </article>
  </section>
</template>
<style scoped>
.reports-view {
  min-width: 0;
}
.reports-view table {
  min-width: 640px;
}
.report-target-name {
  max-width: 24rem;
  overflow-wrap: anywhere;
}
.report-text {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.reports-view label {
  font-weight: 600;
}
</style>

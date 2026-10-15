<script setup>
import AppIcon from './ui/AppIcon.vue'
import { nextTick, onUnmounted, ref, useId, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { createReport } from '../api/report.api'
import { domainError } from '../api/domain.api'
import { reportReasons } from '../utils/reportPresentation'
const props = defineProps({
  targetType: { type: String, required: true },
  targetId: { type: [String, Number], required: true },
  label: { type: String, default: 'Báo cáo nội dung' },
})
const emit = defineEmits(['submitted'])
const auth = useAuthStore(),
  route = useRoute(),
  router = useRouter(),
  id = useId()
const opened = ref(false),
  dialog = ref(null),
  trigger = ref(null),
  reason = ref(''),
  description = ref(''),
  busy = ref(false),
  error = ref(''),
  errors = ref({}),
  success = ref(false)
let disposed = false
async function open() {
  if (!auth.isAuthenticated)
    return router.push({ name: 'login', query: { redirect: route.fullPath } })
  reason.value = description.value = error.value = ''
  errors.value = {}
  success.value = false
  opened.value = true
  await nextTick()
  dialog.value?.showModal()
}
function close() {
  if (!busy.value) {
    dialog.value?.close?.()
    opened.value = false
    trigger.value?.focus()
  }
}
function trapFocus(event) {
  const controls = [
    ...dialog.value.querySelectorAll(
      'button:not(:disabled), a[href], select:not(:disabled), textarea:not(:disabled)',
    ),
  ]
  const first = controls[0],
    last = controls.at(-1)
  if (!first) return
  if (
    (!event.shiftKey && document.activeElement === last) ||
    (event.shiftKey && document.activeElement === first) ||
    !dialog.value.contains(document.activeElement)
  ) {
    event.preventDefault()
    ;(event.shiftKey ? last : first).focus()
  }
}
async function submit() {
  if (busy.value || success.value) return
  const owner = auth.user?.id
  busy.value = true
  error.value = ''
  errors.value = {}
  try {
    const report = await createReport({
      target_type: props.targetType,
      target_id: props.targetId,
      reason: reason.value,
      description: description.value.trim() || null,
    })
    if (!disposed && auth.user?.id === owner) {
      success.value = true
      emit('submitted', report)
    }
  } catch (failure) {
    if (!disposed && auth.user?.id === owner) {
      error.value =
        failure.response?.status === 409
          ? 'Bạn đã có báo cáo đang được xem xét cho nội dung này.'
          : domainError(failure)
      errors.value = failure.response?.data?.errors || {}
    }
  } finally {
    if (!disposed) busy.value = false
  }
}
watch(
  () => auth.user?.id,
  () => {
    dialog.value?.close?.()
    opened.value = false
  },
)
onUnmounted(() => {
  disposed = true
  dialog.value?.close?.()
})
</script>
<template>
  <button
    ref="trigger"
    type="button"
    class="btn btn-sm btn-link text-secondary report-trigger btn-icon"
    @click="open"
  >
    <AppIcon name="flag" :size="16" />{{ label }}
  </button>
  <dialog
    ref="dialog"
    class="report-dialog"
    :aria-labelledby="`${id}-title`"
    :aria-describedby="`${id}-help`"
    @cancel.prevent="close"
    @keydown.tab="trapFocus"
  >
    <template v-if="opened">
      <header class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2 :id="`${id}-title`" class="h5 mb-0">Báo cáo nội dung</h2>
        <button
          type="button"
          class="btn btn-sm btn-outline-secondary"
          :disabled="busy"
          aria-label="Đóng báo cáo"
          @click="close"
        >
          Đóng
        </button>
      </header>
      <p :id="`${id}-help`" class="text-muted">Báo cáo sẽ được gửi đến quản trị viên để xem xét.</p>
      <div v-if="success" role="status">
        <p class="alert alert-success">
          Đã gửi báo cáo. Cảm ơn bạn đã giúp sàn mua bán an toàn hơn.
        </p>
        <RouterLink to="/reports" class="btn btn-outline-primary" @click="close"
          >Báo cáo của tôi</RouterLink
        >
      </div>
      <form v-else @submit.prevent="submit">
        <p v-if="error" class="alert alert-danger" role="alert">{{ error }}</p>
        <label :for="`${id}-reason`" class="form-label"
          >Lý do <span aria-hidden="true">*</span></label
        >
        <select
          :id="`${id}-reason`"
          v-model="reason"
          name="reason"
          class="form-select mb-2"
          required
          :disabled="busy"
          :aria-invalid="!!errors.reason"
          :aria-describedby="`${id}-reason-error`"
        >
          <option value="" disabled>Chọn lý do</option>
          <option v-for="(label, value) in reportReasons" :key="value" :value="value">
            {{ label }}
          </option>
        </select>
        <p :id="`${id}-reason-error`" class="text-danger small">
          {{ errors.reason?.length ? 'Vui lòng chọn lý do hợp lệ.' : '' }}
        </p>
        <label :for="`${id}-description`" class="form-label"
          >Mô tả bổ sung {{ reason === 'OTHER' ? '(bắt buộc)' : '(không bắt buộc)' }}</label
        >
        <textarea
          :id="`${id}-description`"
          v-model="description"
          name="description"
          class="form-control"
          rows="4"
          maxlength="1000"
          :required="reason === 'OTHER'"
          :disabled="busy"
          :aria-invalid="!!errors.description"
          :aria-describedby="`${id}-description-error`"
        />
        <p :id="`${id}-description-error`" class="text-danger small mt-2">
          {{ errors.description?.length ? 'Vui lòng nhập mô tả hợp lệ, tối đa 1.000 ký tự.' : '' }}
        </p>
        <div class="d-flex justify-content-end gap-2 mt-3">
          <button type="button" class="btn btn-outline-secondary" :disabled="busy" @click="close">
            Hủy</button
          ><button
            class="btn btn-primary"
            :disabled="busy || !reason || (reason === 'OTHER' && !description.trim())"
          >
            {{ busy ? 'Đang gửi…' : 'Gửi báo cáo' }}
          </button>
        </div>
      </form>
    </template>
  </dialog>
</template>
<style scoped>
.report-trigger {
  text-align: left;
}
.report-dialog {
  width: min(34rem, calc(100vw - 2rem));
  max-height: calc(100dvh - 2rem);
  overflow-y: auto;
  border: 1px solid var(--bs-border-color);
  border-radius: var(--bs-border-radius-lg);
  padding: 1.5rem;
  color: var(--bs-body-color);
  background: var(--bs-body-bg);
}
.report-dialog::backdrop {
  background: var(--scrim);
}
</style>

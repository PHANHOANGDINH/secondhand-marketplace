<script setup>
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import * as api from '../api/productVideo.api'
import { useFeedback } from '../composables/useFeedback'
import { domainError } from '../api/domain.api'
import DomainFeedback from './DomainFeedback.vue'
import StatusBadge from './ui/StatusBadge.vue'
const props = defineProps({
  product: { type: Object, required: true },
  disabled: Boolean,
  headingLevel: { type: Number, default: 2 },
  inputId: { type: String, default: 'product-video' },
})
const emit = defineEmits(['changed', 'busy-change', 'dirty-change'])
const rows = ref([]),
  limits = ref({ max_files: 3, max_bytes: 50 * 1024 * 1024 }),
  loading = ref(false),
  loadError = ref(''),
  file = ref(null),
  preview = ref(''),
  input = ref(null),
  progress = ref(null),
  editing = ref(null),
  confirming = ref(null)
const uploadForm = reactive({ title: '', description: '' }),
  editForm = reactive({ title: '', description: '' })
const { busy, error, errors, success, run } = useFeedback()
const blocked = computed(
  () => props.disabled || !props.product.can_edit || loading.value || busy.value,
)
const dirty = computed(
  () =>
    !!file.value ||
    !!uploadForm.title ||
    !!uploadForm.description ||
    (editing.value !== null &&
      (editForm.title !== (rows.value.find((r) => r.id === editing.value)?.title || '') ||
        editForm.description !==
          (rows.value.find((r) => r.id === editing.value)?.description || ''))),
)
let sequence = 0
async function load() {
  const s = ++sequence
  rows.value = []
  loadError.value = ''
  loading.value = false
  if (!props.product.id) return
  loading.value = true
  try {
    const result = await api.listSellerVideos(props.product.id)
    if (s === sequence) {
      rows.value = result.data
      limits.value = result.limits || limits.value
    }
  } catch (e) {
    if (s === sequence) loadError.value = domainError(e)
  } finally {
    if (s === sequence) loading.value = false
  }
}
function resetFile() {
  if (preview.value) URL.revokeObjectURL(preview.value)
  preview.value = ''
  file.value = null
  if (input.value) input.value.value = ''
}
function choose(e) {
  const candidate = e.target.files?.[0]
  resetFile()
  error.value = ''
  if (!candidate) return
  if (
    !['video/mp4', 'video/webm'].includes(candidate.type) ||
    !/\.(mp4|webm)$/i.test(candidate.name)
  ) {
    error.value = 'Chỉ hỗ trợ video MP4 hoặc WebM.'
    return
  }
  if (candidate.size > limits.value.max_bytes) {
    error.value = `Video không được vượt quá ${Math.round(limits.value.max_bytes / 1024 / 1024)} MiB.`
    return
  }
  file.value = candidate
  preview.value = URL.createObjectURL(candidate)
}
const upload = () =>
  run(async () => {
    progress.value = 0
    await api.uploadVideo(props.product.id, file.value, { ...uploadForm }, (value) => {
      progress.value = value
    })
    resetFile()
    Object.assign(uploadForm, { title: '', description: '' })
    progress.value = null
    await load()
    emit('changed')
  }, 'Đã tải video lên.')
function edit(row) {
  editing.value = row.id
  Object.assign(editForm, { title: row.title || '', description: row.description || '' })
}
const save = (row) =>
  run(async () => {
    await api.updateVideo(props.product.id, row.id, { ...editForm })
    editing.value = null
    await load()
    emit('changed')
  }, 'Đã lưu thông tin video.')
const change = (row, data) =>
  run(async () => {
    await api.updateVideo(props.product.id, row.id, data)
    await load()
    emit('changed')
  }, 'Đã cập nhật video.')
const remove = (row) =>
  run(async () => {
    await api.deleteVideo(props.product.id, row.id)
    confirming.value = null
    await load()
    emit('changed')
  }, 'Đã xóa video.')
watch(
  () => props.product.id,
  () => {
    resetFile()
    editing.value = null
    confirming.value = null
    load()
  },
  { immediate: true },
)
watch(busy, (value) => emit('busy-change', value), { flush: 'sync' })
watch(dirty, (value) => emit('dirty-change', value), { immediate: true })
onBeforeUnmount(() => {
  sequence++
  resetFile()
})
</script>
<template>
  <section class="workspace-panel seller-videos mt-4" aria-label="Video đánh giá sản phẩm">
    <div class="workspace-panel-heading">
      <component :is="`h${headingLevel}`"
        >Video đánh giá
        <span class="text-muted">({{ rows.length }}/{{ limits.max_files }})</span></component
      >
    </div>
    <p class="text-secondary small">
      MP4 / WebM · tối đa {{ Math.round(limits.max_bytes / 1024 / 1024) }} MiB/video. Video không
      bắt buộc; hãy quay đúng sản phẩm và tránh thông tin riêng tư.
    </p>
    <DomainFeedback :busy="busy || loading" :error="error || loadError" :success="success" />
    <ul v-if="Object.keys(errors).length" class="text-danger" role="alert">
      <li v-for="(messages, key) in errors" :key="key">{{ messages.join(' ') }}</li>
    </ul>
    <button v-if="loadError" type="button" class="btn btn-outline-secondary" @click="load">
      Tải lại video
    </button>
    <p v-if="!product.id" class="form-text">Lưu bản nháp trước khi tải video lên.</p>
    <form v-if="!loadError" @submit.prevent="upload">
      <fieldset :disabled="blocked || !product.id || rows.length >= limits.max_files">
        <label :for="inputId" class="form-label">Chọn video đánh giá</label>
        <input
          :id="inputId"
          ref="input"
          name="video"
          type="file"
          accept="video/mp4,video/webm"
          class="visually-hidden"
          tabindex="-1"
          :aria-describedby="`${inputId}-help`"
          @change="choose"
        />
        <button
          type="button"
          class="btn btn-outline-primary"
          :aria-controls="inputId"
          :aria-describedby="`${inputId}-help`"
          @click="input?.click()"
        >Chọn video</button>
        <p class="small text-muted mt-2 mb-0" aria-live="polite">
          {{ file?.name || 'Chưa chọn video' }}
        </p>
        <p :id="`${inputId}-help`" class="form-text">
          Chỉ một tệp mỗi lần tải. Tệp sẽ được kiểm tra lại trên máy chủ.
        </p>
        <label :for="`${inputId}-title`" class="form-label">Tiêu đề (không bắt buộc)</label
        ><input
          :id="`${inputId}-title`"
          v-model="uploadForm.title"
          maxlength="200"
          class="form-control mb-2"
        />
        <label :for="`${inputId}-description`" class="form-label">Mô tả ngắn</label
        ><textarea
          :id="`${inputId}-description`"
          v-model="uploadForm.description"
          maxlength="1000"
          rows="2"
          class="form-control mb-3"
        />
        <video
          v-if="preview"
          :src="preview"
          controls
          preload="none"
          playsinline
          class="product-video-player mb-3"
          aria-label="Xem trước video đã chọn"
        />
        <button type="submit" class="btn btn-primary" :disabled="!file">Tải video lên</button>
      </fieldset>
    </form>
    <p v-if="busy && progress !== null" class="form-text" role="status">
      {{ progress === 100 ? 'Đã gửi tệp, đang lưu…' : `Đang tải ${progress}%` }}
    </p>
    <p v-if="rows.length >= limits.max_files" class="form-text">
      Đã đạt giới hạn video. Xóa video không cần thiết trước khi tải thêm.
    </p>
    <div class="row g-3 mt-2">
      <article v-for="(row, index) in rows" :key="row.id" class="col-lg-6" :data-video="row.id">
        <div class="card p-3 h-100">
          <video
            :src="row.url"
            :poster="row.poster_url || undefined"
            controls
            preload="none"
            playsinline
            class="product-video-player"
            :aria-label="row.title || `Video đánh giá ${index + 1}`"
          />
          <h3 class="h6 mt-3 text-break">{{ row.title || `Video đánh giá ${index + 1}` }}</h3>
          <p v-if="row.description" class="small text-break">{{ row.description }}</p>
          <div class="d-flex gap-2 flex-wrap mb-2">
            <StatusBadge
              :status="row.status"
              domain="video"
            /><span v-if="row.is_primary" class="badge text-bg-success">Video chính</span>
          </div>
          <form v-if="editing === row.id" @submit.prevent="save(row)">
            <fieldset :disabled="blocked">
              <label :for="`${inputId}-edit-${row.id}`" class="form-label">Tiêu đề video</label
              ><input
                :id="`${inputId}-edit-${row.id}`"
                v-model="editForm.title"
                maxlength="200"
                class="form-control mb-2"
              />
              <label :for="`${inputId}-edit-description-${row.id}`" class="form-label"
                >Mô tả video</label
              ><textarea
                :id="`${inputId}-edit-description-${row.id}`"
                v-model="editForm.description"
                maxlength="1000"
                class="form-control mb-2"
              />
              <button class="btn btn-primary btn-sm" type="submit">Lưu video</button
              ><button
                class="btn btn-outline-secondary btn-sm ms-2"
                type="button"
                @click="editing = null"
              >
                Hủy
              </button>
            </fieldset>
          </form>
          <div v-else class="d-flex gap-2 flex-wrap">
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :disabled="blocked || editing !== null"
              @click="edit(row)"
            >
              Sửa thông tin
            </button>
            <button
              v-if="row.status === 'READY' && !row.is_primary"
              type="button"
              class="btn btn-sm btn-outline-success"
              :disabled="blocked || editing !== null"
              @click="change(row, { is_primary: true })"
            >
              Đặt video chính
            </button>
            <button
              v-if="['READY', 'HIDDEN'].includes(row.status)"
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :disabled="blocked || editing !== null"
              @click="change(row, { status: row.status === 'READY' ? 'HIDDEN' : 'READY' })"
            >
              {{ row.status === 'READY' ? 'Ẩn video' : 'Hiện video' }}
            </button>
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :disabled="blocked || editing !== null || index === 0"
              :aria-label="`Đưa video ${index + 1} lên trước`"
              @click="change(row, { sort_order: index - 1 })"
            >
              Lên
            </button>
            <button
              type="button"
              class="btn btn-sm btn-outline-secondary"
              :disabled="blocked || editing !== null || index === rows.length - 1"
              :aria-label="`Đưa video ${index + 1} xuống sau`"
              @click="change(row, { sort_order: index + 1 })"
            >
              Xuống
            </button>
            <button
              type="button"
              class="btn btn-sm btn-outline-danger"
              :disabled="blocked || editing !== null"
              @click="confirming = row.id"
            >
              Xóa video
            </button>
          </div>
          <div
            v-if="confirming === row.id"
            class="alert alert-warning mt-2 mb-0"
            role="group"
            aria-label="Xác nhận xóa video"
          >
            <p>Xóa video này? Không thể hoàn tác.</p>
            <button
              type="button"
              class="btn btn-sm btn-danger"
              :disabled="blocked"
              @click="remove(row)"
            >
              Xác nhận xóa video</button
            ><button
              type="button"
              class="btn btn-sm btn-outline-secondary ms-2"
              :disabled="blocked"
              @click="confirming = null"
            >
              Giữ lại
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>
<style scoped>
.product-video-player {
  display: block;
  width: 100%;
  max-height: 280px;
  background: var(--surface-muted);
  border-radius: var(--radius-md);
  object-fit: contain;
}
.seller-videos {
  min-width: 0;
}
.seller-videos .card {
  border-color: var(--border);
}
</style>

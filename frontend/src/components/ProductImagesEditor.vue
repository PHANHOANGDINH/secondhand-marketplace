<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import * as productsApi from '../api/product.api'
import { useFeedback } from '../composables/useFeedback'
import DomainFeedback from './DomainFeedback.vue'
import ProductPhoto from './ProductPhoto.vue'
import AppIcon from './ui/AppIcon.vue'
const props = defineProps({
  product: { type: Object, required: true },
  disabled: Boolean,
  inputId: { type: String, default: 'product_images' },
  headingLevel: { type: Number, default: 2 },
})
const emit = defineEmits(['changed', 'previews-change', 'busy-change'])
const files = ref([]),
  previews = ref([]),
  input = ref(null),
  dragging = ref(false)
const LIMIT = 8
const room = computed(() => LIMIT - props.product.images.length - files.value.length)
const { busy, error, errors, success, clear, run } = useFeedback()
const blocked = computed(() => busy.value || props.disabled || !props.product.can_edit)
function resetFiles() {
  for (const preview of previews.value) URL.revokeObjectURL(preview.url)
  files.value = []
  previews.value = []
  if (input.value) input.value.value = ''
  emit('previews-change', [])
}
const valid = (file) =>
  ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) &&
  /\.(jpe?g|png|webp)$/i.test(file.name) &&
  file.size <= 5 * 1024 * 1024
const announce = () =>
  emit(
    'previews-change',
    previews.value.map((preview) => ({ ...preview })),
  )
// Several photos at once (multi-select or drag & drop); each pick adds to the ones already chosen.
// Invalid files are skipped one by one instead of cancelling the whole pick.
function addFiles(list) {
  clear()
  const picked = Array.from(list || [])
  const accepted = picked.filter(valid)
  const notes = []
  if (accepted.length < picked.length)
    notes.push(
      `Đã bỏ qua ${picked.length - accepted.length} ảnh không hợp lệ (chỉ nhận JPEG/PNG/WebP, tối đa 5 MiB mỗi ảnh).`,
    )
  const kept = accepted.slice(0, Math.max(0, room.value))
  if (kept.length < accepted.length)
    notes.push(`Mỗi sản phẩm tối đa ${LIMIT} ảnh: đã bỏ bớt ${accepted.length - kept.length} ảnh.`)
  if (notes.length) error.value = notes.join(' ')
  if (input.value) input.value.value = ''
  if (!kept.length) return
  files.value = [...files.value, ...kept]
  previews.value = [
    ...previews.value,
    ...kept.map((file) => ({ name: file.name, url: URL.createObjectURL(file) })),
  ]
  announce()
}
const choose = (event) => addFiles(event.target.files)
function drop(event) {
  dragging.value = false
  if (!blocked.value && room.value > 0) addFiles(event.dataTransfer?.files)
}
function unstage(index) {
  URL.revokeObjectURL(previews.value[index].url)
  files.value = files.value.filter((_, position) => position !== index)
  previews.value = previews.value.filter((_, position) => position !== index)
  announce()
}
const upload = () =>
  run(async () => {
    if (!props.product.id) return
    await productsApi.uploadProductImages(props.product.id, files.value)
    resetFiles()
    emit('changed')
  }, 'Đã tải ảnh lên.')
const primary = (id) =>
  run(async () => {
    await productsApi.setPrimaryImage(props.product.id, id)
    emit('changed')
  }, 'Đã đổi ảnh chính.')
const remove = (id) =>
  run(async () => {
    await productsApi.deleteProductImage(props.product.id, id)
    emit('changed')
  }, 'Đã xóa ảnh khỏi sản phẩm.')
const move = (index, offset) =>
  run(async () => {
    const ids = props.product.images.map((image) => image.id)
    const swap = ids[index]
    ids[index] = ids[index + offset]
    ids[index + offset] = swap
    await productsApi.reorderProductImages(props.product.id, ids)
    emit('changed')
  }, 'Đã đổi thứ tự ảnh.')
watch(busy, (value) => emit('busy-change', value), { flush: 'sync' })
onBeforeUnmount(resetFiles)
</script>
<template>
  <section class="mt-4 workspace-panel seller-images">
    <div class="workspace-panel-heading">
      <span class="workspace-step">01</span>
      <component :is="`h${headingLevel}`">Ảnh sản phẩm ({{ product.images.length }}/8)</component>
    </div>
    <p class="text-secondary">JPEG/PNG/WebP · tối đa 5 MiB và 20 triệu điểm ảnh/ảnh.</p>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div v-for="(messages, key) in errors" :key="key" class="text-danger">
      {{ messages.join(' ') }}
    </div>
    <div
      class="listing-upload-zone"
      :class="{ 'is-disabled': blocked || room <= 0, 'is-dragging': dragging }"
      data-upload-zone
      @dragenter.prevent="dragging = !blocked && room > 0"
      @dragover.prevent
      @dragleave.self="dragging = false"
      @drop.prevent="drop"
    >
      <span class="listing-upload-icon" aria-hidden="true"><AppIcon name="upload" :size="28" /></span>
      <label :for="inputId" class="form-label">Chọn ảnh sản phẩm</label>
      <p :id="`${inputId}-help`" class="text-muted">
        Chọn <strong>nhiều ảnh cùng lúc</strong> (giữ Ctrl hoặc Shift khi chọn) hoặc kéo thả ảnh vào
        đây · JPEG, PNG, WebP · tối đa {{ LIMIT }} ảnh. Ảnh vuông hiển thị đẹp nhất.
      </p>
      <input
        :id="inputId"
        ref="input"
        name="images"
        type="file"
        accept="image/jpeg,image/png,image/webp"
        multiple
        class="visually-hidden"
        tabindex="-1"
        :aria-describedby="`${inputId}-help`"
        :disabled="blocked || room <= 0"
        @change="choose"
      />
      <button
        type="button"
        class="btn btn-outline-primary"
        :disabled="blocked || room <= 0"
        :aria-controls="inputId"
        :aria-describedby="`${inputId}-help`"
        @click="input?.click()"
      >
        {{ files.length ? 'Chọn thêm ảnh' : 'Chọn ảnh' }}
      </button>
      <p class="small text-muted mb-0 mt-2" aria-live="polite">
        {{
          files.length
            ? `Đã chọn ${files.length} ảnh · còn chọn được ${Math.max(0, room)} ảnh`
            : 'Chưa chọn ảnh'
        }}
      </p>
    </div>
    <p v-if="!product.id" class="form-text">
      Bạn có thể xem trước ảnh đã chọn. Lưu bản nháp trước khi tải ảnh lên.
    </p>
    <ul v-if="previews.length" class="listing-staged" aria-label="Ảnh đã chọn, chưa tải lên">
      <li v-for="(preview, index) in previews" :key="preview.url" data-staged>
        <img :src="preview.url" :alt="preview.name" width="88" height="88" decoding="async" />
        <button
          type="button"
          class="listing-staged-remove"
          :aria-label="`Bỏ ảnh ${preview.name}`"
          :disabled="busy"
          @click="unstage(index)"
        >
          <AppIcon name="close" :size="14" />
        </button>
      </li>
    </ul>
    <button
      class="btn btn-primary mb-3"
      :disabled="blocked || !files.length || !product.id"
      data-upload
      @click="upload"
    >
      {{ files.length ? `Tải lên ${files.length} ảnh` : 'Tải ảnh lên' }}
    </button>
    <div class="row g-3">
      <article
        v-for="(image, index) in product.images"
        :key="image.id"
        :data-image="image.id"
        class="col-sm-6 col-md-4"
      >
        <div class="card h-100 p-2">
          <ProductPhoto :src="image.url" :alt="`Ảnh ${index + 1}`" style="height: 180px" /><span
            v-if="image.is_primary"
            class="badge text-bg-success my-2"
            >Ảnh chính</span
          >
          <div class="d-flex gap-2 flex-wrap mt-2 seller-image-controls">
            <button
              v-if="!image.is_primary"
              class="btn btn-sm btn-outline-success"
              :disabled="blocked"
              @click="primary(image.id)"
            >
              Đặt ảnh chính</button
            ><button
              class="btn btn-sm btn-outline-danger"
              :disabled="blocked"
              @click="remove(image.id)"
            >
              Xóa ảnh</button
            ><button
              class="btn btn-sm btn-outline-secondary"
              :disabled="blocked || index === 0"
              :aria-label="`Đưa ảnh ${index + 1} lên trước`"
              @click="move(index, -1)"
            >
              Lên</button
            ><button
              class="btn btn-sm btn-outline-secondary"
              :disabled="blocked || index === product.images.length - 1"
              :aria-label="`Đưa ảnh ${index + 1} xuống sau`"
              @click="move(index, 1)"
            >
              Xuống
            </button>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<style scoped>
.listing-upload-zone {
  position: relative;
  min-height: 160px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  text-align: center;
  gap: 0.35rem;
  padding: var(--space-6);
  border: 2px dashed var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface-muted);
}
.listing-upload-zone:focus-within {
  outline: var(--focus-ring);
  outline-offset: 3px;
  border-color: var(--primary);
}
.listing-upload-zone.is-disabled {
  opacity: 0.65;
}
.listing-upload-zone.is-dragging {
  border-color: var(--primary);
  background: var(--primary-soft);
}
.listing-staged {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(88px, 1fr));
  gap: var(--space-2);
  margin: var(--space-3) 0;
  padding: 0;
  list-style: none;
}
.listing-staged li {
  position: relative;
  aspect-ratio: 1;
  overflow: hidden;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--surface-2);
}
.listing-staged img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.listing-staged-remove {
  position: absolute;
  top: 4px;
  right: 4px;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--scrim);
  color: #fff;
  font-size: 0.8rem;
}
.listing-staged-remove:focus-visible {
  outline: var(--focus-ring);
}
.listing-upload-zone p {
  font-size: var(--font-size-small);
  margin: 0;
}
.listing-upload-icon {
  font-size: 1.8rem;
  color: var(--primary);
}
.listing-upload-input {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  opacity: 0;
  cursor: pointer;
}
.listing-upload-input:disabled {
  cursor: not-allowed;
}
</style>

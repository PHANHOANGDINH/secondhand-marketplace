<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import * as productsApi from '../api/product.api'
import { useFeedback } from '../composables/useFeedback'
import DomainFeedback from './DomainFeedback.vue'
import ProductPhoto from './ProductPhoto.vue'
const props = defineProps({ product: { type: Object, required: true }, disabled: Boolean })
const emit = defineEmits(['changed'])
const files = ref([]),
  previews = ref([]),
  input = ref(null)
const { busy, error, errors, success, clear, run } = useFeedback()
const blocked = computed(() => busy.value || props.disabled || !props.product.can_edit)
function resetFiles() {
  for (const preview of previews.value) URL.revokeObjectURL(preview.url)
  files.value = []
  previews.value = []
  if (input.value) input.value.value = ''
}
function choose(event) {
  const selected = Array.from(event.target.files || [])
  clear()
  resetFiles()
  if (selected.length + props.product.images.length > 8) {
    error.value = 'Mỗi sản phẩm tối đa 8 ảnh.'
    return
  }
  if (
    selected.some(
      (file) =>
        !['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ||
        !/\.(jpe?g|png|webp)$/i.test(file.name) ||
        file.size > 5 * 1024 * 1024,
    )
  ) {
    error.value = 'Chọn ảnh JPEG/PNG/WebP, tối đa 5 MiB mỗi ảnh.'
    return
  }
  files.value = selected
  previews.value = selected.map((file) => ({ name: file.name, url: URL.createObjectURL(file) }))
}
const upload = () =>
  run(async () => {
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
onBeforeUnmount(resetFiles)
</script>
<template>
  <section class="mt-4">
    <h2 class="h4">Ảnh sản phẩm ({{ product.images.length }}/8)</h2>
    <p class="text-secondary">
      JPEG/PNG/WebP · tối đa 5 MiB và 20 megapixel/ảnh. Thay ảnh của hàng ACTIVE/HIDDEN sẽ gửi duyệt
      lại.
    </p>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div v-for="(messages, key) in errors" :key="key" class="text-danger">
      {{ messages.join(' ') }}
    </div>
    <label for="product_images" class="form-label">Chọn ảnh</label
    ><input
      id="product_images"
      ref="input"
      name="images"
      type="file"
      accept="image/jpeg,image/png,image/webp"
      multiple
      class="form-control"
      :disabled="blocked || product.images.length >= 8"
      @change="choose"
    />
    <div class="d-flex gap-2 flex-wrap my-3">
      <img
        v-for="preview in previews"
        :key="preview.url"
        :src="preview.url"
        :alt="preview.name"
        style="width: 90px; height: 80px; object-fit: contain"
      />
    </div>
    <button class="btn btn-primary mb-3" :disabled="blocked || !files.length" @click="upload">
      Tải ảnh lên
    </button>
    <div class="row g-3">
      <article
        v-for="(image, index) in product.images"
        :key="image.id"
        :data-image="image.id"
        class="col-sm-6 col-md-4"
      >
        <div class="card h-100 p-2">
          <ProductPhoto :src="image.url" :alt="`Ảnh ${index + 1}`" style="height: 140px" /><span
            v-if="image.is_primary"
            class="badge text-bg-success my-2"
            >Ảnh chính</span
          >
          <div class="d-flex gap-2 flex-wrap mt-2">
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
              @click="move(index, -1)"
            >
              Lên</button
            ><button
              class="btn btn-sm btn-outline-secondary"
              :disabled="blocked || index === product.images.length - 1"
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

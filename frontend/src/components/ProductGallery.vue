<script setup>
import { computed, ref, watch } from 'vue'
import ProductPhoto from './ProductPhoto.vue'
const props = defineProps({
  images: { type: Array, default: () => [] },
  name: { type: String, default: '' },
})
const selected = ref(null)
watch(
  () => props.images,
  (rows) => {
    selected.value = rows.find((row) => row.is_primary)?.id || rows[0]?.id || null
  },
  { immediate: true },
)
const current = computed(() => props.images.find((row) => row.id === selected.value))
</script>
<template>
  <div class="marketplace-gallery">
    <ProductPhoto
      :src="current?.url"
      :alt="name"
      class="w-100"
      style="aspect-ratio: 1; max-height: 540px"
    />
    <div class="d-flex gap-2 flex-wrap mt-3">
      <button
        v-for="(image, index) in images"
        :key="image.id"
        type="button"
        :aria-label="`Xem ảnh ${index + 1}`"
        :aria-pressed="selected === image.id"
        class="btn p-1 border"
        :class="{ 'border-primary': selected === image.id }"
        @click="selected = image.id"
      >
        <ProductPhoto
          :src="image.url"
          :alt="`Ảnh ${index + 1}`"
          style="height: 65px; width: 75px"
        />
      </button>
    </div>
  </div>
</template>

<style scoped>
.marketplace-gallery > :deep(img) {
  padding: 1rem;
  border: 1px solid var(--bs-border-color);
  border-radius: 1rem !important;
  background: var(--bs-tertiary-bg) !important;
}
button {
  border-radius: 0.6rem;
  overflow: hidden;
}
button[aria-pressed='true'] {
  box-shadow: 0 0 0 2px rgba(var(--bs-primary-rgb), 0.15);
}
</style>

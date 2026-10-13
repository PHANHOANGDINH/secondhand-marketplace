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
    <div class="gallery-stage">
      <ProductPhoto
        :src="current?.url"
        :alt="name"
        class="w-100"
        style="aspect-ratio: 1; max-height: 540px"
      />
      <span v-if="images.length > 1" class="gallery-count"
        >{{ images.findIndex((row) => row.id === selected) + 1 }} / {{ images.length }}</span
      >
    </div>
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
.gallery-stage {
  position: relative;
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
  overflow: hidden;
}
/* The whole photo stays visible here; only listing cards crop to a square. */
.gallery-stage :deep(img) {
  display: block;
  padding: var(--space-4);
  object-fit: contain !important;
  background: var(--surface) !important;
  border-radius: 0 !important;
}
.gallery-count {
  position: absolute;
  right: var(--space-3);
  bottom: var(--space-3);
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius-pill);
  background: rgb(21 22 26 / 78%);
  color: #fff;
  font-family: var(--font-money);
  font-size: 0.75rem;
  font-weight: 700;
}
button {
  min-height: 0;
  border-color: var(--border) !important;
  border-radius: var(--radius-sm);
  background: var(--surface);
  overflow: hidden;
  transition: border-color var(--transition-fast);
}
button[aria-pressed='true'] {
  border-color: var(--primary) !important;
  box-shadow: 0 0 0 2px rgba(var(--primary-rgb), 0.18) !important;
}
button :deep(img) {
  display: block;
  object-fit: contain !important;
}
</style>

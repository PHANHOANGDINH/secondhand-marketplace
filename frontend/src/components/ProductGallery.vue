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
  <div>
    <ProductPhoto :src="current?.url" :alt="name" class="w-100" style="max-height: 440px" />
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

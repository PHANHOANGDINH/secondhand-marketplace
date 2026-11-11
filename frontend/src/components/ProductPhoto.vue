<script setup>
import { ref, watch } from 'vue'
const props = defineProps({
  src: { type: String, default: null },
  alt: { type: String, default: 'Ảnh sản phẩm' },
  // 'contain' (default) shows the whole photo with rounded corners. 'cover' fills a frame the parent
  // sizes and clips (product cards): no rounding of its own, no letterbox colour.
  fit: { type: String, default: 'contain', validator: (value) => ['contain', 'cover'].includes(value) },
  // The first photo on a page loads eagerly; everything else stays lazy.
  eager: Boolean,
})
const failed = ref(false)
watch(
  () => props.src,
  () => {
    failed.value = false
  },
)
</script>
<template>
  <img
    :src="failed || !src ? '/product-placeholder.svg' : src"
    :alt="alt"
    :loading="eager ? 'eager' : 'lazy'"
    :class="fit === 'cover' ? 'product-photo-cover' : 'img-fluid rounded'"
    :style="fit === 'cover' ? undefined : 'object-fit: contain; background: var(--surface-2)'"
    @error="failed = true"
  />
</template>
<style scoped>
.product-photo-cover {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: center;
}
</style>

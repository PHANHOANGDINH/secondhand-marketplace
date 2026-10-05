<script setup>
import { ref, watch } from 'vue'
const props = defineProps({
  src: { type: String, default: null },
  alt: { type: String, default: 'Ảnh sản phẩm' },
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
    loading="lazy"
    class="img-fluid rounded"
    style="object-fit: contain; background: #f5f5f5"
    @error="failed = true"
  />
</template>

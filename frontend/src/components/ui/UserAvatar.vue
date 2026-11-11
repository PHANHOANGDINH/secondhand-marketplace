<script setup>
import { computed, ref, watch } from 'vue'
// A person's picture, or their initials when there is none or it fails to load. Decoration only: the
// name always stands next to it, so the picture itself has no text alternative.
const props = defineProps({
  name: { type: String, default: '' },
  url: { type: String, default: '' },
})
const broken = ref(false)
watch(
  () => props.url,
  () => {
    broken.value = false
  },
)
const initials = computed(() =>
  (props.name || 'Tài khoản')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toLocaleUpperCase('vi-VN'),
)
</script>
<template>
  <span class="user-avatar" aria-hidden="true">
    <img v-if="url && !broken" :src="url" alt="" decoding="async" @error="broken = true" />
    <template v-else>{{ initials }}</template>
  </span>
</template>
<style scoped>
.user-avatar {
  overflow: hidden;
}
.user-avatar img {
  display: block;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>

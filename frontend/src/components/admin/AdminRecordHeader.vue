<script setup>
import { nextTick, ref, watch } from 'vue'
import StatusBadge from '../ui/StatusBadge.vue'
const props = defineProps({ title: String, status: String, subtitle: String })
const header = ref(null)
watch(
  () => props.title,
  async () => {
    await nextTick()
    if (window.matchMedia?.('(max-width: 1199px)').matches) header.value?.focus()
  },
  { immediate: true },
)
</script>
<template>
  <header ref="header" tabindex="-1" class="admin-record-header">
    <p class="admin-caption">Chi tiết bản ghi</p>
    <h2>{{ title }}</h2>
    <p v-if="subtitle" class="text-secondary">{{ subtitle }}</p>
    <StatusBadge v-if="status" :status="status" />
  </header>
</template>

<script setup>
import SkeletonState from './SkeletonState.vue'
defineProps({
  cards: { type: Number, default: 4 },
  label: { type: String, default: 'Đang tải sản phẩm…' },
  layout: { type: String, default: 'cards' },
})
</script>
<template><SkeletonState :layout="layout" :count="cards" :label="label" /></template>

<script setup>
import AppIcon from './AppIcon.vue'
defineProps({ title: { type: String, required: true }, description: String })
</script>
<template>
  <div class="buyer-empty" role="status">
    <span class="buyer-empty-icon" aria-hidden="true"><AppIcon name="box" :size="26" /></span>
    <h2 class="h5">{{ title }}</h2>
    <p v-if="description">{{ description }}</p>
    <div v-if="$slots.default" class="mt-3"><slot /></div>
  </div>
</template>

<script setup>
// A short page header: the title and its actions share one row, so the work starts above the fold.
defineProps({
  title: { type: String, required: true },
  description: String,
  eyebrow: { type: String, default: '' },
})
</script>
<template>
  <header class="buyer-page-header">
    <div class="buyer-page-heading">
      <p v-if="eyebrow" class="buyer-eyebrow">{{ eyebrow }}</p>
      <h1>{{ title }}</h1>
      <p v-if="description" class="buyer-page-description">{{ description }}</p>
    </div>
    <div v-if="$slots.default" class="buyer-page-actions"><slot /></div>
  </header>
</template>

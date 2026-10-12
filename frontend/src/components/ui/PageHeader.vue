<script setup>
defineProps({
  title: { type: String, required: true },
  description: String,
  eyebrow: { type: String, default: 'Secondhand · Sàn mua bán' },
})
</script>
<template>
  <header class="buyer-page-header">
    <div>
      <p class="buyer-eyebrow">{{ eyebrow }}</p>
      <h1>{{ title }}</h1>
      <p v-if="description" class="buyer-page-description">{{ description }}</p>
    </div>
    <div v-if="$slots.default" class="buyer-page-actions"><slot /></div>
  </header>
</template>

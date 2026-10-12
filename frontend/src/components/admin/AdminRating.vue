<script setup>
defineProps({ rating: { type: Number, required: true } })
</script>
<template>
  <span class="admin-rating" :aria-label="`${rating} trên 5 sao`"
    ><span aria-hidden="true" class="admin-rating-stars"
      ><span v-for="n in 5" :key="n" :class="{ 'is-filled': n <= rating }">★</span></span
    ><span aria-hidden="true" class="admin-rating-number">{{ rating }} / 5</span></span
  >
</template>

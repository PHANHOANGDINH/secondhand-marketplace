<script setup>
import ProductPhoto from './ProductPhoto.vue'
import { formatPrice, conditionLabel } from '../composables/productPresentation'
defineProps({ product: { type: Object, required: true } })
</script>
<template>
  <article class="card h-100" :data-product="product.id">
    <RouterLink :to="`/products/${product.slug}`"
      ><ProductPhoto
        :src="product.primary_image?.url"
        :alt="product.name"
        class="w-100"
        style="height: 190px"
    /></RouterLink>
    <div class="card-body">
      <h2 class="h5">
        <RouterLink :to="`/products/${product.slug}`">{{ product.name }}</RouterLink>
      </h2>
      <p class="fw-bold">{{ formatPrice(product.price) }}</p>
      <p>{{ conditionLabel(product.condition) }}</p>
      <RouterLink v-if="product.shop" :to="`/shops/${product.shop.slug}`">{{
        product.shop.name
      }}</RouterLink>
    </div>
  </article>
</template>

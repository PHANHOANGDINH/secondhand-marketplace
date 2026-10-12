<script setup>
import { computed } from 'vue'
const props = defineProps({ product: { type: Object, required: true } })
const state = computed(() => props.product.inventory_state)
const label = computed(() =>
  state.value === 'OUT_OF_STOCK'
    ? 'Hết hàng'
    : state.value === 'LOW_STOCK'
      ? `Sắp hết: ${props.product.stock_quantity}`
      : `Còn ${props.product.stock_quantity}`,
)
</script>
<template>
  <span
    class="badge"
    :class="
      state === 'OUT_OF_STOCK'
        ? 'text-bg-secondary'
        : state === 'LOW_STOCK'
          ? 'text-bg-warning'
          : 'text-bg-success'
    "
    >{{ label }}</span
  >
</template>

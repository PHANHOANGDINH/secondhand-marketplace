<script setup>
import { computed } from 'vue'
import { auctionLockMessage, auctionLockRoute } from '../../composables/productPresentation'
const props = defineProps({ product: { type: Object, required: true }, compact: Boolean })
const to = computed(() => auctionLockRoute(props.product))
</script>
<template>
  <p
    v-if="product.auction_locked"
    data-auction-lock
    :class="compact ? 'small text-muted mb-0' : 'alert alert-warning'"
    role="note"
  >
    {{ auctionLockMessage }}
    <RouterLink v-if="to" :to="to">Xem phiên đấu giá</RouterLink>
  </p>
</template>

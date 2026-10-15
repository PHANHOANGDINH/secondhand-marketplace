<script setup>
defineProps({
  auctionId: { type: [String, Number], default: null },
  seller: Boolean,
  admin: Boolean,
})
</script>
<template>
  <span v-if="auctionId" class="badge text-bg-secondary me-2" data-auction-source>
    <span v-if="admin">Thắng đấu giá #{{ auctionId }}</span>
    <RouterLink
      v-else
      :to="seller ? `/seller/auctions/${auctionId}` : `/auctions/${auctionId}/checkout`"
      >Thắng đấu giá</RouterLink
    >
  </span>
</template>

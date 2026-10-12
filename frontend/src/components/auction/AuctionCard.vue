<script setup>
import { onMounted, ref } from 'vue'
import { getProduct } from '../../api/product.api'
import { formatPrice } from '../../composables/productPresentation'
import { auctionLabels } from '../../composables/auctionPresentation'
import ProductPhoto from '../ProductPhoto.vue'
import StatusBadge from '../ui/StatusBadge.vue'
import AuctionCountdown from './AuctionCountdown.vue'
const props = defineProps({ auction: { type: Object, required: true } })
const product = ref(null)
onMounted(async () => {
  if (props.auction.product?.slug) {
    try {
      product.value = await getProduct(props.auction.product.slug)
    } catch {
      /* unavailable context is not invented */
    }
  }
})
</script>
<template>
  <article class="auction-card card">
    <RouterLink :to="`/auctions/${auction.id}`" class="auction-card-image"
      ><ProductPhoto
        :src="product?.primary_image?.url"
        :alt="auction.product?.name || 'Sản phẩm đấu giá'"
    /></RouterLink>
    <div class="card-body">
      <StatusBadge :status="auction.status" :label="auctionLabels[auction.status]" />
      <h2>
        <RouterLink :to="`/auctions/${auction.id}`">{{
          auction.product?.name || 'Phiên đấu giá'
        }}</RouterLink>
      </h2>
      <p v-if="product?.shop" class="text-muted small">{{ product.shop.name }}</p>
      <small class="text-muted">{{ auction.bid_count ? 'Giá hiện tại' : 'Giá khởi điểm' }}</small>
      <p class="auction-card-price">{{ formatPrice(auction.current_price) }}</p>
      <AuctionCountdown :auction="auction" />
      <p class="small text-muted mb-0">{{ auction.bid_count }} lượt ra giá</p>
    </div>
  </article>
</template>

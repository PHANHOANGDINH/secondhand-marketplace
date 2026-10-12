<script setup>
import AuctionOrderSource from './auction/AuctionOrderSource.vue'
import { formatPrice } from '../composables/productPresentation'
import ProductPhoto from './ProductPhoto.vue'
import ReviewEditor from './ReviewEditor.vue'
defineProps({ items: { type: Array, required: true }, allowReviews: Boolean, seller: Boolean })
const emit = defineEmits(['reviewed'])
</script>
<template>
  <article
    v-for="item in items"
    :key="item.id"
    class="d-flex gap-3 border-bottom py-3"
    data-order-item
  >
    <ProductPhoto
      :src="item.product_image_url"
      :alt="item.product_name"
      style="width: 80px; height: 80px"
    />
    <div>
      <AuctionOrderSource :auction-id="item.auction_id" :seller="seller" />
      <strong>{{ item.product_name }}</strong>
      <p class="mb-0">{{ formatPrice(item.product_price) }} × {{ item.quantity }}</p>
      <p class="mb-0">{{ formatPrice(item.subtotal) }}</p>
      <ReviewEditor
        v-if="allowReviews && (item.can_review || item.review)"
        :item="item"
        @saved="emit('reviewed', item.id, $event)"
      />
    </div>
  </article>
</template>

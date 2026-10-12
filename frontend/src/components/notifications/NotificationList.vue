<script setup>
import { formatPrice } from '../../composables/productPresentation'
defineProps({ rows: { type: Array, required: true }, busy: Boolean })
defineEmits(['open', 'read'])
const dateLabel = (value) =>
  new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
const actionLabel = (type) =>
  type === 'REPORT_RESULT'
    ? 'Xem báo cáo'
    : type === 'OUTBID'
      ? 'Ra giá lại'
      : ['AUCTION_WON', 'WINNER_CHECKOUT_EXPIRING'].includes(type)
        ? 'Hoàn tất mua hàng'
        : type === 'AUCTION_ORDER_CREATED'
          ? 'Xem đơn hàng'
          : type === 'AUCTION_CHECKOUT_UNAVAILABLE'
            ? 'Xem các phiên khác'
            : 'Xem phiên'
</script>
<template>
  <ul class="notification-list">
    <li
      v-for="row in rows"
      :key="row.id"
      :class="{ 'is-unread': !row.read_at }"
      :data-notification-id="row.id"
    >
      <button type="button" class="notification-open" :disabled="busy" @click="$emit('open', row)">
        <span v-if="!row.read_at" class="notification-dot"
          ><span class="visually-hidden">Chưa đọc</span></span
        >
        <span
          ><strong>{{ row.message }}</strong
          ><small v-if="row.winning_amount">Giá thắng: {{ formatPrice(row.winning_amount) }}</small
          ><time :datetime="row.created_at">{{ dateLabel(row.created_at) }}</time
          ><span class="notification-action">{{ actionLabel(row.type) }} →</span></span
        >
      </button>
      <button
        v-if="!row.read_at"
        type="button"
        class="btn btn-sm btn-outline-secondary notification-read"
        :disabled="busy"
        :aria-label="`Đánh dấu đã đọc: ${row.product_name || row.message}`"
        @click="$emit('read', row)"
      >
        Đã đọc
      </button>
    </li>
  </ul>
</template>

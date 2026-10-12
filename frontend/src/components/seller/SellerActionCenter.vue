<script setup>
import { onMounted, ref } from 'vue'
import { getActionCenter } from '../../api/inventory.api'
import { useFeedback } from '../../composables/useFeedback'
import DomainFeedback from '../DomainFeedback.vue'
const data = ref(null)
const { busy, error, run } = useFeedback()
const cards = [
  ['low_stock_products', 'Sắp hết hàng', '/seller/inventory?filter=low_stock'],
  ['out_of_stock_products', 'Hết hàng', '/seller/inventory?filter=out_of_stock'],
  ['orders_requiring_action', 'Đơn cần xử lý', '/seller/orders?action_required=1'],
  ['pending_products', 'Sản phẩm chờ duyệt', '/seller/inventory?filter=pending'],
  ['rejected_products', 'Sản phẩm bị từ chối', '/seller/inventory?filter=rejected'],
  ['live_auctions', 'Đấu giá đang diễn ra', '/seller/auctions?live_now=1'],
  ['auctions_ending_soon', 'Đấu giá sắp kết thúc', '/seller/auctions?ending_soon=1'],
]
const load = () =>
  run(async () => {
    data.value = await getActionCenter()
  })
onMounted(load)
</script>
<template>
  <section class="mb-4" aria-labelledby="seller-actions-title">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
      <div>
        <h2 id="seller-actions-title" class="h5 mb-1">Cần xử lý</h2>
        <p class="text-muted small mb-0">
          Tình trạng hiện tại, không phụ thuộc khoảng ngày của báo cáo.
        </p>
      </div>
      <button class="btn btn-outline-secondary btn-sm" :disabled="busy" @click="load">
        Làm mới
      </button>
    </div>
    <DomainFeedback :busy="busy" :error="error" />
    <div v-if="data && !error" class="row g-3">
      <div v-for="[key, label, to] in cards" :key="key" class="col-6 col-lg-4 col-xl-3">
        <RouterLink :to="to" class="card h-100 text-decoration-none"
          ><div class="card-body">
            <span class="small text-muted">{{ label }}</span
            ><strong class="d-block fs-3 mt-1">{{ data[key] }}</strong>
          </div></RouterLink
        >
      </div>
    </div>
    <p v-if="data && !error" class="small text-muted mt-3 mb-0">
      Sắp hết: từ 1 đến {{ data.low_stock_threshold }} sản phẩm. Sắp kết thúc: trong
      {{ data.ending_soon_minutes }} phút. Đơn trực tuyến chỉ cần xử lý khi đã xác nhận thanh toán.
    </p>
  </section>
</template>

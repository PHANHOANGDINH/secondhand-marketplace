<script setup>
import { onMounted, ref } from 'vue'
import { listAuctions } from '../../api/auction.api'
import AuctionCard from '../auction/AuctionCard.vue'
import '../../styles/auction.css'
// Live auctions in the dark auction area, right under the newest listings. A short list only:
// the section stays out of the page while there is nothing to show (or the request fails).
const rows = ref([])
onMounted(async () => {
  try {
    const result = await listAuctions({ status: 'LIVE', per_page: 4 })
    rows.value = Array.isArray(result?.data) ? result.data : []
  } catch {
    rows.value = []
  }
})
</script>
<template>
  <section
    v-if="rows.length"
    class="home-auctions theme-auction"
    aria-labelledby="home-auctions-title"
  >
    <header class="home-auctions-head">
      <div>
        <h2 id="home-auctions-title">Đấu giá đang diễn ra</h2>
        <p>Ra giá trực tiếp, thắng phiên là thanh toán ngay.</p>
      </div>
      <RouterLink to="/auctions" class="home-auctions-link">Xem tất cả</RouterLink>
    </header>
    <div class="auction-grid auction-rail">
      <AuctionCard v-for="auction in rows" :key="auction.id" :auction="auction" />
    </div>
  </section>
</template>
<style scoped>
.home-auctions {
  margin-bottom: var(--space-8);
  padding: var(--space-4);
  border-radius: var(--radius-xl);
}
.home-auctions-head {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}
.home-auctions h2 {
  margin: 0;
  font-family: var(--font-display);
  font-size: 1.3rem;
  font-weight: 600;
  line-height: 1.25;
}
.home-auctions-head p {
  margin: var(--space-1) 0 0;
  color: var(--muted);
  font-size: var(--font-size-small);
}
.home-auctions-link {
  display: inline-flex;
  align-items: center;
  flex: none;
  min-height: var(--control-height);
  color: var(--neon);
  font-size: var(--font-size-body);
  font-weight: 600;
}
@media (min-width: 768px) {
  .home-auctions {
    padding: var(--space-6);
  }
  .home-auctions h2 {
    font-size: 1.5rem;
  }
}
</style>

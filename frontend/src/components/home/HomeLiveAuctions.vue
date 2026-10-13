<script setup>
import { onMounted, ref } from 'vue'
import { listAuctions } from '../../api/auction.api'
import AuctionCard from '../auction/AuctionCard.vue'
import AppIcon from '../ui/AppIcon.vue'
import '../../styles/auction.css'
// A short strip of running auctions. Optional: hidden when there is none or the request fails.
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
  <section v-if="rows.length" class="home-live theme-auction" aria-labelledby="home-live-title">
    <header class="home-live-header">
      <h2 id="home-live-title"><span class="auction-live-dot" aria-hidden="true"></span>Đang đấu giá</h2>
      <RouterLink to="/auctions" class="home-live-all"
        >Xem sàn đấu giá <AppIcon name="arrowRight" :size="16"
      /></RouterLink>
    </header>
    <div class="home-live-grid">
      <AuctionCard v-for="auction in rows" :key="auction.id" :auction="auction" />
    </div>
  </section>
</template>
<style scoped>
.home-live {
  margin-bottom: var(--space-10);
  padding: clamp(1rem, 3vw, 2rem);
  border-radius: var(--radius-xl);
}
.home-live-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  margin-bottom: var(--space-5);
}
.home-live h2 {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  margin: 0;
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text);
}
.home-live-all {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 44px;
  color: var(--neon);
  font-size: 0.85rem;
  font-weight: 700;
  text-decoration: none;
}
.home-live-all:hover {
  color: #fff;
}
.home-live-all:focus-visible {
  outline: 3px solid var(--neon);
  outline-offset: 3px;
}
.home-live-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-4);
}
@media (max-width: 1199px) {
  .home-live-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (max-width: 480px) {
  .home-live-grid {
    grid-template-columns: 1fr;
  }
}
</style>

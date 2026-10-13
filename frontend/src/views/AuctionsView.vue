<script setup>
import { onMounted, onUnmounted, ref } from 'vue'
import { listAuctions } from '../api/auction.api'
import { domainError } from '../api/domain.api'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import CollectionPager from '../components/CollectionPager.vue'
import AuctionCard from '../components/auction/AuctionCard.vue'
import { auctionLabels } from '../composables/auctionPresentation'
import '../styles/auction.css'
const rows = ref([]),
  meta = ref(null),
  status = ref(''),
  busy = ref(false),
  error = ref('')
let request = 0
async function load(page = 1) {
  const sequence = ++request
  busy.value = true
  error.value = ''
  try {
    const result = await listAuctions({ page, per_page: 12, status: status.value || undefined })
    if (sequence === request) {
      rows.value = result.data
      meta.value = result.meta
    }
  } catch (e) {
    if (sequence === request) error.value = domainError(e)
  } finally {
    if (sequence === request) busy.value = false
  }
}
onMounted(() => load())
onUnmounted(() => request++)
</script>
<template>
  <section class="auction-discovery theme-auction">
    <PageHeader
      title="Sàn đấu giá"
      eyebrow="Mỗi món đồ, một cơ hội mới"
      description="Khám phá các phiên đấu giá thật. Theo dõi giá và ra giá trực tiếp."
    />
    <form class="auction-filter" @submit.prevent="load()">
      <label for="auction-status">Trạng thái phiên</label
      ><select id="auction-status" v-model="status" class="form-select">
        <option value="">Tất cả</option>
        <option v-for="state in ['LIVE', 'SCHEDULED', 'ENDED']" :key="state" :value="state">
          {{ auctionLabels[state] }}
        </option></select
      ><button class="btn btn-outline-secondary" :disabled="busy">Lọc / tải lại</button>
    </form>
    <DomainFeedback :busy="busy" :error="error" /><EmptyState
      v-if="!busy && !error && !rows.length"
      title="Chưa có phiên đấu giá"
      description="Các phiên mới sẽ xuất hiện tại đây khi người bán mở đấu giá."
    />
    <div v-else-if="!error" class="auction-grid">
      <AuctionCard v-for="auction in rows" :key="auction.id" :auction="auction" />
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
  </section>
</template>

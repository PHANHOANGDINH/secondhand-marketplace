<script setup>
import { onMounted, ref } from 'vue'
import { listShops } from '../api/shop.api'
import { useFeedback } from '../composables/useFeedback'
import DomainFeedback from '../components/DomainFeedback.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import CollectionPager from '../components/CollectionPager.vue'
const rows = ref([]),
  meta = ref(null),
  search = ref('')
const { busy, error, run } = useFeedback()
const load = (page = 1) =>
  run(async () => {
    const result = await listShops({ page, search: search.value || undefined })
    rows.value = result.data
    meta.value = result.meta
  })
onMounted(() => load())
</script>
<template>
  <section class="buyer-flow">
    <PageHeader
      title="Khám phá gian hàng"
      description="Tìm gian hàng và những món đồ đang chờ một hành trình mới."
    />
    <form class="d-flex gap-2 buyer-filter" @submit.prevent="load()">
      <label for="search" class="visually-hidden">Tìm theo tên</label
      ><input
        id="search"
        v-model="search"
        maxlength="100"
        placeholder="Tìm theo tên gian hàng"
        class="form-control"
      /><button class="btn btn-outline-secondary" :disabled="busy">Tìm</button>
    </form>
    <DomainFeedback :busy="busy" :error="error" />
    <EmptyState
      v-if="!rows.length && !busy && !error"
      title="Chưa tìm thấy gian hàng."
      description="Thử tìm với tên khác để khám phá thêm gian hàng."
    />
    <div class="row g-3">
      <article v-for="shop in rows" :key="shop.id" class="col-md-6 col-lg-4">
        <div class="card h-100 public-shop-card">
          <div class="card-body">
            <h2 class="h5">
              <RouterLink :to="`/shops/${shop.slug}`">{{ shop.name }}</RouterLink>
            </h2>
            <p class="text-break">{{ shop.description }}</p>
          </div>
        </div>
      </article>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
  </section>
</template>

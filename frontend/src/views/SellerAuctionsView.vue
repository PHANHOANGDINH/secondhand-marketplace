<script setup>
import IconButton from '../components/ui/IconButton.vue'
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as api from '../api/auction.api'
import { domainError } from '../api/domain.api'
import { getSellerShop } from '../api/shop.api'
import { listSellerProducts, listProducts } from '../api/product.api'
import { useFeedback } from '../composables/useFeedback'
import { useSellerIdentity } from '../components/seller/useSellerIdentity'
import { formatPrice } from '../composables/productPresentation'
import { auctionLabels, parseBidInput } from '../composables/auctionPresentation'
import { createAuctionClock } from '../composables/auctionTimeSync'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import CollectionPager from '../components/CollectionPager.vue'
import '../styles/auction.css'
import FilterSheet from '../components/ui/FilterSheet.vue'
const route = useRoute(),
  router = useRouter()
const auctionFilter = ref(
  route.query.ending_soon === '1'
    ? 'ending_soon'
    : route.query.live_now === '1'
      ? 'live_now'
      : 'all',
)
function filterAuctions() {
  router.push({
    path: '/seller/auctions',
    query: auctionFilter.value === 'all' ? {} : { [auctionFilter.value]: '1' },
  })
}
watch(
  () => route.fullPath,
  () => {
    auctionFilter.value =
      route.query.ending_soon === '1'
        ? 'ending_soon'
        : route.query.live_now === '1'
          ? 'live_now'
          : 'all'
    load()
  },
)
const rows = ref([]),
  meta = ref(null),
  shop = ref(null),
  products = ref([]),
  open = ref(false),
  confirmation = ref(null),
  // "Bán ngay": the auction row, the highest bid read when the panel opened and an optional note.
  sale = ref(null)
const form = ref({ product_id: '', start_price: '', bid_increment: '', starts_at: '', ends_at: '' })
const publicIds = ref(new Set()),
  reservedIds = ref(new Set())
const { busy, error, errors, success, run } = useFeedback()
useSellerIdentity(() => shop.value)
const eligible = computed(() =>
  products.value.filter(
    (p) =>
      p.status === 'ACTIVE' &&
      p.stock_quantity > 0 &&
      !p.archived &&
      publicIds.value.has(String(p.id)) &&
      !reservedIds.value.has(String(p.id)),
  ),
)
// Display only: the server-synchronised clock keeps the button honest when the device clock drifts.
const clock = createAuctionClock()
const canCancel = (row) =>
  ['LIVE', 'SCHEDULED'].includes(row.status) &&
  row.bid_count === 0 &&
  Date.parse(row.ends_at) > clock.now()
const canSellNow = (row) =>
  row.status === 'LIVE' && row.bid_count > 0 && Date.parse(row.ends_at) > clock.now()
async function openSale(row) {
  confirmation.value = null
  sale.value = null
  await run(async () => {
    // Bids only go up, so the newest bid is the highest one.
    const top = (await api.listAuctionBids(row.id, { per_page: 1 })).data[0]
    if (top) sale.value = { row, bid: top, reason: '' }
    else await refresh()
  })
}
async function sellNow() {
  const current = sale.value
  if (!current) return
  const ok = await run(async () => {
    await api.acceptAuctionBid(current.row.id, {
      expected_status: 'LIVE',
      expected_bid_id: current.bid.id,
      ...(current.reason.trim() ? { reason: current.reason.trim() } : {}),
    })
    sale.value = null
    await refresh()
  }, 'Đã bán ngay. Phiên kết thúc, người trả giá cao nhất được mời thanh toán.')
  // A newer bid or an ended auction: close the panel and show the latest state so the seller can decide again.
  if (!ok) {
    const conflict = error.value === domainError({ response: { status: 409 } })
    sale.value = null
    await refresh().catch(() => {})
    if (conflict)
      error.value = 'Vừa có giá mới hoặc phiên đã kết thúc. Danh sách đã được tải lại, vui lòng xem lại trước khi bán.'
  }
}
async function refresh(page = 1) {
  const result = await api.listSellerAuctions({
    page,
    ...(auctionFilter.value === 'all' ? {} : { [auctionFilter.value]: 1 }),
  })
  clock.sync(result.server_time || result.data[0]?.server_time)
  rows.value = result.data
  meta.value = result.meta
}
const load = (page) => run(() => refresh(page))
async function prepare() {
  await run(async () => {
    shop.value = await getSellerShop()
    await refresh()
    if (shop.value?.status !== 'ACTIVE') return
    async function all(fetcher, params) {
      const data = []
      let page = 1,
        result
      do {
        result = await fetcher({ ...params, page, per_page: 100 })
        data.push(...result.data)
        page++
      } while (page <= result.meta.last_page)
      return data
    }
    const [owned, visible, scheduled, active, ended] = await Promise.all([
      all(listSellerProducts, { status: 'ACTIVE' }),
      all(listProducts, { shop: shop.value.id }),
      all(api.listSellerAuctions, { status: 'SCHEDULED' }),
      all(api.listSellerAuctions, { status: 'LIVE' }),
      all(api.listSellerAuctions, { status: 'ENDED' }),
    ])
    products.value = owned
    publicIds.value = new Set(visible.map((p) => String(p.id)))
    reservedIds.value = new Set(
      [
        ...scheduled,
        ...active,
        ...ended.filter((a) => a.settlement_state === 'AWAITING_CHECKOUT'),
      ].map((a) => String(a.product_id)),
    )
  })
}
async function create() {
  const data = {
    ...form.value,
    start_price: parseBidInput(form.value.start_price) || form.value.start_price,
    bid_increment: parseBidInput(form.value.bid_increment) || form.value.bid_increment,
  }
  for (const key of ['starts_at', 'ends_at']) {
    const date = new Date(data[key])
    if (Number.isFinite(date.getTime())) data[key] = date.toISOString()
  }
  const ok = await run(async () => {
    await api.createAuction(data)
    reservedIds.value.add(String(data.product_id))
    open.value = false
    form.value = { product_id: '', start_price: '', bid_increment: '', starts_at: '', ends_at: '' }
    await refresh()
  }, 'Đã tạo phiên đấu giá.')
  if (!ok) document.querySelector('.auction-create [aria-invalid="true"]')?.focus()
}
async function cancel() {
  const row = confirmation.value
  if (!row) return
  await run(async () => {
    await api.cancelAuction(row.id)
    reservedIds.value.delete(String(row.product_id))
    confirmation.value = null
    await refresh()
  }, 'Đã huỷ phiên đấu giá.')
}
onMounted(prepare)
const activeFilters = computed(() => [
  {
    label: 'Lọc',
    value: { live_now: 'Đang diễn ra', ending_soon: 'Sắp kết thúc' }[auctionFilter.value] || '',
  },
])
</script>
<template>
  <section>
    <PageHeader
      title="Đấu giá của tôi"
      eyebrow="Gian hàng của tôi"
      description="Mở phiên cho sản phẩm đang công khai và theo dõi các lượt ra giá."
      ><button
        v-if="shop?.status === 'ACTIVE'"
        class="btn btn-primary"
        :disabled="busy"
        @click="open = !open"
      >
        {{ open ? 'Đóng biểu mẫu' : '+ Tạo đấu giá' }}
      </button></PageHeader
    >
    <FilterSheet :active="activeFilters">
      <form class="d-flex flex-wrap gap-2 mb-3" @submit.prevent="filterAuctions">
        <select v-model="auctionFilter" aria-label="Lọc đấu giá" class="form-select">
          <option value="all">Tất cả đấu giá</option>
          <option value="live_now">Đang diễn ra</option>
          <option value="ending_soon">Sắp kết thúc</option></select
        ><IconButton type="submit" class="btn btn-outline-secondary" :disabled="busy" icon="check" label="Áp dụng" show-label />
      </form>
    </FilterSheet>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <p v-if="shop && shop.status !== 'ACTIVE'" class="alert alert-info">
      Gian hàng phải đang hoạt động để mở đấu giá.
    </p>
    <form
      v-if="open"
      aria-label="Tạo phiên đấu giá"
      class="card card-body auction-create mb-4"
      @submit.prevent="create"
    >
      <h2>Tạo phiên đấu giá</h2>
      <p class="small text-muted">
        Chỉ sản phẩm đang hoạt động còn hàng được chọn. Máy chủ kiểm tra lại điều kiện và phiên chưa
        hoàn tất.
      </p>
      <div class="auction-form-grid">
        <label
          v-for="[key, label] in [
            ['product_id', 'Sản phẩm'],
            ['start_price', 'Giá khởi điểm (₫)'],
            ['bid_increment', 'Bước giá (₫)'],
            ['starts_at', 'Bắt đầu lúc'],
            ['ends_at', 'Kết thúc lúc'],
          ]"
          :key="key"
          :for="'create-auction-' + key"
          >{{ label }} <span aria-hidden="true">*</span>
          <select
            v-if="key === 'product_id'"
            :id="'create-auction-' + key"
            v-model="form[key]"
            class="form-select"
            required
            :disabled="busy"
            :aria-invalid="!!errors[key]"
            :aria-describedby="'error-' + key"
          >
            <option value="">Chọn sản phẩm</option>
            <option v-for="product in eligible" :key="product.id" :value="product.id">
              {{ product.name }}
            </option>
          </select>
          <input
            v-else
            :id="'create-auction-' + key"
            v-model="form[key]"
            :type="key.endsWith('_at') ? 'datetime-local' : 'text'"
            :inputmode="key.endsWith('_at') ? undefined : 'decimal'"
            class="form-control"
            required
            :disabled="busy"
            :aria-invalid="!!errors[key]"
            :aria-describedby="'error-' + key"
          />
          <span :id="'error-' + key" class="text-danger small">{{ errors[key]?.join(' ') }}</span>
        </label>
      </div>
      <p v-if="!eligible.length" class="text-muted">
        Chưa có sản phẩm đủ điều kiện.
        <RouterLink to="/my-ads">Quản lý tin</RouterLink>
      </p>
      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary" :disabled="busy || !eligible.length">
          {{ busy ? 'Đang tạo…' : 'Tạo phiên đấu giá' }}</button
        ><button
          type="button"
          class="btn btn-outline-secondary"
          :disabled="busy"
          @click="open = false"
        >
          Huỷ nhập
        </button>
      </div>
    </form>
    <EmptyState
      v-if="!busy && !error && !rows.length"
      title="Chưa có phiên đấu giá"
      description="Tạo phiên từ sản phẩm đang công khai của gian hàng."
    />
    <div class="auction-seller-list">
      <article v-for="row in rows" :key="row.id" class="card card-body">
        <div>
          <h2>{{ row.product?.name || 'Phiên đấu giá' }}</h2>
          <StatusBadge :status="row.status" :label="auctionLabels[row.status]" />
          <p class="mt-2 mb-0">
            {{ formatPrice(row.current_price) }} · {{ row.bid_count }} lượt ra giá
          </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <RouterLink class="btn btn-outline-secondary" :to="`/seller/auctions/${row.id}`"
            >Xem phiên</RouterLink
          ><RouterLink
            v-if="row.status !== 'CANCELLED'"
            class="btn btn-outline-secondary"
            :to="`/auctions/${row.id}`"
            >Mở phòng đấu giá</RouterLink
          ><button
            v-if="canSellNow(row)"
            class="btn btn-primary"
            :disabled="busy"
            data-sell-now
            @click="openSale(row)"
          >
            Bán ngay
          </button
          ><button
            v-if="canCancel(row)"
            class="btn btn-outline-danger"
            :disabled="busy"
            @click="(sale = null), (confirmation = row)"
          >
            Huỷ phiên
          </button>
        </div>
      </article>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
    <div
      v-if="sale"
      class="card card-body mt-3 border-primary"
      role="region"
      aria-label="Xác nhận bán ngay"
      data-sell-now-panel
    >
      <h2>Bán ngay {{ sale.row.product?.name }}?</h2>
      <p>
        Bán cho người đang trả giá cao nhất với giá
        <strong style="color: var(--price)">{{ formatPrice(sale.bid.amount) }}</strong>. Phiên kết
        thúc ngay và người thắng được mời thanh toán như khi phiên hết giờ. Nếu vừa có giá mới cao
        hơn, máy chủ sẽ dừng lại để bạn xem lại.
      </p>
      <label for="sell-now-reason" class="form-label">Ghi chú (không bắt buộc)</label>
      <textarea
        id="sell-now-reason"
        v-model="sale.reason"
        class="form-control mb-3"
        rows="2"
        maxlength="500"
        :disabled="busy"
      ></textarea>
      <div class="d-flex gap-2">
        <button class="btn btn-primary" :disabled="busy" data-sell-now-confirm @click="sellNow">
          Xác nhận bán {{ formatPrice(sale.bid.amount) }}</button
        ><button class="btn btn-outline-secondary" :disabled="busy" @click="sale = null">
          Tiếp tục đấu giá
        </button>
      </div>
    </div>
    <div
      v-if="confirmation"
      class="card card-body mt-3 border-danger"
      role="region"
      aria-label="Xác nhận huỷ đấu giá"
    >
      <h2>Huỷ phiên {{ confirmation.product?.name }}?</h2>
      <p>Chỉ phiên chưa có lượt ra giá và chưa hết hạn được huỷ. Máy chủ sẽ xác nhận lại.</p>
      <div class="d-flex gap-2">
        <button class="btn btn-danger" :disabled="busy" @click="cancel">Xác nhận huỷ</button
        ><button class="btn btn-outline-secondary" :disabled="busy" @click="confirmation = null">
          Giữ phiên
        </button>
      </div>
    </div>
  </section>
</template>

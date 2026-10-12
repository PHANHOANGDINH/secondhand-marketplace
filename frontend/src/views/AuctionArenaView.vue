<script setup>
import { localizeValidationMessage } from '../utils/validationLabels'

import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useAuctionRealtime } from '../composables/useAuctionRealtime'
import { useAuctionCountdown } from '../composables/useAuctionCountdown'
import { listAuctionBids, placeAuctionBid, getSellerAuction } from '../api/auction.api'
import { getProduct, getSellerProduct } from '../api/product.api'
import { domainError } from '../api/domain.api'
import { formatPrice, conditionLabel } from '../composables/productPresentation'
import {
  auctionLabels,
  amountCents,
  parseBidInput,
  quickBid,
  bidIdentity,
  bidTime,
  checkoutDeadlineLabel,
} from '../composables/auctionPresentation'
import ProductGallery from '../components/ProductGallery.vue'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import AuctionCountdown from '../components/auction/AuctionCountdown.vue'
import '../styles/auction.css'
const props = defineProps({ seller: Boolean })
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore()
const id = computed(() => String(route.params.id))
const live = useAuctionRealtime(computed(() => (props.seller ? null : id.value)))
const sellerAuction = ref(null),
  sellerError = ref(''),
  sellerLoading = ref(false),
  product = ref(null),
  productError = ref(''),
  bids = ref([]),
  meta = ref(null),
  historyError = ref(''),
  historyBusy = ref(false)
const auction = computed(() => (props.seller ? sellerAuction.value : live.auction.value))
const { remaining } = useAuctionCountdown(auction, props.seller ? undefined : live.clock)
const input = ref(''),
  inputElement = ref(null),
  submitting = ref(false),
  feedback = ref(''),
  bidError = ref(''),
  highlighted = ref(false),
  entered = ref(null)
let generation = 0,
  historyRequest = 0,
  flashTimer,
  enterTimer,
  disposed = false
const own = computed(() => !!auth.user && String(auth.user.id) === String(auction.value?.seller_id))
const winner = computed(
  () =>
    !props.seller &&
    !own.value &&
    !!auth.user &&
    auction.value?.status === 'ENDED' &&
    String(auth.user.id) === String(auction.value.winner_user_id),
)
const liveStatus = computed(() => auction.value?.status === 'LIVE')
const canSubmit = computed(
  () =>
    liveStatus.value &&
    remaining.value > 0 &&
    !own.value &&
    !props.seller &&
    !!auction.value?.minimum_next_bid,
)
async function loadHistory(page = 1) {
  if (props.seller) return
  const g = generation,
    sequence = ++historyRequest,
    target = id.value
  historyBusy.value = true
  historyError.value = ''
  try {
    const result = await listAuctionBids(target, { page, per_page: 15 })
    if (g !== generation || sequence !== historyRequest) return
    if (page === 1) {
      const incoming = result.data,
        newest = incoming[0]?.id
      const newer = bids.value.filter((b) => !newest || BigInt(b.id) > BigInt(newest))
      bids.value = [...newer, ...incoming]
    } else {
      const known = new Set(bids.value.map((b) => b.id))
      bids.value.push(...result.data.filter((b) => !known.has(b.id)))
    }
    meta.value = result.meta
  } catch (e) {
    if (g === generation && sequence === historyRequest) historyError.value = domainError(e)
  } finally {
    if (g === generation && sequence === historyRequest) historyBusy.value = false
  }
}
watch(
  id,
  async () => {
    generation++
    historyRequest++
    product.value = null
    productError.value = ''
    bids.value = []
    meta.value = null
    input.value = ''
    feedback.value = bidError.value = ''
    submitting.value = false
    sellerAuction.value = null
    sellerError.value = ''
    const g = generation
    if (props.seller) {
      sellerLoading.value = true
      try {
        const a = await getSellerAuction(id.value)
        if (g === generation) sellerAuction.value = a
      } catch (e) {
        if (g === generation) sellerError.value = domainError(e)
      } finally {
        if (g === generation) sellerLoading.value = false
      }
    } else loadHistory()
  },
  { immediate: true },
)
watch(
  () => auction.value?.product?.slug,
  async (slug) => {
    if (!slug) return
    const g = generation
    try {
      const p = props.seller
        ? await getSellerProduct(auction.value.product_id)
        : await getProduct(slug)
      if (g === generation) product.value = p
    } catch (e) {
      if (g === generation) productError.value = domainError(e)
    }
  },
  { immediate: true },
)
watch(
  () => auction.value?.current_price,
  (price, previous) => {
    if (!previous || !price || amountCents(price) <= amountCents(previous)) return
    highlighted.value = true
    clearTimeout(flashTimer)
    flashTimer = setTimeout(() => {
      highlighted.value = false
    }, 550)
  },
)
watch(live.latestBid, (bid) => {
  if (!bid || props.seller || bids.value.some((row) => row.id === bid.id)) return
  bids.value.unshift(bid)
  entered.value = bid.id
  clearTimeout(enterTimer)
  enterTimer = setTimeout(() => {
    entered.value = null
  }, 550)
})
watch(live.snapshotVersion, () => loadHistory())
watch(
  () => auction.value?.status,
  (status, previous) => {
    if (!props.seller && status === 'ENDED' && previous && previous !== 'ENDED') live.refresh()
  },
)
watch(remaining, (value, previous) => {
  if (value === 0 && previous > 0 && !props.seller) live.refresh()
})
function chip(increment) {
  input.value = quickBid(auction.value.minimum_next_bid, increment)
  inputElement.value?.focus()
}
async function submit() {
  if (submitting.value || !canSubmit.value) return
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: `/auctions/${id.value}` } })
    return
  }
  const amount = parseBidInput(input.value)
  if (!amount) {
    bidError.value = 'Nhập số tiền hợp lệ, ví dụ 150.000 hoặc 150000.00.'
    await nextTick()
    inputElement.value?.focus()
    return
  }
  submitting.value = true
  feedback.value = bidError.value = ''
  const g = generation,
    target = id.value
  let focusError = false
  try {
    await placeAuctionBid(target, amount)
    if (g !== generation || disposed) return
    feedback.value = 'Đã ghi nhận giá của bạn.'
    input.value = ''
    await live.refresh()
  } catch (e) {
    if (g !== generation || disposed) return
    bidError.value =
      e.response?.data?.errors?.amount?.length ? e.response.data.errors.amount.map(message => localizeValidationMessage(message, 'amount')).join(' ') : domainError(e)
    await live.refresh()
    focusError = true
  } finally {
    if (g === generation && !disposed) {
      submitting.value = false
      if (focusError) {
        await nextTick()
        inputElement.value?.focus()
      }
    }
  }
}
onUnmounted(() => {
  disposed = true
  generation++
  historyRequest++
  clearTimeout(flashTimer)
  clearTimeout(enterTimer)
})
</script>
<template>
  <section class="auction-arena">
    <RouterLink :to="seller ? '/seller/auctions' : '/auctions'" class="buyer-back-link"
      >← {{ seller ? 'Đấu giá của tôi' : 'Sàn đấu giá' }}</RouterLink
    >
    <DomainFeedback
      :busy="seller ? sellerLoading : live.loading.value && !auction"
      :error="seller ? sellerError : live.error.value"
    />
    <template v-if="auction">
      <PageHeader
        :title="auction.product?.name || 'Phiên đấu giá'"
        eyebrow="Phòng đấu giá"
        description="Ra giá minh bạch. Giá và thời gian được xác nhận bởi máy chủ."
      />
      <div class="auction-arena-grid">
        <div class="auction-context">
          <ProductGallery v-if="product" :images="product.images || []" :name="product.name" />
          <DomainFeedback :error="productError" />
          <section class="card card-body mt-4">
            <h2>Thông tin sản phẩm</h2>
            <p v-if="product?.condition">Tình trạng: {{ conditionLabel(product.condition) }}</p>
            <p v-if="product?.category" class="text-muted">{{ product.category.name }}</p>
            <p class="auction-description">
              {{
                product?.description ||
                (productError
                  ? 'Thông tin sản phẩm không khả dụng.'
                  : 'Đang tải thông tin sản phẩm…')
              }}
            </p>
            <RouterLink v-if="product && !seller" :to="`/products/${product.slug}`"
              >Xem sản phẩm trên sàn mua bán →</RouterLink
            >
          </section>
        </div>
        <aside class="auction-controls card card-body">
          <div class="d-flex justify-content-between gap-2 flex-wrap">
            <RouterLink v-if="product?.shop" :to="`/shops/${product.shop.slug}`">{{
              product.shop.name
            }}</RouterLink
            ><StatusBadge :status="auction.status" :label="auctionLabels[auction.status]" />
          </div>
          <p v-if="!seller" class="auction-connection" role="status">
            {{
              live.connection.value === 'connected'
                ? '● Cập nhật trực tiếp'
                : '○ Kết nối gián đoạn — vẫn có thể ra giá qua API'
            }}
          </p>
          <small class="text-muted">{{
            auction.status === 'ENDED' ? 'Giá cuối phiên' : 'Giá hiện tại'
          }}</small
          ><strong
            class="auction-price"
            :class="{ 'is-changed': highlighted }"
            aria-live="polite"
            >{{ formatPrice(auction.current_price) }}</strong
          >
          <p class="small text-muted">
            Khởi điểm {{ formatPrice(auction.start_price) }} · {{ auction.bid_count }} lượt ra giá
          </p>
          <AuctionCountdown :auction="auction" :clock="seller ? undefined : live.clock" />
          <p v-if="auction.status === 'ENDED'" role="status">
            {{
              auction.winner_user_id
                ? `Người thắng: Người mua #${auction.winner_user_id}`
                : auction.bid_count > 0
                  ? 'Đang cập nhật kết quả từ máy chủ.'
                  : 'Phiên kết thúc mà không có lượt ra giá.'
            }}
          </p>
          <section v-if="winner" class="alert alert-success" data-auction-winner>
            <h2 class="h5">Bạn đã thắng phiên đấu giá</h2>
            <p>Giá thắng {{ formatPrice(auction.current_price) }}</p>
            <template v-if="auction.settlement_state === 'AWAITING_CHECKOUT'">
              <p>
                Hoàn tất trước
                <time :datetime="auction.winner_checkout_expires_at">{{
                  checkoutDeadlineLabel(auction.winner_checkout_expires_at)
                }}</time
                >.
              </p>
              <RouterLink :to="`/auctions/${id}/checkout`" class="btn btn-primary"
                >HOÀN TẤT MUA HÀNG</RouterLink
              >
            </template>
            <RouterLink
              v-else-if="auction.settlement_state === 'ORDER_CREATED'"
              :to="`/auctions/${id}/checkout`"
              class="btn btn-outline-primary"
              >Xem đơn thắng đấu giá</RouterLink
            >
            <p v-else-if="auction.settlement_state === 'CHECKOUT_EXPIRED'">Quyền mua đã hết hạn.</p>
          </section>
          <p v-if="auction.status === 'CANCELLED'" role="status">
            Người bán đã huỷ phiên đấu giá này.
          </p>
          <p v-else-if="auction.status === 'SCHEDULED'">
            Phiên chưa bắt đầu. Bạn có thể quay lại khi phiên mở.
          </p>
          <p v-if="own || seller" class="alert alert-info">
            Bạn đang xem phiên của mình. Người bán không thể ra giá.
          </p>
          <form v-else-if="liveStatus" class="auction-bid-form" @submit.prevent="submit">
            <label for="auction-bid-amount" class="form-label">Giá của bạn</label
            ><input
              id="auction-bid-amount"
              ref="inputElement"
              v-model="input"
              class="form-control"
              inputmode="decimal"
              autocomplete="off"
              maxlength="30"
              :disabled="submitting || !canSubmit"
              :aria-invalid="!!bidError"
              aria-describedby="auction-bid-help auction-bid-error"
              placeholder="Ví dụ: 150.000"
            />
            <p id="auction-bid-help" class="small mt-2">
              Tối thiểu <strong>{{ formatPrice(auction.minimum_next_bid) }}</strong
              >. Máy chủ kiểm tra lại giá khi gửi.
            </p>
            <div class="auction-chips">
              <button
                v-for="increment in [50000, 100000, 200000]"
                :key="increment"
                type="button"
                class="btn btn-outline-secondary btn-sm"
                :disabled="submitting || !canSubmit"
                @click="chip(increment)"
              >
                +{{ new Intl.NumberFormat('vi-VN').format(increment) }} ₫
              </button>
            </div>
            <button class="btn auction-bid-button w-100" :disabled="submitting || !canSubmit">
              {{
                submitting
                  ? 'ĐANG GỬI…'
                  : auth.isAuthenticated
                    ? 'RA GIÁ NGAY'
                    : 'ĐĂNG NHẬP ĐỂ RA GIÁ'
              }}
            </button>
            <p v-if="!remaining" class="small text-danger">
              Thời gian hiển thị đã hết. Đang chờ trạng thái máy chủ.
            </p>
          </form>
          <p id="auction-bid-error" class="text-danger" role="alert">{{ bidError }}</p>
          <p v-if="feedback" class="text-success" role="status">{{ feedback }}</p>
          <section v-if="!seller" class="auction-history">
            <h2>Lịch sử ra giá</h2>
            <DomainFeedback :busy="historyBusy" :error="historyError" />
            <p v-if="!historyBusy && !historyError && !bids.length" class="text-muted small">
              Chưa có lượt ra giá. Hãy là người đầu tiên.
            </p>
            <ol class="auction-bid-log">
              <li v-for="bid in bids" :key="bid.id" :class="{ 'is-new': entered === bid.id }">
                <div>
                  <strong>{{ bidIdentity(bid) }}</strong
                  ><time :datetime="bid.created_at">{{ bidTime(bid.created_at) }}</time>
                </div>
                <span>{{ formatPrice(bid.amount) }}</span>
              </li>
            </ol>
            <button
              v-if="meta && meta.current_page < meta.last_page"
              class="btn btn-outline-secondary btn-sm"
              :disabled="historyBusy"
              @click="loadHistory(meta.current_page + 1)"
            >
              Xem thêm lượt ra giá
            </button>
          </section>
        </aside>
      </div>
    </template>
  </section>
</template>

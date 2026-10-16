<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { previewCheckout } from '../api/voucher.api'
import { domainError } from '../api/domain.api'
import * as paymentApi from '../api/payment.api'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useCartStore } from '../stores/cart'
import { listAddresses } from '../api/address.api'
import { checkout } from '../api/order.api'
import { useFeedback } from '../composables/useFeedback'
import { formatPrice } from '../composables/productPresentation'
import DomainFeedback from '../components/DomainFeedback.vue'
import PageHeader from '../components/ui/PageHeader.vue'
const route = useRoute(),
  router = useRouter(),
  auth = useAuthStore(),
  cart = useCartStore()
const addresses = ref([]),
  addressId = ref(''),
  paymentMethod = ref('COD'),
  channel = ref(''),
  attempt = ref(null)
const { busy, error, errors, run } = useFeedback()
const storageKey = `checkout-attempt:${auth.user?.id}`
try {
  const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null')
  if (
    saved?.key &&
    saved?.payload?.address_id &&
    ['COD', 'ZALOPAY'].includes(saved.payload.payment_method)
  ) {
    attempt.value = saved
    addressId.value = saved.payload.address_id
    paymentMethod.value = saved.payload.payment_method
    const methods = saved.payload.preferred_payment_method || []
    channel.value =
      methods.length === 2 && methods.includes('domestic_card') && methods.includes('account')
        ? 'domestic'
        : methods.length === 1
          ? methods[0]
          : ''
  }
} catch {
  /* Storage may be unavailable. */
}
function persist(value) {
  attempt.value = value
  try {
    if (value) sessionStorage.setItem(storageKey, JSON.stringify(value))
    else sessionStorage.removeItem(storageKey)
  } catch {
    /* Keep retry key in memory. */
  }
}
// "Mua ngay" arrives with ?items=<cart item ids>: only those lines are ordered, the rest stay in the cart.
const selected = computed(() =>
  String(route?.query?.items || '')
    .split(',')
    .filter((id) => /^[1-9]\d*$/.test(id)),
)
const lines = computed(() => {
  const items = cart.data?.items || []
  return selected.value.length
    ? items.filter((item) => selected.value.includes(String(item.id)))
    : items
})
// Display only: the server recalculates every amount. Summed in whole cents to avoid float drift.
const cents = (value) => {
  const [whole, fraction = ''] = String(value ?? '0').split('.')
  return BigInt(whole || '0') * 100n + BigInt((fraction + '00').slice(0, 2))
}
const selectedTotal = computed(() => {
  const sum = lines.value.reduce((total, item) => total + cents(item.subtotal), 0n)
  return `${sum / 100n}.${String(sum % 100n).padStart(2, '0')}`
})
// Vouchers: `codes` is what is typed, `applied` what the last price preview accepted.
// The preview only shows numbers; checkout recalculates and decides.
const codes = reactive({ platform: '', shops: {} }),
  applied = reactive({ platform: '', shops: {} }),
  voucherErrors = reactive({ platform: '', shops: {} }),
  quote = ref(null),
  voucherBusy = ref(false)
const hasVouchers = computed(() => Boolean(applied.platform) || Object.keys(applied.shops).length > 0)
const quoteRow = (shopId) =>
  quote.value?.shop_orders.find((row) => String(row.shop_id) === String(shopId)) || null
const clean = (value) => String(value || '').trim().toUpperCase()
async function applyVouchers(platform, shops) {
  const wanted = Object.fromEntries(Object.entries(shops).filter(([, code]) => code))
  voucherErrors.platform = ''
  voucherErrors.shops = {}
  if (!platform && !Object.keys(wanted).length) {
    applied.platform = ''
    applied.shops = {}
    quote.value = null
    return
  }
  voucherBusy.value = true
  try {
    const result = await previewCheckout({
      ...(selected.value.length ? { cart_item_ids: selected.value.map(Number) } : {}),
      ...(platform ? { platform_voucher: platform } : {}),
      ...(Object.keys(wanted).length ? { shop_vouchers: wanted } : {}),
    })
    voucherErrors.platform = result.errors?.platform_voucher || ''
    voucherErrors.shops = { ...result.errors?.shop_vouchers }
    applied.platform = result.platform_voucher_code || ''
    applied.shops = Object.fromEntries(
      result.shop_orders.filter((row) => row.voucher_code).map((row) => [row.shop_id, row.voucher_code]),
    )
    quote.value = hasVouchers.value ? result : null
  } catch (failure) {
    voucherErrors.platform = domainError(failure)
  } finally {
    voucherBusy.value = false
  }
}
const applyPlatform = () => applyVouchers(clean(codes.platform), { ...applied.shops })
const applyShop = (shopId) =>
  applyVouchers(applied.platform, { ...applied.shops, [shopId]: clean(codes.shops[shopId]) })
function removePlatform() {
  codes.platform = ''
  return applyVouchers('', { ...applied.shops })
}
function removeShop(shopId) {
  codes.shops[shopId] = ''
  const rest = { ...applied.shops }
  delete rest[shopId]
  return applyVouchers(applied.platform, rest)
}
const groups = computed(() => {
  const map = new Map()
  for (const item of lines.value) {
    const id = item.shop?.id
    if (!map.has(id)) map.set(id, { shop: item.shop, items: [] })
    map.get(id).items.push(item)
  }
  return [...map.values()]
})
const canSubmit = computed(
  () =>
    !busy.value &&
    !cart.busy &&
    (attempt.value ||
      (addressId.value &&
        (selected.value.length
          ? lines.value.length === selected.value.length && lines.value.every((item) => item.available)
          : cart.data?.items.length && !cart.data.has_unavailable_items))),
)
async function load() {
  await run(async () => {
    await cart.load()
    addresses.value = (await listAddresses({ per_page: 100 })).data
    if (!attempt.value)
      addressId.value = (addresses.value.find((a) => a.is_default) || addresses.value[0])?.id || ''
  })
}
async function submit() {
  if (!canSubmit.value) return
  await run(async () => {
    const buyerId = auth.user?.id
    if (!attempt.value)
      persist({
        key: crypto.randomUUID(),
        payload: {
          address_id: addressId.value,
          payment_method: paymentMethod.value,
          ...(selected.value.length ? { cart_item_ids: selected.value.map(Number) } : {}),
          ...(applied.platform ? { platform_voucher: applied.platform } : {}),
          ...(Object.keys(applied.shops).length ? { shop_vouchers: { ...applied.shops } } : {}),
          ...(paymentMethod.value === 'ZALOPAY'
            ? {
                preferred_payment_method:
                  channel.value === 'domestic'
                    ? ['domestic_card', 'account']
                    : channel.value
                      ? [channel.value]
                      : [],
              }
            : {}),
        },
      })
    let order
    try {
      order = await checkout(attempt.value.payload, attempt.value.key)
    } catch (failure) {
      if (failure.response?.status && failure.response.status < 500) persist(null)
      await cart.load()
      throw failure
    }
    persist(null)
    if (auth.user?.id !== buyerId) return
    cart.reset()
    await cart.load()
    const online = order.payments?.at(-1)
    if (
      order.payment_method === 'ZALOPAY' &&
      online?.status === 'PENDING' &&
      online.order_url &&
      !order.reconciliation_required
    )
    {
      // ZaloPay opens in a new tab; this tab waits for the verified result.
      if (paymentApi.redirectToGateway(online.order_url) === 'tab')
        await router.push(paymentApi.paymentResultPath(order.id))
    } else await router.push(`/orders/${order.id}`)
  })
}
onMounted(load)
</script>
<template>
  <section class="buyer-flow">
    <PageHeader
      title="Đặt hàng"
      description="Chọn địa chỉ giao hàng và phương thức thanh toán để hoàn tất đơn mua."
    />
    <DomainFeedback :busy="busy || cart.busy" :error="error || cart.error" />
    <p v-for="(messages, field) in errors" :key="field" class="text-danger">
      {{ messages.join(' ') }}
    </p>
    <p v-if="attempt" class="alert alert-warning">
      Có yêu cầu chưa nhận được kết quả. Bấm kiểm tra lại để lấy đúng đơn đã tạo; địa chỉ được giữ
      nguyên.
    </p>
    <p v-if="selected.length" class="alert alert-info" data-checkout-selection>
      Mua ngay: chỉ đặt {{ lines.length }} sản phẩm đã chọn. Các sản phẩm khác vẫn ở trong giỏ.
      <RouterLink to="/checkout">Đặt cả giỏ hàng</RouterLink>
    </p>
    <p
      v-if="selected.length && cart.data && (lines.length !== selected.length || lines.some((item) => !item.available))"
      class="alert alert-warning"
    >
      Sản phẩm đã chọn không còn mua được. Hãy kiểm tra lại giỏ hàng.
    </p>
    <p v-else-if="!selected.length && cart.data?.has_unavailable_items" class="alert alert-warning">
      Giỏ có sản phẩm không thể mua. Hãy sửa giỏ trước khi đặt toàn bộ đơn.
    </p>
    <p v-if="cart.data && !cart.data.items.length" class="alert alert-info">Giỏ hàng đang trống.</p>
    <form class="buyer-two-column" @submit.prevent="submit">
      <div class="buyer-checkout-panels">
        <section class="buyer-panel">
          <h2>01 · Giao hàng</h2>
          <label for="checkout-address" class="form-label">Địa chỉ giao hàng</label>
          <select
            id="checkout-address"
            v-model="addressId"
            class="form-select mb-3"
            :disabled="busy || !!attempt"
            :required="!attempt"
          >
            <option value="">Chọn địa chỉ</option>
            <option v-for="address in addresses" :key="address.id" :value="address.id">
              {{ address.recipient_name }} — {{ address.address_line }}, {{ address.province }}
            </option>
          </select>
          <RouterLink to="/account/addresses">Quản lý địa chỉ</RouterLink>
        </section>
        <section class="buyer-panel">
          <h2>02 · Sản phẩm trong đơn</h2>
          <section
            v-for="group in groups"
            :key="group.shop?.id"
            class="card my-3"
            data-checkout-shop
          >
            <h2 class="h5 card-header">{{ group.shop?.name }}</h2>
            <div class="card-body">
              <p v-for="item in group.items" :key="item.id" class="buyer-checkout-summary-item">
                <span>{{ item.product?.name }} × {{ item.quantity }}</span
                ><strong>{{ formatPrice(item.subtotal) }}</strong>
              </p>
              <div class="checkout-voucher" :data-shop-voucher="group.shop?.id">
                <label :for="`voucher-shop-${group.shop?.id}`" class="form-label"
                  >Mã giảm giá của gian hàng</label
                >
                <div class="checkout-voucher-row">
                  <input
                    :id="`voucher-shop-${group.shop?.id}`"
                    v-model="codes.shops[group.shop?.id]"
                    class="form-control checkout-voucher-input"
                    maxlength="20"
                    autocomplete="off"
                    placeholder="Nhập mã"
                    :disabled="busy || voucherBusy || !!attempt || !!applied.shops[group.shop?.id]"
                  />
                  <button
                    v-if="!applied.shops[group.shop?.id]"
                    type="button"
                    class="btn btn-outline-primary"
                    :disabled="busy || voucherBusy || !!attempt || !codes.shops[group.shop?.id]"
                    @click="applyShop(group.shop?.id)"
                  >
                    Áp dụng
                  </button>
                  <button
                    v-else
                    type="button"
                    class="btn btn-outline-secondary"
                    :disabled="busy || voucherBusy || !!attempt"
                    @click="removeShop(group.shop?.id)"
                  >
                    Bỏ mã
                  </button>
                </div>
                <p v-if="voucherErrors.shops[group.shop?.id]" role="alert" class="text-danger small">
                  {{ voucherErrors.shops[group.shop?.id] }}
                </p>
                <p
                  v-if="quoteRow(group.shop?.id) && Number(quoteRow(group.shop?.id).discount_shop) + Number(quoteRow(group.shop?.id).discount_platform) > 0"
                  class="checkout-voucher-line"
                  data-shop-discount
                >
                  <template v-if="Number(quoteRow(group.shop?.id).discount_shop) > 0"
                    >Mã gian hàng: −{{ formatPrice(quoteRow(group.shop?.id).discount_shop) }} ·
                  </template>
                  <template v-if="Number(quoteRow(group.shop?.id).discount_platform) > 0"
                    >Mã của sàn: −{{ formatPrice(quoteRow(group.shop?.id).discount_platform) }} ·
                  </template>
                  Còn {{ formatPrice(quoteRow(group.shop?.id).total) }}
                </p>
              </div>
            </div>
          </section>
        </section>
        <section class="buyer-panel" data-platform-voucher>
          <h2>Mã giảm giá của sàn</h2>
          <label for="voucher-platform" class="form-label">Mỗi đơn dùng được một mã của sàn</label>
          <div class="checkout-voucher-row">
            <input
              id="voucher-platform"
              v-model="codes.platform"
              class="form-control checkout-voucher-input"
              maxlength="20"
              autocomplete="off"
              placeholder="Nhập mã"
              :disabled="busy || voucherBusy || !!attempt || !!applied.platform"
            />
            <button
              v-if="!applied.platform"
              type="button"
              class="btn btn-outline-primary"
              :disabled="busy || voucherBusy || !!attempt || !codes.platform"
              @click="applyPlatform"
            >
              Áp dụng
            </button>
            <button
              v-else
              type="button"
              class="btn btn-outline-secondary"
              :disabled="busy || voucherBusy || !!attempt"
              @click="removePlatform"
            >
              Bỏ mã
            </button>
          </div>
          <p v-if="voucherErrors.platform" role="alert" class="text-danger small">
            {{ voucherErrors.platform }}
          </p>
          <p v-if="applied.platform && quote" class="checkout-voucher-line">
            Mã {{ applied.platform }}: −{{ formatPrice(quote.platform_discount) }}
          </p>
        </section>
        <section class="buyer-panel">
          <h2>03 · Thanh toán</h2>
          <div class="buyer-payment-choice" aria-hidden="true">
            <div :class="{ selected: paymentMethod === 'COD' }">
              <strong>Thanh toán khi nhận hàng</strong>
              <p>Thanh toán khi nhận hàng.</p>
            </div>
            <div :class="{ selected: paymentMethod === 'ZALOPAY' }">
              <strong>Thanh toán trực tuyến qua ZaloPay</strong>
              <p>Thanh toán tại cổng ZaloPay.</p>
            </div>
          </div>
          <label for="checkout-payment" class="form-label">Phương thức thanh toán</label>
          <select
            id="checkout-payment"
            v-model="paymentMethod"
            class="form-select mb-3"
            :disabled="busy || !!attempt"
          >
            <option value="COD">Thanh toán khi nhận hàng</option>
            <option value="ZALOPAY">Thanh toán trực tuyến qua ZaloPay</option>
          </select>
          <template v-if="paymentMethod === 'ZALOPAY'">
            <label for="checkout-channel" class="form-label">Kênh ưu tiên tại ZaloPay</label>
            <select
              id="checkout-channel"
              v-model="channel"
              class="form-select mb-3"
              :disabled="busy || !!attempt"
            >
              <option value="">Tất cả kênh</option>
              <option value="international_card">Thẻ quốc tế</option>
              <option value="domestic">Thẻ / tài khoản ngân hàng nội địa</option>
              <option value="zalopay_wallet">Ví ZaloPay</option>
              <option value="vietqr">VietQR</option>
            </select>
            <p>
              Bạn nhập thông tin thanh toán tại cổng ZaloPay. Cửa hàng không lưu thông tin thẻ hoặc
              tài khoản ngân hàng.
            </p>
          </template>
        </section>
      </div>
      <aside class="buyer-panel buyer-sticky-summary" aria-label="Tóm tắt thanh toán">
        <h2>Xác nhận đơn mua</h2>
        <p data-checkout-discount>
          Phí giao hàng: 0 ₫. Giảm giá: {{ quote ? formatPrice(quote.discount_total) : '0 ₫' }}.
        </p>
        <p class="buyer-summary-total" data-checkout-total>
          Tổng tạm tính:
          {{ formatPrice(quote ? quote.total : selected.length ? selectedTotal : cart.data?.subtotal) }}
        </p>
        <p class="buyer-summary-note">
          Giá và tồn kho được kiểm tra lại khi xác nhận. Đơn hàng lưu giá tại thời điểm đặt.
        </p>
        <button
          type="submit"
          class="btn btn-primary w-100"
          :disabled="!canSubmit"
          :aria-busy="busy"
        >
          {{ busy ? 'Đang xử lý…' : attempt ? 'Kiểm tra lại yêu cầu' : 'Xác nhận đặt hàng' }}
        </button>
        <RouterLink to="/cart" class="d-block text-center mt-3">Về giỏ hàng</RouterLink>
      </aside>
    </form>
  </section>
</template>
<style scoped>
.checkout-voucher {
  margin-top: var(--space-3);
  padding-top: var(--space-3);
  border-top: 1px dashed var(--border);
}
.checkout-voucher-row {
  display: flex;
  gap: var(--space-2);
}
.checkout-voucher-input {
  font-family: var(--font-money);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}
.checkout-voucher-input::placeholder {
  font-family: var(--font-ui);
  letter-spacing: 0;
  text-transform: none;
}
.checkout-voucher-row .btn {
  flex: none;
}
.checkout-voucher-line {
  margin: var(--space-2) 0 0;
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-sm);
  background: var(--bs-success-bg-subtle);
  color: var(--success-ink);
  font-size: 0.85rem;
  font-weight: 600;
}
.checkout-voucher p[role='alert'] {
  margin: var(--space-2) 0 0;
}
</style>

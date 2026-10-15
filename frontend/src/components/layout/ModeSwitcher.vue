<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { getSellerShop } from '../../api/shop.api'
import AppIcon from '../ui/AppIcon.vue'
// A UI context, not a role: it only decides which shortcuts are offered.
// What a user may actually open is still decided by the route guard and the backend.
// inline: drawn open inside another menu (the account menu), as a segmented control plus its shortcuts.
const props = defineProps({ inline: Boolean })
const auth = useAuthStore(),
  route = useRoute()
const open = ref(false),
  root = ref(null),
  stored = ref('buyer'),
  hasShop = ref(null)
const key = () => `passlai:mode:${auth.user?.id}`
const labels = { buyer: 'Dành cho người mua', seller: 'Dành cho người bán', admin: 'Quản trị' }
const shortLabels = { buyer: 'Mua', seller: 'Bán', admin: 'Quản trị' }
const modes = computed(() => (auth.isAdmin ? ['buyer', 'seller', 'admin'] : ['buyer', 'seller']))
// The page decides first: the seller and admin areas are their own context.
const fromRoute = computed(() =>
  /^\/(seller|my-ads)(\/|$)/.test(route.path) ? 'seller' : /^\/admin(\/|$)/.test(route.path) ? 'admin' : '',
)
const mode = computed(() => {
  const wanted = fromRoute.value || stored.value
  return modes.value.includes(wanted) ? wanted : 'buyer'
})
const links = computed(
  () =>
    ({
      buyer: [
        ['/orders', 'Đơn mua'],
        ['/wishlist', 'Yêu thích'],
        ['/reports', 'Báo cáo đã gửi'],
        ['/account/addresses', 'Địa chỉ'],
      ],
      seller:
        hasShop.value === false
          ? [['/seller/shop', 'Tạo cửa hàng']]
          : [
              ['/my-ads', 'Quản lý tin'],
              ['/seller/orders', 'Đơn bán'],
              ['/seller/inventory', 'Kho hàng'],
              ['/seller/auctions', 'Đấu giá'],
              ['/seller/vouchers', 'Voucher'],
              ['/seller/dashboard', 'Thống kê'],
            ],
      admin: [
        ['/admin', 'Action Center'],
        ['/admin/products', 'Duyệt tin'],
        ['/admin/shops', 'Gian hàng'],
        ['/admin/users', 'Người dùng'],
        ['/admin/reports', 'Báo cáo'],
        ['/admin/payments', 'Đối soát'],
        ['/admin/vouchers', 'Voucher sàn'],
      ],
    })[mode.value],
)
function restore() {
  stored.value = 'buyer'
  hasShop.value = null
  try {
    const saved = window.localStorage.getItem(key())
    if (saved && saved in labels) stored.value = saved
  } catch {
    /* no remembered choice */
  }
}
async function choose(next) {
  stored.value = next
  try {
    window.localStorage.setItem(key(), next)
  } catch {
    /* the choice still applies to this page view */
  }
  if (next === 'seller') checkShop()
}
// Asked once, only when the seller shortcuts are about to be shown.
async function checkShop() {
  if (hasShop.value !== null) return
  try {
    await getSellerShop()
    hasShop.value = true
  } catch (failure) {
    hasShop.value = failure.response?.status === 404 ? false : true
  }
}
function toggle() {
  open.value = !open.value
  if (open.value && mode.value === 'seller') checkShop()
}
function outside(event) {
  if (open.value && root.value && !root.value.contains(event.target)) open.value = false
}
watch(() => auth.user?.id, restore, { immediate: true })
// Drawn open, the seller shortcuts need to know whether there is a shop as soon as they show.
watch(
  () => props.inline && mode.value === 'seller',
  (shown) => shown && checkShop(),
  { immediate: true },
)
watch(
  () => route.fullPath,
  () => {
    open.value = false
  },
)
onMounted(() => document.addEventListener('pointerdown', outside))
onBeforeUnmount(() => document.removeEventListener('pointerdown', outside))
</script>
<template>
  <div
    v-if="auth.isAuthenticated"
    ref="root"
    class="mode-switcher"
    :class="{ 'is-inline': inline }"
    :data-mode="mode"
    @keydown.esc.stop="open = false"
  >
    <p v-if="inline" class="mode-switcher-title">Chế độ</p>
    <button
      v-if="!inline"
      type="button"
      class="mode-switcher-button"
      :aria-expanded="open"
      aria-controls="mode-switcher-panel"
      @click="toggle"
    >
      {{ labels[mode] }} <AppIcon name="chevronDown" :size="16" />
    </button>
    <div v-if="open || inline" :id="inline ? undefined : 'mode-switcher-panel'" class="mode-switcher-panel">
      <div class="mode-switcher-modes" role="group" aria-label="Chế độ sử dụng">
        <button
          v-for="item in modes"
          :key="item"
          type="button"
          class="mode-switcher-mode"
          :class="{ 'is-active': mode === item }"
          :aria-pressed="mode === item"
          :disabled="Boolean(fromRoute) && fromRoute !== item"
          :data-mode-option="item"
          :aria-label="inline ? labels[item] : undefined"
          @click.stop="choose(item)"
        >
          {{ inline ? shortLabels[item] : labels[item] }}
        </button>
      </div>
      <!-- Inside the account menu the buyer pages are already listed, so only selling/admin shortcuts show. -->
      <nav v-if="!(inline && mode === 'buyer')" class="mode-switcher-links" :aria-label="labels[mode]">
        <RouterLink v-for="[to, text] in links" :key="to" :to="to">{{ text }}</RouterLink>
      </nav>
    </div>
  </div>
</template>
<style scoped>
.mode-switcher {
  position: relative;
}
.mode-switcher-button {
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  min-height: 44px;
  padding: 0 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--surface);
  color: var(--text);
  font-size: 0.82rem;
  font-weight: 700;
  white-space: nowrap;
  transition: border-color var(--transition-fast);
}
.mode-switcher-button:hover,
.mode-switcher-button[aria-expanded='true'] {
  border-color: var(--primary);
}
.mode-switcher[data-mode='seller'] .mode-switcher-button {
  background: var(--accent);
  border-color: var(--accent);
  color: var(--accent-ink);
}
.mode-switcher[data-mode='admin'] .mode-switcher-button {
  background: var(--inverse);
  border-color: var(--inverse);
  color: var(--on-inverse);
}
.mode-switcher-panel {
  position: absolute;
  top: calc(100% + 0.5rem);
  left: 0;
  z-index: 5;
  width: min(18rem, calc(100vw - 2rem));
  padding: var(--space-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-dialog);
}
.mode-switcher-modes {
  display: grid;
  gap: 0.25rem;
  padding-bottom: var(--space-2);
  border-bottom: 1px solid var(--border);
}
.mode-switcher-mode {
  min-height: 44px;
  padding: 0 0.75rem;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text);
  font-size: 0.85rem;
  font-weight: 600;
  text-align: left;
}
.mode-switcher-mode:hover:not(:disabled) {
  background: var(--surface-2);
}
.mode-switcher-mode.is-active {
  border-color: var(--primary);
  background: var(--primary-soft);
  color: var(--primary-ink);
}
.mode-switcher-mode:disabled {
  color: var(--muted);
}
.mode-switcher-links {
  display: grid;
  padding-top: var(--space-2);
}
.mode-switcher-links a {
  display: flex;
  align-items: center;
  min-height: 44px;
  padding: 0 0.75rem;
  border-radius: var(--radius-sm);
  color: var(--text);
  font-size: 0.88rem;
  text-decoration: none;
}
.mode-switcher-links a:hover {
  background: var(--surface-2);
  color: var(--primary);
}
.mode-switcher :is(button, a):focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.mode-switcher.is-inline .mode-switcher-panel {
  position: static;
  width: auto;
  padding: 0;
  border: 0;
  box-shadow: none;
  background: transparent;
}
.mode-switcher-title {
  margin: 0 0 0.4rem;
  color: var(--muted);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
}
.mode-switcher.is-inline .mode-switcher-modes {
  display: grid;
  grid-auto-columns: minmax(0, 1fr);
  grid-auto-flow: column;
  gap: 0.25rem;
  padding: 0.2rem;
  border-radius: var(--radius-pill);
  background: var(--surface-2);
}
.mode-switcher.is-inline .mode-switcher-modes {
  border-bottom: 0;
}
.mode-switcher.is-inline .mode-switcher-mode {
  min-height: 36px;
  padding: 0 0.5rem;
  border: 0;
  border-radius: var(--radius-pill);
  text-align: center;
}
.mode-switcher.is-inline .mode-switcher-mode.is-active {
  background: var(--surface);
  color: var(--text);
  box-shadow: 0 1px 3px rgb(var(--shadow-rgb) / 14%);
}
.mode-switcher.is-inline .mode-switcher-links a {
  min-height: 36px;
  padding: 0 0.5rem;
}
.mode-switcher.is-inline .mode-switcher-links {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.15rem 0.5rem;
  margin-top: 0.5rem;
}
</style>

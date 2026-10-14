<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { getSellerShop } from '../../api/shop.api'
import AppIcon from '../ui/AppIcon.vue'
// A UI context, not a role: it only decides which shortcuts are offered.
// What a user may actually open is still decided by the route guard and the backend.
const auth = useAuthStore(),
  route = useRoute()
const open = ref(false),
  root = ref(null),
  stored = ref('buyer'),
  hasShop = ref(null)
const key = () => `passlai:mode:${auth.user?.id}`
const labels = { buyer: 'Dành cho người mua', seller: 'Dành cho người bán', admin: 'Quản trị' }
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
    :data-mode="mode"
    @keydown.esc.stop="open = false"
  >
    <button
      type="button"
      class="mode-switcher-button"
      :aria-expanded="open"
      aria-controls="mode-switcher-panel"
      @click="toggle"
    >
      {{ labels[mode] }} <AppIcon name="chevronDown" :size="16" />
    </button>
    <div v-if="open" id="mode-switcher-panel" class="mode-switcher-panel">
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
          @click="choose(item)"
        >
          {{ labels[item] }}
        </button>
      </div>
      <nav class="mode-switcher-links" :aria-label="labels[mode]">
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
  background: var(--text);
  border-color: var(--text);
  color: #fff;
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
</style>

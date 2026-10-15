<script setup>
import { brand } from '../../config/brand'
import BrandLogo from '../ui/BrandLogo.vue'
import ThemeToggle from '../ui/ThemeToggle.vue'
import ModeSwitcher from '../layout/ModeSwitcher.vue'
import NotificationBell from '../notifications/NotificationBell.vue'
import { provide, ref, watch } from 'vue'
import { sellerIdentityKey } from './useSellerIdentity'
import StatusBadge from '../ui/StatusBadge.vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import LogoutButton from '../LogoutButton.vue'
import ChatLauncher from '../chat/ChatLauncher.vue'
import AppIcon from '../ui/AppIcon.vue'
const shopIdentity = ref(null)
provide(sellerIdentityKey, shopIdentity)
const route = useRoute(),
  auth = useAuthStore(),
  open = ref(false),
  toggle = ref(null)
const links = [
  ['/seller/dashboard', 'Tổng quan', 'chart'],
  ['/seller/inventory', 'Tồn kho', 'box'],
  ['/seller/products', 'Sản phẩm', 'tag'],
  ['/seller/orders', 'Đơn hàng', 'receipt'],
  ['/seller/shop', 'Gian hàng', 'store'],
  ['/seller/auctions', 'Đấu giá', 'gavel'],
  ['/seller/vouchers', 'Mã giảm giá', 'ticket'],
]
const active = (path) => route.path === path || route.path.startsWith(`${path}/`)
watch(
  () => route.path,
  () => {
    open.value = false
  },
)
function close() {
  if (!open.value) return
  open.value = false
  toggle.value?.focus()
}
</script>
<template>
  <div class="seller-shell" @keydown.esc="close">
    <header class="seller-topbar">
      <RouterLink class="seller-brand" to="/"
        ><BrandLogo compact /> <span>{{ brand.sellerArea }}</span></RouterLink
      >
      <ModeSwitcher />
      <ThemeToggle class="seller-theme" />
      <div class="seller-topbar-actions">
        <RouterLink to="/products">← Sàn mua bán</RouterLink
        ><RouterLink to="/account/profile" class="seller-user">{{
          auth.user?.name || 'Tài khoản'
        }}</RouterLink
        ><NotificationBell /><ChatLauncher /><LogoutButton />
      </div>
      <button
        ref="toggle"
        class="btn btn-outline-secondary seller-menu-toggle"
        :aria-expanded="open"
        aria-controls="seller-navigation"
        @click="open = !open"
      >
        {{ open ? 'Đóng điều hướng' : 'Điều hướng gian hàng' }}
      </button>
    </header>
    <div class="seller-workspace">
      <aside id="seller-navigation" class="seller-sidebar" :class="{ 'is-open': open }">
        <p class="seller-sidebar-caption">QUẢN LÝ BÁN HÀNG</p>
        <nav aria-label="Điều hướng người bán">
          <RouterLink
            v-for="[path, label, icon] in links"
            :key="path"
            :to="path"
            :class="{ active: active(path) }"
            :aria-current="active(path) ? 'page' : undefined"
            ><AppIcon :name="icon" :size="18" />{{ label }}</RouterLink
          >
        </nav>
        <div class="seller-sidebar-note">
          <strong>{{ shopIdentity?.name || 'Gian hàng của tôi' }}</strong>
          <StatusBadge v-if="shopIdentity?.status" :status="shopIdentity.status" />
          <p>Quản lý thông tin và trạng thái gian hàng.</p>
          <RouterLink to="/seller/shop">Thông tin gian hàng →</RouterLink>
        </div>
        <RouterLink class="seller-account-link" to="/account">Hồ sơ &amp; địa chỉ →</RouterLink>
      </aside>
      <main class="seller-content"><slot /></main>
    </div>
  </div>
</template>

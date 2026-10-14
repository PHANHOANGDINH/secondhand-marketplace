<script setup>
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref, watch } from 'vue'
import { provideNotifications } from '../composables/useNotifications'
import NotificationBell from '../components/notifications/NotificationBell.vue'
import { provideMessaging } from '../composables/useMessaging'
import ChatDrawer from '../components/chat/ChatDrawer.vue'
import ChatLauncher from '../components/chat/ChatLauncher.vue'
import { useRoute, useRouter } from 'vue-router'
import '../styles/ui.css'
import '../styles/buyer.css'
import '../styles/seller.css'
import '../styles/admin.css'
import '../styles/core.css'
import BrandLogo from '../components/ui/BrandLogo.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import AdminShell from '../components/admin/AdminShell.vue'
import SellerShell from '../components/seller/SellerShell.vue'
import AccountNav from '../components/account/AccountNav.vue'
import ModeSwitcher from '../components/layout/ModeSwitcher.vue'
import SiteFooter from '../components/layout/SiteFooter.vue'
import { useAuthStore } from '../stores/auth'
import LogoutButton from '../components/LogoutButton.vue'
import CartLink from '../components/CartLink.vue'
import ProductSearch from '../components/ProductSearch.vue'
import { brand } from '../config/brand'
import FirstTimeGuide from '../components/onboarding/FirstTimeGuide.vue'
import { resetFirstTime } from '../composables/useFirstTime'
import { areaPickerOpen, useArea } from '../composables/useArea'
const auth = useAuthStore()
provideMessaging()
provideNotifications()
const route = useRoute(),
  router = useRouter()
const { code: areaCode, label: areaLabel, setArea, loadProvinces } = useArea()
const searchKeyword = ref('')
// The chosen area travels with every search from the header.
const searchProducts = () =>
  router.push({
    path: '/products',
    query: { q: searchKeyword.value.trim(), ...(areaCode.value ? { province: areaCode.value } : {}) },
  })
async function confirmArea(next) {
  setArea(next)
  areaPickerOpen.value = false
  if (route.path === '/products') {
    const query = { ...route.query }
    delete query.page
    if (areaCode.value) query.province = areaCode.value
    else delete query.province
    await router.replace({ path: '/products', query })
  }
}
// The welcome tour opens on every page view of a signed-in user until it is finished or skipped
// (FirstTimeGuide keeps the per-user flag); "Xem lại hướng dẫn" clears the flags and reopens it.
const guideRun = ref(0)
const welcomeSteps = [
  { title: 'Tìm kiếm', text: 'Gõ tên món đồ, danh mục hoặc gian hàng vào ô tìm kiếm để xem gợi ý, và chọn khu vực gần bạn.' },
  { title: 'Đăng tin', text: 'Nút "Đăng tin" mở biểu mẫu đăng bán nhanh ngay tại trang bạn đang xem.' },
  { title: 'Đấu giá', text: 'Vào "Đấu giá" để theo dõi các phiên đang diễn ra và ra giá trực tiếp.' },
]
function replayGuides() {
  accountMenu.value?.removeAttribute('open')
  if (resetFirstTime(auth.user?.id)) guideRun.value += 1
}
const OutbidToasts = defineAsyncComponent(() => import('../components/auction/OutbidToasts.vue'))
const QuickListingDialog = defineAsyncComponent(
  () => import('../components/seller/QuickListingDialog.vue'),
)
const NavDrawer = defineAsyncComponent(() => import('../components/layout/NavDrawer.vue'))
const AreaPickerDialog = defineAsyncComponent(
  () => import('../components/layout/AreaPickerDialog.vue'),
)
const drawerOpen = ref(false),
  scrolled = ref(false),
  menuToggle = ref(null),
  accountMenu = ref(null)
const quickOpen = computed(() => auth.isAuthenticated && route.query['quick-listing'] === '1')
const initials = computed(() =>
  (auth.user?.name || 'Tài khoản')
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word[0])
    .join('')
    .toLocaleUpperCase('vi-VN'),
)
function closeNavigation() {
  accountMenu.value?.removeAttribute('open')
}
async function openQuickListing() {
  const guestPage =
    route.meta.guestOnly || ['/forgot-password', '/reset-password'].includes(route.path)
  const target = router.resolve({
    path: guestPage ? '/' : route.path,
    query: { ...(guestPage ? {} : route.query), 'quick-listing': '1' },
    hash: guestPage ? '' : route.hash,
  })
  await router.push(
    auth.isAuthenticated
      ? target.fullPath
      : { name: 'login', query: { redirect: target.fullPath } },
  )
}
async function closeQuickListing() {
  const query = { ...route.query }
  delete query['quick-listing']
  await router.replace({ path: route.path, query, hash: route.hash })
}
const updateScroll = () => {
  scrolled.value = window.scrollY > 12
}
function outsideMenu(event) {
  if (accountMenu.value && !accountMenu.value.contains(event.target))
    accountMenu.value.removeAttribute('open')
}
watch(
  () => route.fullPath,
  () => {
    drawerOpen.value = false
    accountMenu.value?.removeAttribute('open')
  },
)
onMounted(() => {
  loadProvinces()
  updateScroll()
  window.addEventListener('scroll', updateScroll, { passive: true })
  document.addEventListener('pointerdown', outsideMenu)
})
onUnmounted(() => {
  window.removeEventListener('scroll', updateScroll)
  document.removeEventListener('pointerdown', outsideMenu)
})
const buyerPage = computed(() => !/^\/(seller|admin)(\/|$)/.test(route?.path || ''))
const sellerPage = computed(() => /^\/seller(\/|$)/.test(route?.path || ''))
const adminPage = computed(() => /^\/admin(\/|$)/.test(route?.path || ''))
const accountPage = computed(() => /^\/account(\/|$)/.test(route?.path || ''))
// The home page has its own large search box; every other page gets the compact one in the header.
const homePage = computed(() => (route?.path || '/') === '/')
function closeAccountMenu(event) {
  const menu = event.currentTarget.closest('details')
  if (event.type === 'keydown') {
    menu.open = false
    menu.querySelector('summary').focus()
  } else if (event.target.closest('a')) menu.open = false
}
</script>
<template>
  <header
    v-if="!sellerPage && !adminPage"
    class="marketplace-header"
    :class="{ 'is-scrolled': scrolled, 'is-home': homePage }"
    @keydown.esc="closeNavigation"
  >
    <nav class="container marketplace-nav" aria-label="Điều hướng chính">
      <button
        ref="menuToggle"
        type="button"
        class="btn marketplace-menu-toggle"
        :aria-expanded="drawerOpen"
        aria-controls="nav-drawer"
        aria-label="Mở danh mục"
        @click="drawerOpen = true"
      >
        <AppIcon name="menu" />
      </button>
      <RouterLink to="/" class="marketplace-brand" :aria-label="`${brand.name} · Trang chủ`"
        ><BrandLogo
      /></RouterLink>
      <ModeSwitcher class="marketplace-mode" />
      <div v-if="homePage" class="marketplace-directory">
        <RouterLink to="/" class="is-current">Trang chủ</RouterLink>
        <RouterLink to="/auctions">Đấu giá</RouterLink>
        <RouterLink to="/?discovery=video#home-discovery">Video</RouterLink>
      </div>
      <div v-else class="marketplace-compact">
        <form
          action="/products"
          method="get"
          class="marketplace-search"
          role="search"
          @submit.prevent="searchProducts"
        >
          <ProductSearch
            id="header-product-search"
            v-model="searchKeyword"
            @submit="searchProducts"
          /><button class="btn" type="submit" aria-label="Tìm sản phẩm">
            <AppIcon name="search" />
          </button>
        </form>
        <button
          type="button"
          class="marketplace-area"
          data-area-open
          :aria-label="`Chọn khu vực, đang chọn ${areaLabel}`"
          @click="areaPickerOpen = true"
        >
          <AppIcon name="pin" :size="18" /><span>{{ areaCode ? areaLabel : 'Chọn khu vực' }}</span
          ><AppIcon name="chevronDown" :size="14" />
        </button>
      </div>
      <div class="marketplace-links">
        <template v-if="auth.isAuthenticated">
          <RouterLink to="/wishlist" class="marketplace-icon-link" aria-label="Sản phẩm yêu thích"
            ><AppIcon name="heart"
          /></RouterLink>
          <NotificationBell />
          <ChatLauncher compact />
          <CartLink icon-only />
          <RouterLink to="/my-ads" class="marketplace-manage">Quản lý tin</RouterLink>
          <RouterLink v-if="auth.isAdmin" to="/admin/dashboard" class="marketplace-admin-entry"
            >Quản trị</RouterLink
          >
        </template>
        <button
          type="button"
          class="btn btn-primary marketplace-sell"
          :disabled="auth.loading"
          @click="openQuickListing"
        >
          <AppIcon name="plus" :size="18" /> Đăng tin
        </button>
        <template v-if="auth.isAuthenticated">
          <details
            ref="accountMenu"
            class="marketplace-account"
            @keydown.esc.stop="closeAccountMenu"
          >
            <summary>
              <span class="marketplace-avatar" aria-hidden="true">{{ initials }}</span
              ><span class="visually-hidden">Tài khoản</span
              ><span class="marketplace-account-name">{{ auth.user?.name || 'Tài khoản' }}</span
              ><AppIcon name="chevronDown" :size="16" />
            </summary>
            <div class="marketplace-account-menu" @click="closeAccountMenu">
              <RouterLink to="/reports">Báo cáo của tôi</RouterLink>
              <RouterLink to="/wishlist">Sản phẩm yêu thích</RouterLink>
              <RouterLink to="/orders">Đơn mua</RouterLink
              ><RouterLink to="/seller/orders">Đơn bán</RouterLink>
              <RouterLink to="/account">Tài khoản</RouterLink>
              <RouterLink to="/account/profile">Hồ sơ</RouterLink>
              <RouterLink to="/account/addresses">Địa chỉ</RouterLink>
              <RouterLink to="/seller/shop">Gian hàng của tôi</RouterLink>
              <RouterLink to="/seller/dashboard">Thống kê gian hàng</RouterLink>
              <RouterLink to="/my-ads">Quản lý tin</RouterLink>
              <template v-if="auth.isAdmin"
                ><RouterLink v-if="auth.isAdmin" class="nav-link" to="/admin/dashboard"
                  >Tổng quan quản trị</RouterLink
                >
                <RouterLink v-if="auth.isAdmin" class="nav-link" to="/admin/users"
                  >Người dùng</RouterLink
                >
                <RouterLink v-if="auth.isAdmin" class="nav-link" to="/admin/reviews"
                  >Duyệt đánh giá</RouterLink
                >
                <RouterLink v-if="auth.isAdmin" class="nav-link" to="/admin/orders"
                  >Tổng quan đơn hàng</RouterLink
                >
                <RouterLink to="/admin/categories">Quản lý danh mục</RouterLink
                ><RouterLink to="/admin/shops">Gian hàng</RouterLink
                ><RouterLink to="/admin/products">Tin đăng</RouterLink></template
              >
              <button type="button" class="marketplace-menu-button" @click="replayGuides">
                Xem lại hướng dẫn
              </button>
              <LogoutButton />
            </div>
          </details>
        </template>
        <template v-else-if="auth.initialized"
          ><LogoutButton v-if="auth.sessionBlocked" /><RouterLink to="/login">Đăng nhập</RouterLink
          ><RouterLink to="/register">Đăng ký</RouterLink></template
        >
      </div>
    </nav>
  </header>
  <NavDrawer v-if="drawerOpen" @dismiss="drawerOpen = false" />
  <AreaPickerDialog
    v-if="areaPickerOpen"
    @confirm="confirmArea"
    @dismiss="areaPickerOpen = false"
  />
  <QuickListingDialog v-if="quickOpen" @close="closeQuickListing" />
  <FirstTimeGuide
    v-if="auth.isAuthenticated && !quickOpen"
    :key="guideRun"
    name="welcome"
    tour
    :title="`Chào mừng đến ${brand.name}`"
    :steps="welcomeSteps"
  />
  <OutbidToasts v-if="auth.isAuthenticated" /><ChatDrawer v-if="auth.isAuthenticated" />
  <AdminShell v-if="adminPage"><slot /></AdminShell>
  <SellerShell v-else-if="sellerPage"><slot /></SellerShell>
  <main
    v-else
    class="container py-5"
    :class="{ 'buyer-shell': buyerPage, 'account-shell': accountPage }"
  >
    <AccountNav v-if="accountPage" /><slot />
  </main>
  <SiteFooter v-if="!sellerPage && !adminPage" />
</template>

<style scoped>
.marketplace-header {
  position: sticky;
  top: 0;
  z-index: var(--layer-header);
  background: var(--band);
  transition: box-shadow var(--transition-fast);
}
.marketplace-header.is-scrolled {
  box-shadow: 0 2px 10px rgb(21 22 26 / 12%);
}
.marketplace-nav {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding-block: 0.75rem;
}
.marketplace-menu-toggle {
  flex: none;
  width: 44px;
  min-width: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  color: var(--band-ink);
  background: var(--surface);
}
.marketplace-menu-toggle:hover {
  color: var(--band-ink);
  background: var(--surface-2);
}
.marketplace-brand {
  flex: none;
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 0.9rem;
  text-decoration: none;
  border-radius: var(--radius-pill);
  background: var(--surface);
}
.marketplace-mode {
  flex: none;
}
/* On the band the mode switcher is plain text with a chevron, whatever the mode. */
.marketplace-header .marketplace-nav .marketplace-mode :deep(.mode-switcher-button) {
  border-color: transparent;
  background: transparent;
  color: var(--band-ink);
  font-size: 0.9rem;
}
.marketplace-header .marketplace-nav .marketplace-mode :deep(.mode-switcher-button:hover),
.marketplace-header .marketplace-nav .marketplace-mode :deep(.mode-switcher-button[aria-expanded='true']) {
  background: rgb(255 255 255 / 45%);
}
.marketplace-directory {
  flex: 1;
  display: flex;
  justify-content: center;
  gap: 0.25rem;
  min-width: 0;
}
.marketplace-directory a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 0.9rem;
  border-radius: var(--radius-pill);
  color: rgb(21 22 26 / 55%);
  font-size: 0.95rem;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
  transition:
    background-color var(--transition-fast),
    color var(--transition-fast);
}
.marketplace-directory a:hover {
  background: rgb(255 255 255 / 45%);
  color: var(--band-ink);
}
.marketplace-directory a.is-current {
  color: var(--band-ink);
}
.marketplace-compact {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-width: 0;
}
.marketplace-search {
  position: relative;
  flex: 1;
  min-width: 0;
}
.marketplace-search :deep(input) {
  min-height: 44px;
  padding: 0.5rem 3rem 0.5rem 1rem;
  border-radius: var(--radius-pill);
  font-size: 0.9rem;
  background: var(--surface);
  border: 1px solid transparent;
}
.marketplace-search :deep(input):focus {
  background: var(--surface);
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.16);
}
.marketplace-search button {
  position: absolute;
  right: 0;
  top: 0;
  width: 44px;
  height: 44px;
  min-height: 44px;
  padding: 0;
  border: 0;
  color: var(--text);
}
.marketplace-search button:hover {
  color: var(--primary);
}
.marketplace-area {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 44px;
  max-width: 12rem;
  padding: 0 0.9rem;
  border: 1px solid transparent;
  border-radius: var(--radius-pill);
  background: var(--surface);
  color: var(--text);
  font-size: 0.85rem;
  font-weight: 600;
  transition: border-color var(--transition-fast);
}
.marketplace-area span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.marketplace-area:hover {
  border-color: var(--primary);
}
.marketplace-links {
  flex: none;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-left: auto;
  font-size: 0.85rem;
}
.marketplace-links a {
  color: var(--text);
  text-decoration: none;
  font-weight: 600;
}
.marketplace-links a:hover,
.marketplace-links a.router-link-active {
  color: var(--band-ink);
}
/* Text links on the band are white pills. */
.marketplace-links > a:not(.marketplace-icon-link) {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 1rem;
  border-radius: var(--radius-pill);
  background: var(--surface);
  white-space: nowrap;
  transition: background-color var(--transition-fast);
}
.marketplace-links > a:not(.marketplace-icon-link):hover {
  background: var(--surface-2);
}
.marketplace-sell {
  white-space: nowrap;
  --bs-btn-color: #fff;
  --bs-btn-bg: var(--band-ink);
  --bs-btn-border-color: var(--band-ink);
  --bs-btn-hover-color: #fff;
  --bs-btn-hover-bg: #000;
  --bs-btn-hover-border-color: #000;
  --bs-btn-active-color: #fff;
  --bs-btn-active-bg: #000;
  --bs-btn-active-border-color: #000;
  --bs-btn-disabled-bg: var(--band-ink);
  --bs-btn-disabled-border-color: var(--band-ink);
  min-height: var(--control-height);
  border-radius: var(--radius-pill);
  font-size: 0.85rem;
  font-weight: 700;
  padding: 0.6rem 1.1rem;
}
.marketplace-avatar {
  display: inline-grid;
  place-items: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  color: var(--accent-ink);
  background: var(--accent);
  font-weight: 800;
  font-size: 0.72rem;
}
.marketplace-account {
  position: relative;
}
.marketplace-account summary {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 44px;
  cursor: pointer;
  list-style: none;
  border-radius: var(--radius-pill);
  padding: 0.25rem 0.6rem 0.25rem 0.25rem;
  border: 0;
  background: var(--surface);
}
.marketplace-account summary:hover {
  background: var(--surface-2);
}
.marketplace-account summary::-webkit-details-marker {
  display: none;
}
.marketplace-account-name {
  display: none;
  max-width: 7rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-weight: 600;
}
.marketplace-account-menu {
  position: absolute;
  z-index: 1;
  right: 0;
  top: calc(100% + 0.6rem);
  width: min(260px, calc(100vw - 2rem));
  max-height: min(65vh, 480px);
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  padding: 0.5rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-dialog);
}
.marketplace-account-menu :deep(a) {
  display: flex;
  align-items: center;
  min-height: 44px;
  padding: 0.5rem 0.75rem;
  color: var(--text);
  text-decoration: none;
  border-radius: var(--radius-sm);
}
.marketplace-account-menu :deep(a:hover) {
  background: var(--surface-2);
  color: var(--primary);
}
.marketplace-menu-button {
  display: flex;
  align-items: center;
  min-height: 44px;
  padding: 0.5rem 0.75rem;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text);
  font: inherit;
  text-align: left;
}
.marketplace-menu-button:hover {
  background: var(--surface-2);
  color: var(--primary);
}
/* Icon actions: one 44px hit area each, badge on the corner. */
.marketplace-icon-link,
.marketplace-links :deep(.chat-launcher),
.marketplace-links :deep(.cart-icon-link),
.marketplace-links :deep(.notification-bell-button) {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  min-width: 44px;
  min-height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--surface);
  color: var(--band-ink);
}
.marketplace-icon-link:hover,
.marketplace-links :deep(.chat-launcher:hover),
.marketplace-links :deep(.cart-icon-link:hover),
.marketplace-links :deep(.notification-bell-button:hover) {
  background: var(--surface-2);
  color: var(--band-ink);
}
.marketplace-links :deep(.chat-launcher .badge),
.marketplace-links :deep(.cart-icon-link .badge),
.marketplace-links :deep(.notification-badge) {
  position: absolute;
  top: 2px;
  right: 0;
  min-width: 18px;
  margin: 0 !important;
  padding: 2px 5px;
  border: 2px solid var(--surface);
  border-radius: var(--radius-pill);
  background: var(--danger) !important;
  color: #fff !important;
  font-family: var(--font-money);
  font-size: 0.62rem;
  font-weight: 700;
  line-height: 1.2;
}
@media (min-width: 1400px) {
  .marketplace-account-name {
    display: inline;
  }
}
/* Below a wide desktop the mode switcher already carries this link; the search needs the room. */
@media (max-width: 1499px) {
  .marketplace-links > a.marketplace-manage {
    display: none;
  }
}
@media (max-width: 575px) {
  .marketplace-links > a.marketplace-admin-entry {
    display: none;
  }
}
/* Tablets and phones: the search (or the page links) drops to a second row. */
@media (max-width: 991px) {
  .marketplace-nav {
    flex-wrap: wrap;
  }
  .marketplace-directory,
  .marketplace-compact {
    order: 10;
    flex: 1 0 100%;
  }
  .marketplace-directory {
    justify-content: flex-start;
    overflow-x: auto;
  }
  .marketplace-icon-link {
    display: none;
  }
}
@media (max-width: 575px) {
  .marketplace-nav {
    gap: 0.4rem;
  }
  .marketplace-mode {
    order: 9;
    flex: 1 0 100%;
  }
  .marketplace-brand :deep(.brand-logo-word) {
    display: none;
  }
  .marketplace-sell {
    padding-inline: 0.7rem;
    font-size: 0.8rem;
  }
  .marketplace-links :deep(.cart-icon-link) {
    display: none;
  }
  .marketplace-area {
    max-width: 8.5rem;
  }
}
</style>

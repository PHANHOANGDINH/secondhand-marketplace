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
import ThemeChoices from '../components/ui/ThemeChoices.vue'
import ThemeToggle from '../components/ui/ThemeToggle.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import AdminShell from '../components/admin/AdminShell.vue'
import SellerShell from '../components/seller/SellerShell.vue'
import AccountNav from '../components/account/AccountNav.vue'
import ModeSwitcher from '../components/layout/ModeSwitcher.vue'
import SiteFooter from '../components/layout/SiteFooter.vue'
import MobileTabBar from '../components/layout/MobileTabBar.vue'
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
  { title: 'Tìm kiếm', icon: 'search', text: 'Gõ tên món đồ, danh mục hoặc gian hàng vào ô tìm kiếm để xem gợi ý, và chọn khu vực gần bạn.' },
  { title: 'Đăng tin', icon: 'plus', text: 'Nút "Đăng tin" mở biểu mẫu đăng bán nhanh ngay tại trang bạn đang xem.' },
  { title: 'Đấu giá', icon: 'gavel', text: 'Vào "Đấu giá" để theo dõi các phiên đang diễn ra và ra giá trực tiếp.' },
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
// Sign-in, sign-up and the password pages stand on their own: no pinned menu, a way home inside the page.
const authPage = computed(() =>
  ['/login', '/register', '/forgot-password', '/reset-password'].includes(route?.path),
)
// The home page has its own large search box; every other page gets the compact one in the header.
const homePage = computed(() => (route?.path || '/') === '/')
// Personal pages in the account menu: [path, label, icon]. Selling and admin shortcuts come from the
// mode switcher above them, so each page is offered once.
const accountLinks = [
  ['/orders', 'Đơn mua', 'cart'],
  ['/wishlist', 'Sản phẩm yêu thích', 'heart'],
  ['/account/profile', 'Hồ sơ', 'edit'],
  ['/account/addresses', 'Địa chỉ', 'pin'],
  ['/reports', 'Báo cáo của tôi', 'flag'],
  ['/seller/shop', 'Gian hàng của tôi', 'store'],
  ['/account', 'Tài khoản', 'user'],
]
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
    v-if="!sellerPage && !adminPage && !authPage"
    class="marketplace-header"
    :class="{ 'is-scrolled': scrolled, 'is-home': homePage }"
    @keydown.esc="closeNavigation"
  >
    <!-- One row: menu, logo, search (area inside it), icons, "Đăng tin", account. Nothing wraps on a desktop. -->
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
          <AppIcon name="search" class="marketplace-search-icon" :size="18" />
          <ProductSearch
            id="header-product-search"
            v-model="searchKeyword"
            @submit="searchProducts"
          />
          <button
            type="button"
            class="marketplace-area"
            data-area-open
            :aria-label="`Chọn khu vực, đang chọn ${areaLabel}`"
            @click="areaPickerOpen = true"
          >
            <AppIcon name="pin" :size="16" /><span>{{ areaCode ? areaLabel : 'Chọn khu vực' }}</span
            ><AppIcon name="chevronDown" :size="14" />
          </button>
          <button class="btn marketplace-search-submit" type="submit" aria-label="Tìm sản phẩm">
            <AppIcon name="search" :size="18" />
          </button>
        </form>
      </div>
      <div class="marketplace-links">
        <ThemeToggle v-if="!auth.isAuthenticated" class="marketplace-theme" />
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
              <div class="marketplace-account-head">
                <span class="marketplace-avatar" aria-hidden="true">{{ initials }}</span>
                <div>
                  <strong>{{ auth.user?.name || 'Tài khoản' }}</strong>
                  <small>{{ auth.user?.email }}</small>
                </div>
              </div>
              <ModeSwitcher inline class="marketplace-account-section" />
              <nav class="marketplace-account-links" aria-label="Tài khoản">
                <RouterLink v-for="[path, label, icon] in accountLinks" :key="path" :to="path"
                  ><AppIcon :name="icon" :size="18" />{{ label }}</RouterLink
                >
              </nav>
              <ThemeChoices />
              <div class="marketplace-account-foot">
                <button type="button" class="marketplace-menu-button" @click="replayGuides">
                  <AppIcon name="refresh" :size="18" />Xem lại hướng dẫn
                </button>
                <LogoutButton />
              </div>
            </div>
          </details>
        </template>
        <template v-else-if="auth.initialized"
          ><LogoutButton v-if="auth.sessionBlocked" /><RouterLink
            to="/login"
            class="marketplace-login"
            >Đăng nhập</RouterLink
          ><RouterLink to="/register" class="marketplace-register">Đăng ký</RouterLink></template
        >
      </div>
    </nav>
  </header>
  <NavDrawer v-if="drawerOpen" @dismiss="drawerOpen = false" />
  <!-- One dialog at a time: the area question waits until the posting dialog is closed. -->
  <AreaPickerDialog
    v-if="areaPickerOpen && !quickOpen"
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
    class="container marketplace-main"
    :class="{ 'buyer-shell': buyerPage, 'account-shell': accountPage, 'has-tabbar': !authPage }"
  >
    <AccountNav v-if="accountPage" /><slot />
  </main>
  <SiteFooter v-if="!sellerPage && !adminPage" :class="{ 'has-tabbar': !authPage }" />
  <MobileTabBar v-if="!sellerPage && !adminPage && !authPage" @post="openQuickListing" />
</template>

<style scoped>
.marketplace-header {
  position: sticky;
  top: 0;
  z-index: var(--layer-header);
  border-bottom: 1px solid var(--border);
  background: var(--band);
  transition: box-shadow var(--transition-fast);
}
.marketplace-header.is-scrolled {
  box-shadow: 0 4px 16px rgb(var(--shadow-rgb) / 8%);
}
.marketplace-nav {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 68px;
  padding-block: 0.6rem;
}
.marketplace-menu-toggle {
  flex: none;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--band-ink);
}
.marketplace-menu-toggle:hover {
  background: var(--surface-2);
}
.marketplace-brand {
  flex: none;
  display: inline-flex;
  align-items: center;
  margin-right: 0.5rem;
  color: var(--band-ink);
  text-decoration: none;
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
  min-height: 40px;
  padding: 0 0.9rem;
  border-radius: var(--radius-pill);
  color: var(--band-ink-muted);
  font-size: 0.92rem;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
  transition:
    background-color var(--transition-fast),
    color var(--transition-fast);
}
.marketplace-directory a:hover {
  background: var(--surface-2);
  color: var(--band-ink);
}
.marketplace-directory a.is-current {
  color: var(--band-ink);
  background: var(--surface-2);
}
/* The search takes every pixel left between the logo and the icons, and never less than it needs. */
.marketplace-compact {
  flex: 1 1 auto;
  min-width: 0;
}
.marketplace-search {
  position: relative;
  display: flex;
  align-items: center;
  min-width: 0;
  height: 46px;
  padding: 0 0.3rem 0 0.9rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--surface-2);
  transition:
    border-color var(--transition-fast),
    background-color var(--transition-fast);
}
.marketplace-search:focus-within {
  border-color: var(--primary);
  background: var(--surface);
}
.marketplace-search-icon {
  flex: none;
  color: var(--muted);
}
.marketplace-search :deep(.product-search) {
  flex: 1 1 auto;
  min-width: 0;
}
.marketplace-search :deep(input) {
  height: 44px;
  border: 0;
  background: transparent;
  box-shadow: none;
  padding-inline: 0.6rem;
  font-size: 0.92rem;
}
.marketplace-search :deep(input):focus {
  box-shadow: none;
}
.marketplace-area {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  max-width: 11rem;
  height: 34px;
  margin-right: 0.3rem;
  padding: 0 0.7rem;
  border: 0;
  border-left: 1px solid var(--border);
  border-radius: 0;
  background: transparent;
  color: var(--text);
  font-size: 0.85rem;
  font-weight: 600;
}
.marketplace-area span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.marketplace-area:hover {
  color: var(--primary);
}
.marketplace-search-submit {
  flex: none;
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--primary);
  color: var(--on-primary);
}
.marketplace-search-submit:hover {
  background: var(--primary-hover);
  color: var(--on-primary);
}
.marketplace-links {
  flex: none;
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-left: auto;
  font-size: 0.88rem;
}
.marketplace-compact + .marketplace-links {
  margin-left: 0.5rem;
}
.marketplace-links a {
  color: var(--text);
  text-decoration: none;
}
.marketplace-links > a:not(.marketplace-icon-link) {
  display: inline-flex;
  align-items: center;
  min-height: 40px;
  padding: 0 0.8rem;
  border-radius: var(--radius-pill);
  font-weight: 600;
  white-space: nowrap;
  transition: background-color var(--transition-fast);
}
.marketplace-links > a:not(.marketplace-icon-link):hover,
.marketplace-links > a.router-link-active {
  background: var(--surface-2);
}
.marketplace-links > a.marketplace-register {
  border: 1px solid var(--border);
}
.marketplace-sell {
  flex: none;
  min-height: 42px;
  margin-left: 0.25rem;
  padding-inline: 1rem;
  border-radius: var(--radius-pill);
  font-weight: 700;
  white-space: nowrap;
}
.marketplace-avatar {
  display: inline-grid;
  place-items: center;
  flex: none;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--primary-soft);
  color: var(--primary-ink);
  font-size: 0.78rem;
  font-weight: 800;
}
.marketplace-account {
  position: relative;
}
.marketplace-account summary {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-height: 44px;
  padding: 0.2rem 0.5rem 0.2rem 0.2rem;
  border-radius: var(--radius-pill);
  cursor: pointer;
  list-style: none;
}
.marketplace-account summary:hover,
.marketplace-account[open] summary {
  background: var(--surface-2);
}
.marketplace-account summary::-webkit-details-marker {
  display: none;
}
.marketplace-account-name {
  display: none;
  max-width: 9rem;
  overflow: hidden;
  font-weight: 600;
  text-overflow: ellipsis;
  white-space: nowrap;
}
/* The account menu holds everything that is not used every minute: modes, account pages, theme. */
.marketplace-account-menu {
  position: absolute;
  top: calc(100% + 0.5rem);
  right: 0;
  z-index: 5;
  display: grid;
  gap: 0.5rem;
  width: min(21rem, calc(100vw - 1.5rem));
  max-height: calc(100vh - 6rem);
  overflow-y: auto;
  padding: 0.75rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-dialog);
}
.marketplace-account-head {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  padding: 0.25rem 0.25rem 0.6rem;
  border-bottom: 1px solid var(--border);
}
.marketplace-account-head strong,
.marketplace-account-head small {
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.marketplace-account-head small {
  color: var(--muted);
}
.marketplace-account-head .marketplace-avatar {
  width: 40px;
  height: 40px;
}
.marketplace-account-links {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0.15rem;
  padding-top: 0.5rem;
  border-top: 1px solid var(--border);
}
.marketplace-account-menu :deep(a) {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 38px;
  padding: 0 0.5rem;
  border-radius: var(--radius-sm);
  color: var(--text);
  font-size: 0.86rem;
  text-decoration: none;
}
.marketplace-account-menu :deep(a:hover) {
  background: var(--surface-2);
  color: var(--primary);
}
.marketplace-account-menu :deep(.theme-choices) {
  margin: 0;
  padding: 0.5rem 0 0;
  border-bottom: 0;
}
.marketplace-account-foot :deep(.btn) {
  min-height: 38px;
  font-size: 0.86rem;
}
.marketplace-account-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  justify-content: space-between;
  gap: 0.5rem;
  padding-top: 0.5rem;
  border-top: 1px solid var(--border);
}
.marketplace-menu-button {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  min-height: 38px;
  padding: 0 0.5rem;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--text);
  font-size: 0.86rem;
}
.marketplace-menu-button:hover {
  background: var(--surface-2);
}
.marketplace-icon-link,
.marketplace-links :deep(.chat-launcher),
.marketplace-links :deep(.cart-icon-link),
.marketplace-links :deep(.notification-bell-button) {
  position: relative;
  display: inline-grid;
  place-items: center;
  width: 42px;
  height: 42px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: transparent;
  color: var(--text);
}
.marketplace-icon-link:hover,
.marketplace-links :deep(.chat-launcher:hover),
.marketplace-links :deep(.cart-icon-link:hover),
.marketplace-links :deep(.notification-bell-button:hover) {
  background: var(--surface-2);
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
  color: var(--on-danger) !important;
  font-family: var(--font-money);
  font-size: 0.62rem;
  line-height: 1;
}
.marketplace-main {
  padding-block: 1.5rem 2.5rem;
}
@media (min-width: 1400px) {
  .marketplace-account-name {
    display: inline;
  }
}
@media (max-width: 1499px) {
  .marketplace-links > a.marketplace-manage {
    display: none;
  }
}
@media (max-width: 1199px) {
  .marketplace-links > a.marketplace-admin-entry,
  .marketplace-icon-link {
    display: none;
  }
}
/* Tablets: the search drops to its own full-width row under the logo row. */
@media (max-width: 991px) {
  .marketplace-nav {
    flex-wrap: wrap;
    row-gap: 0.5rem;
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
  .marketplace-compact + .marketplace-links {
    margin-left: auto;
  }
}
/* Phones: the bottom tab bar carries home, auctions, posting, messages and the account. */
@media (max-width: 767px) {
  .marketplace-nav {
    min-height: 0;
    gap: 0.25rem;
  }
  .marketplace-brand {
    margin-right: 0;
  }
  .marketplace-brand :deep(.brand-logo-word) {
    display: none;
  }
  .marketplace-sell,
  .marketplace-links :deep(.chat-launcher),
  .marketplace-links > a.marketplace-register {
    display: none;
  }
  .marketplace-area {
    max-width: 7.5rem;
  }
  .marketplace-area > svg:last-child {
    display: none;
  }
  .has-tabbar {
    padding-bottom: calc(5.5rem + env(safe-area-inset-bottom));
  }
}
</style>

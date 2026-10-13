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
import { useAuthStore } from '../stores/auth'
import LogoutButton from '../components/LogoutButton.vue'
import CartLink from '../components/CartLink.vue'
import ProductSearch from '../components/ProductSearch.vue'
import { listCategories } from '../api/category.api'
import { brand } from '../config/brand'
import FirstTimeGuide from '../components/onboarding/FirstTimeGuide.vue'
import { resetFirstTime } from '../composables/useFirstTime'
const searchKeyword = ref('')
const searchProducts = () =>
  router.push({ path: '/products', query: { q: searchKeyword.value.trim() } })
const auth = useAuthStore()
// The welcome tour opens on every page view of a signed-in user until it is finished or skipped
// (FirstTimeGuide keeps the per-user flag); "Xem lại hướng dẫn" clears the flags and reopens it.
const guideRun = ref(0)
const welcomeSteps = [
  { title: 'Tìm kiếm', text: 'Gõ tên món đồ, danh mục hoặc gian hàng vào ô tìm kiếm ở đầu trang để xem gợi ý.' },
  { title: 'Đăng bán nhanh', text: 'Nút "Đăng bán nhanh" mở biểu mẫu đăng tin ngay tại trang bạn đang xem.' },
  { title: 'Đấu giá', text: 'Vào "Sàn đấu giá" để theo dõi các phiên đang diễn ra và ra giá trực tiếp.' },
]
function replayGuides() {
  accountMenu.value?.removeAttribute('open')
  if (resetFirstTime(auth.user?.id)) guideRun.value += 1
}
// Top-level categories for the browse row; the row stays hidden if they cannot be loaded.
const categories = ref([])
provideMessaging()
provideNotifications()
const route = useRoute(),
  router = useRouter()
const OutbidToasts = defineAsyncComponent(() => import('../components/auction/OutbidToasts.vue'))
const QuickListingDialog = defineAsyncComponent(
  () => import('../components/seller/QuickListingDialog.vue'),
)
const menuOpen = ref(false),
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
  menuOpen.value = false
  accountMenu.value?.removeAttribute('open')
  menuToggle.value?.focus()
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
    menuOpen.value = false
    accountMenu.value?.removeAttribute('open')
  },
)
onMounted(() => {
  listCategories()
    .then((result) => {
      categories.value = Array.isArray(result?.data) ? result.data : []
    })
    .catch(() => {})
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
    :class="{ 'is-scrolled': scrolled }"
    @keydown.esc="closeNavigation"
  >
    <nav class="container marketplace-nav" aria-label="Điều hướng chính">
      <RouterLink to="/" class="marketplace-brand" :aria-label="`${brand.name} · Trang chủ`"
        ><BrandLogo
      /></RouterLink>
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
        class="btn btn-primary marketplace-sell"
        :disabled="auth.loading"
        @click="openQuickListing"
      >
        <AppIcon name="plus" :size="18" /> Đăng bán nhanh
      </button>
      <button
        ref="menuToggle"
        type="button"
        class="btn btn-outline-secondary marketplace-menu-toggle"
        :aria-expanded="menuOpen"
        aria-controls="marketplace-navigation"
        aria-label="Điều hướng sàn mua bán"
        @click="menuOpen = !menuOpen"
      >
        <AppIcon name="menu" />
      </button>
      <div id="marketplace-navigation" class="marketplace-links" :class="{ 'is-open': menuOpen }">
        <div class="marketplace-directory">
          <RouterLink to="/products">Sản phẩm</RouterLink
          ><RouterLink to="/shops">Gian hàng</RouterLink
          ><RouterLink to="/auctions">Sàn đấu giá</RouterLink>
          <RouterLink to="/?discovery=video#home-discovery">Video đánh giá</RouterLink>
        </div>
        <NotificationBell v-if="auth.isAuthenticated" />
        <template v-if="auth.isAuthenticated">
          <ChatLauncher compact />
          <CartLink icon-only />
          <RouterLink v-if="auth.isAdmin" to="/admin/dashboard" class="marketplace-admin-entry"
            >Quản trị</RouterLink
          >
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
              <RouterLink to="/seller/products">Sản phẩm của tôi</RouterLink>
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
                ><RouterLink to="/admin/shops">Kiểm duyệt gian hàng</RouterLink
                ><RouterLink to="/admin/products">Kiểm duyệt sản phẩm</RouterLink></template
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
  <nav
    v-if="!sellerPage && !adminPage && categories.length"
    class="marketplace-categories"
    aria-label="Danh mục"
  >
    <ul class="container">
      <li><RouterLink to="/products">Tất cả</RouterLink></li>
      <li v-for="category in categories" :key="category.id">
        <RouterLink :to="{ path: '/products', query: { category: category.id } }">{{
          category.name
        }}</RouterLink>
      </li>
    </ul>
  </nav>
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
</template>

<style scoped>
.marketplace-header {
  position: sticky;
  top: 0;
  z-index: var(--layer-header);
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}
.marketplace-header.is-scrolled {
  border-bottom-color: #d4d3cc;
}
.marketplace-nav {
  display: grid;
  grid-template-columns: auto minmax(180px, 1fr) auto auto;
  grid-template-areas: 'brand search sell actions';
  align-items: center;
  gap: 1rem;
  padding-block: 0.75rem;
}
.marketplace-brand {
  grid-area: brand;
  display: inline-flex;
  align-items: center;
  text-decoration: none;
  border-radius: var(--radius-sm);
}
/* Browse row under the header: scrolls sideways on narrow screens, scrolls away with the page. */
.marketplace-categories {
  background: var(--surface);
  border-bottom: 1px solid var(--border);
}
.marketplace-categories ul {
  display: flex;
  gap: 0.25rem;
  margin-block: 0;
  padding-block: 0.35rem;
  list-style: none;
  overflow-x: auto;
  scrollbar-width: none;
}
.marketplace-categories ul::-webkit-scrollbar {
  display: none;
}
.marketplace-categories a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 0.85rem;
  border-radius: var(--radius-pill);
  color: var(--text);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition:
    background-color var(--transition-fast),
    color var(--transition-fast);
}
.marketplace-categories a:hover {
  background: var(--surface-2);
  color: var(--primary);
}
.marketplace-categories a:focus-visible {
  outline: var(--focus-ring);
  outline-offset: -3px;
}
.marketplace-search {
  grid-area: search;
  position: relative;
  min-width: 0;
}
.marketplace-search :deep(input) {
  min-height: 48px;
  padding: 0.75rem 3.25rem 0.75rem 1rem;
  border-radius: var(--radius-md);
  font-size: 0.9rem;
  background: var(--surface-2);
  border: 1px solid transparent;
}
.marketplace-search :deep(input):focus {
  background: var(--surface);
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.16);
}
.marketplace-search button {
  position: absolute;
  right: 2px;
  top: 2px;
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
.marketplace-links {
  grid-area: actions;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}
.marketplace-links a {
  color: var(--text);
  text-decoration: none;
  font-weight: 600;
}
.marketplace-directory {
  display: flex;
  gap: 0.25rem;
  margin-right: 0.25rem;
}
.marketplace-directory a {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 0.6rem;
  border-radius: var(--radius-sm);
  font-size: 0.82rem;
  white-space: nowrap;
}
.marketplace-directory a:hover {
  background: var(--surface-2);
}
.marketplace-links a:hover,
.marketplace-links a.router-link-active {
  color: var(--primary);
}
.marketplace-sell {
  grid-area: sell;
  white-space: nowrap;
  min-height: var(--control-height);
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
  font-weight: 700;
  padding: 0.6rem 1rem;
}
.marketplace-admin-entry {
  display: inline-flex;
  align-items: center;
  min-height: 44px;
  padding: 0 0.6rem;
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
  padding: 0.25rem 0.5rem 0.25rem 0.25rem;
  border: 1px solid var(--border);
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
.marketplace-menu-toggle {
  display: none;
  grid-area: toggle;
  padding: 0;
  width: 44px;
  min-width: 44px;
  min-height: 44px;
}
/* Icon actions: one 44px hit area each, badge on the corner. */
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
  border: 1px solid transparent;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--text);
}
.marketplace-links :deep(.chat-launcher:hover),
.marketplace-links :deep(.cart-icon-link:hover),
.marketplace-links :deep(.notification-bell-button:hover) {
  background: var(--surface-2);
  color: var(--primary);
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
  background: var(--primary) !important;
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
@media (max-width: 1199px) {
  .marketplace-nav {
    grid-template-columns: 1fr auto auto;
    grid-template-areas: 'brand sell toggle' 'search search search' 'actions actions actions';
    gap: 0.6rem;
    padding-block: 0.6rem;
  }
  .marketplace-brand {
    justify-self: start;
  }
  .marketplace-menu-toggle {
    display: inline-flex;
  }
  .marketplace-links {
    display: none;
    flex-wrap: wrap;
    justify-content: flex-end;
    padding-top: 0.6rem;
    border-top: 1px solid var(--border);
  }
  .marketplace-links.is-open {
    display: flex;
  }
  .marketplace-directory {
    flex-wrap: wrap;
    margin-right: auto;
  }
  .marketplace-account-name {
    display: inline;
  }
}
@media (max-width: 575px) {
  .marketplace-nav {
    gap: 0.5rem;
  }
  .marketplace-brand :deep(.brand-logo-mark) {
    width: 34px;
    height: 34px;
  }
  .marketplace-brand :deep(.brand-logo-word) {
    font-size: 1.05rem;
  }
  .marketplace-sell {
    font-size: 0.78rem;
    padding-inline: 0.7rem;
  }
  .marketplace-account-name {
    display: none;
  }
  .marketplace-search :deep(input) {
    min-height: 44px;
  }
  .marketplace-search button {
    top: 0;
  }
}
</style>

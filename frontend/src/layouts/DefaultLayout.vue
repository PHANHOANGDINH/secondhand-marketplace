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
import AdminShell from '../components/admin/AdminShell.vue'
import SellerShell from '../components/seller/SellerShell.vue'
import AccountNav from '../components/account/AccountNav.vue'
import { useAuthStore } from '../stores/auth'
import LogoutButton from '../components/LogoutButton.vue'
import CartLink from '../components/CartLink.vue'
import ProductSearch from '../components/ProductSearch.vue'
const searchKeyword = ref('')
const searchProducts = () =>
  router.push({ path: '/products', query: { q: searchKeyword.value.trim() } })
const auth = useAuthStore()
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
      <RouterLink to="/" class="marketplace-brand"
        ><span class="marketplace-brand-mark" aria-hidden="true">↗</span
        ><span>Secondhand <small>Sàn mua bán</small></span></RouterLink
      >
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
          <svg
            aria-hidden="true"
            width="20"
            height="20"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
          >
            <circle cx="10" cy="10" r="6" />
            <path d="m15 15 5 5" />
          </svg>
        </button>
      </form>
      <button
        type="button"
        class="btn btn-primary marketplace-sell"
        :disabled="auth.loading"
        @click="openQuickListing"
      >
        <span aria-hidden="true">＋</span> Đăng bán nhanh
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
        <svg
          aria-hidden="true"
          width="20"
          height="20"
          viewBox="0 0 24 24"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
        >
          <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
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
              ><span aria-hidden="true">⌄</span>
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
  <QuickListingDialog v-if="quickOpen" @close="closeQuickListing" />
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
  transition:
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}
.marketplace-header.is-scrolled {
  background: rgb(255 255 255 / 98%);
  box-shadow: var(--shadow-hover);
}
.marketplace-nav {
  display: grid;
  grid-template-columns: auto minmax(180px, 1fr) auto auto;
  grid-template-areas: 'brand search sell actions';
  align-items: center;
  gap: 1rem;
  padding-block: 0.9rem;
}
.marketplace-brand {
  grid-area: brand;
  display: flex;
  align-items: center;
  gap: 0.65rem;
  text-decoration: none;
  color: var(--text);
  font-size: 1rem;
  font-weight: 750;
  letter-spacing: -0.03em;
  line-height: 1.15;
}
.marketplace-brand small {
  display: block;
  font-size: 0.6rem;
  font-weight: 500;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  margin-top: 0.25rem;
  color: var(--text-muted);
}
.marketplace-brand-mark {
  display: grid;
  place-items: center;
  width: 38px;
  height: 38px;
  border-radius: var(--radius-md);
  background: var(--primary);
  color: white;
  font-size: 1.35rem;
  font-weight: 400;
}
.marketplace-search {
  grid-area: search;
  position: relative;
  min-width: 0;
}
.marketplace-search :deep(input) {
  min-height: 48px;
  padding: 0.75rem 3rem 0.75rem 1rem;
  border-radius: var(--radius-lg);
  font-size: 0.85rem;
  background: var(--surface-muted);
  border: 1px solid var(--border);
}
.marketplace-search :deep(input):focus {
  background: var(--surface);
  border-color: var(--primary);
  box-shadow: 0 0 0 3px rgba(var(--bs-primary-rgb), 0.12);
}
.marketplace-search button {
  position: absolute;
  right: 6px;
  top: 6px;
  width: 36px;
  height: 36px;
  padding: 0;
  display: grid;
  place-items: center;
  color: var(--primary);
}
.marketplace-links {
  grid-area: actions;
  display: flex;
  align-items: center;
  gap: 0.7rem;
  font-size: 0.8rem;
}
.marketplace-links a {
  color: var(--text);
  text-decoration: none;
  font-weight: 550;
}
.marketplace-directory {
  display: flex;
  gap: 0.65rem;
}
.marketplace-directory a {
  font-size: 0.75rem;
}
.marketplace-links a:hover,
.marketplace-links a.router-link-active {
  color: var(--primary);
}
.marketplace-sell {
  grid-area: sell;
  white-space: nowrap;
  min-height: var(--control-height);
  border-radius: var(--radius-md);
  font-size: 0.8rem;
  font-weight: 650;
  padding: 0.65rem 0.8rem;
}
.marketplace-notification {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  color: var(--text-muted);
  opacity: 0.65;
}
.marketplace-avatar {
  display: inline-grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  color: var(--bs-primary-text-emphasis);
  background: var(--bs-primary-bg-subtle);
  font-weight: 700;
  font-size: 0.7rem;
}
.marketplace-account {
  position: relative;
}
.marketplace-account summary {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
  list-style: none;
  border-radius: var(--radius-md);
  padding: 0.25rem;
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
  padding: 0.6rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-dialog);
}
.marketplace-account-menu :deep(a) {
  padding: 0.6rem 0.75rem;
  color: var(--text);
  text-decoration: none;
  border-radius: var(--radius-sm);
}
.marketplace-account-menu :deep(a:hover) {
  background: var(--surface-muted);
  color: var(--primary);
}
.marketplace-menu-toggle {
  display: none;
  grid-area: toggle;
  padding: 0.5rem;
  min-width: 40px;
  min-height: 40px;
}
.marketplace-links :deep(.chat-launcher) {
  position: relative;
  padding: 0.4rem;
  min-height: 38px;
  min-width: 38px;
  border-color: transparent;
}
.marketplace-links :deep(.chat-launcher .badge) {
  position: absolute;
  top: -4px;
  right: -5px;
  font-size: 0.6rem;
}
.marketplace-links :deep(.cart-icon-link) {
  position: relative;
  display: inline-flex;
  align-items: center;
  padding: 0.4rem;
  min-width: 38px;
  min-height: 38px;
}
.marketplace-links :deep(.cart-icon-link .badge) {
  position: absolute;
  top: -4px;
  right: -5px;
  font-size: 0.6rem;
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
    padding-block: 0.75rem;
  }
  .marketplace-menu-toggle {
    display: inline-grid;
    place-items: center;
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
  .marketplace-brand {
    font-size: 0.87rem;
    gap: 0.4rem;
  }
  .marketplace-brand-mark {
    width: 30px;
    height: 30px;
  }
  .marketplace-sell {
    font-size: 0.72rem;
    padding-inline: 0.55rem;
  }
  .marketplace-account-name {
    display: none;
  }
  .marketplace-links {
    gap: 0.5rem;
  }
  .marketplace-search :deep(input) {
    min-height: 44px;
  }
  .marketplace-search button {
    top: 4px;
  }
}
@media (prefers-reduced-motion: reduce) {
  .marketplace-header {
    transition: none;
  }
}
</style>

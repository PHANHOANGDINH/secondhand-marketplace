<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppIcon from '../ui/AppIcon.vue'
import { useAuthStore } from '../../stores/auth'
import { useMessaging } from '../../composables/useMessaging'
// Phones only: the five things people do most, always one thumb away, so nobody scrolls back up to the header.
defineEmits(['post'])
const auth = useAuthStore(),
  route = useRoute()
const chat = useMessaging()
const unread = computed(() => chat?.unreadTotal?.value || 0)
const current = (path) => (path === '/' ? route.path === '/' : route.path.startsWith(path))
</script>
<template>
  <nav class="mobile-tabbar" aria-label="Điều hướng nhanh">
    <RouterLink
      to="/"
      class="mobile-tab"
      :class="{ 'is-current': current('/') }"
      :aria-current="current('/') ? 'page' : undefined"
      ><AppIcon name="home" :size="22" /><span>Trang chủ</span></RouterLink
    >
    <RouterLink to="/auctions" class="mobile-tab" :class="{ 'is-current': current('/auctions') }"
      ><AppIcon name="gavel" :size="22" /><span>Đấu giá</span></RouterLink
    >
    <button type="button" class="mobile-tab mobile-tab-post" data-tab-post @click="$emit('post')">
      <span class="mobile-tab-plus"><AppIcon name="plus" :size="22" /></span><span>Đăng tin</span>
    </button>
    <button
      v-if="auth.isAuthenticated && chat"
      type="button"
      class="mobile-tab"
      :aria-label="`Tin nhắn, ${unread} chưa đọc`"
      @click="chat.open"
    >
      <AppIcon name="message" :size="22" /><span aria-hidden="true">Tin nhắn</span
      ><span v-if="unread" class="mobile-tab-badge" aria-hidden="true">{{
        unread > 99 ? '99+' : unread
      }}</span>
    </button>
    <RouterLink
      v-else
      to="/products"
      class="mobile-tab"
      :class="{ 'is-current': current('/products') }"
      ><AppIcon name="search" :size="22" /><span>Tìm kiếm</span></RouterLink
    >
    <RouterLink
      :to="auth.isAuthenticated ? '/account' : '/login'"
      class="mobile-tab"
      :class="{ 'is-current': current('/account') || current('/login') }"
      ><AppIcon name="user" :size="22" /><span>{{
        auth.isAuthenticated ? 'Tài khoản' : 'Đăng nhập'
      }}</span></RouterLink
    >
  </nav>
</template>
<style scoped>
.mobile-tabbar {
  display: none;
}
@media (max-width: 767px) {
  .mobile-tabbar {
    position: fixed;
    inset: auto 0 0;
    z-index: var(--layer-header);
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    padding: 0.25rem 0.25rem calc(0.25rem + env(safe-area-inset-bottom));
    border-top: 1px solid var(--border);
    background: var(--surface);
    box-shadow: var(--shadow-bar);
  }
}
.mobile-tab {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.15rem;
  min-height: 52px;
  padding: 0;
  border: 0;
  border-radius: var(--radius-sm);
  background: transparent;
  color: var(--muted);
  font-size: 0.68rem;
  font-weight: 600;
  text-decoration: none;
}
.mobile-tab.is-current {
  color: var(--primary);
}
.mobile-tab:focus-visible {
  outline: var(--focus-ring);
  outline-offset: -2px;
}
.mobile-tab-post {
  color: var(--text);
}
.mobile-tab-plus {
  display: grid;
  place-items: center;
  width: 36px;
  height: 28px;
  border-radius: var(--radius-pill);
  background: var(--primary);
  color: var(--on-primary);
}
.mobile-tab-badge {
  position: absolute;
  top: 4px;
  left: calc(50% + 6px);
  min-width: 18px;
  padding: 0 4px;
  border-radius: var(--radius-pill);
  background: var(--danger);
  color: var(--on-danger);
  font-size: 0.62rem;
  line-height: 18px;
  text-align: center;
}
</style>

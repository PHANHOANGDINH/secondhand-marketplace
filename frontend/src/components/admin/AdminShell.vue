<script setup>
import NotificationBell from '../notifications/NotificationBell.vue'
import { ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import LogoutButton from '../LogoutButton.vue'
import ChatLauncher from '../chat/ChatLauncher.vue'
const route = useRoute(),
  auth = useAuthStore(),
  open = ref(false),
  toggle = ref(null)
const links = [
  ['/admin/dashboard', 'Tổng quan', '01'],
  ['/admin/users', 'Người dùng', '02'],
  ['/admin/shops', 'Gian hàng', '03'],
  ['/admin/products', 'Sản phẩm', '04'],
  ['/admin/categories', 'Danh mục', '05'],
  ['/admin/orders', 'Đơn hàng', '06'],
  ['/admin/reviews', 'Đánh giá', '07'],
  ['/admin/reports', 'Báo cáo vi phạm', '08'],
]
const active = (path) => route.path === path || route.path.startsWith(`${path}/`)
watch(
  () => route.fullPath,
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
  <div class="admin-shell" @keydown.esc="close">
    <header class="admin-topbar">
      <RouterLink class="admin-brand" to="/"
        >Secondhand <span>Không gian quản trị</span></RouterLink
      >
      <div class="admin-topbar-actions">
        <span class="admin-identity">Quản trị</span
        ><RouterLink to="/products">← Sàn mua bán</RouterLink
        ><RouterLink to="/account/profile" class="admin-user">{{
          auth.user?.name || 'Tài khoản'
        }}</RouterLink
        ><NotificationBell /><ChatLauncher /><LogoutButton />
      </div>
      <button
        ref="toggle"
        class="btn btn-outline-secondary admin-menu-toggle"
        :aria-expanded="open"
        aria-controls="admin-navigation"
        @click="open = !open"
      >
        {{ open ? 'Đóng điều hướng' : 'Điều hướng quản trị' }}
      </button>
    </header>
    <div class="admin-workspace">
      <aside id="admin-navigation" class="admin-sidebar" :class="{ 'is-open': open }">
        <p class="admin-sidebar-caption">QUẢN TRỊ SÀN MUA BÁN</p>
        <nav aria-label="Điều hướng quản trị">
          <RouterLink
            v-for="[path, label, number] in links"
            :key="path"
            :to="path"
            :class="{ active: active(path) }"
            :aria-current="active(path) ? 'page' : undefined"
            ><span aria-hidden="true">{{ number }}</span
            >{{ label }}</RouterLink
          >
        </nav>
        <div class="admin-sidebar-note">
          <strong>Không gian quản trị</strong>
          <p>Người dùng, danh mục và nội dung sàn mua bán.</p>
          <RouterLink to="/account">Tài khoản của tôi →</RouterLink>
        </div>
      </aside>
      <main class="admin-content"><slot /></main>
    </div>
  </div>
</template>

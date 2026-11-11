<script setup>
import { useAuthStore } from '../../stores/auth'
import { brand, brandLine } from '../../config/brand'
import BrandLogo from '../ui/BrandLogo.vue'
// Every link goes to a page that exists: a route of the app or one of the simple static pages.
const auth = useAuthStore()
</script>
<template>
  <footer class="site-footer">
    <div class="container site-footer-grid">
      <section aria-labelledby="footer-support">
        <h2 id="footer-support">Hỗ trợ</h2>
        <RouterLink to="/help">Trợ giúp</RouterLink>
        <RouterLink to="/safety">An toàn mua bán</RouterLink>
        <RouterLink to="/contact">Liên hệ</RouterLink>
      </section>
      <section aria-labelledby="footer-about">
        <h2 id="footer-about">Về chúng tôi</h2>
        <RouterLink to="/about">Giới thiệu</RouterLink>
        <RouterLink to="/terms">Quy chế hoạt động</RouterLink>
        <RouterLink to="/privacy">Chính sách bảo mật</RouterLink>
      </section>
      <section aria-labelledby="footer-account">
        <h2 id="footer-account">Tài khoản</h2>
        <template v-if="auth.isAuthenticated">
          <RouterLink to="/account">Tài khoản của tôi</RouterLink>
          <RouterLink to="/orders">Đơn mua</RouterLink>
          <RouterLink to="/wishlist">Sản phẩm yêu thích</RouterLink>
        </template>
        <template v-else-if="auth.initialized">
          <RouterLink to="/login">Đăng nhập</RouterLink>
          <RouterLink to="/register">Đăng ký</RouterLink>
        </template>
      </section>
      <section aria-labelledby="footer-links">
        <h2 id="footer-links">Liên kết</h2>
        <RouterLink to="/products">Tất cả tin đăng</RouterLink>
        <RouterLink to="/shops">Gian hàng</RouterLink>
        <RouterLink to="/auctions">Sàn đấu giá</RouterLink>
      </section>
    </div>
    <div class="container site-footer-bottom">
      <BrandLogo compact />
      <p>{{ brandLine }}. {{ brand.name }} là đồ án học tập, không phải sàn thương mại đang vận hành.</p>
    </div>
  </footer>
</template>
<style scoped>
.site-footer {
  margin-top: var(--space-12);
  padding-block: var(--space-10) var(--space-6);
  border-top: 1px solid var(--border);
  background: var(--surface);
}
.site-footer-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: var(--space-6);
}
.site-footer section {
  display: grid;
  align-content: start;
}
.site-footer h2 {
  margin-bottom: var(--space-2);
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0;
  text-transform: none;
}
.site-footer a {
  justify-self: start;
  display: inline-flex;
  align-items: center;
  min-height: 36px;
  color: var(--muted);
  font-size: 0.88rem;
  text-decoration: none;
}
.site-footer a:hover {
  color: var(--primary);
}
.site-footer a:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.site-footer-bottom {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-3) var(--space-5);
  margin-top: var(--space-8);
  padding-top: var(--space-5);
  border-top: 1px solid var(--border);
}
.site-footer-bottom p {
  margin: 0;
  color: var(--muted);
  font-size: 0.8rem;
}
@media (max-width: 767px) {
  .site-footer-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>

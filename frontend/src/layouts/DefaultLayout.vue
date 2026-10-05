<script setup>
import { useAuthStore } from '../stores/auth'
import LogoutButton from '../components/LogoutButton.vue'
import CartLink from '../components/CartLink.vue'
const auth = useAuthStore()
</script>
<template>
  <header class="border-bottom bg-light">
    <nav
      class="container py-3 d-flex flex-wrap gap-3 align-items-center"
      aria-label="Điều hướng chính"
    >
      <RouterLink to="/" class="fw-bold text-decoration-none me-auto"
        >Secondhand Marketplace</RouterLink
      >
      <RouterLink to="/products">Sản phẩm</RouterLink>
      <RouterLink to="/shops">Gian hàng</RouterLink>
      <template v-if="auth.isAuthenticated">
        <CartLink /><RouterLink to="/orders">Đơn mua</RouterLink
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
          <RouterLink v-if="auth.isAdmin" class="nav-link" to="/admin/users">Người dùng</RouterLink>
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
      </template>
      <template v-else-if="auth.initialized">
        <LogoutButton v-if="auth.sessionBlocked" />
        <RouterLink to="/login">Đăng nhập</RouterLink
        ><RouterLink to="/register">Đăng ký</RouterLink>
      </template>
    </nav>
  </header>
  <main class="container py-5"><slot /></main>
</template>

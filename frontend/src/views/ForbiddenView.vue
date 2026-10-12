<script setup>
import PageHeader from '../components/ui/PageHeader.vue'
</script>

<template>
  <section class="card card-body">
    <PageHeader
      title="403 — Không có quyền truy cập"
      description="Bạn không có quyền truy cập trang này."
    />
    <div class="d-flex flex-wrap gap-2">
      <RouterLink to="/" class="btn btn-primary">Về trang chủ</RouterLink>
      <RouterLink to="/account" class="btn btn-outline-secondary">Tài khoản của tôi</RouterLink>
    </div>
  </section>
</template>

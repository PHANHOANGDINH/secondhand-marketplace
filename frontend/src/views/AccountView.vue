<script setup>
import { useAuthStore } from '../stores/auth'
import LogoutButton from '../components/LogoutButton.vue'
const auth = useAuthStore()
</script>
<template>
  <section v-if="auth.user" class="card card-body">
    <h1 class="h3">Tài khoản của bạn</h1>
    <dl class="row mt-3">
      <dt class="col-sm-3">Họ tên</dt>
      <dd class="col-sm-9">{{ auth.user.name }}</dd>
      <dt class="col-sm-3">Email</dt>
      <dd class="col-sm-9">{{ auth.user.email }}</dd>
      <dt class="col-sm-3">Vai trò</dt>
      <dd class="col-sm-9">{{ auth.user.role }}</dd>
      <dt class="col-sm-3">Trạng thái</dt>
      <dd class="col-sm-9">{{ auth.user.status }}</dd>
    </dl>
    <LogoutButton />
  </section>
</template>

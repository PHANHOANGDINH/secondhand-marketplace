<script setup>
import { getRoleLabel } from '../utils/labels'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import { useAuthStore } from '../stores/auth'
import LogoutButton from '../components/LogoutButton.vue'
const auth = useAuthStore()
</script>
<template>
  <section v-if="auth.user" class="account-profile">
    <PageHeader
      title="Tài khoản của bạn"
      eyebrow="Tài khoản"
      description="Quản lý hồ sơ, địa chỉ và không gian bán hàng của bạn."
    />
    <div class="workspace-panel">
      <dl class="row mt-3">
        <dt class="col-sm-3">Họ tên</dt>
        <dd class="col-sm-9">{{ auth.user.name }}</dd>
        <dt class="col-sm-3">Thư điện tử</dt>
        <dd class="col-sm-9">{{ auth.user.email }}</dd>
        <dt class="col-sm-3">Vai trò</dt>
        <dd class="col-sm-9">{{ getRoleLabel(auth.user.role) }}</dd>
        <dt class="col-sm-3">Trạng thái</dt>
        <dd class="col-sm-9"><StatusBadge :status="auth.user.status" /></dd>
      </dl>
      <div class="account-actions">
        <RouterLink to="/account/profile" class="btn btn-primary">Chỉnh sửa hồ sơ</RouterLink
        ><RouterLink to="/seller/shop" class="btn btn-outline-primary">Gian hàng của tôi</RouterLink
        ><LogoutButton />
      </div>
    </div>
  </section>
</template>

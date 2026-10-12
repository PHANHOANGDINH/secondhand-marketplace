<script setup>
import PageHeader from '../components/ui/PageHeader.vue'
import { onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '../stores/auth'
import { getProfile, updateProfile } from '../api/profile.api'
import { useFeedback } from '../composables/useFeedback'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
const auth = useAuthStore()
const profile = ref(null)
const form = reactive({ name: '', phone: '' })
const { busy, error, errors, success, run } = useFeedback()
function fill(user) {
  profile.value = user
  form.name = user.name
  form.phone = user.phone || ''
}
const load = () => run(async () => fill(await getProfile()))
const save = () =>
  run(async () => {
    const user = await updateProfile({ name: form.name, phone: form.phone || null })
    fill(user)
    auth.user = user
  }, 'Đã cập nhật hồ sơ.')
onMounted(load)
</script>
<template>
  <section class="account-profile">
    <PageHeader
      title="Hồ sơ cá nhân"
      eyebrow="Tài khoản"
      description="Thông tin cá nhân để hành trình mua bán thuận tiện hơn."
    />
    <DomainFeedback :busy="busy" :error="error" :success="success" /><button
      v-if="!profile && !busy"
      class="btn btn-outline-secondary"
      @click="load"
    >
      Tải lại
    </button>
    <form v-if="profile" class="workspace-panel" @submit.prevent="save">
      <div class="workspace-panel-heading"><h2>Thông tin cá nhân</h2></div>
      <div class="account-readonly">
        <small>Thư điện tử đăng nhập</small><strong>{{ profile.email }}</strong>
      </div>
      <div class="row">
        <div class="col-md-6">
          <DomainField
            v-model="form.name"
            name="name"
            label="Họ tên"
            required
            :maxlength="100"
            :errors="errors.name"
            :disabled="busy"
          />
        </div>
        <div class="col-md-6">
          <DomainField
            v-model="form.phone"
            name="phone"
            label="Số điện thoại"
            type="tel"
            :maxlength="20"
            :errors="errors.phone"
            :disabled="busy"
          />
        </div>
      </div>
      <div class="account-actions">
        <button type="submit" class="btn btn-primary" :disabled="busy">Lưu hồ sơ</button
        ><button
          type="button"
          class="btn btn-outline-secondary"
          :disabled="busy"
          @click="fill(profile)"
        >
          Hủy thay đổi
        </button>
      </div>
    </form>
  </section>
</template>

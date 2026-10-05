<script setup>
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
  <section style="max-width: 640px">
    <h1 class="h3">Hồ sơ cá nhân</h1>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <button v-if="!profile && !busy" class="btn btn-outline-secondary" @click="load">
      Tải lại
    </button>
    <form v-if="profile" @submit.prevent="save">
      <p>
        Email: <strong>{{ profile.email }}</strong>
      </p>
      <DomainField
        v-model="form.name"
        name="name"
        label="Họ tên"
        required
        :maxlength="100"
        :errors="errors.name"
      />
      <DomainField
        v-model="form.phone"
        name="phone"
        label="Số điện thoại"
        type="tel"
        :maxlength="20"
        :errors="errors.phone"
      />
      <button type="submit" class="btn btn-primary" :disabled="busy">Lưu hồ sơ</button>
    </form>
  </section>
</template>

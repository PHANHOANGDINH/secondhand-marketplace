<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { authError } from '../api/auth.api'
const props = defineProps({ registration: Boolean })
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  remember: false,
})
const errors = ref({})
const message = ref('')
async function submit() {
  if (auth.loading) return
  errors.value = {}
  message.value = ''
  try {
    if (props.registration) {
      await auth.register({
        name: form.name,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      })
    } else {
      await auth.login({ email: form.email, password: form.password, remember: form.remember })
    }
    form.password = ''
    form.password_confirmation = ''
    const target = route.query.redirect
    await router.replace(
      typeof target === 'string' && /^\/(?!\/)/.test(target) && !target.includes('\\')
        ? target
        : { name: 'account' },
    )
  } catch (error) {
    errors.value = error.response?.data?.errors ?? {}
    message.value = authError(error)
    form.password = ''
    form.password_confirmation = ''
  }
}
</script>
<template>
  <form
    class="card card-body shadow-sm mx-auto auth-form"
    :aria-busy="auth.loading"
    @submit.prevent="submit"
  >
    <h1 class="h3 mb-4">{{ registration ? 'Tạo tài khoản' : 'Đăng nhập' }}</h1>
    <p v-if="message || auth.initializationError" class="alert alert-danger" role="alert">
      {{ message || auth.initializationError }}
    </p>
    <div v-if="registration" class="mb-3">
      <label for="auth-name" class="form-label">Họ tên</label>
      <input
        id="auth-name"
        v-model="form.name"
        class="form-control"
        :class="{ 'is-invalid': errors.name }"
        :aria-invalid="!!errors.name"
        aria-describedby="name-errors"
        name="name"
        autocomplete="name"
        required
        minlength="2"
        maxlength="100"
      />
      <div id="name-errors" class="invalid-feedback">
        <div v-for="error in errors.name" :key="error">{{ error }}</div>
      </div>
    </div>
    <div class="mb-3">
      <label for="auth-email" class="form-label">Email</label>
      <input
        id="auth-email"
        v-model="form.email"
        class="form-control"
        :class="{ 'is-invalid': errors.email }"
        :aria-invalid="!!errors.email"
        aria-describedby="email-errors"
        name="email"
        type="email"
        autocomplete="username"
        required
        maxlength="254"
      />
      <div id="email-errors" class="invalid-feedback">
        <div v-for="error in errors.email" :key="error">{{ error }}</div>
      </div>
    </div>
    <div class="mb-3">
      <label for="auth-password" class="form-label">Mật khẩu</label>
      <input
        id="auth-password"
        v-model="form.password"
        class="form-control"
        :class="{ 'is-invalid': errors.password }"
        :aria-invalid="!!errors.password"
        aria-describedby="password-help password-errors"
        name="password"
        type="password"
        :autocomplete="registration ? 'new-password' : 'current-password'"
        required
        maxlength="128"
      />
      <div v-if="registration" id="password-help" class="form-text">
        Tối thiểu 12 ký tự, có chữ và số; tối đa 72 byte.
      </div>
      <div id="password-errors" class="invalid-feedback">
        <div v-for="error in errors.password" :key="error">{{ error }}</div>
      </div>
    </div>
    <div v-if="registration" class="mb-3">
      <label for="auth-confirmation" class="form-label">Xác nhận mật khẩu</label>
      <input
        id="auth-confirmation"
        v-model="form.password_confirmation"
        class="form-control"
        :class="{ 'is-invalid': errors.password_confirmation }"
        :aria-invalid="!!errors.password_confirmation"
        aria-describedby="confirmation-errors"
        name="password_confirmation"
        type="password"
        autocomplete="new-password"
        required
        maxlength="128"
      />
      <div id="confirmation-errors" class="invalid-feedback">
        <div v-for="error in errors.password_confirmation" :key="error">{{ error }}</div>
      </div>
    </div>
    <div v-else class="form-check mb-3">
      <input id="auth-remember" v-model="form.remember" type="checkbox" class="form-check-input" />
      <label for="auth-remember" class="form-check-label">Ghi nhớ đăng nhập</label>
    </div>
    <button class="btn btn-primary" type="submit" :disabled="auth.loading">
      {{ auth.loading ? 'Đang xử lý…' : registration ? 'Đăng ký' : 'Đăng nhập' }}
    </button>
    <RouterLink
      class="mt-3 text-center"
      :to="{
        path: registration ? '/login' : '/register',
        query: route.query.redirect ? { redirect: route.query.redirect } : {},
      }"
      >{{ registration ? 'Đã có tài khoản? Đăng nhập' : 'Chưa có tài khoản? Đăng ký' }}</RouterLink
    >
  </form>
</template>
<style scoped>
.auth-form {
  max-width: 460px;
}
</style>

<script setup>
import { localizeValidationErrors } from '../utils/validationLabels'

import PageHeader from './ui/PageHeader.vue'
import AppIcon from './ui/AppIcon.vue'
import AuthShell from './auth/AuthShell.vue'
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
const showPassword = ref(false)
const shell = ref(null),
  card = ref(null)
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
    const defaultDestination = props.registration
      ? 'account'
      : auth.isAdmin
        ? 'admin-dashboard'
        : 'home'
    await router.replace(
      typeof target === 'string' && /^\/(?!\/)/.test(target) && !target.includes('\\')
        ? target
        : { name: defaultDestination },
    )
  } catch (error) {
    errors.value = localizeValidationErrors(error.response?.data?.errors)
    message.value = authError(error)
    form.password = ''
    form.password_confirmation = ''
    shell.value?.shake(card.value)
  }
}
</script>
<template>
  <AuthShell ref="shell">
    <form
      ref="card"
      class="card card-body shadow-sm auth-form"
      :aria-busy="auth.loading"
      @submit.prevent="submit"
    >
      <PageHeader
        :title="registration ? 'Tạo tài khoản' : 'Đăng nhập'"
        :description="
          registration
            ? 'Bắt đầu hành trình mua bán đồ cũ của bạn.'
            : 'Chào mừng bạn trở lại sàn mua bán.'
        "
      />
      <p v-if="message || auth.initializationError" class="alert alert-danger" role="alert">
        {{ message || auth.initializationError }}
      </p>
      <div v-if="registration" class="mb-3 auth-field">
        <label for="auth-name" class="form-label"
          >Họ tên<span class="ui-required" aria-hidden="true"> *</span></label
        >
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
      <div class="mb-3 auth-field">
        <label for="auth-email" class="form-label"
          >Thư điện tử<span class="ui-required" aria-hidden="true"> *</span></label
        >
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
      <div class="mb-3 auth-field">
        <label for="auth-password" class="form-label"
          >Mật khẩu<span class="ui-required" aria-hidden="true"> *</span></label
        >
        <div class="auth-password">
          <input
            id="auth-password"
            v-model="form.password"
            class="form-control"
            :class="{ 'is-invalid': errors.password }"
            :aria-invalid="!!errors.password"
            aria-describedby="password-help password-errors"
            name="password"
            :type="showPassword ? 'text' : 'password'"
            :autocomplete="registration ? 'new-password' : 'current-password'"
            required
            maxlength="128"
          /><button
            type="button"
            class="auth-password-toggle"
            :aria-pressed="showPassword"
            :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            :title="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            @click="showPassword = !showPassword"
          >
            <AppIcon :name="showPassword ? 'eyeOff' : 'eye'" :size="18" />
          </button>
        </div>
        <div v-if="registration" id="password-help" class="form-text">
          Tối thiểu 12 ký tự, có chữ và số; tối đa 72 byte.
        </div>
        <div id="password-errors" class="invalid-feedback">
          <div v-for="error in errors.password" :key="error">{{ error }}</div>
        </div>
      </div>
      <div v-if="registration" class="mb-3 auth-field">
        <label for="auth-confirmation" class="form-label"
          >Xác nhận mật khẩu<span class="ui-required" aria-hidden="true"> *</span></label
        >
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
      <div v-else class="form-check mb-3 auth-field">
        <input
          id="auth-remember"
          v-model="form.remember"
          type="checkbox"
          class="form-check-input"
        />
        <label for="auth-remember" class="form-check-label">Ghi nhớ đăng nhập</label>
      </div>
      <RouterLink v-if="!registration" class="auth-forgot" to="/forgot-password"
        ><AppIcon name="lock" :size="15" />Quên mật khẩu?</RouterLink
      >
      <button class="btn btn-primary btn-icon auth-submit" type="submit" :disabled="auth.loading">
        <AppIcon :name="registration ? 'user' : 'arrowRight'" :size="18" />{{
          auth.loading ? 'Đang xử lý…' : registration ? 'Đăng ký' : 'Đăng nhập'
        }}
      </button>
      <p class="auth-divider" aria-hidden="true"><span>hoặc</span></p>
      <div class="auth-switch">
        <span class="auth-switch-question">{{
          registration ? 'Đã có tài khoản?' : 'Chưa có tài khoản?'
        }}</span>
        <RouterLink
          class="auth-switch-link"
          :to="{
            path: registration ? '/login' : '/register',
            query: route.query.redirect ? { redirect: route.query.redirect } : {},
          }"
          ><AppIcon :name="registration ? 'user' : 'plus'" :size="16" />{{
            registration ? 'Đăng nhập' : 'Tạo tài khoản mới'
          }}<AppIcon name="arrowRight" :size="16" class="auth-switch-arrow"
        /></RouterLink>
      </div>
    </form>
  </AuthShell>
</template>

<script setup>
import { localizeValidationErrors } from '../utils/validationLabels'

import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import { resetPassword, authError } from '../api/auth.api'
import AuthShell from '../components/auth/AuthShell.vue'
import AppIcon from '../components/ui/AppIcon.vue'
const route = useRoute()
const router = useRouter()
const form = reactive({
  email: typeof route.query.email === 'string' ? route.query.email : '',
  password: '',
  password_confirmation: '',
})
const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const validLink = computed(
  () =>
    !!token.value &&
    token.value.length <= 512 &&
    typeof route.query.email === 'string' &&
    !!route.query.email,
)
const busy = ref(false)
const error = ref('')
const success = ref('')
const errors = ref({})
const shell = ref(null),
  card = ref(null)
let referrerMeta
let previousReferrer
onMounted(() => {
  referrerMeta = document.querySelector('meta[name="referrer"]')
  previousReferrer = referrerMeta?.content
  if (!referrerMeta) {
    referrerMeta = document.createElement('meta')
    referrerMeta.name = 'referrer'
    document.head.append(referrerMeta)
  }
  referrerMeta.content = 'no-referrer'
})
onUnmounted(() => {
  // Explicitly restore the browser policy: removing a meta tag does not reset it.
  if (referrerMeta) referrerMeta.content = previousReferrer ?? 'strict-origin-when-cross-origin'
})
async function submit() {
  if (busy.value || !validLink.value || success.value) return
  error.value = ''
  errors.value = {}
  if (form.password !== form.password_confirmation) {
    errors.value.password_confirmation = ['Mật khẩu xác nhận chưa khớp.']
    return
  }
  busy.value = true
  try {
    await resetPassword({ ...form, token: token.value })
    success.value = 'Đã đặt lại mật khẩu. Bạn có thể đăng nhập bằng mật khẩu mới.'
    form.password = form.password_confirmation = ''
    await router.replace({ name: 'reset-password' })
  } catch (failure) {
    errors.value = localizeValidationErrors(failure.response?.data?.errors)
    error.value = errors.value.token
      ? 'Liên kết không hợp lệ hoặc đã hết hạn. Vui lòng yêu cầu một liên kết mới.'
      : authError(failure)
    form.password = form.password_confirmation = ''
    shell.value?.shake(card.value)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <AuthShell ref="shell">
    <section ref="card" class="card card-body shadow-sm auth-form" :aria-busy="busy">
      <PageHeader
        title="Đặt lại mật khẩu"
        description="Chọn mật khẩu mới để tiếp tục hành trình của bạn."
      />
      <DomainFeedback :error="error" :success="success" />
      <template v-if="success">
        <RouterLink class="btn btn-primary btn-icon auth-submit" to="/login"
          ><AppIcon name="arrowRight" :size="18" />Đăng nhập</RouterLink
        >
      </template>
      <template v-else-if="!validLink">
        <p class="alert alert-warning" role="alert">
          Liên kết đặt lại mật khẩu bị thiếu hoặc không hợp lệ.
        </p>
        <RouterLink class="btn btn-primary btn-icon auth-submit" to="/forgot-password"
          ><AppIcon name="send" :size="18" />Yêu cầu liên kết mới</RouterLink
        >
      </template>
      <form v-else @submit.prevent="submit">
        <div class="mb-3 auth-field">
          <label for="reset-email" class="form-label"
            >Thư điện tử<span class="ui-required" aria-hidden="true"> *</span></label
          >
          <input
            id="reset-email"
            v-model="form.email"
            name="email"
            type="email"
            autocomplete="username"
            required
            maxlength="254"
            class="form-control"
            :class="{ 'is-invalid': errors.email }"
            :aria-invalid="!!errors.email"
            aria-describedby="reset-email-errors"
            :disabled="busy"
          />
          <div id="reset-email-errors" class="invalid-feedback">
            <div v-for="message in errors.email" :key="message">{{ message }}</div>
          </div>
        </div>
        <p id="reset-password-help" class="form-text">
          Tối thiểu 12 ký tự, có chữ và số; tối đa 72 byte.
        </p>
        <div
          v-for="field in [
            { name: 'password', label: 'Mật khẩu mới' },
            { name: 'password_confirmation', label: 'Xác nhận mật khẩu' },
          ]"
          :key="field.name"
          class="mb-3 auth-field"
        >
          <label :for="`reset-${field.name}`" class="form-label"
            >{{ field.label }}<span class="ui-required" aria-hidden="true"> *</span></label
          >
          <input
            :id="`reset-${field.name}`"
            v-model="form[field.name]"
            :name="field.name"
            type="password"
            autocomplete="new-password"
            required
            minlength="12"
            maxlength="128"
            class="form-control"
            :class="{ 'is-invalid': errors[field.name] }"
            :aria-invalid="!!errors[field.name]"
            :aria-describedby="`reset-password-help reset-${field.name}-errors`"
            :disabled="busy"
          />
          <div :id="`reset-${field.name}-errors`" class="invalid-feedback">
            <div v-for="message in errors[field.name]" :key="message">{{ message }}</div>
          </div>
        </div>
        <button class="btn btn-primary w-100 btn-icon auth-submit" type="submit" :disabled="busy">
          <AppIcon name="lock" :size="18" />{{ busy ? 'Đang lưu…' : 'Lưu mật khẩu mới' }}
        </button>
        <p class="auth-divider" aria-hidden="true"><span>hoặc</span></p>
        <div class="auth-switch">
          <span class="auth-switch-question">Liên kết đã hết hạn?</span>
          <RouterLink class="auth-switch-link" to="/forgot-password"
            ><AppIcon name="send" :size="16" />Yêu cầu liên kết mới<AppIcon
              name="arrowRight"
              :size="16"
              class="auth-switch-arrow"
          /></RouterLink>
        </div>
      </form>
    </section>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { localizeValidationErrors } from '../utils/validationLabels'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import AuthShell from '../components/auth/AuthShell.vue'
import AppIcon from '../components/ui/AppIcon.vue'
import { authError, resendVerification, verifyEmail, verifyEmailCode } from '../api/auth.api'
// Two ways in: from the link in the mail (it carries id, hash, expires, signature), or right after
// registering or a blocked sign-in (the address comes in the history state, never in the URL).
// Without a working link the user types the code from the same mail.
const route = useRoute()
const router = useRouter()
const link = computed(() => {
  const { id, hash, expires, signature } = route.query
  return [id, hash, expires, signature].every((part) => typeof part === 'string' && part)
    ? { id, hash, expires, signature }
    : null
})
// waiting: enter the code · checking: posting the link · verified · failed: the link was refused.
const state = ref(link.value ? 'checking' : 'waiting')
const email = ref(typeof window.history.state?.email === 'string' ? window.history.state.email : '')
const code = ref('')
const busy = ref(false)
const error = ref('')
const success = ref('')
const errors = ref({})
const titles = {
  waiting: 'Kiểm tra hộp thư của bạn',
  checking: 'Đang xác minh…',
  verified: 'Đã xác minh thư điện tử',
  failed: 'Liên kết không dùng được',
}
const descriptions = {
  waiting: 'Bấm nút trong thư chúng tôi vừa gửi, hoặc nhập mã 6 số trong thư vào đây. Không thấy thư thì xem cả mục thư rác.',
  checking: 'Vui lòng chờ trong giây lát.',
  verified: 'Tài khoản của bạn đã sẵn sàng. Bạn có thể đăng nhập ngay.',
  failed: 'Liên kết sai hoặc đã hết hạn. Hãy nhập mã 6 số trong thư, hoặc gửi lại mã mới.',
}
const clean = () => {
  error.value = success.value = ''
  errors.value = {}
}
onMounted(async () => {
  if (!link.value) return
  const params = link.value
  // The signature is not left in the address bar or the browser history.
  await router.replace({ name: 'verify-email' })
  try {
    await verifyEmail(params)
    state.value = 'verified'
  } catch (failure) {
    state.value = 'failed'
    if (failure.response?.data?.code !== 'EMAIL_VERIFICATION_LINK_INVALID') error.value = authError(failure)
  }
})
const onCode = (event) => {
  code.value = event.target.value.replace(/\D/g, '').slice(0, 6)
  event.target.value = code.value
}
async function submitCode() {
  if (busy.value) return
  busy.value = true
  clean()
  try {
    await verifyEmailCode({ email: email.value, code: code.value })
    state.value = 'verified'
  } catch (failure) {
    code.value = ''
    error.value =
      failure.response?.data?.code === 'EMAIL_VERIFICATION_CODE_INVALID'
        ? 'Mã không đúng hoặc đã hết hạn. Hãy thử lại hoặc gửi lại mã mới.'
        : authError(failure)
    errors.value = localizeValidationErrors(failure.response?.data?.errors)
  } finally {
    busy.value = false
  }
}
async function resend() {
  if (busy.value) return
  clean()
  if (!email.value.trim()) {
    errors.value = { email: ['Vui lòng nhập thư điện tử để nhận mã mới.'] }
    return
  }
  busy.value = true
  try {
    await resendVerification({ email: email.value })
    code.value = ''
    success.value = 'Nếu tài khoản này đang chờ xác minh, chúng tôi đã gửi thư mới với mã và liên kết mới. Mã cũ không còn dùng được.'
  } catch (failure) {
    error.value = authError(failure)
    errors.value = localizeValidationErrors(failure.response?.data?.errors)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <AuthShell>
    <form class="card card-body shadow-sm auth-form" :aria-busy="busy || state === 'checking'" @submit.prevent="submitCode">
      <PageHeader :title="titles[state]" :description="descriptions[state]" />
      <DomainFeedback :error="error" :success="success" />
      <RouterLink v-if="state === 'verified'" class="btn btn-primary btn-icon auth-submit" to="/login"
        ><AppIcon name="user" :size="18" />Đăng nhập</RouterLink
      >
      <template v-else-if="state !== 'checking'">
        <DomainField
          v-model="email"
          class="auth-field"
          name="email"
          label="Thư điện tử"
          type="email"
          required
          :maxlength="254"
          :disabled="busy"
          :errors="errors.email"
        />
        <div class="mb-3 auth-field">
          <label for="verification-code" class="form-label"
            >Mã xác minh<span class="ui-required" aria-hidden="true"> *</span></label
          >
          <input
            id="verification-code"
            name="code"
            class="form-control"
            :class="{ 'is-invalid': errors.code?.length }"
            type="text"
            inputmode="numeric"
            autocomplete="one-time-code"
            placeholder="6 số trong thư"
            required
            pattern="[0-9]{6}"
            maxlength="6"
            :value="code"
            :disabled="busy"
            :aria-invalid="errors.code?.length ? 'true' : undefined"
            :aria-describedby="errors.code?.length ? 'verification-code-errors' : undefined"
            @input="onCode"
          />
          <div v-if="errors.code?.length" id="verification-code-errors" class="invalid-feedback d-block">
            {{ errors.code[0] }}
          </div>
        </div>
        <button class="btn btn-primary btn-icon auth-submit" type="submit" :disabled="busy">
          <AppIcon name="check" :size="18" />{{ busy ? 'Đang xử lý…' : 'Xác minh' }}
        </button>
        <button class="btn btn-outline-secondary btn-icon mt-2" type="button" data-resend :disabled="busy" @click="resend">
          <AppIcon name="send" :size="18" />Gửi lại mã
        </button>
        <p class="auth-divider" aria-hidden="true"><span>hoặc</span></p>
        <div class="auth-switch">
          <span class="auth-switch-question">Đã xác minh rồi?</span>
          <RouterLink class="auth-switch-link" to="/login"
            ><AppIcon name="user" :size="16" />Quay lại đăng nhập<AppIcon
              name="arrowRight"
              :size="16"
              class="auth-switch-arrow"
          /></RouterLink>
        </div>
      </template>
    </form>
  </AuthShell>
</template>

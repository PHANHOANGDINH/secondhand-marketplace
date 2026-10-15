<script setup>
import { localizeValidationErrors } from '../utils/validationLabels'

import { ref } from 'vue'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import { forgotPassword, authError } from '../api/auth.api'
import AuthShell from '../components/auth/AuthShell.vue'
import AppIcon from '../components/ui/AppIcon.vue'
const email = ref('')
const busy = ref(false)
const error = ref('')
const success = ref('')
const errors = ref({})
const shell = ref(null),
  card = ref(null)
async function submit() {
  if (busy.value) return
  busy.value = true
  error.value = success.value = ''
  errors.value = {}
  try {
    await forgotPassword({ email: email.value })
    success.value =
      'Nếu địa chỉ thư điện tử khớp với một tài khoản, bạn sẽ nhận được hướng dẫn đặt lại mật khẩu. Hãy kiểm tra hộp thư và thư rác.'
  } catch (failure) {
    error.value = authError(failure)
    errors.value = localizeValidationErrors(failure.response?.data?.errors)
    shell.value?.shake(card.value)
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <AuthShell ref="shell">
    <form
      ref="card"
      class="card card-body shadow-sm auth-form"
      :aria-busy="busy"
      @submit.prevent="submit"
    >
      <PageHeader
        title="Quên mật khẩu?"
        description="Nhập địa chỉ thư điện tử của bạn để nhận hướng dẫn đặt lại mật khẩu."
      />
      <DomainFeedback :error="error" :success="success" />
      <DomainField
        class="auth-field"
        v-model="email"
        name="email"
        label="Thư điện tử"
        type="email"
        required
        :maxlength="254"
        :disabled="busy"
        :errors="errors.email"
      />
      <button class="btn btn-primary btn-icon auth-submit" type="submit" :disabled="busy">
        <AppIcon name="send" :size="18" />{{ busy ? 'Đang gửi…' : 'Gửi hướng dẫn' }}
      </button>
      <p class="auth-divider" aria-hidden="true"><span>hoặc</span></p>
      <div class="auth-switch">
        <span class="auth-switch-question">Nhớ ra mật khẩu rồi?</span>
        <RouterLink class="auth-switch-link" to="/login"
          ><AppIcon name="user" :size="16" />Quay lại đăng nhập<AppIcon
            name="arrowRight"
            :size="16"
            class="auth-switch-arrow"
        /></RouterLink>
      </div>
    </form>
  </AuthShell>
</template>

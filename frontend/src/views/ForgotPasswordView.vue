<script setup>
import { localizeValidationErrors } from '../utils/validationLabels'

import { ref } from 'vue'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import { forgotPassword, authError } from '../api/auth.api'
const email = ref('')
const busy = ref(false)
const error = ref('')
const success = ref('')
const errors = ref({})
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
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <form
    class="card card-body shadow-sm mx-auto auth-form"
    :aria-busy="busy"
    @submit.prevent="submit"
  >
    <PageHeader
      title="Quên mật khẩu?"
      description="Nhập địa chỉ thư điện tử của bạn để nhận hướng dẫn đặt lại mật khẩu."
    />
    <DomainFeedback :error="error" :success="success" />
    <DomainField
      v-model="email"
      name="email"
      label="Thư điện tử"
      type="email"
      required
      :maxlength="254"
      :disabled="busy"
      :errors="errors.email"
    />
    <button class="btn btn-primary" type="submit" :disabled="busy">
      {{ busy ? 'Đang gửi…' : 'Gửi hướng dẫn' }}
    </button>
    <RouterLink class="mt-3 text-center" to="/login">Quay lại đăng nhập</RouterLink>
  </form>
</template>
<style scoped>
.auth-form {
  max-width: 460px;
}
</style>

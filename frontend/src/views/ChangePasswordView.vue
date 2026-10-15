<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { changePassword, authError } from '../api/auth.api'
import { localizeValidationErrors } from '../utils/validationLabels'
import PageHeader from '../components/ui/PageHeader.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import AppIcon from '../components/ui/AppIcon.vue'
// Change the password. Right after signing in with a temporary password from "Quên mật khẩu" the
// current password is not asked for, and the rest of the site waits until a new one is set.
const auth = useAuthStore()
const route = useRoute()
const router = useRouter()
const forced = computed(() => !!auth.user?.must_change_password)
const form = reactive({ current_password: '', password: '', password_confirmation: '' })
const busy = ref(false),
  error = ref(''),
  success = ref(''),
  errors = ref({}),
  visible = ref(false)
const fields = computed(() => [
  ...(forced.value
    ? []
    : [{ name: 'current_password', label: 'Mật khẩu hiện tại', autocomplete: 'current-password' }]),
  { name: 'password', label: 'Mật khẩu mới', autocomplete: 'new-password' },
  { name: 'password_confirmation', label: 'Nhập lại mật khẩu mới', autocomplete: 'new-password' },
])
async function submit() {
  if (busy.value) return
  error.value = success.value = ''
  errors.value = {}
  if (form.password !== form.password_confirmation) {
    errors.value.password_confirmation = ['Mật khẩu xác nhận chưa khớp.']
    return
  }
  const wasForced = forced.value
  busy.value = true
  try {
    auth.user = await changePassword({
      ...form,
      ...(wasForced ? { current_password: undefined } : {}),
    })
    form.current_password = form.password = form.password_confirmation = ''
    success.value = 'Đã đổi mật khẩu. Lần sau hãy đăng nhập bằng mật khẩu mới.'
    const target = route.query.redirect
    if (wasForced)
      await router.replace(
        typeof target === 'string' && /^\/(?!\/)/.test(target) && !target.includes('\\')
          ? target
          : { name: 'home' },
      )
  } catch (failure) {
    errors.value = localizeValidationErrors(failure.response?.data?.errors)
    error.value = authError(failure)
    form.current_password = form.password = form.password_confirmation = ''
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <section class="change-password">
    <PageHeader
      title="Đổi mật khẩu"
      eyebrow="Tài khoản · Bảo mật"
      description="Mật khẩu mới tối thiểu 12 ký tự, có chữ và số; tối đa 72 byte."
    />
    <p v-if="forced" class="alert alert-warning change-password-forced" role="alert" data-forced>
      <AppIcon name="lock" :size="18" /> Bạn vừa đăng nhập bằng mật khẩu tạm thời. Hãy đặt mật khẩu
      mới ngay để tiếp tục dùng tài khoản.
    </p>
    <form class="card card-body change-password-card" :aria-busy="busy" @submit.prevent="submit">
      <DomainFeedback :error="error" :success="success" />
      <div v-for="field in fields" :key="field.name" class="mb-3">
        <label :for="`change-${field.name}`" class="form-label"
          >{{ field.label }}<span class="ui-required" aria-hidden="true"> *</span></label
        >
        <input
          :id="`change-${field.name}`"
          v-model="form[field.name]"
          :name="field.name"
          :type="visible ? 'text' : 'password'"
          :autocomplete="field.autocomplete"
          required
          maxlength="128"
          class="form-control"
          :class="{ 'is-invalid': errors[field.name] }"
          :aria-invalid="!!errors[field.name]"
          :aria-describedby="`change-${field.name}-errors`"
          :disabled="busy"
        />
        <div :id="`change-${field.name}-errors`" class="invalid-feedback">
          <div v-for="message in errors[field.name]" :key="message">{{ message }}</div>
        </div>
      </div>
      <div class="form-check mb-3">
        <input id="change-show" v-model="visible" type="checkbox" class="form-check-input" />
        <label for="change-show" class="form-check-label">Hiện mật khẩu</label>
      </div>
      <button class="btn btn-primary btn-icon" type="submit" :disabled="busy">
        <AppIcon name="lock" :size="18" />{{ busy ? 'Đang lưu…' : 'Lưu mật khẩu mới' }}
      </button>
    </form>
  </section>
</template>
<style scoped>
.change-password {
  max-width: 560px;
}
.change-password-forced {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}
.change-password-card {
  border-radius: var(--radius-lg);
}
</style>

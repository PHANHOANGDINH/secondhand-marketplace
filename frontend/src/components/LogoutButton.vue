<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { authError } from '../api/auth.api'
const auth = useAuthStore()
const router = useRouter()
const error = ref('')
async function logout() {
  error.value = ''
  try {
    await auth.logout()
    await router.replace({ name: 'login' })
  } catch (failure) {
    error.value = authError(failure)
  }
}
</script>
<template>
  <div>
    <button
      type="button"
      class="btn btn-outline-secondary"
      :disabled="auth.loading"
      @click="logout"
    >
      Đăng xuất
    </button>
    <p v-if="error" class="text-danger mt-2 mb-0" role="alert">{{ error }}</p>
  </div>
</template>

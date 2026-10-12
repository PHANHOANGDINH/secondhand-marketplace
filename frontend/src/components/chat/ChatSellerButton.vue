<script setup>
import { localizeValidationMessage } from '../../utils/validationLabels'
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { useMessaging } from '../../composables/useMessaging'
import { domainError } from '../../api/domain.api'
const props = defineProps({ product: { type: Object, required: true } })
const auth = useAuthStore(),
  route = useRoute(),
  router = useRouter(),
  chat = useMessaging()
const busy = ref(false),
  error = ref('')
async function start() {
  if (busy.value) return
  const destination = router.resolve({
    path: route.path,
    query: { ...route.query, chat: '1' },
    hash: route.hash,
  }).fullPath
  if (!auth.isAuthenticated) {
    await router.push({ name: 'login', query: { redirect: destination } })
    return
  }
  if (!chat) return
  busy.value = true
  error.value = ''
  if (route.query.chat === '1') {
    const query = { ...route.query }
    delete query.chat
    await router.replace({ path: route.path, query, hash: route.hash })
  }
  try {
    await chat.start(props.product.id)
  } catch (failure) {
    error.value = failure.response?.data?.errors?.product_id?.[0]
      ? localizeValidationMessage(failure.response.data.errors.product_id[0], 'product_id')
      : domainError(failure)
  } finally {
    busy.value = false
  }
}
watch(
  () => [auth.isAuthenticated, route.query.chat],
  () => {
    if (auth.isAuthenticated && route.query.chat === '1') void start()
  },
  { immediate: true },
)
</script>
<template>
  <div class="mt-3">
    <button
      type="button"
      class="btn btn-outline-primary"
      :disabled="busy || auth.loading"
      @click="start"
    >
      {{ busy ? 'Đang mở hội thoại…' : 'Nhắn người bán' }}
    </button>
    <p v-if="error" class="text-danger mt-2 mb-0" role="alert">{{ error }}</p>
  </div>
</template>

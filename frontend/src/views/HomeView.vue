<script setup>
import { onMounted, ref } from 'vue'
import api from '../api/axios'

const status = ref('checking')
async function checkHealth() {
  status.value = 'checking'
  try {
    const { data } = await api.get('/api/v1/health')
    status.value = data.status === 'ok' ? 'ok' : 'error'
  } catch {
    status.value = 'error'
  }
}
onMounted(checkHealth)
</script>

<template>
  <h1>Secondhand Marketplace</h1>
  <p>Mua và bán đồ đã qua sử dụng từ các gian hàng được kiểm duyệt.</p>
  <div class="d-flex flex-wrap gap-2 mb-4">
    <RouterLink to="/products" class="btn btn-primary">Xem sản phẩm</RouterLink>
    <RouterLink to="/shops" class="btn btn-outline-primary">Khám phá gian hàng</RouterLink>
  </div>
  <p role="status" aria-live="polite">
    {{
      status === 'checking'
        ? 'Đang kiểm tra kết nối…'
        : status === 'ok'
          ? 'Kết nối cửa hàng: hoạt động'
          : 'Chưa kết nối được cửa hàng.'
    }}
  </p>
  <button v-if="status === 'error'" class="btn btn-outline-primary" @click="checkHealth">
    Thử lại
  </button>
</template>

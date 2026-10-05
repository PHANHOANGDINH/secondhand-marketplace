<script setup>
defineProps({ busy: Boolean, error: String, success: String })
</script>
<template>
  <p v-if="busy" role="status" class="text-secondary">Đang xử lý…</p>
  <div v-if="error" role="alert" class="alert alert-danger">{{ error }}</div>
  <div v-if="success" role="status" class="alert alert-success">{{ success }}</div>
</template>

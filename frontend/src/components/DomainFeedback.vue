<script setup>
import SkeletonState from './ui/SkeletonState.vue'
defineProps({ busy: Boolean, error: String, success: String, loadingLayout: String })
</script>
<template>
  <SkeletonState v-if="busy && loadingLayout" :layout="loadingLayout" label="Đang xử lý…" />
  <p v-else-if="busy" role="status" class="text-secondary">Đang xử lý…</p>
  <div v-if="error" role="alert" class="alert alert-danger">{{ error }}</div>
  <div v-if="success" role="status" class="alert alert-success">{{ success }}</div>
</template>

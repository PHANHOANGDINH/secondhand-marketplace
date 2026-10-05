<script setup>
defineProps({ meta: { type: Object, default: null }, busy: Boolean })
defineEmits(['page'])
</script>
<template>
  <nav
    v-if="meta && meta.last_page > 1"
    class="d-flex gap-3 align-items-center my-3"
    aria-label="Phân trang"
  >
    <button
      class="btn btn-outline-secondary"
      :disabled="busy || meta.current_page <= 1"
      @click="$emit('page', meta.current_page - 1)"
    >
      Trước
    </button>
    <span>Trang {{ meta.current_page }} / {{ meta.last_page }}</span>
    <button
      class="btn btn-outline-secondary"
      :disabled="busy || meta.current_page >= meta.last_page"
      @click="$emit('page', meta.current_page + 1)"
    >
      Sau
    </button>
  </nav>
</template>

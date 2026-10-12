<script setup>
import { nextTick, ref } from 'vue'
defineProps({ disabled: Boolean })
const emit = defineEmits(['confirm'])
const confirming = ref(false),
  trigger = ref(null),
  cancel = ref(null)
async function show() {
  confirming.value = true
  await nextTick()
  cancel.value?.focus()
}
async function dismiss() {
  confirming.value = false
  await nextTick()
  trigger.value?.focus()
}
function confirm() {
  confirming.value = false
  emit('confirm')
}
</script>
<template>
  <div class="confirm-delete" @keydown.esc.stop="dismiss">
    <button
      v-if="!confirming"
      ref="trigger"
      type="button"
      class="btn btn-outline-danger btn-sm"
      :disabled="disabled"
      @click="show"
    >
      Xóa
    </button>
    <div v-else class="confirm-delete-panel" role="group" aria-label="Xác nhận xóa địa chỉ">
      <p>Xóa địa chỉ này? Thao tác này không thể hoàn tác.</p>
      <div class="d-flex gap-2 flex-wrap">
        <button
          ref="cancel"
          type="button"
          class="btn btn-outline-secondary btn-sm"
          :disabled="disabled"
          @click="dismiss"
        >
          Giữ lại</button
        ><button type="button" class="btn btn-danger btn-sm" :disabled="disabled" @click="confirm">
          Xác nhận xóa
        </button>
      </div>
    </div>
  </div>
</template>

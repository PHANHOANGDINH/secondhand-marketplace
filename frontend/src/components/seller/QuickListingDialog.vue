<script setup>
import { nextTick, ref } from 'vue'
import AccessibleDialog from '../ui/AccessibleDialog.vue'
import SellerProductEditor from './SellerProductEditor.vue'
const emit = defineEmits(['close'])
const editor = ref(null),
  dialog = ref(null),
  busy = ref(false),
  confirmation = ref(false),
  keepEditing = ref(null)
async function resume() {
  confirmation.value = false
  await nextTick()
  dialog.value?.focusClose()
}
async function dismiss() {
  if (busy.value) return
  if (confirmation.value) {
    confirmation.value = false
    await nextTick()
    dialog.value?.focusClose()
    return
  }
  if (editor.value?.hasUnsavedChanges) {
    confirmation.value = true
    await nextTick()
    keepEditing.value?.focus()
  } else emit('close')
}
</script>
<template>
  <AccessibleDialog
    id="quick-listing-dialog"
    ref="dialog"
    title="Đăng bán nhanh"
    description="Ảnh rõ ràng, thông tin trung thực. Lưu bản nháp rồi gửi duyệt khi sẵn sàng."
    :busy="busy"
    @dismiss="dismiss"
  >
    <SellerProductEditor ref="editor" embedded @busy-change="busy = $event" />
    <template v-if="confirmation" #footer
      ><div class="alert alert-warning mb-0" role="alert">
        <p>
          Bạn có thông tin, ảnh hoặc video đã chọn chưa lưu. Bản nháp đã lưu vẫn được giữ trong Sản
          phẩm của tôi.
        </p>
        <div class="d-flex flex-wrap gap-2">
          <button ref="keepEditing" class="btn btn-primary" @click="resume">
            Tiếp tục chỉnh sửa</button
          ><button class="btn btn-outline-danger" @click="emit('close')">
            Bỏ thay đổi chưa lưu
          </button>
        </div>
      </div></template
    >
  </AccessibleDialog>
</template>

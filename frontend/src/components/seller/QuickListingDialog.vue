<script setup>
import { nextTick, ref } from 'vue'
import AccessibleDialog from '../ui/AccessibleDialog.vue'
import SellerProductEditor from './SellerProductEditor.vue'
import FirstTimeGuide from '../onboarding/FirstTimeGuide.vue'
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
    description="Ảnh rõ ràng, thông tin trung thực. Lưu bản nháp rồi bấm Đăng bán: tin hiện ngay."
    :busy="busy"
    @dismiss="dismiss"
  >
    <FirstTimeGuide
      name="quick-listing"
      title="Lần đầu đăng bán"
      :steps="[
        { title: 'Ảnh:', text: 'ảnh vuông, đủ sáng hiển thị đẹp nhất.' },
        { title: 'Bản nháp:', text: 'lưu trước, bấm Đăng bán khi đã sẵn sàng.' },
        { title: 'Đăng ngay:', text: 'tin hiện công khai ngay, không phải chờ duyệt.' },
        {
          title: 'Báo cáo:',
          text: 'tin bị nhiều người báo cáo sẽ tạm ẩn chờ quản trị viên xem xét.',
        },
      ]"
    />
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

<script setup>
import { ref, watch } from 'vue'
import ReviewForm from './ReviewForm.vue'
const props = defineProps({ item: { type: Object, required: true } })
const emit = defineEmits(['saved'])
const editing = ref(false),
  saved = ref(false)
watch(
  () => props.item.id,
  () => {
    editing.value = false
    saved.value = false
  },
)
function onSaved(review) {
  editing.value = false
  saved.value = true
  emit('saved', review)
}
</script>
<template>
  <div :data-item-review="item.id" class="mt-2">
    <p v-if="saved" role="status" class="text-success">Đã lưu đánh giá.</p>
    <template v-if="item.review"
      ><p class="mb-1">Đã đánh giá: {{ item.review.rating }} / 5</p>
      <p v-if="item.review.status === 'HIDDEN'" class="text-secondary">
        Đánh giá đang được ẩn. Sửa nội dung không tự công khai lại.
      </p>
      <p v-if="item.review.comment" class="text-break" style="white-space: pre-wrap">
        {{ item.review.comment }}
      </p></template
    >
    <button
      v-if="!editing && (item.can_review || item.can_edit_review)"
      type="button"
      class="btn btn-outline-primary btn-sm"
      @click="editing = true"
    >
      {{ item.review ? 'Sửa đánh giá' : 'Đánh giá' }}
    </button>
    <ReviewForm
      v-if="editing && (item.can_review || item.can_edit_review)"
      :key="item.id"
      :item="item"
      @saved="onSaved"
      @cancel="editing = false"
    />
  </div>
</template>

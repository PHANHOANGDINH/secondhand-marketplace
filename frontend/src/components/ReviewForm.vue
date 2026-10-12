<script setup>
import { ref } from 'vue'
import { createReview, updateReview } from '../api/review.api'
import { useFeedback } from '../composables/useFeedback'
import DomainFeedback from './DomainFeedback.vue'
const props = defineProps({ item: { type: Object, required: true } })
const emit = defineEmits(['saved', 'cancel'])
const rating = ref(props.item.review?.rating ?? 5),
  comment = ref(props.item.review?.comment ?? '')
const { busy, error, errors, run } = useFeedback()
async function submit() {
  await run(async () => {
    const data = { rating: rating.value, comment: comment.value.trim() || null }
    const review = props.item.review
      ? await updateReview(props.item.review.id, data)
      : await createReview(props.item.id, data)
    emit('saved', review)
  })
}
</script>
<template>
  <form class="border rounded p-3 my-2" data-review-form @submit.prevent="submit">
    <DomainFeedback :busy="busy" :error="error" />
    <p v-for="(messages, field) in errors" :key="field" class="text-danger">
      {{ messages.join(' ') }}
    </p>
    <label :for="`review-rating-${item.id}`" class="form-label">Số sao</label>
    <select
      :id="`review-rating-${item.id}`"
      v-model.number="rating"
      name="rating"
      required
      class="form-select mb-2"
      :disabled="busy"
    >
      <option v-for="value in 5" :key="value" :value="value">
        {{ '★'.repeat(value) }} · {{ value }} / 5
      </option>
    </select>
    <label :for="`review-comment-${item.id}`" class="form-label">Nhận xét (không bắt buộc)</label>
    <textarea
      :id="`review-comment-${item.id}`"
      v-model="comment"
      name="comment"
      maxlength="2000"
      rows="3"
      class="form-control mb-2"
      :disabled="busy"
    />
    <button type="submit" class="btn btn-primary" :disabled="busy">
      {{ item.review ? 'Lưu đánh giá' : 'Gửi đánh giá' }}
    </button>
    <button
      type="button"
      class="btn btn-outline-secondary ms-2"
      :disabled="busy"
      @click="emit('cancel')"
    >
      Đóng
    </button>
  </form>
</template>

<script setup>
import { ref } from 'vue'
import {
  createReview,
  removeReviewVideo,
  REVIEW_VIDEO_MAX_BYTES,
  updateReview,
  uploadReviewVideo,
} from '../api/review.api'
import { useFeedback } from '../composables/useFeedback'
import DomainFeedback from './DomainFeedback.vue'
const props = defineProps({ item: { type: Object, required: true } })
const emit = defineEmits(['saved', 'cancel'])
const rating = ref(props.item.review?.rating ?? 5),
  comment = ref(props.item.review?.comment ?? '')
// The review as last saved here: a retry after a failed video upload edits it instead of creating a second one.
const saved = ref(props.item.review ?? null)
const video = ref(null),
  dropVideo = ref(false),
  videoError = ref('')
const { busy, error, errors, run } = useFeedback()
function pickVideo(event) {
  const file = event.target.files?.[0] || null
  videoError.value = ''
  if (file && !['video/mp4', 'video/webm'].includes(file.type)) {
    videoError.value = 'Chỉ nhận video MP4 hoặc WebM.'
    event.target.value = ''
    video.value = null
    return
  }
  if (file && file.size > REVIEW_VIDEO_MAX_BYTES) {
    videoError.value = 'Video tối đa 50 MB.'
    event.target.value = ''
    video.value = null
    return
  }
  video.value = file
  if (file) dropVideo.value = false
}
async function submit() {
  if (videoError.value) return
  await run(async () => {
    const data = { rating: rating.value, comment: comment.value.trim() || null }
    let review = saved.value
      ? await updateReview(saved.value.id, data)
      : await createReview(props.item.id, data)
    saved.value = review
    if (video.value) review = await uploadReviewVideo(review.id, video.value)
    // The "Xóa video này" box only shows while a video is attached.
    else if (dropVideo.value) review = await removeReviewVideo(review.id)
    saved.value = review
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
    <label :for="`review-video-${item.id}`" class="form-label">Video thực tế (không bắt buộc)</label>
    <input
      :id="`review-video-${item.id}`"
      type="file"
      name="video"
      accept="video/mp4,video/webm"
      class="form-control"
      :aria-describedby="`review-video-hint-${item.id}`"
      :disabled="busy"
      @change="pickVideo"
    />
    <p :id="`review-video-hint-${item.id}`" class="form-text mb-2">
      1 video MP4 hoặc WebM, tối đa 50 MB. Chọn video mới sẽ thay video cũ.
    </p>
    <p v-if="videoError" class="text-danger small" role="alert" data-video-error>{{ videoError }}</p>
    <template v-if="saved?.video && !video">
      <video
        class="review-video mb-2"
        :src="saved.video.url"
        controls
        preload="metadata"
        playsinline
        aria-label="Video đang gắn với đánh giá"
      ></video>
      <label class="form-check mb-2"
        ><input
          v-model="dropVideo"
          type="checkbox"
          class="form-check-input"
          name="remove_video"
          :disabled="busy"
        />
        Xóa video này</label
      >
    </template>
    <button type="submit" class="btn btn-primary" :disabled="busy || Boolean(videoError)">
      {{ saved ? 'Lưu đánh giá' : 'Gửi đánh giá' }}
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

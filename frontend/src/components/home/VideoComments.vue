<script setup>
import { computed, nextTick, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import ReportDialog from '../ReportDialog.vue'
import { useAuthStore } from '../../stores/auth'
import { useVideoComments } from '../../composables/useVideoComments'
const props = defineProps({ videoId: { type: String, required: true } })
const auth = useAuthStore(),
  route = useRoute()
const {
  comments,
  loading,
  sending,
  error,
  actionError,
  olderBusy,
  hasOlder,
  managing,
  change,
  connection,
  refresh,
  older,
  send,
  hide,
} = useVideoComments(() => props.videoId)
const body = ref(''),
  region = ref(null),
  follow = ref(true),
  newComments = ref(false),
  confirmation = ref(null)
const login = computed(() => ({ name: 'login', query: { redirect: route.fullPath } }))
function scrolled() {
  const node = region.value
  if (node) follow.value = node.scrollHeight - node.scrollTop - node.clientHeight < 60
}
async function latest() {
  await nextTick()
  if (region.value) region.value.scrollTop = region.value.scrollHeight
  follow.value = true
  newComments.value = false
}
watch(change, () => {
  if (follow.value) latest()
  else newComments.value = true
})
watch(loading, (value) => {
  if (!value && follow.value) latest()
})
watch(
  () => props.videoId,
  () => {
    body.value = ''
    confirmation.value = null
    follow.value = true
    newComments.value = false
  },
)
async function submit() {
  if (!body.value.trim() || sending.value) return
  if (await send(body.value)) body.value = ''
}
async function loadOlder() {
  const node = region.value,
    top = node?.scrollTop || 0,
    height = node?.scrollHeight || 0
  await older()
  await nextTick()
  if (region.value === node && node) node.scrollTop = top + node.scrollHeight - height
}
async function confirmHide(id) {
  await hide(id)
  confirmation.value = null
}
const timestamp = (value) =>
  new Date(value).toLocaleString('vi-VN', { dateStyle: 'short', timeStyle: 'short' })
</script>
<template>
  <section class="theater-comments" aria-labelledby="video-comments-title">
    <header class="d-flex justify-content-between align-items-center gap-2">
      <h4 id="video-comments-title">Bình luận</h4>
      <small class="text-muted">{{
        connection === 'connected' ? 'Trực tiếp' : 'Đã lưu · có thể tải lại'
      }}</small>
    </header>
    <div v-if="error" class="small mb-2" role="alert">
      {{ error }} <button class="btn btn-sm btn-outline-secondary" @click="refresh">Tải lại</button>
    </div>
    <div
      ref="region"
      class="video-comments-region"
      role="region"
      aria-label="Danh sách bình luận"
      tabindex="0"
      @scroll="scrolled"
    >
      <button
        v-if="hasOlder"
        class="btn btn-sm btn-outline-secondary mb-2"
        :disabled="olderBusy"
        @click="loadOlder"
      >
        {{ olderBusy ? 'Đang tải…' : 'Bình luận trước' }}
      </button>
      <p v-if="loading" role="status">Đang tải bình luận…</p>
      <p v-else-if="!comments.length && !error" class="small text-muted">
        Chưa có bình luận. Hãy bắt đầu cuộc trò chuyện.
      </p>
      <ol class="video-comments-list">
        <li v-for="comment in comments" :key="comment.id" :data-comment="comment.id">
          <div class="d-flex justify-content-between align-items-start gap-2">
            <strong>{{ comment.author.name }}</strong
            ><time class="text-muted" :datetime="comment.created_at">{{
              timestamp(comment.created_at)
            }}</time>
          </div>
          <p>{{ comment.body }}</p>
          <ReportDialog
            target-type="VIDEO_COMMENT"
            :target-id="comment.id"
            label="Báo cáo bình luận"
          />
          <button
            v-if="comment.can_manage && confirmation !== comment.id"
            class="btn btn-sm btn-link"
            :aria-label="`Ẩn bình luận của ${comment.author.name}`"
            @click="confirmation = comment.id"
          >
            Ẩn bình luận
          </button>
          <div
            v-if="confirmation === comment.id"
            class="d-flex flex-wrap gap-2"
            role="group"
            aria-label="Xác nhận ẩn bình luận"
          >
            <button
              class="btn btn-sm btn-outline-danger"
              :disabled="!!managing"
              @click="confirmHide(comment.id)"
            >
              Xác nhận ẩn</button
            ><button
              class="btn btn-sm btn-outline-secondary"
              :disabled="!!managing"
              @click="confirmation = null"
            >
              Giữ lại
            </button>
          </div>
        </li>
      </ol>
    </div>
    <p class="visually-hidden" role="status" aria-live="polite">
      {{ newComments ? 'Có bình luận mới' : '' }}
    </p>
    <button v-if="newComments" class="btn btn-sm btn-outline-primary my-2" @click="latest">
      Có bình luận mới ↓
    </button>
    <form v-if="auth.isAuthenticated" class="video-comment-form" @submit.prevent="submit">
      <label for="video-comment-body" class="form-label">Bình luận của bạn</label>
      <textarea
        id="video-comment-body"
        v-model="body"
        class="form-control"
        rows="2"
        maxlength="500"
        :disabled="sending"
        :aria-invalid="!!actionError"
        aria-describedby="video-comment-help video-comment-error"
      />
      <div class="d-flex justify-content-between align-items-center gap-2 mt-2">
        <small id="video-comment-help" class="text-muted"
          >{{ body.length }}/500 · Enter xuống dòng</small
        ><button class="btn btn-primary btn-sm" :disabled="sending || !body.trim()">
          {{ sending ? 'Đang gửi…' : 'Gửi bình luận' }}
        </button>
      </div>
    </form>
    <RouterLink v-else :to="login" class="btn btn-outline-primary w-100 mt-2"
      >Đăng nhập để bình luận</RouterLink
    >
    <p
      id="video-comment-error"
      class="small text-danger mt-2 mb-0"
      :role="actionError ? 'alert' : undefined"
    >
      {{ actionError }}
    </p>
  </section>
</template>
<style scoped>
.theater-comments {
  border-top: 1px solid var(--bs-border-color);
  padding-top: 1.25rem;
  margin-top: 1.25rem;
}
.theater-comments h4 {
  font-size: 1.05rem;
  margin: 0;
}
.video-comments-region {
  max-height: 17rem;
  overflow-y: auto;
  overscroll-behavior: contain;
  margin: 0.75rem 0;
  overflow-anchor: none;
}
.video-comments-region:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.video-comments-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.video-comments-list li {
  padding: 0.75rem 0;
  border-bottom: 1px solid var(--bs-border-color);
  animation: comment-appear 0.15s ease;
}
.video-comments-list time {
  font-size: 0.7rem;
  white-space: nowrap;
}
.video-comments-list p {
  font-size: 0.9rem;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  margin: 0.3rem 0;
}
.video-comment-form textarea {
  min-height: 5rem;
}
@keyframes comment-appear {
  from {
    opacity: 0.5;
  }
  to {
    opacity: 1;
  }
}
@media (prefers-reduced-motion: reduce) {
  .video-comments-list li {
    animation: none;
  }
}
@media (max-width: 767px) {
  .video-comments-region {
    max-height: 20rem;
  }
}
</style>

<script setup>
import { ref, watch, onBeforeUnmount } from 'vue'
import { listProductVideos } from '../api/productVideo.api'
import { domainError } from '../api/domain.api'
import DomainFeedback from './DomainFeedback.vue'
const props = defineProps({ slug: { type: String, required: true } })
const rows = ref([]),
  busy = ref(false),
  error = ref('')
let sequence = 0
async function load() {
  const s = ++sequence
  rows.value = []
  busy.value = true
  error.value = ''
  try {
    const result = await listProductVideos(props.slug)
    if (s === sequence) rows.value = result.data || []
  } catch (e) {
    if (s === sequence) error.value = domainError(e)
  } finally {
    if (s === sequence) busy.value = false
  }
}
watch(() => props.slug, load, { immediate: true })
onBeforeUnmount(() => {
  sequence++
})
</script>
<template>
  <section
    v-if="rows.length || error"
    class="buyer-section product-video-section"
    aria-label="Video đánh giá"
  >
    <h2 class="h4">Video đánh giá</h2>
    <DomainFeedback :busy="busy" :error="error" />
    <button v-if="error" class="btn btn-outline-secondary" @click="load">Tải lại video</button>
    <div class="row g-3">
      <article v-for="(video, index) in rows" :key="video.id" class="col-md-6">
        <div class="card p-3 h-100">
          <h3 class="h6 text-break">{{ video.title || `Video đánh giá ${index + 1}` }}</h3>
          <p v-if="!video.poster_url" class="text-muted small">Nhấn phát để xem video sản phẩm.</p>
          <video
            :src="video.url"
            :poster="video.poster_url || undefined"
            controls
            preload="none"
            playsinline
            :aria-label="video.title || `Video đánh giá ${index + 1}`"
          />
          <p v-if="video.description" class="mt-3 mb-0 text-break">{{ video.description }}</p>
        </div>
      </article>
    </div>
  </section>
</template>
<style scoped>
.product-video-section {
  margin-top: var(--space-8);
  min-width: 0;
}
video {
  display: block;
  width: 100%;
  max-height: 360px;
  object-fit: contain;
  background: var(--surface-muted);
  border-radius: var(--radius-md);
}
</style>

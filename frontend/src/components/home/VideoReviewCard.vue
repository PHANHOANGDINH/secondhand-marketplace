<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { formatPrice } from '../../composables/productPresentation'
import { getStatusLabel } from '../../utils/labels'
const props = defineProps({ video: { type: Object, required: true }, active: Boolean })
const emit = defineEmits(['preview', 'open'])
const player = ref(null),
  failed = ref(false)
const card = ref(null)
let observer
const cover = computed(() => props.video.poster_url || props.video.product.primary_image?.url)
function stop() {
  if (player.value) {
    player.value.pause()
    player.value.removeAttribute('src')
    player.value.load()
  }
}
watch(
  () => props.active,
  async (active) => {
    if (!active) return stop()
    await nextTick()
    if (player.value) {
      player.value.muted = true
      try {
        await player.value.play()
      } catch {
        failed.value = true
      }
    }
  },
)
onMounted(() => {
  if (!window.IntersectionObserver) return
  observer = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting && props.active) emit('preview', null)
  })
  observer.observe(card.value)
})
onBeforeUnmount(() => {
  observer?.disconnect()
  stop()
})
</script>
<template>
  <article ref="card" class="video-review-card">
    <button
      class="video-review-cover"
      :aria-label="`Xem video: ${video.product.name}`"
      @mouseenter="emit('preview', video.id)"
      @mouseleave="emit('preview', null)"
      @focus="emit('preview', video.id)"
      @blur="emit('preview', null)"
      @click="emit('open', video.id)"
    >
      <img
        v-if="cover"
        :src="cover"
        alt=""
        loading="lazy"
        @error="$event.target.style.display = 'none'"
      />
      <span v-else class="video-cover-fallback">Video đánh giá</span>
      <video
        v-if="active && !failed"
        ref="player"
        :src="video.url"
        muted
        playsinline
        loop
        preload="none"
        aria-hidden="true"
        @error="failed = true"
      />
      <span class="video-play-label">▶ Xem video</span>
    </button>
    <div class="video-review-copy">
      <p class="small text-muted mb-1">{{ video.product.shop?.name }}</p>
      <h3>{{ video.product.name }}</h3>
      <p class="fw-bold mb-0">
        {{ formatPrice(video.auction?.current_price || video.product.price) }}
      </p>
      <small v-if="video.auction" class="text-primary">{{
        getStatusLabel(video.auction.status, 'auction')
      }}</small>
    </div>
  </article>
</template>
<style scoped>
.video-review-card {
  min-width: 0;
  border: 1px solid var(--bs-border-color);
  border-radius: var(--radius-lg);
  overflow: hidden;
  background: var(--surface);
}
.video-review-cover {
  position: relative;
  display: grid;
  place-items: center;
  width: 100%;
  aspect-ratio: 9 / 16;
  border: 0;
  padding: 0;
  background: var(--bs-tertiary-bg);
  overflow: hidden;
}
.video-review-cover img,
.video-review-cover video {
  position: absolute;
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.video-cover-fallback {
  color: var(--bs-secondary-color);
  font-weight: 600;
}
.video-play-label {
  position: absolute;
  bottom: 1rem;
  left: 1rem;
  border-radius: 2rem;
  padding: 0.45rem 0.7rem;
  background: rgb(0 0 0 / 70%);
  color: white;
  font-size: 0.8rem;
}
.video-review-copy {
  padding: 1rem;
}
.video-review-copy h3 {
  font-size: 1rem;
  line-height: 1.5;
  overflow-wrap: anywhere;
}
.video-review-cover:focus-visible {
  outline: var(--focus-ring);
  outline-offset: -4px;
}
</style>

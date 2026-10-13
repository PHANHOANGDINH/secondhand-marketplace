<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { listPublicVideos, getPublicVideo } from '../../api/productVideo.api'
import { listAuctions } from '../../api/auction.api'
import VideoReviewCard from './VideoReviewCard.vue'
import VideoTheater from './VideoTheater.vue'
import AuctionCard from '../auction/AuctionCard.vue'
import DomainFeedback from '../DomainFeedback.vue'
import EmptyState from '../ui/EmptyState.vue'
import CollectionPager from '../CollectionPager.vue'
import '../../styles/auction.css'
// The tab shown first when the URL does not ask for one.
const props = defineProps({ defaultMode: { type: String, default: 'auction' } })
const route = useRoute(),
  router = useRouter()
const mode = ref(
    route.query.video || route.query.discovery === 'video' ? 'video' : props.defaultMode,
  ),
  rows = ref([]),
  meta = ref(null)
const busy = ref(false),
  error = ref(''),
  preview = ref(null)
const selected = ref(null),
  theaterBusy = ref(false),
  theaterError = ref('')
let loadSequence = 0,
  selectionSequence = 0,
  openedHere = false
let reduced, pointer
let availabilityTimer
const index = computed(() =>
  rows.value.findIndex((row) => String(row.id) === String(route.query.video)),
)
function stopPreview() {
  preview.value = null
}
function requestPreview(id) {
  preview.value = !route.query.video && pointer?.matches && !reduced?.matches ? id : null
}
async function load(page = 1) {
  const sequence = ++loadSequence
  busy.value = true
  error.value = ''
  stopPreview()
  try {
    const result = await (mode.value === 'video' ? listPublicVideos : listAuctions)({
      page,
      per_page: 12,
    })
    if (sequence === loadSequence) {
      rows.value = result.data
      meta.value = result.meta
    }
  } catch {
    if (sequence === loadSequence) {
      rows.value = []
      meta.value = null
      error.value = 'Không thể tải nội dung. Vui lòng thử lại.'
    }
  } finally {
    if (sequence === loadSequence) busy.value = false
  }
}
async function select(id) {
  const sequence = ++selectionSequence
  stopPreview()
  selected.value = null
  theaterError.value = ''
  if (!id) {
    theaterBusy.value = false
    return
  }
  theaterBusy.value = true
  try {
    if (!/^\d+$/.test(String(id))) throw new Error('invalid')
    const result = await getPublicVideo(id)
    if (sequence === selectionSequence) selected.value = result
  } catch {
    if (sequence === selectionSequence)
      theaterError.value = 'Video hoặc sản phẩm này hiện không còn công khai.'
  } finally {
    if (sequence === selectionSequence) theaterBusy.value = false
  }
}
function open(id) {
  stopPreview()
  openedHere = true
  router.push({ query: { ...route.query, video: id } })
}
function close() {
  if (openedHere) {
    openedHere = false
    router.back()
  } else {
    const query = { ...route.query }
    delete query.video
    router.replace({ query })
  }
}
function move(direction) {
  const next = index.value + direction
  if (index.value < 0 || next < 0 || next >= rows.value.length || theaterBusy.value) return
  router.replace({ query: { ...route.query, video: rows.value[next].id } })
}
watch(mode, () => {
  rows.value = []
  meta.value = null
  load()
})
watch(
  () => route.query.video,
  (id) => {
    if (id && mode.value !== 'video') mode.value = 'video'
    select(id)
  },
  { immediate: true },
)
function visibility() {
  if (document.hidden) stopPreview()
}
function revealVideos() {
  if (route.query.discovery !== 'video') return
  mode.value = 'video'
  document.getElementById('home-discovery')?.scrollIntoView?.({ block: 'start' })
}
watch(() => route.query.discovery, revealVideos)
async function checkAvailability() {
  const id = route.query.video,
    sequence = selectionSequence
  if (!id || document.hidden || theaterBusy.value || theaterError.value) return
  try {
    const result = await getPublicVideo(id)
    if (sequence === selectionSequence) selected.value = result
  } catch {
    if (sequence === selectionSequence) {
      selected.value = null
      theaterError.value = 'Video hoặc sản phẩm này hiện không còn công khai.'
    }
  }
}
onMounted(() => {
  reduced = window.matchMedia('(prefers-reduced-motion: reduce)')
  pointer = window.matchMedia('(hover: hover) and (pointer: fine)')
  reduced.addEventListener('change', stopPreview)
  pointer.addEventListener('change', stopPreview)
  document.addEventListener('visibilitychange', visibility)
  window.addEventListener('blur', stopPreview)
  availabilityTimer = window.setInterval(checkAvailability, 15000)
  load()
  queueMicrotask(revealVideos)
})
onBeforeUnmount(() => {
  loadSequence++
  selectionSequence++
  stopPreview()
  reduced?.removeEventListener('change', stopPreview)
  pointer?.removeEventListener('change', stopPreview)
  document.removeEventListener('visibilitychange', visibility)
  window.removeEventListener('blur', stopPreview)
  window.clearInterval(availabilityTimer)
})
</script>
<template>
  <section id="home-discovery" class="home-discovery" aria-labelledby="home-discovery-title">
    <p class="buyer-eyebrow">Khám phá theo cách của bạn</p>
    <h2 id="home-discovery-title">Món đồ thật. Câu chuyện thật.</h2>
    <div class="discovery-switch" role="group" aria-label="Nội dung khám phá">
      <button
        class="btn"
        :class="mode === 'auction' ? 'btn-primary' : 'btn-outline-secondary'"
        :aria-pressed="mode === 'auction'"
        @click="mode = 'auction'"
      >
        Sàn đấu giá
      </button>
      <button
        class="btn"
        :class="mode === 'video' ? 'btn-primary' : 'btn-outline-secondary'"
        :aria-pressed="mode === 'video'"
        @click="mode = 'video'"
      >
        Video đánh giá
      </button>
    </div>
    <DomainFeedback :busy="busy" :error="error" />
    <button v-if="error" class="btn btn-outline-primary" @click="load()">Tải lại</button>
    <EmptyState
      v-else-if="!busy && !rows.length"
      :title="mode === 'video' ? 'Chưa có Video đánh giá' : 'Chưa có phiên đấu giá'"
      description="Nội dung công khai mới sẽ xuất hiện tại đây."
    />
    <div v-else-if="!busy" :class="mode === 'video' ? 'video-review-grid' : 'auction-grid'">
      <template v-if="mode === 'video'"
        ><VideoReviewCard
          v-for="video in rows"
          :key="video.id"
          :video="video"
          :active="preview === video.id"
          @preview="requestPreview"
          @open="open"
      /></template>
      <template v-else
        ><AuctionCard v-for="auction in rows" :key="auction.id" :auction="auction"
      /></template>
    </div>
    <CollectionPager :meta="meta" :busy="busy" @page="load" />
    <VideoTheater
      v-if="route.query.video"
      :video="selected"
      :busy="theaterBusy"
      :error="theaterError"
      :previous="index > 0"
      :next="index >= 0 && index < rows.length - 1"
      @close="close"
      @move="move"
      @retry="select(route.query.video)"
    />
  </section>
</template>
<style scoped>
.home-discovery {
  margin-bottom: var(--space-10);
  padding: clamp(1rem, 3vw, 2rem);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.home-discovery h2 {
  font-size: 1.35rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.discovery-switch {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
  margin: 1.5rem 0 2rem;
}
.video-review-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 1.25rem;
}
@media (max-width: 1100px) {
  .video-review-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 800px) {
  .video-review-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
  }
}
@media (max-width: 350px) {
  .video-review-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import AccessibleDialog from '../ui/AccessibleDialog.vue'
import VideoComments from './VideoComments.vue'
import FavoriteButton from '../FavoriteButton.vue'
import StatusBadge from '../ui/StatusBadge.vue'
import { auctionLabels } from '../../composables/auctionPresentation'
import { conditionLabel, formatPrice } from '../../composables/productPresentation'
const props = defineProps({
  video: Object,
  busy: Boolean,
  error: String,
  previous: Boolean,
  next: Boolean,
})
const emit = defineEmits(['close', 'move', 'retry'])
const player = ref(null),
  failed = ref(false)
function stop() {
  if (player.value) {
    player.value.pause()
    player.value.removeAttribute('src')
    player.value.load()
  }
}
watch(
  () => props.video?.id,
  () => {
    stop()
    failed.value = false
  },
)
onBeforeUnmount(stop)
function keyboard(event) {
  if (
    event.target.closest('video, input, textarea, select, [contenteditable], .theater-comments') ||
    event.altKey ||
    event.ctrlKey ||
    event.metaKey
  )
    return
  if (['ArrowUp', 'ArrowDown'].includes(event.key)) {
    event.preventDefault()
    emit('move', event.key === 'ArrowUp' ? -1 : 1)
  }
}
</script>
<template>
  <AccessibleDialog
    id="video-theater"
    title="Video đánh giá"
    class="video-theater-backdrop"
    @dismiss="emit('close')"
    @keydown="keyboard"
  >
    <div class="video-theater-body">
      <p v-if="busy" role="status">Đang tải video…</p>
      <div v-else-if="error" role="alert">
        <p>{{ error }}</p>
        <button class="btn btn-outline-primary" @click="emit('retry')">Thử lại</button>
      </div>
      <template v-else-if="video">
        <div class="video-theater-media">
          <video
            v-if="!failed"
            :key="video.id"
            ref="player"
            :src="video.url"
            :poster="video.poster_url || video.product.primary_image?.url"
            controls
            playsinline
            preload="none"
            tabindex="0"
            :aria-label="video.title || video.product.name"
            @error="failed = true"
          />
          <div v-else class="video-media-error" role="status">
            <p>Trình duyệt không phát được video này.</p>
            <img
              v-if="video.product.primary_image?.url"
              :src="video.product.primary_image.url"
              alt="Ảnh sản phẩm"
            />
          </div>
        </div>
        <aside class="video-theater-product">
          <p class="buyer-eyebrow">{{ video.product.shop?.name }}</p>
          <StatusBadge
            v-if="video.auction"
            :status="video.auction.status"
            :label="auctionLabels[video.auction.status]"
          />
          <h3>{{ video.product.name }}</h3>
          <p class="h4 fw-bold">
            {{ formatPrice(video.auction?.current_price || video.product.price) }}
          </p>
          <p>{{ conditionLabel(video.product.condition) }}</p>
          <FavoriteButton :product="video.product" class="mb-3" />
          <p class="video-description">{{ video.description || video.product.description }}</p>
          <RouterLink
            v-if="video.auction"
            :to="`/auctions/${video.auction.id}`"
            class="btn btn-primary w-100"
            >{{
              video.auction.status === 'ENDED' ? 'Xem phiên đấu giá' : 'Vào phòng đấu giá'
            }}</RouterLink
          >
          <RouterLink v-else :to="`/products/${video.product.slug}`" class="btn btn-primary w-100"
            >Xem sản phẩm</RouterLink
          >
          <p class="small text-muted mt-3">
            Thông tin mua hàng và điều kiện giao dịch được kiểm tra tại trang sản phẩm.
          </p>
          <VideoComments :key="video.id" :video-id="video.id" />
        </aside>
      </template>
    </div>
    <template #footer
      ><nav class="d-flex justify-content-between gap-2" aria-label="Chuyển video">
        <button
          class="btn btn-outline-secondary"
          :disabled="!previous || busy"
          @click="emit('move', -1)"
        >
          ↑ Video trước
        </button>
        <button
          class="btn btn-outline-secondary"
          :disabled="!next || busy"
          @click="emit('move', 1)"
        >
          Video tiếp ↓
        </button>
      </nav></template
    >
  </AccessibleDialog>
</template>
<style>
.video-theater-backdrop {
  background: rgb(0 0 0 / 90%);
}
.video-theater-backdrop .ui-dialog {
  width: min(76rem, 100%);
}
.video-theater-backdrop .ui-dialog-content {
  padding: 0;
}
.video-theater-body {
  display: grid;
  grid-template-columns: 3fr 2fr;
  min-width: 0;
}
.video-theater-body > [role] {
  grid-column: 1 / -1;
  padding: 2rem;
}
.video-theater-media {
  background: #090909;
  display: grid;
  place-items: center;
  min-width: 0;
}
.video-theater-media video {
  display: block;
  width: min(100%, 36.5625dvh);
  max-height: 65dvh;
  aspect-ratio: 9 / 16;
  object-fit: contain;
}
.video-theater-product {
  padding: clamp(1rem, 3vw, 2rem);
  min-width: 0;
  overflow-wrap: anywhere;
}
.video-description {
  white-space: pre-line;
  color: var(--bs-secondary-color);
}
.video-media-error {
  color: white;
  padding: 1rem;
}
.video-media-error img {
  max-width: 100%;
  max-height: 45dvh;
  object-fit: contain;
}
@media (max-width: 767px) {
  .video-theater-body {
    grid-template-columns: 1fr;
  }
  .video-theater-media video {
    width: min(100%, 30.9375dvh);
    max-height: 55dvh;
  }
  .video-theater-backdrop .ui-dialog-header {
    position: sticky;
    top: 0;
  }
}
</style>

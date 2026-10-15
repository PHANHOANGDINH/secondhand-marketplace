<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue'
import { useAuthStore } from '../../stores/auth'
import AppIcon from '../ui/AppIcon.vue'
// A small rotating banner under the home search. GSAP animates it in, floats the icon and slides between
// messages; with "reduce motion", or where matchMedia is missing, it is a plain banner with dots and no autoplay.
const auth = useAuthStore()
const slides = computed(() => [
  {
    key: 'post',
    tone: 'band',
    icon: 'upload',
    eyebrow: 'Đăng tin miễn phí',
    title: 'Đăng là hiện ngay, không chờ duyệt',
    text: 'Chụp ảnh, đặt giá, bấm Đăng bán: người mua gần bạn thấy liền.',
    cta: 'Đăng tin',
    to: auth.isAuthenticated
      ? { path: '/', query: { 'quick-listing': '1' } }
      : { path: '/login', query: { redirect: '/?quick-listing=1' } },
  },
  {
    key: 'auction',
    tone: 'auction',
    icon: 'gavel',
    eyebrow: 'Sàn đấu giá',
    title: 'Phiên đấu giá đang chạy, trả giá trực tiếp',
    text: 'Giá cập nhật theo thời gian thực; thắng phiên là thanh toán ngay.',
    cta: 'Vào sàn đấu giá',
    to: '/auctions',
  },
  {
    key: 'safe',
    tone: 'primary',
    icon: 'shield',
    eyebrow: 'Mua bán an toàn',
    title: 'Thanh toán qua giỏ hàng: ZaloPay hoặc COD',
    text: 'Không chuyển khoản riêng cho người bán; thấy tin đáng ngờ, báo cáo một chạm.',
    cta: 'Xem tin mới',
    to: { path: '/products', query: { sort: 'newest' } },
  },
])
const index = ref(0),
  root = ref(null),
  content = ref(null),
  icon = ref(null)
const current = computed(() => slides.value[index.value])
let gsap = null,
  context = null,
  timer = null,
  animate = false,
  paused = false,
  switching = false

async function show(target) {
  const next = (target + slides.value.length) % slides.value.length
  if (next === index.value || switching) return
  if (!animate || !gsap) {
    index.value = next
    return
  }
  if (!content.value) return
  switching = true
  const direction = target > index.value ? 1 : -1
  await gsap.to(content.value.children, { x: -24 * direction, autoAlpha: 0, duration: 0.25, stagger: 0.04, ease: 'power2.in' })
  index.value = next
  await nextTick()
  // The page may have been left while the old message was sliding out.
  if (!content.value) {
    switching = false
    return
  }
  gsap.fromTo(
    content.value.children,
    { x: 24 * direction, autoAlpha: 0 },
    { x: 0, autoAlpha: 1, duration: 0.45, stagger: 0.06, ease: 'power3.out' },
  )
  gsap.fromTo(icon.value, { scale: 0.6, rotate: -12 }, { scale: 1, rotate: 0, duration: 0.5, ease: 'back.out(2)' })
  switching = false
}
function startAutoplay() {
  stopAutoplay()
  timer = window.setInterval(() => {
    if (!paused) show(index.value + 1)
  }, 5000)
}
function stopAutoplay() {
  if (timer) window.clearInterval(timer)
  timer = null
}
// A chosen message stays for a full interval before the banner moves on again.
function select(position) {
  show(position)
  if (timer) startAutoplay()
}
const pause = () => (paused = true)
const resume = () => (paused = false)

onMounted(async () => {
  if (typeof window.matchMedia !== 'function') return
  try {
    ;({ gsap } = await import('gsap'))
  } catch {
    return
  }
  if (!root.value) return
  context = gsap.context(() => {
    gsap.matchMedia().add('(prefers-reduced-motion: no-preference)', () => {
      animate = true
      gsap.from(root.value, { y: 18, autoAlpha: 0, duration: 0.6, ease: 'power3.out' })
      gsap.from(content.value.children, { y: 12, autoAlpha: 0, duration: 0.5, stagger: 0.08, delay: 0.15, ease: 'power3.out' })
      gsap.to(icon.value, { y: -5, duration: 1.6, repeat: -1, yoyo: true, ease: 'sine.inOut' })
      gsap.to('.promo-blob', { scale: 1.12, rotate: 25, duration: 6, repeat: -1, yoyo: true, ease: 'sine.inOut', stagger: 1.2 })
      startAutoplay()
      return () => {
        animate = false
        stopAutoplay()
      }
    })
  }, root.value)
})
onUnmounted(() => {
  stopAutoplay()
  context?.revert()
})
</script>
<template>
  <section
    ref="root"
    class="home-promo"
    :class="`is-${current.tone}`"
    aria-roledescription="carousel"
    aria-label="Tin nổi bật của sàn"
    data-promo
    @mouseenter="pause"
    @mouseleave="resume"
    @focusin="pause"
    @focusout="resume"
  >
    <span class="promo-blob is-one" aria-hidden="true"></span>
    <span class="promo-blob is-two" aria-hidden="true"></span>
    <span ref="icon" class="promo-icon" aria-hidden="true"><AppIcon :name="current.icon" :size="28" /></span>
    <div
      ref="content"
      class="promo-content"
      role="group"
      aria-roledescription="slide"
      :aria-label="`${index + 1} / ${slides.length}`"
      :data-slide="current.key"
    >
      <p class="promo-eyebrow">{{ current.eyebrow }}</p>
      <p class="promo-title">{{ current.title }}</p>
      <p class="promo-text">{{ current.text }}</p>
    </div>
    <RouterLink :to="current.to" class="promo-cta" data-promo-cta
      >{{ current.cta }}<AppIcon name="arrowRight" :size="16"
    /></RouterLink>
    <div class="promo-dots" role="group" aria-label="Chọn tin nổi bật">
      <button
        v-for="(slide, position) in slides"
        :key="slide.key"
        type="button"
        class="promo-dot"
        :class="{ 'is-active': position === index }"
        :aria-label="`Tin nổi bật ${position + 1}: ${slide.eyebrow}`"
        :aria-current="position === index ? 'true' : undefined"
        @click="select(position)"
      ></button>
    </div>
  </section>
</template>
<style scoped>
.home-promo {
  --promo-bg: var(--band-soft);
  --promo-ink: var(--band-ink);
  --promo-accent: var(--brand);
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  align-items: center;
  gap: var(--space-4);
  justify-self: center;
  width: min(100%, 60rem);
  min-height: 104px;
  margin: 0 0 var(--space-6);
  padding: var(--space-4) var(--space-5) var(--space-5);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: var(--promo-bg);
  color: var(--promo-ink);
  transition: background-color 0.4s ease;
}
.home-promo.is-auction {
  --promo-bg: var(--auction-accent-soft);
  --promo-ink: var(--auction-accent-ink);
  --promo-accent: var(--auction-accent);
}
.home-promo.is-primary {
  --promo-bg: var(--primary-soft);
  --promo-ink: var(--primary-ink);
  --promo-accent: var(--primary);
}
.promo-blob {
  position: absolute;
  border-radius: 50%;
  background: var(--promo-accent);
  opacity: 0.16;
  pointer-events: none;
}
.promo-blob.is-one {
  width: 160px;
  height: 160px;
  right: -40px;
  top: -70px;
}
.promo-blob.is-two {
  width: 90px;
  height: 90px;
  left: 38%;
  bottom: -55px;
}
.promo-icon {
  position: relative;
  display: grid;
  place-items: center;
  width: 60px;
  height: 60px;
  border-radius: 18px;
  background: var(--surface);
  color: var(--promo-ink);
  box-shadow: 0 6px 16px rgb(var(--shadow-rgb) / 8%);
}
.promo-content {
  position: relative;
  min-width: 0;
}
.promo-content p {
  margin: 0;
}
.promo-eyebrow {
  font-size: 0.72rem;
  font-weight: 800;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  opacity: 0.8;
}
.promo-title {
  font-size: 1.05rem;
  font-weight: 800;
  line-height: 1.3;
}
.promo-text {
  margin-top: 0.15rem !important;
  font-size: 0.85rem;
  opacity: 0.85;
}
.promo-cta {
  position: relative;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  min-height: 44px;
  padding: 0 1.1rem;
  border-radius: var(--radius-pill);
  background: var(--inverse);
  color: var(--on-inverse);
  font-size: 0.88rem;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
}
.promo-cta:hover {
  background: var(--inverse-hover);
  color: var(--on-inverse);
}
.promo-cta:focus-visible,
.promo-dot:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.promo-dots {
  position: absolute;
  left: 50%;
  bottom: 8px;
  display: flex;
  gap: 6px;
  transform: translateX(-50%);
}
.promo-dot {
  width: 8px;
  height: 8px;
  padding: 0;
  border: 0;
  border-radius: var(--radius-pill);
  background: var(--promo-ink);
  opacity: 0.25;
  transition:
    width 0.25s ease,
    opacity 0.25s ease;
}
/* The visible dot is small; the hit area is enlarged without moving the layout. */
.promo-dot::after {
  content: '';
  position: absolute;
  inset: -10px -6px;
}
.promo-dot {
  position: relative;
}
.promo-dot.is-active {
  width: 22px;
  opacity: 0.85;
}
@media (max-width: 767px) {
  .home-promo {
    grid-template-columns: auto minmax(0, 1fr);
    padding: var(--space-3) var(--space-4) var(--space-5);
  }
  .promo-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
  }
  .promo-text {
    display: none;
  }
  .promo-cta {
    grid-column: 1 / -1;
    justify-self: start;
  }
}
@media (prefers-reduced-motion: reduce) {
  .home-promo,
  .promo-dot {
    transition: none;
  }
}
</style>

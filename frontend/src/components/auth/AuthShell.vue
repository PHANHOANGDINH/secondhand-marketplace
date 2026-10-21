<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
import AppIcon from '../ui/AppIcon.vue'
import BrandLogo from '../ui/BrandLogo.vue'
import ThemeToggle from '../ui/ThemeToggle.vue'
import { brand } from '../../config/brand'
import '../../styles/auth.css'
// The frame of the sign-in, sign-up and password pages: brand and a way home instead of the site menu,
// the welcome panel, and the motion. The page puts its form card (class "auth-form") in the slot.
// Things people sell here, floating over the welcome panel (our own line icons).
const tiles = [
  ['device', 'is-a'],
  ['shirt', 'is-b'],
  ['sofa', 'is-c'],
  ['guitar', 'is-d'],
  ['motorbike', 'is-e'],
  ['book', 'is-f'],
]
const perks = [
  ['upload', 'Đăng tin miễn phí, hiện ngay không chờ duyệt'],
  ['gavel', 'Đấu giá trực tiếp, giá cập nhật tức thì'],
  ['shield', 'Thanh toán qua giỏ: ZaloPay hoặc COD'],
]
// GSAP: panels slide in, fields follow, tiles float and lean with the pointer, the form shakes on an error.
// Skipped where matchMedia is missing and when the visitor asks for reduced motion.
const page = ref(null)
let gsap = null,
  context = null,
  animate = false,
  stopParallax = null
onMounted(async () => {
  if (typeof window.matchMedia !== 'function') return
  try {
    ;({ gsap } = await import('gsap'))
  } catch {
    return
  }
  if (!page.value) return
  context = gsap.context(() => {
    gsap.matchMedia().add('(prefers-reduced-motion: no-preference)', () => {
      animate = true
      const intro = gsap.timeline({ defaults: { ease: 'power3.out' } })
      intro
        .from('.auth-top', { y: -14, autoAlpha: 0, duration: 0.5 })
        .from('.auth-showcase', { x: -40, autoAlpha: 0, duration: 0.7 }, '<0.1')
        .from('.auth-form', { x: 40, autoAlpha: 0, duration: 0.7 }, '<0.1')
        .from(
          '.auth-showcase-title > *',
          { y: 20, autoAlpha: 0, stagger: 0.08, duration: 0.5 },
          '<0.2',
        )
        .from('.auth-perk', { x: -16, autoAlpha: 0, stagger: 0.1, duration: 0.45 }, '<0.2')
        .from('.auth-form .auth-field', { y: 14, autoAlpha: 0, stagger: 0.07, duration: 0.4 }, '<')
        .from(
          '.auth-divider, .auth-switch',
          { y: 10, autoAlpha: 0, stagger: 0.08, duration: 0.4 },
          '>-0.1',
        )
        .from(
          '.auth-tile',
          {
            scale: 0,
            rotate: -30,
            autoAlpha: 0,
            stagger: 0.06,
            duration: 0.6,
            ease: 'back.out(1.8)',
          },
          '<',
        )
      gsap.utils.toArray('.auth-tile').forEach((tile, position) => {
        gsap.to(tile, {
          y: position % 2 ? -12 : 12,
          rotate: position % 2 ? -8 : 8,
          duration: 2.4 + (position % 3) * 0.6,
          repeat: -1,
          yoyo: true,
          ease: 'sine.inOut',
          delay: position * 0.15,
        })
      })
      gsap.to('.auth-glow', {
        scale: 1.15,
        x: 30,
        duration: 6,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut',
      })
      // Tiles lean towards the pointer over the welcome panel.
      const layer = page.value.querySelector('.auth-tiles')
      const panel = page.value.querySelector('.auth-showcase')
      if (layer && panel) {
        const moveX = gsap.quickTo(layer, 'x', { duration: 0.8, ease: 'power3.out' }),
          moveY = gsap.quickTo(layer, 'y', { duration: 0.8, ease: 'power3.out' })
        const follow = (event) => {
          const box = panel.getBoundingClientRect()
          moveX(((event.clientX - box.left) / box.width - 0.5) * 24)
          moveY(((event.clientY - box.top) / box.height - 0.5) * 24)
        }
        panel.addEventListener('pointermove', follow)
        stopParallax = () => panel.removeEventListener('pointermove', follow)
      }
      return () => {
        animate = false
        stopParallax?.()
      }
    })
  }, page.value)
})
onUnmounted(() => context?.revert())
/** Shakes the given card, e.g. after a failed submit; nothing happens without motion. */
async function shake(card) {
  if (!animate || !gsap || !card) return
  await nextTick()
  gsap.fromTo(
    card,
    { x: 0 },
    { keyframes: { x: [-10, 10, -6, 6, -2, 0] }, duration: 0.45, ease: 'power1.out' },
  )
}
defineExpose({ shake })
</script>
<template>
  <div ref="page" class="auth-page">
    <nav class="auth-top" aria-label="Quay lại">
      <RouterLink to="/" class="auth-brand" :aria-label="`${brand.name} · Trang chủ`"
        ><BrandLogo
      /></RouterLink>
      <RouterLink to="/" class="auth-home" data-auth-home
        ><AppIcon name="arrowLeft" :size="18" />Về trang chủ</RouterLink
      >
      <ThemeToggle class="auth-theme" />
    </nav>
    <aside class="auth-showcase" aria-label="Giới thiệu sàn mua bán">
      <span class="auth-glow" aria-hidden="true"></span>
      <div class="auth-tiles" aria-hidden="true">
        <span v-for="[icon, place] in tiles" :key="place" class="auth-tile" :class="place"
          ><AppIcon :name="icon" :size="26"
        /></span>
      </div>
      <div class="auth-showcase-title">
        <p class="auth-showcase-brand">{{ brand.name }}</p>
        <p class="auth-showcase-slogan">Mỗi món đồ cũ là một đời mới cho ai đó.</p>
      </div>
      <ul class="auth-perks">
        <li v-for="[icon, text] in perks" :key="icon" class="auth-perk">
          <span class="auth-perk-icon"><AppIcon :name="icon" :size="18" /></span>{{ text }}
        </li>
      </ul>
    </aside>
    <slot />
  </div>
</template>

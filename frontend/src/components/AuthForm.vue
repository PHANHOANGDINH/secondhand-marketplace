<script setup>
import { localizeValidationErrors } from '../utils/validationLabels'

import PageHeader from './ui/PageHeader.vue'
import AppIcon from './ui/AppIcon.vue'
import { brand } from '../config/brand'
import { nextTick, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { authError } from '../api/auth.api'
const props = defineProps({ registration: Boolean })
const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  remember: false,
})
const errors = ref({})
const message = ref('')
const showPassword = ref(false)
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
const page = ref(null),
  card = ref(null)
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
        .from('.auth-showcase', { x: -40, autoAlpha: 0, duration: 0.7 })
        .from('.auth-form', { x: 40, autoAlpha: 0, duration: 0.7 }, '<0.1')
        .from(
          '.auth-showcase-title > *',
          { y: 20, autoAlpha: 0, stagger: 0.08, duration: 0.5 },
          '<0.2',
        )
        .from('.auth-perk', { x: -16, autoAlpha: 0, stagger: 0.1, duration: 0.45 }, '<0.2')
        .from('.auth-form .auth-field', { y: 14, autoAlpha: 0, stagger: 0.07, duration: 0.4 }, '<')
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
async function shake() {
  if (!animate || !gsap || !card.value) return
  await nextTick()
  gsap.fromTo(
    card.value,
    { x: 0 },
    { keyframes: { x: [-10, 10, -6, 6, -2, 0] }, duration: 0.45, ease: 'power1.out' },
  )
}
async function submit() {
  if (auth.loading) return
  errors.value = {}
  message.value = ''
  try {
    if (props.registration) {
      await auth.register({
        name: form.name,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
      })
    } else {
      await auth.login({ email: form.email, password: form.password, remember: form.remember })
    }
    form.password = ''
    form.password_confirmation = ''
    const target = route.query.redirect
    const defaultDestination = props.registration
      ? 'account'
      : auth.isAdmin
        ? 'admin-dashboard'
        : 'home'
    await router.replace(
      typeof target === 'string' && /^\/(?!\/)/.test(target) && !target.includes('\\')
        ? target
        : { name: defaultDestination },
    )
  } catch (error) {
    errors.value = localizeValidationErrors(error.response?.data?.errors)
    message.value = authError(error)
    form.password = ''
    form.password_confirmation = ''
    shake()
  }
}
</script>
<template>
  <div ref="page" class="auth-page">
    <aside class="auth-showcase" aria-label="Giới thiệu sàn mua bán">
      <span class="auth-glow" aria-hidden="true"></span>
      <div class="auth-tiles" aria-hidden="true">
        <span v-for="[icon, place] in tiles" :key="place" class="auth-tile" :class="place"
          ><AppIcon :name="icon" :size="26"
        /></span>
      </div>
      <div class="auth-showcase-title">
        <p class="auth-showcase-brand">{{ brand.name }}</p>
        <p class="auth-showcase-slogan">Đồ cũ giá tốt, gần bạn, chốt nhanh!</p>
      </div>
      <ul class="auth-perks">
        <li v-for="[icon, text] in perks" :key="icon" class="auth-perk">
          <span class="auth-perk-icon"><AppIcon :name="icon" :size="18" /></span>{{ text }}
        </li>
      </ul>
    </aside>
    <form
      ref="card"
      class="card card-body shadow-sm auth-form"
      :aria-busy="auth.loading"
      @submit.prevent="submit"
    >
      <PageHeader
        :title="registration ? 'Tạo tài khoản' : 'Đăng nhập'"
        :description="
          registration
            ? 'Bắt đầu hành trình mua bán đồ cũ của bạn.'
            : 'Chào mừng bạn trở lại sàn mua bán.'
        "
      />
      <p v-if="message || auth.initializationError" class="alert alert-danger" role="alert">
        {{ message || auth.initializationError }}
      </p>
      <div v-if="registration" class="mb-3 auth-field">
        <label for="auth-name" class="form-label"
          >Họ tên<span class="ui-required" aria-hidden="true"> *</span></label
        >
        <input
          id="auth-name"
          v-model="form.name"
          class="form-control"
          :class="{ 'is-invalid': errors.name }"
          :aria-invalid="!!errors.name"
          aria-describedby="name-errors"
          name="name"
          autocomplete="name"
          required
          minlength="2"
          maxlength="100"
        />
        <div id="name-errors" class="invalid-feedback">
          <div v-for="error in errors.name" :key="error">{{ error }}</div>
        </div>
      </div>
      <div class="mb-3 auth-field">
        <label for="auth-email" class="form-label"
          >Thư điện tử<span class="ui-required" aria-hidden="true"> *</span></label
        >
        <input
          id="auth-email"
          v-model="form.email"
          class="form-control"
          :class="{ 'is-invalid': errors.email }"
          :aria-invalid="!!errors.email"
          aria-describedby="email-errors"
          name="email"
          type="email"
          autocomplete="username"
          required
          maxlength="254"
        />
        <div id="email-errors" class="invalid-feedback">
          <div v-for="error in errors.email" :key="error">{{ error }}</div>
        </div>
      </div>
      <div class="mb-3 auth-field">
        <label for="auth-password" class="form-label"
          >Mật khẩu<span class="ui-required" aria-hidden="true"> *</span></label
        >
        <div class="auth-password">
          <input
            id="auth-password"
            v-model="form.password"
            class="form-control"
            :class="{ 'is-invalid': errors.password }"
            :aria-invalid="!!errors.password"
            aria-describedby="password-help password-errors"
            name="password"
            :type="showPassword ? 'text' : 'password'"
            :autocomplete="registration ? 'new-password' : 'current-password'"
            required
            maxlength="128"
          /><button
            type="button"
            class="auth-password-toggle"
            :aria-pressed="showPassword"
            :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            :title="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'"
            @click="showPassword = !showPassword"
          >
            <AppIcon :name="showPassword ? 'eyeOff' : 'eye'" :size="18" />
          </button>
        </div>
        <div v-if="registration" id="password-help" class="form-text">
          Tối thiểu 12 ký tự, có chữ và số; tối đa 72 byte.
        </div>
        <div id="password-errors" class="invalid-feedback">
          <div v-for="error in errors.password" :key="error">{{ error }}</div>
        </div>
      </div>
      <div v-if="registration" class="mb-3 auth-field">
        <label for="auth-confirmation" class="form-label"
          >Xác nhận mật khẩu<span class="ui-required" aria-hidden="true"> *</span></label
        >
        <input
          id="auth-confirmation"
          v-model="form.password_confirmation"
          class="form-control"
          :class="{ 'is-invalid': errors.password_confirmation }"
          :aria-invalid="!!errors.password_confirmation"
          aria-describedby="confirmation-errors"
          name="password_confirmation"
          type="password"
          autocomplete="new-password"
          required
          maxlength="128"
        />
        <div id="confirmation-errors" class="invalid-feedback">
          <div v-for="error in errors.password_confirmation" :key="error">{{ error }}</div>
        </div>
      </div>
      <div v-else class="form-check mb-3 auth-field">
        <input
          id="auth-remember"
          v-model="form.remember"
          type="checkbox"
          class="form-check-input"
        />
        <label for="auth-remember" class="form-check-label">Ghi nhớ đăng nhập</label>
      </div>
      <RouterLink v-if="!registration" class="mb-3 text-end" to="/forgot-password"
        >Quên mật khẩu?</RouterLink
      >
      <button class="btn btn-primary btn-icon auth-submit" type="submit" :disabled="auth.loading">
        <AppIcon :name="registration ? 'user' : 'arrowRight'" :size="18" />{{
          auth.loading ? 'Đang xử lý…' : registration ? 'Đăng ký' : 'Đăng nhập'
        }}
      </button>
      <RouterLink
        class="mt-3 text-center"
        :to="{
          path: registration ? '/login' : '/register',
          query: route.query.redirect ? { redirect: route.query.redirect } : {},
        }"
        >{{
          registration ? 'Đã có tài khoản? Đăng nhập' : 'Chưa có tài khoản? Đăng ký'
        }}</RouterLink
      >
    </form>
  </div>
</template>
<style scoped>
.auth-page {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 460px);
  gap: var(--space-6);
  align-items: stretch;
  max-width: 1040px;
  margin: 0 auto;
}
.auth-form {
  width: 100%;
  padding: var(--space-6) !important;
  border: 0;
  border-radius: var(--radius-xl);
  box-shadow: 0 12px 40px rgb(21 22 26 / 10%) !important;
}
/* Welcome panel in the marketplace band colours. */
.auth-showcase {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: flex-end;
  gap: var(--space-5);
  min-height: 520px;
  padding: var(--space-8);
  overflow: hidden;
  border-radius: var(--radius-xl);
  background: linear-gradient(150deg, var(--band) 0%, #ffd95a 55%, #ffe9a3 100%);
  color: var(--band-ink);
}
.auth-glow {
  position: absolute;
  width: 340px;
  height: 340px;
  left: -80px;
  top: -90px;
  border-radius: 50%;
  background: radial-gradient(circle, rgb(255 255 255 / 60%), rgb(255 255 255 / 0%) 70%);
  pointer-events: none;
}
.auth-tiles {
  position: absolute;
  inset: 0;
  pointer-events: none;
}
.auth-tile {
  position: absolute;
  display: grid;
  place-items: center;
  width: 64px;
  height: 64px;
  border-radius: 20px;
  background: rgb(255 255 255 / 70%);
  box-shadow: 0 10px 24px rgb(21 22 26 / 10%);
  color: var(--band-ink);
}
.auth-tile.is-a {
  top: 8%;
  left: 10%;
}
.auth-tile.is-b {
  top: 14%;
  right: 14%;
  width: 54px;
  height: 54px;
}
.auth-tile.is-c {
  top: 32%;
  left: 38%;
  width: 72px;
  height: 72px;
}
.auth-tile.is-d {
  top: 40%;
  right: 8%;
}
.auth-tile.is-e {
  top: 30%;
  left: 4%;
  width: 52px;
  height: 52px;
}
.auth-tile.is-f {
  top: 4%;
  left: 48%;
  width: 48px;
  height: 48px;
}
.auth-showcase-title,
.auth-perks {
  position: relative;
}
.auth-showcase-title p {
  margin: 0;
}
.auth-showcase-brand {
  font-size: 0.85rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.auth-showcase-slogan {
  font-size: clamp(1.6rem, 3vw, 2.3rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -0.02em;
}
.auth-perks {
  display: grid;
  gap: var(--space-2);
  margin: 0;
  padding: 0;
  list-style: none;
}
.auth-perk {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) var(--space-3);
  border-radius: var(--radius-md);
  background: rgb(255 255 255 / 55%);
  font-weight: 700;
  font-size: 0.92rem;
}
.auth-perk-icon {
  display: grid;
  flex: none;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 50%;
  background: var(--band-ink);
  color: #fff;
}
/* Password with a show/hide button inside the field. */
.auth-password {
  position: relative;
}
.auth-password .form-control {
  padding-right: 3rem;
}
.auth-password-toggle {
  position: absolute;
  top: 0;
  right: 0;
  display: grid;
  place-items: center;
  width: 44px;
  height: 100%;
  min-height: 44px;
  border: 0;
  background: transparent;
  color: var(--muted);
}
.auth-password-toggle:hover {
  color: var(--text);
}
.auth-password-toggle:focus-visible {
  outline: var(--focus-ring);
  outline-offset: -3px;
}
/* The input now sits in a wrapper, so Bootstrap's ".is-invalid ~ .invalid-feedback" no longer reaches the list. */
.auth-password:has(.is-invalid) ~ .invalid-feedback {
  display: block;
}
.auth-submit {
  min-height: 48px;
  font-weight: 700;
}
@media (max-width: 991px) {
  .auth-page {
    grid-template-columns: minmax(0, 1fr);
    max-width: 520px;
  }
  .auth-showcase {
    min-height: 0;
    padding: var(--space-5);
  }
  .auth-tiles,
  .auth-perks {
    display: none;
  }
}
</style>

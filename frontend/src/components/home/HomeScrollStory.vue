<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue'

const root = ref(null)
const cameraImage = '/home-story-camera.svg'
const scenes = [
  [
    'Một hành trình mới',
    'Đồ cũ không có nghĩa là hết giá trị.',
    'Cho những món đồ bạn không còn sử dụng một hành trình mới.',
  ],
  [
    '01 / Đăng bán',
    'Đăng bán trong vài bước.',
    'Chụp ảnh, mô tả sản phẩm và đưa món đồ của bạn lên sàn mua bán.',
  ],
  [
    '02 / Khám phá',
    'Người cần nó sẽ tìm thấy.',
    'Một món đồ cũ của bạn có thể là thứ người khác đang tìm kiếm.',
  ],
  [
    '03 / Mua & thanh toán',
    'Mua bán đơn giản. Thanh toán thuận tiện.',
    'Hỗ trợ giỏ hàng, quản lý đơn hàng, thanh toán khi nhận hàng và thanh toán trực tuyến.',
  ],
  [
    '04 / Tiếp nối',
    'Trao đồ cũ. Tạo giá trị mới.',
    'Bắt đầu hành trình mua bán đồ cũ ngay hôm nay.',
  ],
]
let context, media, trigger, frame
let disposed = false

function revealCta(event) {
  if (trigger) {
    if (event?.type === 'click') event.preventDefault()
    window.scrollTo({ top: trigger.end, behavior: 'instant' })
    trigger.animation.progress(1)
    trigger.update()
    if (event?.type === 'click') root.value.querySelector('.story-actions a')?.focus()
  }
}

onMounted(async () => {
  // A readable static story is also the fallback when animation cannot initialize.
  if (!window.matchMedia) return
  try {
    const [{ gsap }, { ScrollTrigger }] = await Promise.all([
      import('gsap'),
      import('gsap/ScrollTrigger'),
    ])
    if (disposed) return
    gsap.registerPlugin(ScrollTrigger)
    context = gsap.context(() => {
      media = gsap.matchMedia()
      media.add(
        '(min-width: 768px) and (prefers-reduced-motion: no-preference)',
        () => {
          const element = root.value
          element.classList.add('is-animated')
          const panels = [...element.querySelectorAll('.story-scene')]
          gsap.set(panels.slice(1), { opacity: 0, y: 45, pointerEvents: 'none' })
          const timeline = gsap.timeline({
            scrollTrigger: {
              trigger: element,
              pin: element.querySelector('.story-stage'),
              start: 'top top',
              end: () => `+=${window.innerHeight * 4}`,
              scrub: 0.7,
              invalidateOnRefresh: true,
            },
          })
          trigger = timeline.scrollTrigger
          timeline.to('.intro-object', { y: -20, rotation: -3, scale: 0.94, duration: 0.6 })
          panels.slice(1).forEach((panel, index) => {
            const at = index + 0.8
            timeline
              .to(panels[index], { opacity: 0, y: -35, pointerEvents: 'none', duration: 0.35 }, at)
              .to(panel, { opacity: 1, y: 0, pointerEvents: 'auto', duration: 0.45 }, at + 0.15)
              .from(
                panel.querySelectorAll('[data-reveal]'),
                { y: 24, opacity: 0, scale: 0.96, stagger: 0.08, duration: 0.4 },
                at + 0.25,
              )
          })
          timeline.to({}, { duration: 0.45 })
          frame = requestAnimationFrame(() => ScrollTrigger.refresh())
          return () => {
            cancelAnimationFrame(frame)
            trigger = null
            element.classList.remove('is-animated')
          }
        },
        root.value,
      )
    }, root.value)
    await Promise.all(
      [...root.value.querySelectorAll('img')].map((img) => img.decode?.().catch(() => {})),
    )
    await document.fonts?.ready
    if (!disposed) ScrollTrigger.refresh()
  } catch {
    media?.revert()
    context?.revert()
    root.value?.classList.remove('is-animated')
  }
})

onBeforeUnmount(() => {
  disposed = true
  cancelAnimationFrame(frame)
  media?.revert()
  context?.revert()
})
</script>

<template>
  <section ref="root" class="home-story" aria-label="Hành trình của một món đồ">
    <a class="story-skip visually-hidden-focusable" href="#home-story-cta" @click="revealCta"
      >Đến phần bắt đầu</a
    >
    <div class="story-stage">
      <section
        v-for="(scene, index) in scenes"
        :key="scene[0]"
        class="story-scene"
        :class="`scene-${index}`"
        :aria-labelledby="`story-heading-${index}`"
      >
        <div class="story-copy">
          <p class="story-eyebrow">{{ scene[0] }}</p>
          <h2 :id="`story-heading-${index}`">{{ scene[1] }}</h2>
          <p class="story-description">{{ scene[2] }}</p>
          <div v-if="index === 4" id="home-story-cta" class="story-actions" @focusin="revealCta">
            <RouterLink to="/products" class="btn btn-primary btn-lg"
              >Khám phá sản phẩm
              <svg
                aria-hidden="true"
                width="16"
                height="16"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <path d="M6 18 18 6M6 6h12v12" /></svg
            ></RouterLink>
            <RouterLink to="/seller/shop" class="btn btn-outline-primary btn-lg"
              >Bắt đầu bán</RouterLink
            >
          </div>
        </div>
        <div v-if="index === 0" class="story-visual intro-object" aria-hidden="true">
          <div class="object-halo"></div>
          <img :src="cameraImage" width="640" height="480" alt="" fetchpriority="low" />
          <span class="object-note">Một món đồ. Nhiều câu chuyện.</span>
        </div>
        <div v-else-if="index === 1" class="story-visual" aria-hidden="true">
          <div class="listing-card">
            <div data-reveal class="listing-image">
              <img :src="cameraImage" width="640" height="480" alt="" />
            </div>
            <span data-reveal class="card-label">ĐỒ ĐÃ QUA SỬ DỤNG</span>
            <h3 data-reveal>Máy ảnh film</h3>
            <strong data-reveal class="listing-price">850.000 ₫</strong>
            <p data-reveal class="listing-shop">
              <span class="shop-dot">S</span> Gian hàng minh họa
            </p>
          </div>
        </div>
        <div v-else-if="index === 2" class="story-visual product-grid" aria-hidden="true">
          <div
            v-for="(item, i) in [
              'Máy ảnh film',
              'Âm nhạc mỗi ngày',
              'Góc làm việc',
              'Một cuốn sách hay',
            ]"
            :key="item"
            data-reveal
            class="mini-card"
          >
            <div class="mini-image">
              <img v-if="i === 0" :src="cameraImage" width="640" height="480" alt="" />
              <svg v-else class="product-illustration" viewBox="0 0 160 120" fill="none">
                <ellipse cx="80" cy="105" rx="42" ry="5" fill="currentColor" opacity=".07" />
                <g v-if="i === 1" stroke="#343a40" stroke-linecap="round">
                  <path d="M42 71V55a38 38 0 0 1 76 0v16" stroke-width="12" />
                  <path d="M42 58a38 38 0 0 1 76 0" stroke="#adb5bd" stroke-width="3" />
                  <rect
                    x="29"
                    y="59"
                    width="26"
                    height="40"
                    rx="11"
                    fill="#495057"
                    stroke-width="3"
                  />
                  <rect
                    x="105"
                    y="59"
                    width="26"
                    height="40"
                    rx="11"
                    fill="#495057"
                    stroke-width="3"
                  />
                  <path d="M48 67v23m64-23v23" stroke="#adb5bd" stroke-width="3" />
                </g>
                <g v-else-if="i === 2" stroke="#495057" stroke-width="5" stroke-linejoin="round">
                  <path d="m75 96 23-42-31-21" />
                  <path d="m67 25-20 8-12 30 51-14Z" fill="#adb5bd" />
                  <ellipse cx="77" cy="99" rx="31" ry="5" fill="#6c757d" stroke-width="2" />
                  <circle cx="98" cy="54" r="4" fill="#dee2e6" />
                  <path d="m44 63 13-4" stroke="#dee2e6" stroke-width="3" />
                </g>
                <g v-else>
                  <path d="m39 41 70-12 14 65-71 13Z" fill="#adb5bd" />
                  <path
                    d="m40 43 9 54 73-12 2 9-72 13-12-64Z"
                    fill="#f8f9fa"
                    stroke="#6c757d"
                    stroke-width="2"
                  />
                  <path d="m35 33 69-12 14 65-69 12Z" fill="#495057" />
                  <path d="m44 32 12 64" stroke="#adb5bd" stroke-width="2" />
                  <path d="m61 42 32-5m-30 13 20-3" stroke="#dee2e6" stroke-width="3" />
                  <circle cx="83" cy="67" r="9" stroke="#adb5bd" stroke-width="2" />
                </g>
              </svg>
            </div>
            <span class="mini-category">TÌM MỘT CHỦ NHÂN MỚI</span><strong>{{ item }}</strong>
          </div>
        </div>
        <div v-else-if="index === 3" class="story-visual payment-journey" aria-hidden="true">
          <div data-reveal class="journey-product">
            <img :src="cameraImage" width="640" height="480" alt="" />
          </div>
          <div class="journey-steps">
            <span
              v-for="step in ['Sản phẩm', 'Giỏ hàng', 'Đặt hàng', 'Thanh toán']"
              :key="step"
              data-reveal
              >{{ step }}</span
            >
          </div>
          <div class="payment-options">
            <span data-reveal>Trả khi nhận hàng</span><span data-reveal>ZaloPay</span>
          </div>
          <div data-reveal class="order-confirmation"><span>✓</span> Xác nhận đơn hàng</div>
        </div>
        <div v-else class="story-visual final-object" aria-hidden="true">
          <div class="reuse-ring">
            <img :src="cameraImage" width="640" height="480" alt="" />
            <span class="reuse-mark">↗</span>
          </div>
          <p>Giá trị được tiếp nối.</p>
        </div>
        <p v-if="index === 0" class="story-footnote scroll-hint" aria-hidden="true">
          Cuộn để khám phá <span>↓</span>
        </p>
        <p v-else-if="index < 4" class="story-footnote">Hình ảnh minh họa hành trình mua bán</p>
      </section>
    </div>
  </section>
</template>

<style scoped>
.home-story {
  position: relative;
  margin: 0 0 2rem;
  color: var(--bs-body-color);
  --story-tint: rgba(var(--bs-primary-rgb), 0.045);
  --story-line: rgba(var(--bs-primary-rgb), 0.12);
}
.story-stage {
  position: relative;
  background:
    radial-gradient(ellipse at 80% 48%, var(--story-tint), transparent 56%), var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 1.5rem;
  overflow: hidden;
}
.story-scene {
  position: relative;
  display: grid;
  grid-template-columns: 1.08fr 1fr;
  align-items: center;
  gap: clamp(2rem, 5vw, 5rem);
  padding: clamp(2rem, 5vw, 4.5rem);
  min-height: 650px;
}
.story-copy {
  position: relative;
  z-index: 2;
  min-width: 0;
}
.story-eyebrow {
  display: flex;
  align-items: center;
  gap: 0.8rem;
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  color: var(--bs-primary);
  margin-bottom: 1.7rem;
}
.story-eyebrow::before {
  content: '';
  width: 1.6rem;
  height: 1px;
  background: currentColor;
  opacity: 0.6;
}
h2 {
  font-size: clamp(2.25rem, 4vw, 4.2rem);
  line-height: 1.12;
  font-weight: 700;
  letter-spacing: -0.045em;
  margin-bottom: 1.5rem;
  max-width: 13ch;
  text-wrap: balance;
}
.scene-0 h2 {
  font-size: clamp(2.6rem, 4.6vw, 4.7rem);
  max-width: 12ch;
}
.scene-3 h2 {
  font-size: clamp(2.2rem, 3.65vw, 3.75rem);
  max-width: 15ch;
}
.scene-4 h2 {
  max-width: 13ch;
}
.story-description {
  font-size: clamp(1rem, 1.2vw, 1.125rem);
  line-height: 1.8;
  max-width: 35ch;
  color: var(--bs-secondary-color);
  margin-bottom: 0;
}
.story-visual {
  position: relative;
  min-width: 0;
  width: 100%;
  max-width: 440px;
  justify-self: center;
}
.story-visual img {
  display: block;
  width: 100%;
  height: auto;
}
.intro-object {
  transform: rotate(-4deg);
  max-width: 500px;
}
.object-halo {
  position: absolute;
  inset: 0 4% 12%;
  border-radius: 50%;
  border: 1px solid var(--story-line);
  background: radial-gradient(ellipse, rgba(var(--bs-primary-rgb), 0.05), transparent 70%);
}
.object-halo::after {
  content: '';
  position: absolute;
  inset: 10%;
  border: 1px solid rgba(var(--bs-primary-rgb), 0.045);
  border-radius: inherit;
}
.intro-object img {
  position: relative;
}
.object-note {
  display: block;
  text-align: center;
  margin-top: 0.8rem;
  font-size: 0.68rem;
  letter-spacing: 0.035em;
  color: var(--bs-secondary-color);
  transform: rotate(4deg);
}
.listing-card {
  max-width: 326px;
  margin: auto;
  padding: 1rem 1rem 1.25rem;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 1.2rem;
  box-shadow: 0 12px 35px rgba(var(--bs-dark-rgb), 0.055);
  transform: rotate(1.5deg);
}
.listing-image {
  border-radius: 0.7rem;
  background: linear-gradient(
    145deg,
    rgba(var(--bs-primary-rgb), 0.035),
    rgba(var(--bs-dark-rgb), 0.035)
  );
  margin-bottom: 1.3rem;
  overflow: hidden;
}
.listing-image img {
  padding: 0.5rem;
}
.card-label,
.mini-category {
  display: block;
  color: var(--bs-secondary-color);
  font-size: 0.59rem;
  letter-spacing: 0.09em;
  margin-bottom: 0.65rem;
}
.listing-card h3 {
  font-size: 1.2rem;
  font-weight: 650;
  margin-bottom: 0.6rem;
}
.listing-price {
  color: var(--bs-primary);
  font-size: 1.1rem;
  font-weight: 650;
}
.listing-shop {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.75rem;
  color: var(--bs-secondary-color);
  margin: 1.1rem 0 0;
  padding-top: 0.9rem;
  border-top: 1px solid var(--bs-border-color);
}
.shop-dot {
  display: grid;
  place-items: center;
  background: var(--story-tint);
  border-radius: 50%;
  width: 26px;
  height: 26px;
  color: var(--bs-primary);
  font-size: 0.7rem;
}
.product-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.9rem;
  transform: rotate(-1.5deg);
  max-width: 410px;
}
.mini-card {
  padding: 0.75rem;
  border: 1px solid var(--bs-border-color);
  border-radius: 0.9rem;
  background: var(--bs-body-bg);
  box-shadow: 0 5px 18px rgba(var(--bs-dark-rgb), 0.025);
}
.mini-card:nth-child(even) {
  transform: translateY(14px);
}
.mini-image {
  height: 130px;
  display: grid;
  place-items: center;
  background: rgba(var(--bs-dark-rgb), 0.035);
  border-radius: 0.5rem;
  margin-bottom: 0.85rem;
  overflow: hidden;
}
.mini-card:first-child .mini-image {
  background: var(--story-tint);
}
.mini-image img {
  height: 100%;
  object-fit: contain;
  padding: 0.2rem;
}
.mini-card strong {
  font-size: 0.8rem;
  font-weight: 600;
  display: block;
}
.mini-category {
  font-size: 0.5rem;
  letter-spacing: 0.055em;
}
.product-illustration {
  width: 85%;
  height: 90%;
  color: var(--bs-body-color);
}
.payment-journey {
  max-width: 390px;
  padding: 1.5rem 1.1rem;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: 1.25rem;
  box-shadow: 0 12px 35px rgba(var(--bs-dark-rgb), 0.04);
}
.journey-product {
  max-width: 205px;
  margin: 0 auto 1rem;
}
.journey-steps {
  display: flex;
  gap: 0;
  justify-content: center;
  flex-wrap: nowrap;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--bs-border-color);
}
.journey-steps span {
  font-size: 0.6rem;
  color: var(--bs-secondary-color);
  white-space: nowrap;
  padding: 0.4rem 0.25rem;
}
.journey-steps span + span::before {
  content: '→';
  margin-right: 0.45rem;
  color: var(--bs-primary);
  opacity: 0.6;
}
.payment-options {
  display: flex;
  justify-content: center;
  gap: 0.65rem;
  margin: 1.25rem 0;
}
.payment-options span {
  border: 1px solid var(--story-line);
  background: var(--story-tint);
  border-radius: 0.6rem;
  padding: 0.55rem 1.5rem;
  color: var(--bs-primary);
  font-size: 0.85rem;
  font-weight: 600;
}
.order-confirmation {
  text-align: center;
  font-size: 0.8rem;
  color: var(--bs-secondary-color);
}
.order-confirmation span {
  display: inline-grid;
  place-items: center;
  width: 23px;
  height: 23px;
  border-radius: 50%;
  background: var(--story-tint);
  color: var(--bs-primary);
  margin-right: 0.5rem;
  font-size: 0.7rem;
}
.story-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 2rem;
}
.story-actions .btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  font-size: 0.9rem;
  font-weight: 600;
  padding: 0.95rem 1.3rem;
  border-radius: 0.8rem;
  min-height: 52px;
}
.story-actions .btn-primary {
  box-shadow: 0 5px 16px rgba(var(--bs-primary-rgb), 0.14);
}
.story-actions .btn-outline-primary {
  --bs-btn-border-color: rgba(var(--bs-primary-rgb), 0.35);
  background: var(--bs-body-bg);
}
.story-actions .btn-outline-primary:hover {
  background: var(--bs-primary);
}
.story-actions .btn:focus-visible {
  outline: 3px solid rgba(var(--bs-primary-rgb), 0.4);
  outline-offset: 4px;
}
.final-object {
  text-align: center;
}
.reuse-ring {
  position: relative;
  display: grid;
  place-items: center;
  width: min(100%, 340px);
  aspect-ratio: 1;
  margin: auto auto 1.5rem;
  border: 1px solid var(--story-line);
  border-radius: 50%;
  background: radial-gradient(circle, var(--story-tint), transparent 70%);
}
.reuse-ring img {
  width: 105%;
  max-width: none;
  transform: rotate(-4deg);
}
.reuse-mark {
  position: absolute;
  right: 5%;
  bottom: 9%;
  display: grid;
  place-items: center;
  width: 54px;
  height: 54px;
  border-radius: 50%;
  color: var(--bs-primary);
  background: var(--bs-body-bg);
  border: 1px solid var(--story-line);
  font-size: 1.6rem;
}
.final-object p {
  font-size: 0.68rem;
  letter-spacing: 0.08em;
  color: var(--bs-secondary-color);
}
.story-footnote {
  position: absolute;
  bottom: 1.5rem;
  left: 1rem;
  right: 1rem;
  text-align: center;
  font-size: 0.62rem;
  letter-spacing: 0.025em;
  color: var(--bs-secondary-color);
  margin: 0;
}
.scroll-hint span {
  display: block;
  font-size: 1rem;
  margin-top: 0.5rem;
}
.story-skip:focus {
  position: absolute;
  z-index: 5;
  top: 1rem;
  left: 1rem;
  padding: 0.8rem;
  background: var(--bs-body-bg);
  border-radius: 0.5rem;
}
.is-animated .story-stage {
  height: 100svh;
  min-height: 580px;
  border-radius: 1.5rem;
}
.is-animated .story-scene {
  position: absolute;
  inset: 0;
  min-height: 0;
  height: 100%;
  will-change: transform, opacity;
}
@media (min-width: 768px) and (max-width: 1099px) {
  .story-scene {
    gap: 1.5rem;
    padding: 2.2rem;
    grid-template-columns: 1.05fr 1fr;
  }
  h2,
  .scene-0 h2 {
    font-size: clamp(2.25rem, 4.2vw, 3rem);
  }
  .scene-3 h2 {
    font-size: clamp(2rem, 3.8vw, 2.7rem);
  }
  .story-description {
    font-size: 0.95rem;
  }
  .mini-image {
    height: 105px;
  }
  .mini-category {
    font-size: 0.46rem;
    letter-spacing: 0;
  }
  .mini-card strong {
    font-size: 0.75rem;
  }
  .payment-journey {
    padding: 1rem 0.6rem;
  }
  .journey-steps span {
    font-size: 0.52rem;
    padding-inline: 0.15rem;
  }
  .journey-steps span + span::before {
    margin-right: 0.25rem;
  }
  .reuse-ring {
    max-width: 260px;
  }
  .story-actions {
    gap: 0.6rem;
  }
  .story-actions .btn {
    font-size: 0.8rem;
    padding: 0.8rem 1rem;
  }
}
@media (max-width: 767px) {
  .home-story {
    margin-bottom: 1.5rem;
  }
  .story-stage {
    border-radius: 1.2rem;
  }
  .story-scene {
    grid-template-columns: 1fr;
    gap: 2rem;
    min-height: 0;
    padding: 2.5rem 1.3rem 4rem;
    border-bottom: 1px solid var(--bs-border-color);
  }
  .story-scene:last-child {
    border-bottom: 0;
  }
  h2,
  .scene-0 h2 {
    font-size: clamp(2rem, 8.6vw, 3rem);
    max-width: 16ch;
    line-height: 1.13;
    margin-bottom: 1.2rem;
  }
  .scene-3 h2 {
    font-size: clamp(1.9rem, 8vw, 2.8rem);
  }
  .story-eyebrow {
    margin-bottom: 1.2rem;
    font-size: 0.6rem;
  }
  .story-description {
    font-size: 0.95rem;
    line-height: 1.75;
  }
  .story-visual {
    max-width: 320px;
    margin: auto;
  }
  .intro-object {
    max-width: 360px;
  }
  .listing-card {
    max-width: 260px;
  }
  .product-grid {
    gap: 0.7rem;
  }
  .mini-card {
    padding: 0.65rem;
  }
  .mini-image {
    height: 105px;
  }
  .mini-category {
    font-size: 0.45rem;
    letter-spacing: 0;
  }
  .mini-card strong {
    font-size: 0.72rem;
  }
  .payment-journey {
    padding: 1.25rem 0.65rem;
  }
  .journey-steps span {
    font-size: 0.57rem;
    padding-inline: 0.2rem;
  }
  .journey-steps span + span::before {
    margin-right: 0.3rem;
  }
  .final-object .reuse-ring {
    max-width: 220px;
    margin-bottom: 1rem;
  }
  .reuse-mark {
    width: 42px;
    height: 42px;
    font-size: 1.25rem;
  }
  .story-actions {
    flex-direction: column;
    align-items: stretch;
    margin-top: 1.5rem;
  }
  .story-footnote {
    bottom: 1.15rem;
    font-size: 0.58rem;
  }
}
@media (prefers-reduced-motion: reduce) {
  .intro-object,
  .product-grid,
  .listing-card,
  .reuse-ring img {
    transform: none;
  }
  .story-scene {
    border-bottom: 1px solid var(--bs-border-color);
  }
}
</style>

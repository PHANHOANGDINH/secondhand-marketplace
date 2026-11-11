<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'
import { listCategories } from '../../api/category.api'
import { listProducts } from '../../api/product.api'
import { areaPickerOpen, useArea } from '../../composables/useArea'
import ProductSearch from '../ProductSearch.vue'
import AppIcon from '../ui/AppIcon.vue'
import HomePromoBanner from './HomePromoBanner.vue'
// The forest field: the headline, what Passdi is, the search and how many listings wait for a new owner
// right now (read from the catalogue, never invented). Then the category row, which hides if categories fail.
const router = useRouter()
const auth = useAuthStore()
// The same entrance as the quick-listing slide: signed-in users get the dialog, guests sign in first.
const postTo = computed(() =>
  auth.isAuthenticated
    ? { path: '/', query: { 'quick-listing': '1' } }
    : { path: '/login', query: { redirect: '/?quick-listing=1' } },
)
const { code, label } = useArea()
const keyword = ref(''),
  categories = ref([]),
  track = ref(null),
  waiting = ref(null)
const count = (value) => new Intl.NumberFormat('vi-VN').format(value)
const iconFor = (name) => {
  const text = String(name || '').toLocaleLowerCase('vi-VN')
  if (/điện lạnh|tủ lạnh|máy giặt|điều hòa/.test(text)) return 'fridge'
  if (/xe cộ|xe máy|xe đạp/.test(text)) return 'motorbike'
  if (/nội thất|sofa|giường/.test(text)) return 'sofa'
  if (/mẹ|bé/.test(text)) return 'stroller'
  if (/thể thao|dã ngoại/.test(text)) return 'dumbbell'
  if (/thú cưng/.test(text)) return 'paw'
  if (/văn phòng/.test(text)) return 'desk'
  if (/nhạc/.test(text)) return 'guitar'
  if (/điện|máy|công nghệ|phone/.test(text)) return 'device'
  if (/thời trang|quần|áo|giày|túi/.test(text)) return 'shirt'
  if (/gia dụng|nhà|bếp/.test(text)) return 'home'
  if (/sách|học/.test(text)) return 'book'
  return 'tag'
}
const search = () =>
  router.push({
    path: '/products',
    query: {
      ...(keyword.value.trim() ? { q: keyword.value.trim() } : {}),
      ...(code.value ? { province: code.value } : {}),
    },
  })
const next = () => track.value?.scrollBy({ left: track.value.clientWidth * 0.8, behavior: 'smooth' })
onMounted(async () => {
  listProducts({ per_page: 1 })
    .then((result) => {
      const total = Number(result?.meta?.total)
      waiting.value = Number.isFinite(total) && total > 0 ? total : null
    })
    .catch(() => {})
  try {
    const result = await listCategories()
    categories.value = Array.isArray(result?.data) ? result.data : []
  } catch {
    categories.value = []
  }
})
</script>
<template>
  <section class="home-hero" aria-labelledby="home-hero-title">
    <div class="home-banner">
      <div class="home-banner-copy">
      <h2 id="home-hero-title">Mỗi món đồ cũ là một đời mới cho ai đó.</h2>
      <p class="home-banner-lead">
        Mua bán đồ đã qua sử dụng gần bạn, thanh toán an toàn ngay trên sàn.
      </p>
    <form class="home-search" role="search" aria-label="Tìm tin đăng" @submit.prevent="search">
        <ProductSearch id="home-product-search" v-model="keyword" class="home-search-input" @submit="search" />
        <button
          type="button"
          class="home-search-area"
          data-area-open
          :aria-label="`Chọn khu vực, đang chọn ${label}`"
          @click="areaPickerOpen = true"
        >
          <AppIcon name="pin" :size="18" /><span>{{ code ? label : 'Chọn khu vực' }}</span
          ><AppIcon name="chevronDown" :size="14" />
        </button>
        <button type="submit" class="btn home-search-submit">
          <AppIcon name="search" :size="18" /> Tìm kiếm
        </button>
      </form>
        <div class="home-cta">
          <RouterLink :to="postTo" class="home-post-cta"
            ><AppIcon name="upload" :size="20" />Đăng tin</RouterLink
          >
          <span class="home-cta-note">Miễn phí, hiện ngay, không chờ duyệt.</span>
        </div>
      </div>
      <p v-if="waiting" class="home-banner-count" data-waiting>
        <strong>{{ count(waiting) }}</strong>
        <span>món đồ đang chờ chủ mới</span>
      </p>
    </div>
    <HomePromoBanner class="home-promo" />
    <div v-if="categories.length" class="home-categories">
      <nav ref="track" class="home-categories-track" aria-label="Danh mục nổi bật">
        <RouterLink
          v-for="category in categories"
          :key="category.id"
          :to="{ path: '/products', query: { category: category.id, ...(code ? { province: code } : {}) } }"
          class="home-category"
          ><span class="home-category-icon" aria-hidden="true"
            ><AppIcon :name="iconFor(category.name)" :size="26" /></span
          ><span class="home-category-name">{{ category.name }}</span></RouterLink
        >
      </nav>
      <button type="button" class="home-categories-next" aria-label="Xem thêm danh mục" @click="next">
        <AppIcon name="chevronRight" :size="18" />
      </button>
    </div>
  </section>
</template>
<style scoped>
.home-hero {
  display: grid;
  /* Without this the scrolling category row sets the column width and the page overflows on phones. */
  grid-template-columns: minmax(0, 1fr);
  margin-bottom: var(--space-6);
}
/* One field of forest green, the page's single large block of colour. */
.home-banner {
  display: grid;
  grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr);
  align-items: end;
  gap: var(--space-8);
  margin-top: var(--space-2);
  padding: clamp(1.75rem, 4vw, 3.25rem);
  border-radius: 32px;
  background: var(--field);
  color: var(--on-field);
}
.home-banner h2 {
  max-width: 17ch;
  margin: 0;
  font-family: var(--font-display);
  font-size: clamp(2rem, 4.2vw, 3.4rem);
  font-weight: 500;
  line-height: 1.06;
  letter-spacing: -0.015em;
  color: var(--on-field);
}
.home-banner-lead {
  max-width: 46ch;
  margin: var(--space-4) 0 var(--space-6);
  color: var(--on-field-muted);
  font-size: 1.05rem;
  line-height: 1.6;
}
.home-banner-count {
  display: grid;
  gap: var(--space-1);
  justify-items: start;
  margin: 0;
}
.home-banner-count strong {
  font-family: var(--font-display);
  font-size: clamp(2.6rem, 5vw, 4rem);
  font-weight: 500;
  line-height: 1;
  color: var(--on-field);
  font-variant-numeric: tabular-nums;
}
.home-banner-count span {
  color: var(--on-field-muted);
}
.home-search {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  padding: 0.4rem 0.4rem 0.4rem 1rem;
  border: 1px solid var(--field-edge);
  border-radius: var(--radius-pill);
  background: var(--surface);
  color: var(--text);
}
.home-search-input {
  flex: 1;
  min-width: 0;
}
.home-search-input :deep(input) {
  min-height: 48px;
  border: 0;
  background: transparent;
  box-shadow: none;
}
.home-search-input :deep(input:focus-visible) {
  outline: var(--focus-ring);
  outline-offset: -2px;
}
.home-search-area {
  flex: none;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  max-width: 14rem;
  min-height: 48px;
  padding: 0 1rem;
  border: 0;
  border-left: 1px solid var(--border);
  border-radius: 0;
  background: transparent;
  color: var(--text);
  font-size: 0.9rem;
  font-weight: 500;
}
.home-search-area span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.home-search-area:hover {
  border-color: var(--band-ink);
}
.home-search-submit {
  --bs-btn-color: var(--on-primary);
  --bs-btn-bg: var(--primary);
  --bs-btn-border-color: var(--primary);
  --bs-btn-hover-color: var(--on-primary);
  --bs-btn-hover-bg: var(--primary-hover);
  --bs-btn-hover-border-color: var(--primary-hover);
  --bs-btn-active-color: var(--on-primary);
  --bs-btn-active-bg: var(--primary-hover);
  --bs-btn-active-border-color: var(--primary-hover);
  border-radius: var(--radius-pill);
  flex: none;
  min-height: 48px;
  padding-inline: 1.4rem;
  font-weight: 600;
}
.home-search :is(button):focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.home-categories {
  position: relative;
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.home-categories-track {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  scroll-snap-type: x proximity;
  scrollbar-width: none;
}
.home-categories-track::-webkit-scrollbar {
  display: none;
}
.home-category {
  flex: 0 0 7.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-2);
  border-radius: var(--radius-lg);
  color: var(--text);
  text-align: center;
  text-decoration: none;
  scroll-snap-align: start;
  transition:
    background-color var(--transition-fast),
    color var(--transition-fast);
}
.home-category:hover {
  background: var(--surface-2);
  color: var(--primary);
}
.home-category:focus-visible {
  outline: var(--focus-ring);
  outline-offset: -2px;
}
.home-category-icon {
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: var(--radius-lg);
  background: var(--band-soft);
  color: var(--band-ink);
}
.home-category-name {
  font-size: 0.8rem;
  font-weight: 600;
  line-height: 1.35;
  overflow-wrap: anywhere;
}
.home-categories-next {
  position: absolute;
  top: 50%;
  right: -0.75rem;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  border: 1px solid var(--border);
  border-radius: 50%;
  background: var(--surface);
  color: var(--text);
  transform: translateY(-50%);
}
.home-categories-next:hover {
  border-color: var(--primary);
  color: var(--primary);
}
.home-categories-next:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
/* The post-a-listing entrance: a light pill on the forest field, always in sight on the hero. */
.home-cta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-4);
  margin-top: var(--space-4);
}
.home-post-cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  min-height: 52px;
  padding: 0 var(--space-6);
  border-radius: var(--radius-pill);
  background: var(--on-field);
  color: var(--field);
  font-size: 1rem;
  font-weight: 700;
  text-decoration: none;
  transition: background-color var(--transition-fast);
}
.home-post-cta:hover {
  background: var(--on-field-muted);
  color: var(--field);
}
.home-post-cta:focus-visible {
  outline: 3px solid var(--on-field);
  outline-offset: 3px;
}
.home-cta-note {
  color: var(--on-field-muted);
  font-size: var(--font-size-body);
}
@media (max-width: 767px) {
  /* The rotating banner repeats the hero on a phone and pushes the listings down. */
  .home-promo {
    display: none;
  }
  .home-hero {
    margin-bottom: var(--space-4);
  }
  .home-banner {
    padding: var(--space-5);
  }
  .home-banner h2 {
    font-size: 1.9rem;
  }
  .home-banner-lead {
    margin: var(--space-3) 0 var(--space-4);
    font-size: 1rem;
  }
  .home-banner-count {
    display: flex;
    align-items: baseline;
    gap: var(--space-2);
  }
  .home-banner-count strong {
    font-size: 1.6rem;
  }
  .home-post-cta {
    flex: 1 0 100%;
  }
  .home-search {
    flex-wrap: wrap;
  }
  .home-search-input {
    flex: 1 0 100%;
  }
  .home-search-area {
    flex: 1;
    max-width: none;
  }
  .home-search-submit {
    flex: 1 0 100%;
  }
  .home-banner {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-5);
    border-radius: var(--radius-xl);
  }
  .home-search {
    border-radius: var(--radius-lg);
    padding: 0.5rem;
  }
  .home-search-area {
    border-left: 0;
  }
  .home-categories {
    margin-top: var(--space-3);
    padding: var(--space-2) 0;
  }
  .home-categories-track {
    padding-inline: var(--space-2);
    -webkit-mask-image: linear-gradient(to right, #000 calc(100% - 2rem), transparent);
    mask-image: linear-gradient(to right, #000 calc(100% - 2rem), transparent);
  }
  .home-category {
    flex-basis: 5.25rem;
    padding: var(--space-2) var(--space-1);
  }
  .home-category-icon {
    width: 48px;
    height: 48px;
  }
  .home-categories-next {
    display: none;
  }
}
</style>

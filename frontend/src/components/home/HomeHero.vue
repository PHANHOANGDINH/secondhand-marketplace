<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { listCategories } from '../../api/category.api'
import { areaPickerOpen, useArea } from '../../composables/useArea'
import ProductSearch from '../ProductSearch.vue'
import AppIcon from '../ui/AppIcon.vue'
// Primary band with the slogan and the large search box, then the category row.
// Categories are optional: the row hides if they cannot be loaded.
const router = useRouter()
const { code, label } = useArea()
const keyword = ref(''),
  categories = ref([]),
  track = ref(null)
// Decorative tiles on the banner (our own icon set), hidden on small screens.
const art = [
  ['box', 'a'],
  ['shirt', 'b'],
  ['tag', 'c'],
  ['book', 'd'],
  ['device', 'e'],
  ['home', 'f'],
]
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
      <span v-for="[icon, place] in art" :key="place" class="home-banner-art" :class="`is-${place}`" aria-hidden="true"
        ><AppIcon :name="icon" :size="30"
      /></span>
      <h2 id="home-hero-title">Đồ cũ giá tốt, gần bạn, chốt nhanh!</h2>
    </div>
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
/* The banner continues the header band edge to edge: the colour is painted with a clipped spread shadow,
   so nothing is wider than the page. */
.home-banner {
  position: relative;
  margin-top: -3rem;
  padding: clamp(1.25rem, 3vw, 2.25rem) var(--space-4) 5.25rem;
  background: var(--band);
  box-shadow: 0 0 0 100vmax var(--band);
  clip-path: inset(0 -100vmax);
  text-align: center;
}
.home-banner-art {
  position: absolute;
  display: grid;
  place-items: center;
  width: 60px;
  height: 60px;
  border-radius: 18px;
  background: rgb(255 255 255 / 55%);
  color: var(--band-ink);
}
.home-banner-art.is-a {
  top: 14%;
  left: 2%;
  transform: rotate(-14deg);
}
.home-banner-art.is-b {
  top: 46%;
  left: 11%;
  transform: rotate(10deg) scale(0.8);
}
.home-banner-art.is-c {
  top: 6%;
  left: 15%;
  transform: rotate(18deg) scale(0.7);
}
.home-banner-art.is-d {
  top: 6%;
  right: 15%;
  transform: rotate(-16deg) scale(0.7);
}
.home-banner-art.is-e {
  top: 44%;
  right: 10%;
  transform: rotate(-8deg) scale(0.8);
}
.home-banner-art.is-f {
  top: 14%;
  right: 2%;
  transform: rotate(12deg);
}
.home-banner h2 {
  position: relative;
  margin: 0;
  font-size: clamp(1.4rem, 3.2vw, 2.5rem);
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.03em;
  color: var(--band-ink);
}
.home-search {
  display: flex;
  position: relative;
  z-index: 1;
  align-items: center;
  gap: var(--space-3);
  justify-self: center;
  width: min(100%, 60rem);
  margin: -3.25rem 0 var(--space-6);
  padding: 0.9rem 1.1rem;
  border-radius: 20px;
  background: var(--surface);
  box-shadow: 0 6px 20px rgb(21 22 26 / 9%);
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
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--text);
  font-size: 0.9rem;
  font-weight: 600;
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
  --bs-btn-color: var(--band-ink);
  --bs-btn-bg: var(--band);
  --bs-btn-border-color: var(--band);
  --bs-btn-hover-color: var(--band-ink);
  --bs-btn-hover-bg: var(--band-hover);
  --bs-btn-hover-border-color: var(--band-hover);
  --bs-btn-active-color: var(--band-ink);
  --bs-btn-active-bg: var(--band-hover);
  --bs-btn-active-border-color: var(--band-hover);
  border-radius: var(--radius-md);
  flex: none;
  min-height: 48px;
  padding-inline: 1.25rem;
  font-weight: 700;
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
  font-size: 0.82rem;
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
@media (max-width: 767px) {
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
  .home-banner-art {
    display: none;
  }
  .home-categories-next {
    right: 0.25rem;
  }
}
</style>

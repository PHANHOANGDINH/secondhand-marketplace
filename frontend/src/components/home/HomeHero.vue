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
const iconFor = (name) => {
  const text = String(name || '').toLocaleLowerCase('vi-VN')
  if (/điện|máy|công nghệ|phone/.test(text)) return 'device'
  if (/thời trang|quần|áo|giày|túi/.test(text)) return 'shirt'
  if (/gia dụng|nhà|nội thất|bếp/.test(text)) return 'home'
  if (/sách|văn phòng|học/.test(text)) return 'book'
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
      <h2 id="home-hero-title">Đồ cũ giá tốt, gần bạn, chốt nhanh!</h2>
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
    </div>
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
  gap: var(--space-5);
  margin-bottom: var(--space-8);
}
.home-banner {
  padding: clamp(1.5rem, 4vw, 3rem) clamp(1rem, 4vw, 3rem);
  border-radius: var(--radius-xl);
  background: var(--primary);
  text-align: center;
}
.home-banner h2 {
  margin-bottom: var(--space-5);
  font-size: clamp(1.4rem, 3.2vw, 2.4rem);
  font-weight: 800;
  line-height: 1.2;
  letter-spacing: -0.03em;
  color: #fff;
}
.home-search {
  display: flex;
  align-items: stretch;
  gap: var(--space-2);
  max-width: 52rem;
  margin-inline: auto;
  padding: var(--space-2);
  border-radius: var(--radius-lg);
  background: var(--surface);
  text-align: left;
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
  max-width: 13rem;
  min-height: 48px;
  padding: 0 0.9rem;
  border: 0;
  border-left: 1px solid var(--border);
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
  color: var(--primary);
}
.home-search-submit {
  --bs-btn-color: var(--accent-ink);
  --bs-btn-bg: var(--accent);
  --bs-btn-border-color: var(--accent);
  --bs-btn-hover-color: var(--accent-ink);
  --bs-btn-hover-bg: #d6ff6e;
  --bs-btn-hover-border-color: #d6ff6e;
  --bs-btn-active-color: var(--accent-ink);
  --bs-btn-active-bg: #b5ee2c;
  --bs-btn-active-border-color: #b5ee2c;
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
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
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
  background: var(--primary-soft);
  color: var(--primary);
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
    border-left: 0;
    border-top: 1px solid var(--border);
  }
  .home-categories-next {
    right: 0.25rem;
  }
}
</style>

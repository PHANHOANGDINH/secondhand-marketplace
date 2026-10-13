<script setup>
import { onMounted, ref } from 'vue'
import { listCategories } from '../../api/category.api'
import { brand } from '../../config/brand'
import AppIcon from '../ui/AppIcon.vue'
// Banner plus the category grid. Categories are optional: the grid hides if they cannot be loaded.
const categories = ref([])
// Bound, not static, so the bundler leaves this public-folder path alone.
const art = '/home-story-camera.svg'
const iconFor = (name) => {
  const text = String(name || '').toLocaleLowerCase('vi-VN')
  if (/điện|máy|công nghệ|phone/.test(text)) return 'device'
  if (/thời trang|quần|áo|giày|túi/.test(text)) return 'shirt'
  if (/gia dụng|nhà|nội thất|bếp/.test(text)) return 'home'
  if (/sách|văn phòng|học/.test(text)) return 'book'
  return 'tag'
}
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
      <div class="home-banner-copy">
        <p class="home-banner-eyebrow">{{ brand.slogan }}</p>
        <h2 id="home-hero-title">Món đồ bạn không dùng nữa đang có người tìm.</h2>
        <p class="home-banner-text">
          Đăng bán trong vài bước, mua lại đồ còn tốt từ các gian hàng đã được kiểm duyệt.
        </p>
        <div class="home-banner-actions">
          <RouterLink to="/products" class="btn home-banner-primary"
            >Khám phá sản phẩm <AppIcon name="arrowRight" :size="18"
          /></RouterLink>
          <RouterLink to="/seller/shop" class="btn home-banner-secondary">Mở gian hàng</RouterLink>
        </div>
      </div>
      <img
        class="home-banner-art"
        :src="art"
        alt=""
        width="320"
        height="240"
        loading="eager"
      />
    </div>
    <nav v-if="categories.length" class="home-categories" aria-label="Danh mục nổi bật">
      <RouterLink
        v-for="category in categories"
        :key="category.id"
        :to="{ path: '/products', query: { category: category.id } }"
        class="home-category"
        ><span class="home-category-icon" aria-hidden="true"
          ><AppIcon :name="iconFor(category.name)" :size="26" /></span
        ><span class="home-category-name">{{ category.name }}</span></RouterLink
      >
    </nav>
  </section>
</template>
<style scoped>
.home-hero {
  display: grid;
  gap: var(--space-6);
  margin-bottom: var(--space-10);
}
.home-banner {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr);
  align-items: center;
  gap: var(--space-6);
  padding: clamp(1.5rem, 4vw, 3rem);
  border-radius: var(--radius-xl);
  background: var(--primary);
  color: #fff;
  overflow: hidden;
}
.home-banner-eyebrow {
  display: inline-block;
  margin-bottom: var(--space-4);
  padding: 0.25rem 0.7rem;
  border-radius: var(--radius-pill);
  background: var(--accent);
  color: var(--accent-ink);
  font-size: 0.75rem;
  font-weight: 700;
}
.home-banner h2 {
  margin-bottom: var(--space-4);
  font-size: clamp(1.6rem, 3.4vw, 2.75rem);
  font-weight: 800;
  line-height: 1.15;
  letter-spacing: -0.035em;
  color: #fff;
}
.home-banner-text {
  max-width: 46ch;
  margin-bottom: var(--space-6);
  color: rgb(255 255 255 / 88%);
  line-height: 1.7;
}
.home-banner-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
}
.home-banner-primary {
  --bs-btn-color: var(--accent-ink);
  --bs-btn-bg: var(--accent);
  --bs-btn-border-color: var(--accent);
  --bs-btn-hover-color: var(--accent-ink);
  --bs-btn-hover-bg: #d6ff6e;
  --bs-btn-hover-border-color: #d6ff6e;
  --bs-btn-active-color: var(--accent-ink);
  --bs-btn-active-bg: #b5ee2c;
  --bs-btn-active-border-color: #b5ee2c;
  padding-inline: 1.25rem;
  font-weight: 700;
}
.home-banner-secondary {
  --bs-btn-color: #fff;
  --bs-btn-border-color: rgb(255 255 255 / 55%);
  --bs-btn-hover-color: var(--primary);
  --bs-btn-hover-bg: #fff;
  --bs-btn-hover-border-color: #fff;
  --bs-btn-active-color: var(--primary);
  --bs-btn-active-bg: #fff;
  --bs-btn-active-border-color: #fff;
  padding-inline: 1.25rem;
}
.home-banner :is(a, button):focus-visible {
  outline: 3px solid var(--accent);
  outline-offset: 3px;
}
.home-banner-art {
  justify-self: end;
  width: min(100%, 340px);
  height: auto;
  padding: var(--space-4);
  border-radius: var(--radius-xl);
  background: #fff;
}
.home-categories {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(128px, 1fr));
  gap: var(--space-3);
}
.home-category {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
  padding: var(--space-4) var(--space-3);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  color: var(--text);
  text-align: center;
  text-decoration: none;
  transition:
    border-color var(--transition-fast),
    color var(--transition-fast);
}
.home-category:hover {
  border-color: var(--primary);
  color: var(--primary);
}
.home-category:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.home-category-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: var(--radius-lg);
  background: var(--primary-soft);
  color: var(--primary);
}
.home-category-name {
  font-size: 0.85rem;
  font-weight: 600;
  line-height: 1.35;
  overflow-wrap: anywhere;
}
@media (max-width: 767px) {
  .home-banner {
    grid-template-columns: 1fr;
  }
  .home-banner-art {
    display: none;
  }
  .home-categories {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
@media (max-width: 380px) {
  .home-categories {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>

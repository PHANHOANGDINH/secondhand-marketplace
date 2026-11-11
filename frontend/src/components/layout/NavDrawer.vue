<script setup>
import { onMounted, ref } from 'vue'
import AccessibleDialog from '../ui/AccessibleDialog.vue'
import AppIcon from '../ui/AppIcon.vue'
import { categoryOptions } from '../../api/catalogOptions'
// Side drawer behind the menu button: every category plus the main destinations.
defineEmits(['dismiss'])
const categories = ref([]),
  busy = ref(true),
  failed = ref(false)
async function load() {
  busy.value = true
  failed.value = false
  try {
    categories.value = await categoryOptions()
  } catch {
    categories.value = []
    failed.value = true
  } finally {
    busy.value = false
  }
}
onMounted(load)
</script>
<template>
  <AccessibleDialog
    id="nav-drawer"
    class="ui-drawer"
    title="Danh mục"
    description="Tất cả danh mục và các trang chính."
    @dismiss="$emit('dismiss')"
  >
    <nav class="nav-drawer-section" aria-label="Trang chính" @click="$emit('dismiss')">
      <RouterLink to="/"><AppIcon name="home" :size="18" />Trang chủ</RouterLink>
      <RouterLink to="/products"><AppIcon name="tag" :size="18" />Tất cả tin đăng</RouterLink>
      <RouterLink to="/shops"><AppIcon name="store" :size="18" />Gian hàng</RouterLink>
      <RouterLink to="/auctions"><AppIcon name="gavel" :size="18" />Sàn đấu giá</RouterLink>
      <RouterLink to="/?discovery=video#home-discovery"
        ><AppIcon name="play" :size="18" />Video đánh giá</RouterLink
      >
    </nav>
    <h3 class="nav-drawer-title">Tất cả danh mục</h3>
    <p v-if="busy" role="status" class="text-muted">Đang tải danh mục…</p>
    <template v-else-if="failed">
      <p class="text-muted">Chưa tải được danh mục.</p>
      <button type="button" class="btn btn-outline-secondary" data-retry @click="load">
        <AppIcon name="refresh" :size="18" />Tải lại
      </button>
    </template>
    <p v-else-if="!categories.length" class="text-muted">Chưa có danh mục.</p>
    <nav v-else class="nav-drawer-section" aria-label="Tất cả danh mục" @click="$emit('dismiss')">
      <RouterLink
        v-for="category in categories"
        :key="category.value"
        :to="{ path: '/products', query: { category: category.value } }"
        :class="{ 'is-child': category.label.startsWith('—') }"
        >{{ category.label.replace(/^(— )+/, '') }}</RouterLink
      >
    </nav>
  </AccessibleDialog>
</template>
<style scoped>
.nav-drawer-section {
  display: grid;
}
.nav-drawer-section a {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  min-height: 44px;
  padding: 0 var(--space-3);
  border-radius: var(--radius-sm);
  color: var(--text);
  font-weight: 600;
  text-decoration: none;
}
.nav-drawer-section a.is-child {
  padding-left: var(--space-8);
  color: var(--muted);
  font-weight: 500;
}
.nav-drawer-section a:hover {
  background: var(--surface-2);
  color: var(--primary);
}
.nav-drawer-title {
  margin: var(--space-5) 0 var(--space-2);
  padding: var(--space-4) var(--space-3) 0;
  border-top: 1px solid var(--border);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0;
  text-transform: none;
  color: var(--muted);
}
</style>
<style>
/* A dialog given class="ui-drawer" is a panel docked to the left edge. */
.ui-dialog-backdrop.ui-drawer {
  place-items: stretch start;
  padding: 0;
}
.ui-dialog-backdrop.ui-drawer .ui-dialog {
  width: min(22rem, 100%);
  height: 100dvh;
  max-height: 100dvh;
  border: 0;
  border-right: 1px solid var(--border);
  border-radius: 0;
}
</style>

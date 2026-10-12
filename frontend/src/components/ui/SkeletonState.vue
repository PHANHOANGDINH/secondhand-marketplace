<script setup>
import SkeletonBlock from './SkeletonBlock.vue'
defineProps({
  layout: { type: String, default: 'cards' },
  count: { type: Number, default: 4 },
  label: { type: String, default: 'Đang tải dữ liệu…' },
})
</script>
<template>
  <div class="ui-skeleton-state" role="status" aria-live="polite" :aria-label="label">
    <span class="visually-hidden">{{ label }}</span>
    <div v-if="layout === 'detail'" class="row g-4" aria-hidden="true">
      <div class="col-md-6"><SkeletonBlock shape="image" /></div>
      <div class="col-md-6 ui-skeleton-lines">
        <SkeletonBlock /><SkeletonBlock shape="number" /><SkeletonBlock
          v-for="line in 5"
          :key="line"
        /><SkeletonBlock shape="number" />
      </div>
    </div>
    <div v-else-if="layout === 'cart'" class="row g-4" aria-hidden="true">
      <div class="col-lg-8">
        <div v-for="row in 2" :key="row" class="ui-skeleton-cart">
          <SkeletonBlock shape="image" />
          <div class="ui-skeleton-lines">
            <SkeletonBlock /><SkeletonBlock shape="number" /><SkeletonBlock />
          </div>
        </div>
      </div>
      <div class="col-lg-4 ui-skeleton-lines">
        <SkeletonBlock shape="number" /><SkeletonBlock v-for="line in 3" :key="line" />
      </div>
    </div>
    <div v-else-if="layout === 'form'" class="ui-skeleton-lines" aria-hidden="true">
      <SkeletonBlock shape="number" /><SkeletonBlock v-for="line in 5" :key="line" />
    </div>
    <div v-else class="row g-3" aria-hidden="true">
      <div v-for="card in count" :key="card" class="col-6 col-lg-3">
        <div class="ui-skeleton-card">
          <SkeletonBlock v-if="layout !== 'summary'" shape="image" /><SkeletonBlock /><SkeletonBlock
            shape="number"
          /><SkeletonBlock v-if="layout !== 'summary'" />
        </div>
      </div>
    </div>
  </div>
</template>
<style scoped>
.ui-skeleton-state {
  margin-block: var(--space-4, 1rem);
}
.ui-skeleton-card,
.ui-skeleton-lines {
  display: grid;
  align-content: start;
  gap: var(--space-4, 1rem);
}
.ui-skeleton-card {
  padding: var(--space-4, 1rem);
  border: 1px solid var(--border, #dee2e6);
  border-radius: var(--radius-lg, 1rem);
}
.ui-skeleton-lines {
  padding-block: var(--space-4, 1rem);
}
.ui-skeleton-cart {
  display: grid;
  grid-template-columns: 5.5rem minmax(0, 1fr);
  gap: var(--space-4, 1rem);
  margin-bottom: var(--space-6, 1.5rem);
}
</style>

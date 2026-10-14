<script setup>
import { computed } from 'vue'
import AppIcon from './AppIcon.vue'
// Filters folded into a small card ("phiếu"): its summary says how many filters are on and which,
// and opening it shows the page's own filter form unchanged.
const props = defineProps({
  // [{ label: 'Trạng thái', value: 'Chờ xem xét' }, …] for the filters that are on; value true shows the name only.
  active: { type: Array, default: () => [] },
  open: Boolean,
})
const applied = computed(() =>
  props.active.filter((row) => row && row.value !== '' && row.value != null),
)
</script>
<template>
  <details class="filter-sheet" :open="open || undefined">
    <summary class="filter-sheet-summary">
      <span class="filter-sheet-title"
        ><AppIcon name="sliders" :size="18" />Bộ lọc<span
          v-if="applied.length"
          class="filter-sheet-count"
          >{{ applied.length }}</span
        ></span
      >
      <span v-if="applied.length" class="filter-sheet-chips">
        <span v-for="row in applied" :key="row.label" class="filter-sheet-chip"
          >{{ row.label
          }}<template v-if="row.value !== true"
            >: <strong>{{ row.value }}</strong></template
          ></span
        >
      </span>
      <span v-else class="filter-sheet-hint">Chưa áp dụng bộ lọc nào · bấm để chọn</span>
      <AppIcon name="chevronDown" :size="16" class="filter-sheet-caret" />
    </summary>
    <div class="filter-sheet-body"><slot /></div>
  </details>
</template>
<style scoped>
.filter-sheet {
  margin-bottom: var(--space-4);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
}
.filter-sheet-summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--space-2) var(--space-3);
  min-height: 52px;
  padding: var(--space-2) var(--space-4);
  cursor: pointer;
  list-style: none;
  border-radius: var(--radius-lg);
}
.filter-sheet-summary::-webkit-details-marker {
  display: none;
}
.filter-sheet-summary:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
.filter-sheet-title {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  font-weight: 800;
}
.filter-sheet-count {
  display: inline-grid;
  place-items: center;
  min-width: 1.4rem;
  height: 1.4rem;
  padding: 0 0.35rem;
  border-radius: var(--radius-pill);
  background: var(--band);
  color: var(--band-ink);
  font-size: 0.75rem;
}
.filter-sheet-chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-1);
  min-width: 0;
}
.filter-sheet-chip {
  padding: 0.15rem 0.55rem;
  border-radius: var(--radius-pill);
  background: var(--surface-2);
  font-size: 0.78rem;
  overflow-wrap: anywhere;
}
.filter-sheet-hint {
  color: var(--muted);
  font-size: 0.85rem;
}
.filter-sheet-caret {
  margin-left: auto;
  transition: transform var(--transition-fast);
}
.filter-sheet[open] .filter-sheet-caret {
  transform: rotate(180deg);
}
.filter-sheet-body {
  padding: 0 var(--space-4) var(--space-4);
  border-top: 1px solid var(--border);
}
/* The page's filter form keeps its layout but not its own card frame. */
.filter-sheet-body > :deep(*) {
  margin-top: var(--space-3) !important;
  border: 0 !important;
  box-shadow: none !important;
  padding-inline: 0 !important;
  background: transparent !important;
}
</style>

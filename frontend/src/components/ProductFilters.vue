<script setup>
import DomainField from './DomainField.vue'
import { conditions } from '../composables/productPresentation'
const props = defineProps({ modelValue: Object, categories: Array, shops: Array, errors: Object })
const emit = defineEmits(['update:modelValue'])
const all = [{ value: '', label: 'Tất cả' }]
const set = (key, value) => emit('update:modelValue', { ...props.modelValue, [key]: value })
</script>
<template>
  <div class="filter-chips">
    <div
      v-for="field in [
        { key: 'category', label: 'Danh mục', options: [...all, ...categories] },
        { key: 'condition', label: 'Tình trạng', options: [...all, ...conditions] },
        { key: 'shop', label: 'Gian hàng', options: [...all, ...shops] },
        { key: 'min_price', label: 'Giá từ (VND)' },
        { key: 'max_price', label: 'Giá đến (VND)' },
        {
          key: 'has_video',
          label: 'Video sản phẩm',
          options: [
            ...all,
            { value: '1', label: 'Có video' },
            { value: '0', label: 'Không có video' },
          ],
        },
        {
          key: 'sale_type',
          label: 'Hình thức bán',
          options: [
            ...all,
            { value: 'fixed', label: 'Bán thường' },
            { value: 'auction', label: 'Đấu giá' },
          ],
        },
      ]"
      :key="field.key"
      class="filter-chip"
      :class="{ 'is-active': modelValue[field.key] !== '' && modelValue[field.key] != null }"
    >
      <DomainField
        :model-value="modelValue[field.key]"
        :name="field.key"
        :label="field.label"
        :type="field.options ? 'select' : 'text'"
        :options="field.options"
        :errors="errors[field.key]"
        @update:model-value="(value) => set(field.key, value)"
      />
    </div>
    <p class="filter-chips-note small text-muted mb-0">
      Khoảng giá và sắp xếp giá áp dụng theo giá niêm yết. Giá đấu giá hiện tại được hiển thị trên
      thẻ sản phẩm.
    </p>
  </div>
</template>
<style scoped>
/* Each filter is a pill: label on the left, native dropdown (or price input) on the right. */
.filter-chips {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
.filter-chip :deep(.mb-3) {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-items: center;
  column-gap: var(--space-2);
  margin: 0 !important;
  padding-left: 0.9rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-pill);
  background: var(--surface);
  transition: border-color var(--transition-fast);
}
.filter-chip :deep(.mb-3:focus-within) {
  border-color: var(--primary);
}
.filter-chip.is-active :deep(.mb-3) {
  border-color: var(--primary);
  background: var(--primary-soft);
}
.filter-chip :deep(.form-label) {
  margin: 0;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--muted);
  white-space: nowrap;
}
.filter-chip :deep(.form-select),
.filter-chip :deep(.form-control) {
  min-height: 42px;
  border: 0;
  border-radius: var(--radius-pill);
  background-color: transparent;
  font-size: 0.85rem;
  font-weight: 600;
  box-shadow: none;
}
.filter-chip :deep(.form-select) {
  width: auto;
  max-width: 13rem;
  padding-left: 0.25rem;
}
.filter-chip :deep(.form-control) {
  width: 8.5rem;
  padding-left: 0.25rem;
}
.filter-chip :deep(.form-select:focus-visible),
.filter-chip :deep(.form-control:focus-visible) {
  outline: var(--focus-ring);
  outline-offset: 1px;
}
.filter-chip :deep(.invalid-feedback) {
  grid-column: 1 / -1;
  padding: 0 0.9rem 0.5rem 0;
}
.filter-chips-note {
  flex-basis: 100%;
}
@media (max-width: 767px) {
  .filter-chip {
    flex: 1 1 100%;
  }
  .filter-chip :deep(.form-select),
  .filter-chip :deep(.form-control) {
    width: 100%;
    max-width: none;
  }
}
</style>

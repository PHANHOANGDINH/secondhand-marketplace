<script setup>
import DomainField from './DomainField.vue'
import { conditions } from '../composables/productPresentation'
const props = defineProps({ modelValue: Object, categories: Array, shops: Array, errors: Object })
const emit = defineEmits(['update:modelValue'])
const all = [{ value: '', label: 'Tất cả' }]
const set = (key, value) => emit('update:modelValue', { ...props.modelValue, [key]: value })
</script>
<template>
  <div class="row g-3">
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
      class="col-12 col-sm-6 col-lg-3"
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
    <p class="small text-muted mb-0">
      Khoảng giá và sắp xếp giá áp dụng theo giá niêm yết. Giá đấu giá hiện tại được hiển thị trên
      thẻ sản phẩm.
    </p>
  </div>
</template>

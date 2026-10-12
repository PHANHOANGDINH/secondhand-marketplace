<script setup>
import { computed, onUnmounted, ref, useId, watch } from 'vue'
import { productSuggestions } from '../api/product.api'
const props = defineProps({ modelValue: { type: String, default: '' }, id: String })
const emit = defineEmits(['update:modelValue', 'submit'])
const uid = useId(),
  opened = ref(false),
  items = ref([]),
  active = ref(-1),
  busy = ref(false),
  failed = ref(false)
const inputId = computed(() => props.id || `product-search-${uid}`)
let timer,
  sequence = 0
watch(
  () => props.modelValue,
  (value) => {
    clearTimeout(timer)
    const current = ++sequence
    items.value = []
    active.value = -1
    busy.value = false
    failed.value = false
    if (value.trim().length < 2) return
    busy.value = true
    timer = setTimeout(async () => {
      busy.value = true
      try {
        const { data } = await productSuggestions(value.trim().slice(0, 100))
        if (current !== sequence) return
        items.value = [
          ...(data.products || []).map((p) => ({
            name: p.name,
            kind: 'Sản phẩm',
            to: `/products/${encodeURIComponent(p.slug)}`,
          })),
          ...(data.categories || []).map((c) => ({
            name: c.name,
            kind: 'Danh mục',
            to: { path: '/products', query: { category: String(c.id) } },
          })),
          ...(data.shops || []).map((s) => ({
            name: s.name,
            kind: 'Gian hàng',
            to: { path: '/products', query: { shop: String(s.id) } },
          })),
        ]
      } catch {
        if (current === sequence) failed.value = true
      } finally {
        if (current === sequence) busy.value = false
      }
    }, 300)
  },
)
function update(event) {
  opened.value = true
  emit('update:modelValue', event.target.value)
}
function keyboard(event) {
  if (event.key === 'Escape') {
    event.stopPropagation()
    opened.value = false
    return
  }
  if (['ArrowDown', 'ArrowUp'].includes(event.key) && items.value.length) {
    event.preventDefault()
    opened.value = true
    active.value =
      active.value < 0
        ? event.key === 'ArrowDown'
          ? 0
          : items.value.length - 1
        : (active.value + (event.key === 'ArrowDown' ? 1 : -1) + items.value.length) %
          items.value.length
  }
  if (event.key === 'Enter') {
    event.preventDefault()
    if (opened.value && active.value >= 0)
      document.getElementById(`${inputId.value}-option-${active.value}`)?.click()
    else {
      opened.value = false
      emit('submit')
    }
  }
}
onUnmounted(() => {
  clearTimeout(timer)
  ++sequence
})
</script>
<template>
  <div
    class="product-search"
    @focusout="
      (event) => {
        if (!event.currentTarget.contains(event.relatedTarget)) opened = false
      }
    "
  >
    <label :for="inputId" class="visually-hidden">Tìm sản phẩm, danh mục hoặc gian hàng</label>
    <input
      :id="inputId"
      name="search"
      type="search"
      class="form-control"
      maxlength="100"
      placeholder="Bạn đang tìm món đồ gì?"
      :value="modelValue"
      role="combobox"
      aria-autocomplete="list"
      :aria-expanded="opened && modelValue.trim().length >= 2"
      :aria-controls="`${inputId}-suggestions`"
      :aria-activedescendant="opened && active >= 0 ? `${inputId}-option-${active}` : undefined"
      @input="update"
      @focus="opened = true"
      @keydown="keyboard"
    />
    <div v-if="opened && modelValue.trim().length >= 2" class="suggestions shadow-sm">
      <p v-if="busy" class="small p-3 mb-0" role="status">Đang tìm gợi ý…</p>
      <p v-else-if="failed" class="small p-3 mb-0" role="status">
        Chưa tải được gợi ý. Bạn vẫn có thể tìm kiếm.
      </p>
      <p v-else-if="!items.length" class="small p-3 mb-0" role="status">Không có gợi ý phù hợp.</p>
      <ul
        :id="`${inputId}-suggestions`"
        role="listbox"
        aria-label="Gợi ý tìm kiếm"
        class="list-unstyled mb-0"
      >
        <li v-for="(item, index) in items" :key="`${item.kind}-${index}`" role="none">
          <RouterLink
            :id="`${inputId}-option-${index}`"
            :to="item.to"
            role="option"
            :aria-selected="active === index"
            class="suggestion"
            :class="{ selected: active === index }"
            @click="opened = false"
          >
            <span>{{ item.name }}</span
            ><small class="text-muted">{{ item.kind }}</small>
          </RouterLink>
        </li>
      </ul>
    </div>
  </div>
</template>
<style scoped>
.product-search {
  position: relative;
  flex: 1;
  min-width: 0;
}
.suggestions {
  position: absolute;
  z-index: 1050;
  inset: calc(100% + 0.4rem) 0 auto;
  background: var(--bs-body-bg);
  border: 1px solid var(--bs-border-color);
  border-radius: var(--radius-lg);
  max-height: min(55vh, 420px);
  overflow-y: auto;
}
.suggestion {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
  padding: 0.65rem 0.85rem;
  text-decoration: none;
  color: var(--bs-body-color);
  overflow-wrap: anywhere;
}
.suggestion:hover,
.suggestion:focus-visible,
.suggestion.selected {
  background: var(--bs-tertiary-bg);
}
</style>

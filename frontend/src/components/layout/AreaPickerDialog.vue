<script setup>
import { computed, onMounted, ref } from 'vue'
import AccessibleDialog from '../ui/AccessibleDialog.vue'
import { useArea } from '../../composables/useArea'
// Area picker: three featured cities as large cards, then a searchable radio list of all provinces.
// The illustrations are simple shapes drawn here, not photographs.
const emit = defineEmits(['confirm', 'dismiss'])
const { provinces, code, loadProvinces } = useArea()
const choice = ref(code.value),
  keyword = ref(''),
  failed = ref(false)
const plain = (text) =>
  String(text)
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase()
const featured = computed(() => provinces.value.filter((row) => row.featured))
const matches = computed(() => {
  const needle = plain(keyword.value.trim())
  return needle ? provinces.value.filter((row) => plain(row.name).includes(needle)) : provinces.value
})
onMounted(async () => {
  failed.value = !(await loadProvinces()).length
})
</script>
<template>
  <AccessibleDialog
    id="area-dialog"
    class="area-dialog"
    title="Chọn khu vực"
    description="Xem tin đăng của các gian hàng gần bạn. Có thể đổi lại bất cứ lúc nào."
    @dismiss="emit('dismiss')"
  >
    <form id="area-form" @submit.prevent="emit('confirm', choice)">
      <p v-if="failed" role="alert" class="alert alert-warning">
        Chưa tải được danh sách tỉnh thành. Bạn vẫn có thể xem tin toàn quốc.
      </p>
      <div v-if="featured.length" class="area-featured">
        <label
          v-for="(city, index) in featured"
          :key="city.code"
          class="area-card"
          :class="{ 'is-selected': choice === city.code }"
        >
          <input v-model="choice" type="radio" name="area" :value="city.code" class="visually-hidden" />
          <svg class="area-card-art" viewBox="0 0 160 90" aria-hidden="true" focusable="false">
            <rect width="160" height="90" :style="{ fill: ['var(--primary-soft)', 'var(--surface-2)', 'var(--brand-soft)'][index % 3] }" />
            <circle cx="128" cy="24" r="12" :style="{ fill: ['var(--brand)', 'var(--rating)', 'var(--brand)'][index % 3] }" />
            <g :style="{ fill: ['var(--primary)', 'var(--success)', 'var(--accent-ink)'][index % 3] }">
              <rect x="14" y="46" width="18" height="44" rx="2" />
              <rect x="36" :y="index === 0 ? 22 : 34" width="20" :height="index === 0 ? 68 : 56" rx="2" />
              <rect x="60" y="52" width="16" height="38" rx="2" />
              <rect x="80" :y="index === 1 ? 26 : 40" width="22" :height="index === 1 ? 64 : 50" rx="2" />
              <rect x="106" y="58" width="16" height="32" rx="2" />
              <rect x="126" :y="index === 2 ? 36 : 50" width="20" :height="index === 2 ? 54 : 40" rx="2" />
            </g>
          </svg>
          <span class="area-card-name">{{ city.name }}</span>
        </label>
      </div>
      <label for="area-search" class="form-label">Tìm tỉnh thành</label>
      <input
        id="area-search"
        v-model="keyword"
        type="search"
        class="form-control"
        placeholder="Tìm tỉnh thành…"
        autocomplete="off"
      />
      <fieldset class="area-list">
        <legend class="visually-hidden">Danh sách tỉnh thành</legend>
        <label class="area-option">
          <input v-model="choice" type="radio" name="area" value="" class="form-check-input" />
          <span>Toàn quốc</span>
        </label>
        <label v-for="row in matches" :key="row.code" class="area-option">
          <input v-model="choice" type="radio" name="area" :value="row.code" class="form-check-input" />
          <span>{{ row.name }}</span>
        </label>
        <p v-if="keyword && !matches.length" class="text-muted small mb-0" role="status">
          Không có tỉnh thành nào khớp.
        </p>
      </fieldset>
    </form>
    <template #footer>
      <div class="d-flex justify-content-end">
        <button type="submit" form="area-form" class="btn btn-primary" data-area-confirm>
          Xác nhận
        </button>
      </div>
    </template>
  </AccessibleDialog>
</template>
<style scoped>
.area-featured {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: var(--space-3);
  margin-bottom: var(--space-5);
}
.area-card {
  display: grid;
  gap: var(--space-2);
  padding: var(--space-2);
  border: 2px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  cursor: pointer;
  transition: border-color var(--transition-fast);
}
.area-card:hover,
.area-card:focus-within {
  border-color: var(--primary);
}
.area-card.is-selected {
  border-color: var(--primary);
  background: var(--primary-soft);
}
.area-card-art {
  width: 100%;
  height: auto;
  border-radius: var(--radius-md);
}
.area-card-name {
  padding: 0 var(--space-1) var(--space-1);
  font-weight: 700;
  text-align: center;
}
.area-list {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 var(--space-4);
  max-height: 16rem;
  margin: var(--space-3) 0 0;
  padding: var(--space-2);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  overflow-y: auto;
}
.area-option {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  min-height: 44px;
  padding: 0 var(--space-2);
  border-radius: var(--radius-sm);
  cursor: pointer;
}
.area-option:hover {
  background: var(--surface-2);
}
.area-option .form-check-input {
  flex: none;
  margin: 0;
}
@media (max-width: 575px) {
  .area-featured {
    gap: var(--space-2);
  }
  .area-list {
    grid-template-columns: 1fr;
  }
  .area-card-name {
    font-size: 0.8rem;
  }
}
</style>
<style>
.ui-dialog-backdrop.area-dialog .ui-dialog {
  width: min(38rem, 100%);
}
</style>

<script setup>
import IconButton from '../components/ui/IconButton.vue'
import { getStatusLabel } from '../utils/labels'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'

import { computed, onMounted, reactive, ref } from 'vue'
import DetailSheet from '../components/ui/DetailSheet.vue'
import * as categoriesApi from '../api/category.api'
import { useFeedback } from '../composables/useFeedback'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
const rows = ref([]),
  parents = ref([]),
  meta = ref(null),
  page = ref(1),
  editing = ref(null),
  deleting = ref(null)
const blank = () => ({ name: '', description: '', parent_id: '', status: 'ACTIVE', reason: '' })
const form = reactive(blank()),
  deleteReason = ref('')
const { busy, error, errors, success, clear, run } = useFeedback()
const parentOptions = computed(() => [
  { value: '', label: 'Danh mục gốc' },
  ...parents.value
    .filter((row) => row.id !== editing.value)
    .map((row) => ({ value: row.id, label: `${row.name} (#${row.id}) — ${getStatusLabel(row.status)}` })),
])
const statuses = [
  { value: 'ACTIVE', label: getStatusLabel('ACTIVE') },
  { value: 'INACTIVE', label: getStatusLabel('INACTIVE') },
]
async function refresh() {
  let result = await categoriesApi.listAdminCategories({ page: page.value })
  if (page.value > result.meta.last_page) {
    page.value = result.meta.last_page
    result = await categoriesApi.listAdminCategories({ page: page.value })
  }
  rows.value = result.data
  meta.value = result.meta
  const choices = []
  let current = 1,
    last
  do {
    const batch = await categoriesApi.listAdminCategories({ page: current, per_page: 100 })
    choices.push(...batch.data)
    last = batch.meta.last_page
    current++
  } while (current <= last)
  parents.value = choices
}
const load = (target = page.value) =>
  run(async () => {
    page.value = target
    await refresh()
  })
function cancelEdit() {
  reset()
  clear()
}
// The category form only opens on "Thêm danh mục" or "Sửa", in a panel beside the table.
const formOpen = ref(false)
function reset() {
  editing.value = null
  formOpen.value = false
  Object.assign(form, blank())
}
function add() {
  clear()
  deleting.value = null
  editing.value = null
  Object.assign(form, blank())
  formOpen.value = true
}
function edit(row) {
  clear()
  deleting.value = null
  editing.value = row.id
  formOpen.value = true
  Object.assign(form, {
    name: row.name,
    description: row.description || '',
    parent_id: row.parent_id || '',
    status: row.status,
    reason: '',
  })
}
function cancelDelete() {
  deleting.value = null
  clear()
}
function askDelete(row) {
  clear()
  deleting.value = row
  deleteReason.value = ''
}
const save = () =>
  run(async () => {
    const data = {
      ...form,
      description: form.description || null,
      parent_id: form.parent_id || null,
    }
    if (editing.value) await categoriesApi.updateCategory(editing.value, data)
    else await categoriesApi.createCategory(data)
    reset()
    await refresh()
  }, 'Đã lưu danh mục.')
const remove = () =>
  run(async () => {
    const id = deleting.value.id
    await categoriesApi.deleteCategory(id, deleteReason.value)
    deleting.value = null
    if (editing.value === id) reset()
    await refresh()
  }, 'Đã xóa danh mục.')
onMounted(() => load())
function categoryDepth(row) {
  let depth = 0,
    id = row.parent_id
  const seen = new Set([row.id])
  while (id && !seen.has(id) && depth < 2) {
    seen.add(id)
    depth++
    id = parents.value.find((parent) => parent.id === id)?.parent_id
  }
  return depth
}
</script>
<template>
  <section>
    <PageHeader
      title="Quản lý danh mục"
      eyebrow="Quản trị · Sàn mua bán"
      description="Quản lý cây danh mục, quan hệ cha–con và khả năng hiển thị."
      ><IconButton
        class="btn btn-primary"
        icon="plus"
        label="Thêm danh mục"
        show-label
        :disabled="busy"
        data-category-add
        @click="add"
    /></PageHeader>
    <p class="text-secondary">
      Cây danh mục tối đa 3 cấp. Danh mục có sản phẩm hoặc danh mục con có thể chuyển sang ngừng hoạt động để
      ngừng hiển thị.
    </p>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div>
      <div>
        <IconButton v-if="error" class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load()" icon="refresh" label="Tải lại" />
        <div class="table-responsive admin-table-wrap">
          <table class="table align-middle admin-table">
            <thead>
              <tr>
                <th scope="col">Tên / mã</th>
                <th scope="col">Danh mục cha</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" :data-category="row.id">
                <td>
                  <div
                    class="admin-category-name"
                    :style="{ paddingInlineStart: `${categoryDepth(row) * 12}px` }"
                  >
                    <span v-if="row.parent_id" class="admin-category-branch" aria-hidden="true"
                      >↳</span
                    >
                    <div>
                      <strong>{{ row.name }}</strong
                      ><small class="d-block text-secondary"
                        >Cấp {{ categoryDepth(row) + 1 }} · #{{ row.id }}</small
                      >
                    </div>
                  </div>
                </td>
                <td>
                  {{
                    parents.find((parent) => parent.id === row.parent_id)?.name ||
                    (row.parent_id ? `#${row.parent_id}` : 'Gốc')
                  }}
                </td>
                <td data-label="Trạng thái"><StatusBadge :status="row.status" /></td>
                <td>
                  <div class="d-flex gap-2">
                    <button
                      class="btn btn-sm btn-outline-primary"
                      :disabled="busy"
                      @click="edit(row)"
                    >
                      Sửa</button
                    ><button
                      class="btn btn-sm btn-outline-danger"
                      :disabled="busy"
                      @click="askDelete(row)"
                    >
                      Xóa
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState
          v-if="!rows.length && !busy && !error"
          title="Chưa có danh mục."
          description="Bấm “Thêm danh mục” để tạo danh mục đầu tiên."
        />
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
    </div>
    <DetailSheet
      id="category-delete"
      :open="!!deleting"
      :title="deleting ? `Xóa ${deleting.name}` : 'Xóa danh mục'"
      :busy="busy"
      @close="cancelDelete"
    >
        <form
          v-if="deleting"
          class="admin-delete-confirm"
          data-delete-category
          @submit.prevent="remove"
        >
          <p class="text-secondary">
            Thao tác này không thể hoàn tác. Danh mục có sản phẩm hoặc danh mục con không thể xóa.
          </p>
          <DomainField
            v-model="deleteReason"
            name="delete_reason"
            label="Lý do xóa"
            required
            :maxlength="1000"
            :errors="errors.reason"
          />
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-danger" :disabled="busy">Xác nhận xóa</button
            ><button type="button" class="btn btn-secondary" :disabled="busy" @click="cancelDelete">
              Hủy
            </button>
          </div>
        </form>
    </DetailSheet>
    <DetailSheet
      id="category-form"
      :open="formOpen"
      :title="editing ? 'Sửa danh mục' : 'Thêm danh mục'"
      :busy="busy"
      @close="cancelEdit"
    >
        <form data-category-form @submit.prevent="save">
          <DomainField
            v-model="form.name"
            name="name"
            label="Tên danh mục"
            required
            :maxlength="100"
            :errors="errors.name"
          />
          <DomainField
            v-model="form.description"
            name="description"
            label="Mô tả"
            type="textarea"
            :maxlength="10000"
            :errors="errors.description"
          />
          <DomainField
            v-model="form.parent_id"
            name="parent_id"
            label="Danh mục cha"
            type="select"
            :options="parentOptions"
            :errors="errors.parent_id"
          />
          <DomainField
            v-model="form.status"
            name="status"
            label="Trạng thái"
            type="select"
            :options="statuses"
            :errors="errors.status"
          />
          <DomainField
            v-model="form.reason"
            name="reason"
            label="Lý do thay đổi"
            required
            :maxlength="1000"
            :errors="errors.reason"
          />
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="busy">Lưu danh mục</button
            ><button
              type="button"
              class="btn btn-outline-secondary"
              :disabled="busy"
              @click="cancelEdit"
            >
              {{ editing ? 'Hủy sửa' : 'Đóng' }}
            </button>
          </div>
        </form>
    </DetailSheet>
  </section>
</template>

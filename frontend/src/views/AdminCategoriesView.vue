<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
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
    .map((row) => ({ value: row.id, label: `${row.name} (#${row.id}) — ${row.status}` })),
])
const statuses = [
  { value: 'ACTIVE', label: 'ACTIVE' },
  { value: 'INACTIVE', label: 'INACTIVE' },
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
function reset() {
  editing.value = null
  Object.assign(form, blank())
}
function edit(row) {
  clear()
  deleting.value = null
  editing.value = row.id
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
</script>
<template>
  <section>
    <h1 class="h3">Quản lý danh mục</h1>
    <p class="text-secondary">
      Cây danh mục tối đa 3 cấp. Danh mục có sản phẩm hoặc danh mục con có thể chuyển INACTIVE để
      ngừng hiển thị.
    </p>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div class="row g-4">
      <div class="col-lg-7">
        <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load()">
          Tải lại
        </button>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Tên / ID</th>
                <th>Danh mục cha</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" :data-category="row.id">
                <td>
                  {{ row.name }}<small class="d-block text-secondary">#{{ row.id }}</small>
                </td>
                <td>
                  {{
                    parents.find((parent) => parent.id === row.parent_id)?.name ||
                    (row.parent_id ? `#${row.parent_id}` : 'Gốc')
                  }}
                </td>
                <td>{{ row.status }}</td>
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
        <p v-if="!rows.length && !busy && !error">Chưa có danh mục.</p>
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
        <form
          v-if="deleting"
          class="border border-danger p-3 rounded"
          data-delete-category
          @submit.prevent="remove"
        >
          <h2 class="h5">Xóa {{ deleting.name }}</h2>
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
      </div>
      <form class="col-lg-5" data-category-form @submit.prevent="save">
        <h2 class="h5">{{ editing ? 'Sửa danh mục' : 'Thêm danh mục' }}</h2>
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
            v-if="editing"
            type="button"
            class="btn btn-secondary"
            :disabled="busy"
            @click="cancelEdit"
          >
            Hủy sửa
          </button>
        </div>
      </form>
    </div>
  </section>
</template>

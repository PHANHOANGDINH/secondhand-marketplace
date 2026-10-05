<script setup>
import { computed, onMounted, ref } from 'vue'
import * as shopsApi from '../api/shop.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
const auth = useAuthStore()
const rows = ref([]),
  meta = ref(null),
  page = ref(1),
  search = ref(''),
  status = ref(''),
  selected = ref(null),
  reason = ref('')
const { busy, error, errors, success, run } = useFeedback()
const transitions = {
  PENDING: ['ACTIVE', 'REJECTED'],
  ACTIVE: ['SUSPENDED'],
  SUSPENDED: ['ACTIVE'],
  REJECTED: [],
}
const actions = computed(() =>
  !selected.value || selected.value.archived || selected.value.owner_id === auth.user?.id
    ? []
    : transitions[selected.value.status] || [],
)
const label = (target) =>
  target === 'REJECTED'
    ? 'Từ chối'
    : target === 'SUSPENDED'
      ? 'Tạm ngừng'
      : selected.value?.status === 'SUSPENDED'
        ? 'Kích hoạt lại'
        : 'Duyệt gian hàng'
async function refresh() {
  const result = await shopsApi.listAdminShops({
    page: page.value,
    search: search.value || undefined,
    status: status.value || undefined,
  })
  rows.value = result.data
  meta.value = result.meta
}
const load = (target = 1) =>
  run(async () => {
    page.value = target
    await refresh()
  })
const show = (id) =>
  run(async () => {
    selected.value = await shopsApi.getAdminShop(id)
    reason.value = ''
  })
const moderate = (target) =>
  run(async () => {
    selected.value = await shopsApi.moderateShop(selected.value.id, {
      expected_status: selected.value.status,
      status: target,
      reason: reason.value,
    })
    reason.value = ''
    await refresh()
  }, 'Đã cập nhật trạng thái gian hàng.')
onMounted(() => load())
</script>
<template>
  <section>
    <h1 class="h3">Kiểm duyệt gian hàng</h1>
    <form class="d-flex gap-2 flex-wrap my-3" @submit.prevent="load()">
      <label class="flex-grow-1"
        >Tìm tên<input v-model="search" name="search" maxlength="100" class="form-control"
      /></label>
      <label
        >Trạng thái<select v-model="status" name="filter_status" class="form-select">
          <option value="">Tất cả</option>
          <option v-for="state in Object.keys(transitions)" :key="state">{{ state }}</option>
        </select></label
      >
      <button class="btn btn-primary align-self-end" :disabled="busy">Lọc / Tải lại</button>
    </form>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Gian hàng</th>
                <th>Trạng thái</th>
                <th>Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" :data-shop="row.id">
                <td>
                  {{ row.name }}
                  <span v-if="row.archived" class="badge text-bg-secondary">Đã lưu trữ</span>
                </td>
                <td>{{ row.status }}</td>
                <td>
                  <button
                    class="btn btn-sm btn-outline-primary"
                    :disabled="busy"
                    @click="show(row.id)"
                  >
                    Xem
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <p v-if="!rows.length && !busy && !error">Chưa tìm thấy gian hàng.</p>
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
      <aside v-if="selected" class="col-lg-5">
        <h2 class="h5">{{ selected.name }}</h2>
        <p class="badge text-bg-secondary">{{ selected.status }}</p>
        <p>{{ selected.description }}</p>
        <p v-if="selected.moderation_reason">Lý do gần nhất: {{ selected.moderation_reason }}</p>
        <button
          class="btn btn-outline-secondary btn-sm mb-3"
          :disabled="busy"
          @click="show(selected.id)"
        >
          Tải lại chi tiết
        </button>
        <template v-if="actions.length">
          <DomainField
            v-model="reason"
            name="reason"
            label="Lý do kiểm duyệt"
            type="textarea"
            required
            :maxlength="1000"
            :errors="errors.reason"
          />
          <div v-if="errors.expected_status || errors.status" class="alert alert-danger">
            {{ [...(errors.expected_status || []), ...(errors.status || [])].join(' ') }}
          </div>
          <div class="d-flex gap-2 flex-wrap">
            <button
              v-for="target in actions"
              :key="target"
              :data-target="target"
              class="btn btn-outline-primary"
              :disabled="busy || !reason.trim()"
              @click="moderate(target)"
            >
              {{ label(target) }}
            </button>
          </div>
        </template>
        <p v-else class="text-secondary">Không có thao tác kiểm duyệt hợp lệ cho gian hàng này.</p>
      </aside>
    </div>
  </section>
</template>

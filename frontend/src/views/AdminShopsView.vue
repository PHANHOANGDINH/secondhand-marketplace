<script setup>
import IconButton from '../components/ui/IconButton.vue'
import { useRoute, useRouter } from 'vue-router'
const route = useRoute(),
  router = useRouter()
import { getStatusLabel } from '../utils/labels'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ProductPhoto from '../components/ProductPhoto.vue'
import AdminRecordHeader from '../components/admin/AdminRecordHeader.vue'
import { computed, onMounted, watch, ref } from 'vue'
import * as shopsApi from '../api/shop.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
import FilterSheet from '../components/ui/FilterSheet.vue'
import DetailSheet from '../components/ui/DetailSheet.vue'
const auth = useAuthStore()
const rows = ref([]),
  meta = ref(null),
  page = ref(1),
  search = ref(''),
  // The Action Center links here with the list already filtered.
  status = ref(
    ['PENDING', 'ACTIVE', 'SUSPENDED', 'REJECTED'].includes(route.query.status)
      ? route.query.status
      : '',
  ),
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
// Lock, unlock, approve or refuse: one icon per shop action.
const icon = (target) =>
  ({ REJECTED: 'ban', SUSPENDED: 'lock' })[target] || (selected.value?.status === 'SUSPENDED' ? 'unlock' : 'check')
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
async function inspectTarget(id) {
  if (/^[1-9]\d*$/.test(String(id || ''))) await show(id)
}
watch(() => route.query.inspect, inspectTarget)
onMounted(async () => {
  await load()
  await inspectTarget(route.query.inspect)
})
const activeFilters = computed(() => [
  { label: 'Tìm', value: search.value.trim() },
  { label: 'Trạng thái', value: status.value && getStatusLabel(status.value) },
])
// Closing the details also drops ?inspect= so a reload does not open them again.
function closeDetail() {
  selected.value = null
  reason.value = ''
  if (route.query.inspect) {
    const query = { ...route.query }
    delete query.inspect
    router.replace({ query })
  }
}
</script>
<template>
  <section>
    <PageHeader
      title="Gian hàng"
      eyebrow="Quản trị · Sàn mua bán"
      description="Gian hàng hoạt động ngay khi tạo. Khoá gian hàng bị báo cáo nhiều, mở lại khi đã xử lý xong."
    />
    <FilterSheet :active="activeFilters">
      <form class="d-flex gap-2 flex-wrap admin-toolbar" @submit.prevent="load()">
        <label class="flex-grow-1"
          >Tìm tên<input v-model="search" name="search" maxlength="100" class="form-control"
        /></label>
        <label
          >Trạng thái<select v-model="status" name="filter_status" class="form-select">
            <option value="">Tất cả</option>
            <option v-for="state in Object.keys(transitions)" :key="state" :value="state">
              {{ getStatusLabel(state) }}
            </option>
          </select></label
        >
        <IconButton type="submit" class="btn btn-outline-secondary align-self-end" :disabled="busy" icon="sliders" label="Lọc / Tải lại" show-label />
      </form>
    </FilterSheet>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div>
      <div>
        <div class="table-responsive admin-table-wrap">
          <table class="table align-middle admin-table">
            <thead>
              <tr>
                <th scope="col">Gian hàng</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Thao tác</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                :data-shop="row.id"
                :class="{ 'is-selected': selected?.id === row.id }"
              >
                <td>
                  <strong>{{ row.name }}</strong
                  ><small v-if="row.owner" class="d-block">{{ row.owner.name }}</small
                  ><small v-else-if="row.owner_id" class="d-block"
                    >Chủ gian hàng #{{ row.owner_id }}</small
                  >
                  <span v-if="row.archived" class="badge text-bg-secondary">Đã lưu trữ</span>
                </td>
                <td data-label="Trạng thái"><StatusBadge :status="row.status" /></td>
                <td>
                  <IconButton
                    class="btn btn-sm btn-outline-primary"
                    :disabled="busy"
                    :aria-expanded="selected?.id === row.id"
                    @click="show(row.id)" icon="eye" label="Xem" />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState
          v-if="!rows.length && !busy && !error"
          title="Chưa tìm thấy gian hàng."
          description="Thử điều chỉnh tên hoặc trạng thái."
        />
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
      <DetailSheet
        id="admin-shop-detail"
        :open="!!selected"
        :title="selected ? `Gian hàng: ${selected.name}` : 'Chi tiết gian hàng'"
        :busy="busy"
        @close="closeDetail"
      >
        <div v-if="selected" class="admin-detail">
          <ProductPhoto
            v-if="selected.logo_path"
            :src="
              selected.logo_path.startsWith('http') || selected.logo_path.startsWith('/')
                ? selected.logo_path
                : `/storage/${selected.logo_path}`
            "
            :alt="selected.name"
            class="mb-3 rounded"
            style="width: 64px; height: 64px"
          />
          <AdminRecordHeader :title="selected.name" :status="selected.status" />
          <p class="text-secondary">
            {{
              {
                PENDING: 'Gian hàng đang chờ xét duyệt để hiển thị công khai.',
                ACTIVE: 'Gian hàng đang hoạt động trên sàn mua bán.',
                SUSPENDED: 'Gian hàng đang tạm ngừng hoạt động.',
                REJECTED:
                  'Gian hàng đã bị từ chối. Chủ gian hàng cần cập nhật và gửi lại để xét duyệt.',
              }[selected.status]
            }}
          </p>
          <p v-if="selected.owner || selected.owner_id" class="text-secondary">
            Chủ gian hàng: {{ selected.owner?.name || `#${selected.owner_id}` }}
          </p>
          <p>{{ selected.description }}</p>
          <p>Khu vực: {{ selected.province_name || 'Chưa chọn' }}</p>
          <p v-if="selected.moderation_reason">Lý do gần nhất: {{ selected.moderation_reason }}</p>
          <IconButton
            class="btn btn-outline-secondary btn-sm mb-3"
            :disabled="busy"
            @click="show(selected.id)" icon="refresh" label="Tải lại chi tiết" />
          <div v-if="actions.length" class="admin-moderation">
            <p class="admin-moderation-title">Xác nhận kiểm duyệt</p>
            <p class="text-secondary">Ghi rõ lý do cho thay đổi trạng thái của gian hàng.</p>
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
              <IconButton
                v-for="target in actions"
                :key="target"
                :data-target="target"
                :class="['btn', ['REJECTED', 'SUSPENDED'].includes(target) ? 'btn-outline-danger' : 'btn-outline-primary']"
                :icon="icon(target)"
                :label="label(target)"
                show-label
                :disabled="busy || !reason.trim()"
                @click="moderate(target)"
              />
            </div>
          </div>
          <p v-else class="text-secondary">
            Không có thao tác kiểm duyệt hợp lệ cho gian hàng này.
          </p>
        </div>
      </DetailSheet>
    </div>
  </section>
</template>

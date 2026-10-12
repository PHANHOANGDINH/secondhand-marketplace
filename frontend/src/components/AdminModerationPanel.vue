<script setup>
import { getStatusLabel, getRoleLabel } from '../utils/labels'
import PageHeader from './ui/PageHeader.vue'
import StatusBadge from './ui/StatusBadge.vue'
import EmptyState from './ui/EmptyState.vue'
import AdminRecordHeader from './admin/AdminRecordHeader.vue'
import AdminRating from './admin/AdminRating.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import * as api from '../api/admin.api'
import { useAuthStore } from '../stores/auth'
import { useFeedback } from '../composables/useFeedback'
import DomainFeedback from './DomainFeedback.vue'
import CollectionPager from './CollectionPager.vue'
const props = defineProps({ kind: { type: String, required: true } })
const isUser = computed(() => props.kind === 'users'),
  auth = useAuthStore()
const rows = ref([]),
  meta = ref(null),
  page = ref(1),
  selected = ref(null),
  reason = ref('')
const route = useRoute()
// "flagged" narrows users to accounts with several open reports; the Action Center links here with it set.
const filters = reactive({
  search: '',
  status: '',
  role: '',
  product: '',
  rating: '',
  flagged: props.kind === 'users' && route?.query?.flagged === '1',
})
const { busy, error, errors, success, run } = useFeedback()
const states = computed(() => (isUser.value ? ['ACTIVE', 'SUSPENDED'] : ['PUBLISHED', 'HIDDEN']))
const action = computed(() =>
  !selected.value || (isUser.value && !selected.value.can_moderate)
    ? null
    : states.value.find((state) => state !== selected.value.status),
)
const actionLabel = computed(
  () =>
    ({
      ACTIVE: 'Kích hoạt lại',
      SUSPENDED: 'Tạm khóa',
      PUBLISHED: 'Hiện đánh giá',
      HIDDEN: 'Ẩn đánh giá',
    })[action.value],
)
const calls = computed(() =>
  isUser.value
    ? { list: api.listAdminUsers, get: api.getAdminUser, moderate: api.moderateUser }
    : { list: api.listAdminReviews, get: api.getAdminReview, moderate: api.moderateReview },
)
async function refresh() {
  if (!auth.isAdmin) return
  const actor = auth.user.id
  const allowed = isUser.value ? ['search', 'status', 'role'] : ['status', 'product', 'rating']
  const params = { page: page.value, per_page: 20 }
  for (const key of allowed) if (filters[key] !== '') params[key] = filters[key]
  if (isUser.value && filters.flagged) params.flagged = 1
  const result = await calls.value.list(params)
  if (auth.isAdmin && auth.user.id === actor) {
    rows.value = result.data
    meta.value = result.meta
  }
}
const load = (target = 1) =>
  run(async () => {
    page.value = target
    rows.value = []
    meta.value = null
    selected.value = null
    await refresh()
  })
const show = (id) =>
  run(async () => {
    if (!auth.isAdmin) return
    const actor = auth.user.id
    selected.value = null
    const result = await calls.value.get(id)
    if (auth.isAdmin && auth.user.id === actor) {
      selected.value = result
      reason.value = ''
    }
  })
const moderate = () =>
  run(async () => {
    if (!auth.isAdmin || !action.value) return
    const actor = auth.user.id
    const result = await calls.value.moderate(selected.value.id, {
      expected_status: selected.value.status,
      status: action.value,
      reason: reason.value,
    })
    if (auth.isAdmin && auth.user.id === actor) {
      selected.value = result
      reason.value = ''
      await refresh()
    }
  }, 'Đã cập nhật trạng thái và ghi nhật ký quản lý.')
onMounted(() => load())
</script>
<template>
  <section v-if="auth.isAdmin">
    <PageHeader
      :title="isUser ? 'Quản lý người dùng' : 'Kiểm duyệt đánh giá'"
      eyebrow="Quản trị · Sàn mua bán"
      :description="
        isUser
          ? 'Tra cứu tài khoản và quản lý trạng thái truy cập.'
          : 'Xem nội dung đánh giá và quản lý khả năng hiển thị.'
      "
    />
    <form class="row g-2 align-items-end my-3 admin-toolbar" @submit.prevent="load()">
      <label v-if="isUser" class="col-md-4"
        >Tên hoặc thư điện tử<input
          v-model="filters.search"
          name="search"
          maxlength="100"
          class="form-control"
      /></label>
      <label class="col-md-2"
        >Trạng thái<select v-model="filters.status" name="status" class="form-select">
          <option value="">Tất cả</option>
          <option v-for="state in states" :key="state" :value="state">{{ getStatusLabel(state) }}</option>
        </select></label
      >
      <label v-if="isUser" class="col-md-2"
        >Vai trò<select v-model="filters.role" name="role" class="form-select">
          <option value="">Tất cả</option>
          <option value="USER">{{ getRoleLabel('USER') }}</option>
          <option value="ADMIN">{{ getRoleLabel('ADMIN') }}</option>
        </select></label
      >
      <div v-if="isUser" class="col-md-3">
        <div class="form-check mb-2">
          <input
            id="users-flagged"
            v-model="filters.flagged"
            class="form-check-input"
            type="checkbox"
            name="flagged"
          /><label class="form-check-label" for="users-flagged"
            >Chỉ tài khoản bị báo cáo nhiều</label
          >
        </div>
      </div>
      <template v-else
        ><label class="col-md-3"
          >Mã sản phẩm<input
            v-model="filters.product"
            name="product"
            type="number"
            min="1"
            step="1"
            class="form-control" /></label
        ><label class="col-md-2"
          >Số sao<select v-model="filters.rating" name="rating" class="form-select">
            <option value="">Tất cả</option>
            <option v-for="n in 5" :key="n" :value="n">{{ n }}</option>
          </select></label
        ></template
      >
      <div class="col-md-3">
        <button class="btn btn-outline-secondary" :disabled="busy">Lọc / Tải lại</button>
      </div>
    </form>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <p v-for="(messages, key) in errors" :key="key" class="text-danger">{{ messages.join(' ') }}</p>
    <div class="row g-4 admin-split">
      <div class="col-lg-7">
        <div class="table-responsive admin-table-wrap">
          <table class="table admin-table">
            <thead>
              <tr>
                <th scope="col">{{ isUser ? 'Người dùng' : 'Sản phẩm / tác giả' }}</th>
                <th scope="col">Trạng thái</th>
                <th scope="col">Chi tiết</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="row in rows"
                :key="row.id"
                :data-record="row.id"
                :class="{ 'is-selected': selected?.id === row.id }"
              >
                <td>
                  <template v-if="isUser"
                    ><strong>{{ row.name }}</strong
                    ><small class="d-block">{{ row.email }}</small
                    ><small class="d-block"
                      >{{ getRoleLabel(row.role) }} <span v-if="row.archived">(Đã lưu trữ)</span></small
                    ></template
                  ><template v-else
                    ><strong>{{ row.product.name }}</strong
                    ><small class="d-block">{{ row.author.name }}</small
                    ><AdminRating :rating="row.rating"
                  /></template>
                </td>
                <td data-label="Trạng thái"><StatusBadge :status="row.status" /></td>
                <td>
                  <button
                    class="btn btn-sm btn-outline-primary"
                    :disabled="busy"
                    :aria-expanded="selected?.id === row.id"
                    @click="show(row.id)"
                  >
                    Xem
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <EmptyState
          v-if="!busy && !error && !rows.length"
          title="Không tìm thấy dữ liệu."
          description="Điều chỉnh bộ lọc hoặc tải lại danh sách."
        />
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
      <aside v-if="selected" class="col-lg-5" data-selected>
        <div class="admin-detail">
          <AdminRecordHeader
            :title="isUser ? selected.name : selected.product.name"
            :status="selected.status"
            :subtitle="isUser ? selected.email : selected.author.name"
          />
          <dl v-if="isUser">
            <dt>Vai trò</dt>
            <dd>{{ getRoleLabel(selected.role) }}</dd>
            <template v-if="selected.phone"
              ><dt>Số điện thoại</dt>
              <dd>{{ selected.phone }}</dd></template
            ><template v-if="selected.created_at"
              ><dt>Ngày đăng ký</dt>
              <dd>{{ new Date(selected.created_at).toLocaleDateString('vi-VN') }}</dd></template
            >
          </dl>
          <template v-else
            ><AdminRating :rating="selected.rating" />
            <p class="admin-review-content mt-3">{{ selected.comment || 'Không có nhận xét.' }}</p>
            <p v-if="selected.moderation_reason" class="text-secondary">
              Lý do gần nhất: {{ selected.moderation_reason }}
            </p></template
          >
          <button
            class="btn btn-outline-secondary mb-3"
            :disabled="busy"
            @click="show(selected.id)"
          >
            Tải lại chi tiết
          </button>
          <form v-if="action" class="admin-moderation" @submit.prevent="moderate">
            <p class="admin-moderation-title">Xác nhận {{ actionLabel.toLowerCase() }}</p>
            <p class="text-secondary">
              {{ getStatusLabel(selected.status) }} → {{ getStatusLabel(action) }}. Ghi rõ lý do trước khi xác nhận.
            </p>
            <label class="form-label w-100"
              >Lý do thay đổi<textarea
                v-model="reason"
                name="reason"
                required
                maxlength="1000"
                class="form-control"
              /></label
            ><button
              class="btn btn-outline-danger"
              :data-target="action"
              :disabled="busy || !reason.trim()"
            >
              {{ actionLabel }}
            </button>
          </form>
          <p v-else>Không có thao tác trạng thái được phép.</p>
          <p class="text-secondary">
            {{
              isUser
                ? 'Chỉ thay đổi trạng thái; không sửa thư điện tử, mật khẩu hoặc vai trò.'
                : 'Chỉ ẩn/hiện; không sửa nội dung hoặc số sao của người mua.'
            }}
          </p>
        </div>
      </aside>
      <aside v-else class="col-lg-5">
        <EmptyState
          title="Chọn bản ghi để xem chi tiết"
          description="Thông tin và các thao tác hợp lệ sẽ xuất hiện tại đây."
        />
      </aside>
    </div>
  </section>
</template>

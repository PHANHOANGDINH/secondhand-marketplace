<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
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
const filters = reactive({ search: '', status: '', role: '', product: '', rating: '' })
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
  }, 'Đã cập nhật trạng thái và ghi audit.')
onMounted(() => load())
</script>
<template>
  <section v-if="auth.isAdmin">
    <h1 class="h3">{{ isUser ? 'Quản lý người dùng' : 'Kiểm duyệt đánh giá' }}</h1>
    <form class="row g-2 align-items-end my-3" @submit.prevent="load()">
      <label v-if="isUser" class="col-md-4"
        >Tên hoặc email<input
          v-model="filters.search"
          name="search"
          maxlength="100"
          class="form-control"
      /></label>
      <label class="col-md-2"
        >Trạng thái<select v-model="filters.status" name="status" class="form-select">
          <option value="">Tất cả</option>
          <option v-for="state in states" :key="state">{{ state }}</option>
        </select></label
      >
      <label v-if="isUser" class="col-md-2"
        >Vai trò<select v-model="filters.role" name="role" class="form-select">
          <option value="">Tất cả</option>
          <option>USER</option>
          <option>ADMIN</option>
        </select></label
      >
      <template v-else
        ><label class="col-md-3"
          >ID sản phẩm<input
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
        <button class="btn btn-primary" :disabled="busy">Lọc / Tải lại</button>
      </div>
    </form>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <p v-for="(messages, key) in errors" :key="key" class="text-danger">{{ messages.join(' ') }}</p>
    <div class="row g-4">
      <div class="col-lg-7">
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>{{ isUser ? 'Người dùng' : 'Sản phẩm / tác giả' }}</th>
                <th>Trạng thái</th>
                <th>Chi tiết</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in rows" :key="row.id" :data-record="row.id">
                <td>
                  <template v-if="isUser"
                    >{{ row.name }}<br />{{ row.email }} · {{ row.role }}
                    <span v-if="row.archived">(Đã lưu trữ)</span></template
                  ><template v-else
                    >{{ row.product.name }}<br />{{ row.author.name }} · {{ row.rating }} /
                    5</template
                  >
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
        <p v-if="!busy && !error && !rows.length">Không tìm thấy dữ liệu.</p>
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
      <aside v-if="selected" class="col-lg-5" data-selected>
        <h2 class="h5">{{ isUser ? selected.name : selected.product.name }}</h2>
        <p>{{ selected.status }}</p>
        <p v-if="isUser">{{ selected.email }} · {{ selected.role }}</p>
        <template v-else
          ><p>{{ selected.author.name }} · {{ selected.rating }} / 5</p>
          <p style="white-space: pre-wrap">{{ selected.comment || 'Không có nhận xét.' }}</p>
          <p v-if="selected.moderation_reason">
            Lý do gần nhất: {{ selected.moderation_reason }}
          </p></template
        >
        <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="show(selected.id)">
          Tải lại chi tiết
        </button>
        <form v-if="action" @submit.prevent="moderate">
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
              ? 'Chỉ thay đổi trạng thái; không sửa email, mật khẩu hoặc vai trò.'
              : 'Chỉ ẩn/hiện; không sửa nội dung hoặc số sao của người mua.'
          }}
        </p>
      </aside>
    </div>
  </section>
</template>

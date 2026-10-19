<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { getMyShopReview, listShopReviews, saveShopReview } from '../api/shop.api'
import { domainError } from '../api/domain.api'
import { localizeValidationErrors } from '../utils/validationLabels'
import { useAuthStore } from '../stores/auth'
import AccessibleDialog from './ui/AccessibleDialog.vue'
import StarPicker from './ui/StarPicker.vue'
import StarRating from './ui/StarRating.vue'
import IconButton from './ui/IconButton.vue'
// Star ratings of a shop: summary with a 5→1 breakdown, the newest ratings, and "Đánh giá gian hàng"
// for buyers who completed an order here (the server decides; the button only reflects its answer).
const props = defineProps({ shop: { type: Object, required: true } })
const emit = defineEmits(['summary'])
const auth = useAuthStore(),
  route = useRoute(),
  router = useRouter()
const rows = ref([]),
  summary = ref(null),
  meta = ref(null),
  mine = ref(null),
  loading = ref(false),
  error = ref('')
const open = ref(false),
  saving = ref(false),
  formError = ref(''),
  formErrors = ref({}),
  form = ref({ rating: 0, comment: '' })
let requestId = 0
const dateLabel = (value) => (value ? new Date(value).toLocaleDateString('vi-VN') : '')
const percent = (count) =>
  summary.value?.count ? Math.round((count / summary.value.count) * 100) : 0
const hasMore = computed(() => meta.value && meta.value.current_page < meta.value.last_page)
async function load(page = 1) {
  const current = ++requestId
  loading.value = true
  error.value = ''
  try {
    const [list, own] = await Promise.all([
      listShopReviews(props.shop.slug, { page }),
      auth.isAuthenticated ? getMyShopReview(props.shop.slug).catch(() => null) : null,
    ])
    if (current !== requestId) return
    rows.value = page === 1 ? list.data : [...rows.value, ...list.data]
    summary.value = list.summary
    meta.value = list.meta
    mine.value = own
    emit('summary', list.summary)
  } catch (failure) {
    if (current === requestId) error.value = domainError(failure)
  } finally {
    if (current === requestId) loading.value = false
  }
}
function start() {
  if (!auth.isAuthenticated) {
    router.push({ name: 'login', query: { redirect: route.fullPath } })
    return
  }
  form.value = {
    rating: mine.value?.review?.rating || 0,
    comment: mine.value?.review?.comment || '',
  }
  formError.value = ''
  formErrors.value = {}
  open.value = true
}
async function submit() {
  if (saving.value) return
  if (!form.value.rating) {
    formErrors.value = { rating: ['Hãy chọn số sao.'] }
    return
  }
  saving.value = true
  formError.value = ''
  formErrors.value = {}
  try {
    await saveShopReview(props.shop.slug, {
      rating: form.value.rating,
      comment: form.value.comment.trim() || null,
    })
    open.value = false
    await load()
  } catch (failure) {
    formErrors.value = localizeValidationErrors(failure.response?.data?.errors)
    formError.value =
      failure.response?.status === 403
        ? failure.response.data?.message || domainError(failure)
        : domainError(failure)
  } finally {
    saving.value = false
  }
}
watch(
  () => [props.shop.slug, auth.user?.id],
  () => load(),
  { immediate: true },
)
</script>
<template>
  <section
    id="shop-reviews"
    class="shop-reviews"
    aria-labelledby="shop-reviews-title"
    data-shop-reviews
  >
    <div class="shop-reviews-head">
      <h2 id="shop-reviews-title" class="h4 m-0">Đánh giá gian hàng</h2>
      <IconButton
        v-if="!mine?.own_shop && (!auth.isAuthenticated || mine?.can_review)"
        class="btn btn-primary"
        icon="star"
        :label="mine?.review ? 'Sửa đánh giá của bạn' : 'Đánh giá gian hàng'"
        show-label
        data-review-shop
        @click="start"
      />
    </div>
    <p
      v-if="auth.isAuthenticated && mine && !mine.can_review && !mine.own_shop"
      class="shop-reviews-note"
    >
      Bạn có thể đánh giá sau khi có đơn hàng hoàn tất ở gian hàng này.
    </p>
    <p v-if="error" class="text-danger" role="alert">{{ error }}</p>

    <div v-if="summary" class="shop-reviews-summary">
      <div class="shop-reviews-score">
        <strong data-review-average>{{ summary.count ? summary.average.toFixed(1) : '—' }}</strong>
        <StarRating :value="summary.average || 0" :size="20" />
        <span>{{ summary.count }} đánh giá</span>
      </div>
      <ul class="shop-reviews-bars" aria-label="Phân bố số sao">
        <li v-for="stars in [5, 4, 3, 2, 1]" :key="stars" :data-bar="stars">
          <span>{{ stars }}<span class="visually-hidden"> sao</span></span>
          <span class="shop-reviews-bar" aria-hidden="true"
            ><span :style="{ width: `${percent(summary.distribution[stars])}%` }"></span
          ></span>
          <span class="shop-reviews-bar-count">{{ summary.distribution[stars] }}</span>
        </li>
      </ul>
    </div>

    <p v-if="summary && !summary.count && !loading" class="shop-reviews-empty">
      Chưa có đánh giá nào. Người mua đã nhận hàng có thể là người đầu tiên.
    </p>
    <ul v-if="rows.length" class="shop-reviews-list">
      <li v-for="review in rows" :key="review.id" class="shop-review" data-shop-review>
        <div class="shop-review-top">
          <strong>{{ review.author?.name || 'Người mua' }}</strong>
          <StarRating :value="review.rating" :size="15" />
          <time :datetime="review.updated_at">{{ dateLabel(review.updated_at) }}</time>
        </div>
        <p v-if="review.comment" class="shop-review-comment">{{ review.comment }}</p>
      </li>
    </ul>
    <button
      v-if="hasMore"
      type="button"
      class="btn btn-outline-secondary"
      :disabled="loading"
      @click="load(meta.current_page + 1)"
    >
      Xem thêm đánh giá
    </button>

    <AccessibleDialog
      v-if="open"
      id="shop-review-dialog"
      :title="mine?.review ? 'Sửa đánh giá gian hàng' : 'Đánh giá gian hàng'"
      :description="shop.name"
      :busy="saving"
      @dismiss="open = false"
    >
      <form id="shop-review-form" @submit.prevent="submit">
        <StarPicker v-model="form.rating" :disabled="saving" label="Số sao cho gian hàng" />
        <p v-if="formErrors.rating" class="text-danger small mt-2">
          {{ formErrors.rating.join(' ') }}
        </p>
        <label for="shop-review-comment" class="form-label mt-3">Nhận xét (không bắt buộc)</label>
        <textarea
          id="shop-review-comment"
          v-model="form.comment"
          class="form-control"
          rows="4"
          maxlength="2000"
          :disabled="saving"
          placeholder="Giao hàng, đóng gói, mô tả có đúng không…"
        ></textarea>
        <p v-if="formErrors.comment" class="text-danger small mt-2">
          {{ formErrors.comment.join(' ') }}
        </p>
        <p v-if="formError" class="text-danger mt-2 mb-0" role="alert">{{ formError }}</p>
      </form>
      <template #footer>
        <div class="d-flex justify-content-end gap-2">
          <button
            type="button"
            class="btn btn-outline-secondary"
            :disabled="saving"
            @click="open = false"
          >
            Hủy
          </button>
          <button
            type="submit"
            form="shop-review-form"
            class="btn btn-primary"
            :disabled="saving"
            data-review-submit
          >
            {{ saving ? 'Đang gửi…' : 'Gửi đánh giá' }}
          </button>
        </div>
      </template>
    </AccessibleDialog>
  </section>
</template>
<style scoped>
.shop-reviews {
  display: grid;
  gap: var(--space-4);
  margin-top: var(--space-6);
  padding: var(--space-6);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  background: var(--surface);
}
.shop-reviews-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}
.shop-reviews-note,
.shop-reviews-empty {
  margin: 0;
  color: var(--muted);
}
.shop-reviews-summary {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: var(--space-6);
  align-items: center;
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--surface-2);
}
.shop-reviews-score {
  display: grid;
  justify-items: center;
  gap: 0.25rem;
  min-width: 8rem;
}
.shop-reviews-score strong {
  font-size: 2.5rem;
  line-height: 1;
}
.shop-reviews-score > span:last-child {
  color: var(--muted);
  font-size: 0.85rem;
}
.shop-reviews-bars {
  display: grid;
  gap: 0.35rem;
  margin: 0;
  padding: 0;
  list-style: none;
}
.shop-reviews-bars li {
  display: grid;
  grid-template-columns: 1.25rem minmax(0, 1fr) 2.5rem;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
}
.shop-reviews-bar {
  height: 8px;
  overflow: hidden;
  border-radius: var(--radius-pill);
  background: var(--border);
}
.shop-reviews-bar > span {
  display: block;
  height: 100%;
  border-radius: inherit;
  background: var(--rating);
}
.shop-reviews-bar-count {
  color: var(--muted);
  text-align: right;
}
.shop-reviews-list {
  display: grid;
  gap: var(--space-3);
  margin: 0;
  padding: 0;
  list-style: none;
}
.shop-review {
  padding: var(--space-3) 0;
  border-top: 1px solid var(--border);
}
.shop-review-top {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.5rem 0.75rem;
}
.shop-review-top time {
  margin-left: auto;
  color: var(--muted);
  font-size: 0.8rem;
}
.shop-review-comment {
  margin: 0.4rem 0 0;
  white-space: pre-line;
  overflow-wrap: anywhere;
}
@media (max-width: 575px) {
  .shop-reviews {
    padding: var(--space-4);
  }
  .shop-reviews-summary {
    grid-template-columns: minmax(0, 1fr);
    gap: var(--space-3);
  }
}
</style>

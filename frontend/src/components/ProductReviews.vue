<script setup>
import { onBeforeUnmount, ref, watch } from 'vue'
import { useAuthStore } from '../stores/auth'
import { listProductReviews, getReviewEligibility } from '../api/review.api'
import { domainError } from '../api/domain.api'
import CollectionPager from './CollectionPager.vue'
import DomainFeedback from './DomainFeedback.vue'
import ReviewEditor from './ReviewEditor.vue'
const props = defineProps({ product: { type: Object, required: true } }),
  emit = defineEmits(['changed'])
const auth = useAuthStore(),
  result = ref(null),
  eligibility = ref(null),
  error = ref(''),
  eligibilityError = ref(''),
  busy = ref(false),
  checking = ref(false)
let reviewTicket = 0,
  eligibilityTicket = 0
async function loadReviews(page = 1) {
  const ticket = ++reviewTicket
  busy.value = true
  error.value = ''
  try {
    const response = await listProductReviews(props.product.slug, { page, per_page: 10 })
    if (ticket === reviewTicket) result.value = response
  } catch (failure) {
    if (ticket === reviewTicket) {
      result.value = null
      error.value = domainError(failure)
    }
  } finally {
    if (ticket === reviewTicket) busy.value = false
  }
}
async function loadEligibility(page = 1) {
  const ticket = ++eligibilityTicket
  eligibility.value = null
  eligibilityError.value = ''
  if (!auth.isAuthenticated) {
    checking.value = false
    return
  }
  checking.value = true
  try {
    const response = await getReviewEligibility(props.product.slug, { page, per_page: 10 })
    if (ticket === eligibilityTicket) eligibility.value = response
  } catch (failure) {
    if (ticket === eligibilityTicket) eligibilityError.value = domainError(failure)
  } finally {
    if (ticket === eligibilityTicket) checking.value = false
  }
}
function refresh() {
  loadReviews()
  loadEligibility()
}
function saved(item, review) {
  item.review = review
  item.can_review = false
  item.can_edit_review = true
  loadReviews()
  emit('changed')
}
watch(
  () => props.product.slug,
  () => {
    result.value = null
    refresh()
  },
  { immediate: true },
)
watch(
  () => auth.user?.id,
  () => loadEligibility(),
)
onBeforeUnmount(() => {
  reviewTicket++
  eligibilityTicket++
})
</script>
<template>
  <section id="reviews" class="mt-4 border-top pt-4">
    <h2 class="h4">Đánh giá sản phẩm</h2>
    <p data-rating-summary>
      {{ product.review_count ? `${product.average_rating} / 5` : 'Chưa có đánh giá' }} ·
      {{ product.review_count ?? 0 }} đánh giá
    </p>
    <DomainFeedback :busy="busy" :error="error" />
    <button
      type="button"
      class="btn btn-outline-secondary btn-sm mb-3"
      :disabled="busy || checking"
      @click="refresh"
    >
      Tải lại đánh giá
    </button>
    <p v-if="result && !result.data.length" class="text-secondary">Chưa có đánh giá công khai.</p>
    <article
      v-for="review in result?.data || []"
      :id="`review-${review.id}`"
      :key="review.id"
      class="border-bottom py-2"
      data-public-review
    >
      <strong>{{ review.author?.name || 'Người mua' }}</strong
      ><span class="ms-2">{{ review.rating }} / 5</span>
      <p class="text-break mb-1" style="white-space: pre-wrap">{{ review.comment }}</p>
    </article>
    <CollectionPager :meta="result?.meta" :busy="busy" @page="loadReviews" />
    <template v-if="auth.isAuthenticated"
      ><h3 class="h5 mt-4">Đánh giá từ đơn đã nhận</h3>
      <DomainFeedback :busy="checking" :error="eligibilityError" />
      <p v-if="eligibility && !eligibility.data.length" class="text-secondary">
        Bạn chỉ có thể đánh giá sản phẩm trong đơn đã hoàn tất.
      </p>
      <section
        v-for="item in eligibility?.data || []"
        :key="item.id"
        class="border rounded p-3 mb-2"
        data-review-purchase
      >
        <p class="mb-1">{{ item.product_name }} · Mục mua #{{ item.id }}</p>
        <ReviewEditor :item="item" @saved="saved(item, $event)" />
      </section>
      <CollectionPager :meta="eligibility?.meta" :busy="checking" @page="loadEligibility"
    /></template>
    <p v-else class="text-secondary">
      Đăng nhập tài khoản đã mua hàng để đánh giá sau khi nhận hàng.
    </p>
  </section>
</template>

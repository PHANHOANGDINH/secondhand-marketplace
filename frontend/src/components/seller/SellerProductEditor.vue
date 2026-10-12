<script setup>
import { useSellerIdentity } from './useSellerIdentity'
import PageHeader from '../ui/PageHeader.vue'
import StatusBadge from '../ui/StatusBadge.vue'

import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as productsApi from '../../api/product.api'
import { getSellerShop } from '../../api/shop.api'
import { categoryOptions } from '../../api/catalogOptions'
import {
  conditions,
  sellerActions,
  actionLabels,
  formatPrice,
} from '../../composables/productPresentation'
import { useFeedback } from '../../composables/useFeedback'
import DomainField from '../DomainField.vue'
import DomainFeedback from '../DomainFeedback.vue'
import ProductImagesEditor from '../ProductImagesEditor.vue'
import ProductVideosEditor from '../ProductVideosEditor.vue'
import ProductPhoto from '../ProductPhoto.vue'
const props = defineProps({ embedded: Boolean })
const emit = defineEmits(['busy-change'])
const internalId = ref(null),
  stagedPreviews = ref([]),
  imageBusy = ref(false),
  videoBusy = ref(false),
  videoDirty = ref(false),
  baseline = ref('')
const route = useRoute(),
  router = useRouter(),
  product = ref(null),
  shop = ref(null),
  loaded = ref(false),
  categories = ref([])
const productId = computed(() => (props.embedded ? internalId.value : route.params.id))
const creating = computed(() => !productId.value)
const blank = () => ({
  name: '',
  description: '',
  category_id: '',
  price: '',
  stock_quantity: '1',
  condition: 'GOOD',
})
const form = reactive(blank())
const { busy, error, errors, success, run } = useFeedback()
const editable = computed(() =>
  creating.value ? ['PENDING', 'ACTIVE'].includes(shop.value?.status) : product.value?.can_edit,
)
const categoryChoices = computed(() => {
  const choices = [...categories.value]
  if (product.value?.category && !choices.some((row) => row.value === product.value.category.id))
    choices.push({
      value: product.value.category.id,
      label: `${product.value.category.name} (không còn khả dụng)`,
    })
  return [{ value: '', label: 'Chọn danh mục' }, ...choices]
})
function fill(value) {
  product.value = value
  Object.assign(form, {
    name: value.name,
    description: value.description,
    category_id: value.category.id,
    price: value.price,
    stock_quantity: String(value.stock_quantity),
    condition: value.condition,
  })
  baseline.value = JSON.stringify(form)
}
const load = () =>
  run(async () => {
    loaded.value = false
    if (creating.value) {
      product.value = null
      Object.assign(form, blank())
      shop.value = await getSellerShop()
    } else fill(await productsApi.getSellerProduct(productId.value))
    categories.value = await categoryOptions()
    baseline.value = JSON.stringify(form)
    loaded.value = true
  })
const save = () =>
  run(async () => {
    const data = { ...form }
    if (creating.value) {
      fill(await productsApi.createProduct(data))
      if (props.embedded) internalId.value = product.value.id
      else await router.replace(`/seller/products/${product.value.id}/edit`)
    } else {
      data.expected_updated_at = product.value.updated_at
      fill(await productsApi.updateProduct(product.value.id, data))
    }
  }, 'Đã lưu sản phẩm. Bạn có thể quản lý ảnh bên dưới.')
const action = (target) =>
  run(async () => {
    if (target === 'delete') {
      await productsApi.deleteProduct(product.value.id)
      await router.push('/seller/products')
    } else fill(await productsApi.productAction(product.value.id, target))
  }, 'Đã cập nhật trạng thái.')
const refreshImages = () =>
  run(async () => fill(await productsApi.getSellerProduct(product.value.id)))
watch(
  () => productId.value,
  () => {
    if (!busy.value && !props.embedded) load()
  },
)
onMounted(load)
useSellerIdentity(() =>
  creating.value
    ? shop.value
    : product.value
      ? { ...product.value.shop, status: product.value.shop_status }
      : null,
)
const imageProduct = computed(
  () => product.value || { id: null, images: [], can_edit: editable.value },
)
const hasUnsavedChanges = computed(
  () =>
    loaded.value &&
    (JSON.stringify(form) !== baseline.value ||
      stagedPreviews.value.length > 0 ||
      videoDirty.value),
)
const reviewImage = computed(
  () =>
    stagedPreviews.value[0]?.url || product.value?.images?.find((image) => image.is_primary)?.url,
)
const reviewCategory = computed(
  () =>
    categoryChoices.value.find((row) => row.value === form.category_id)?.label ||
    'Chưa chọn danh mục',
)
const combinedBusy = computed(() => busy.value || imageBusy.value || videoBusy.value)
watch(combinedBusy, (value) => emit('busy-change', value), { immediate: true, flush: 'sync' })
defineExpose({ hasUnsavedChanges })
</script>
<template>
  <section class="seller-editor">
    <RouterLink v-if="!embedded" to="/seller/products">← Sản phẩm của tôi</RouterLink>
    <PageHeader
      v-if="!embedded"
      :title="creating ? 'Đăng bán sản phẩm' : 'Chi tiết / sửa sản phẩm'"
      eyebrow="Không gian người bán"
      description="Mô tả chính xác và hình ảnh rõ ràng giúp món đồ tìm được chủ mới."
    />
    <DomainFeedback
      :busy="busy"
      :loading-layout="loaded ? undefined : 'form'"
      :error="error"
      :success="success"
    />
    <div v-for="key in ['product', 'images', 'expected_updated_at']" :key="key" class="text-danger">
      <div v-for="message in errors[key] || []" :key="message">{{ message }}</div>
    </div>
    <p v-if="embedded && error && !loaded" class="alert alert-info">
      Nếu chưa có gian hàng, hãy
      <RouterLink to="/seller/shop">tạo hoặc kiểm tra gian hàng của bạn</RouterLink> trước khi đăng
      bán.
    </p>
    <button class="btn btn-outline-secondary mb-3" :disabled="combinedBusy" @click="load">
      Tải lại dữ liệu
    </button>
    <template v-if="loaded">
      <p v-if="product" class="seller-status-line">
        Trạng thái: <StatusBadge :status="product.status" />
        <RouterLink v-if="embedded" :to="`/seller/products/${product.id}/edit`" class="ms-2"
          >Mở trang sản phẩm</RouterLink
        >
      </p>
      <p v-if="product?.moderation_reason" class="alert alert-info">
        Kiểm duyệt: {{ product.moderation_reason }}
      </p>
      <p v-if="!editable" class="alert alert-warning">Gian hàng hiện chỉ được xem sản phẩm.</p>
      <p v-if="product?.status === 'ACTIVE'" class="alert alert-info">
        Sửa nội dung hoặc tồn kho dương sẽ gửi duyệt lại. Đặt tồn kho bằng 0 chuyển sản phẩm sang
        trạng thái đã bán.
      </p>
      <div class="listing-layout">
        <div class="listing-main">
          <ProductImagesEditor
            :product="imageProduct"
            :disabled="busy || videoBusy || !editable"
            :input-id="embedded ? 'quick-product-images' : 'product_images'"
            :heading-level="embedded ? 3 : 2"
            @changed="refreshImages"
            @previews-change="stagedPreviews = $event"
            @busy-change="imageBusy = $event"
          />
          <form
            :id="embedded ? 'quick-product-form' : 'seller-product-form'"
            class="seller-form"
            @submit.prevent="save"
          >
            <fieldset :disabled="combinedBusy || !editable">
              <section class="workspace-panel">
                <div class="workspace-panel-heading">
                  <span class="workspace-step">02</span
                  ><component :is="embedded ? 'h3' : 'h2'">Thông tin cơ bản</component>
                </div>
                <DomainField
                  v-model="form.name"
                  :id-prefix="embedded ? 'quick-' : ''"
                  name="name"
                  label="Tên sản phẩm"
                  required
                  :maxlength="200"
                  :errors="errors.name"
                />
                <DomainField
                  v-model="form.category_id"
                  :id-prefix="embedded ? 'quick-' : ''"
                  name="category_id"
                  label="Danh mục"
                  type="select"
                  required
                  :options="categoryChoices"
                  :errors="errors.category_id"
                />
                <DomainField
                  v-model="form.description"
                  :id-prefix="embedded ? 'quick-' : ''"
                  name="description"
                  label="Mô tả tình trạng thực tế"
                  type="textarea"
                  required
                  :maxlength="10000"
                  :errors="errors.description"
                />
                <p class="form-text mb-0">
                  Nêu tình trạng, thời gian sử dụng và phụ kiện đi kèm nếu có.
                </p>
              </section>
              <section class="workspace-panel">
                <div class="workspace-panel-heading">
                  <span class="workspace-step">03</span
                  ><component :is="embedded ? 'h3' : 'h2'">Giá &amp; số lượng</component>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <DomainField
                      v-model="form.price"
                      :id-prefix="embedded ? 'quick-' : ''"
                      name="price"
                      label="Giá (VND, tối đa 2 chữ số sau dấu chấm)"
                      required
                      :errors="errors.price"
                    />
                  </div>
                  <div class="col-md-6">
                    <DomainField
                      v-model="form.stock_quantity"
                      :id-prefix="embedded ? 'quick-' : ''"
                      name="stock_quantity"
                      label="Số lượng tồn"
                      type="number"
                      required
                      :errors="errors.stock_quantity"
                    />
                  </div>
                  <div class="col-12">
                    <DomainField
                      v-model="form.condition"
                      :id-prefix="embedded ? 'quick-' : ''"
                      name="condition"
                      label="Tình trạng"
                      type="select"
                      required
                      :options="conditions"
                      :errors="errors.condition"
                    />
                  </div>
                </div>
              </section>
              <div class="seller-submit-bar">
                <button v-if="editable" type="submit" class="btn btn-primary">
                  {{ creating ? 'Tạo bản nháp' : 'Lưu thay đổi' }}</button
                ><span class="text-secondary"
                  >Lưu bản nháp trước khi tải ảnh lên. Gửi duyệt là bước riêng.</span
                >
              </div>
            </fieldset>
          </form>
          <ProductVideosEditor
            :product="imageProduct"
            :disabled="busy || imageBusy || !editable"
            :input-id="embedded ? 'quick-product-video' : 'product-video'"
            :heading-level="embedded ? 3 : 2"
            @changed="refreshImages"
            @busy-change="videoBusy = $event"
            @dirty-change="videoDirty = $event"
          />
        </div>
        <aside class="listing-review">
          <section class="workspace-panel">
            <div class="workspace-panel-heading">
              <span class="workspace-step">04</span
              ><component :is="embedded ? 'h3' : 'h2'">Xem trước</component>
            </div>
            <ProductPhoto
              :src="reviewImage"
              :alt="form.name || 'Xem trước sản phẩm'"
              class="listing-preview-photo"
            />
            <p v-if="stagedPreviews.length" class="form-text">Ảnh đã chọn chưa được tải lên.</p>
            <p class="listing-preview-title">{{ form.name || 'Tên sản phẩm của bạn' }}</p>
            <p class="money-value listing-preview-price">{{ formatPrice(form.price) }}</p>
            <p class="text-muted small mb-0">{{ reviewCategory }}</p>
          </section>
          <section class="workspace-panel">
            <div class="workspace-panel-heading">
              <span class="workspace-step">05</span
              ><component :is="embedded ? 'h3' : 'h2'">Lưu &amp; gửi duyệt</component>
            </div>
            <p v-if="!product" class="text-secondary mb-0">
              Lưu thông tin để tạo bản nháp. Sau đó tải ảnh và gửi duyệt khi gian hàng được phép.
            </p>
            <template v-else
              ><p class="text-secondary">
                Trạng thái hiện tại: <StatusBadge :status="product.status" />
              </p>
              <p v-if="!sellerActions(product).length" class="text-secondary mb-0">
                Hiện không có thao tác chuyển trạng thái khả dụng.
              </p>
              <div class="d-flex flex-wrap gap-2">
                <button
                  v-for="target in sellerActions(product)"
                  :key="target"
                  :data-action="target"
                  :class="[
                    'btn',
                    target === 'submit'
                      ? 'btn-primary'
                      : target === 'delete'
                        ? 'btn-outline-danger'
                        : 'btn-outline-secondary',
                  ]"
                  :disabled="combinedBusy"
                  @click="action(target)"
                >
                  {{ actionLabels[target] }}
                </button>
              </div>
            </template>
          </section>
        </aside>
      </div>
    </template>
  </section>
</template>
<style scoped>
.listing-layout {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(220px, 0.42fr);
  gap: var(--space-6);
  align-items: start;
}
.listing-main,
.listing-review {
  min-width: 0;
}
.listing-main :deep(.seller-images) {
  margin-top: 0 !important;
}
.workspace-panel {
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  background: var(--surface);
  padding: var(--space-6);
  margin-bottom: var(--space-4);
  box-shadow: var(--shadow-card);
}
.workspace-panel-heading {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-4);
}
.workspace-panel-heading :is(h2, h3) {
  font-size: var(--font-size-section);
  font-weight: 650;
  margin: 0;
}
.workspace-step {
  color: var(--primary);
  font-weight: 700;
  font-size: var(--font-size-small);
}
.listing-preview-photo {
  aspect-ratio: 1;
  width: 100%;
}
.listing-preview-title {
  font-weight: 650;
  margin: var(--space-4) 0 var(--space-2);
  overflow-wrap: anywhere;
}
.listing-preview-price {
  font-size: 1.25rem;
  color: var(--primary);
  font-weight: 700;
}
@media (max-width: 991px) {
  .listing-layout {
    grid-template-columns: minmax(0, 1fr);
  }
  .listing-review {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: var(--space-4);
  }
  .listing-preview-photo {
    max-height: 180px;
  }
}
@media (max-width: 575px) {
  .listing-review {
    grid-template-columns: minmax(0, 1fr);
  }
  .workspace-panel {
    padding: var(--space-4);
  }
}
</style>

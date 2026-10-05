<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as productsApi from '../api/product.api'
import { getSellerShop } from '../api/shop.api'
import { categoryOptions } from '../api/catalogOptions'
import { conditions, sellerActions, actionLabels } from '../composables/productPresentation'
import { useFeedback } from '../composables/useFeedback'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import ProductImagesEditor from '../components/ProductImagesEditor.vue'
const route = useRoute(),
  router = useRouter(),
  product = ref(null),
  shop = ref(null),
  loaded = ref(false),
  categories = ref([])
const creating = computed(() => !route.params.id)
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
}
const load = () =>
  run(async () => {
    loaded.value = false
    if (creating.value) {
      product.value = null
      Object.assign(form, blank())
      shop.value = await getSellerShop()
    } else fill(await productsApi.getSellerProduct(route.params.id))
    categories.value = await categoryOptions()
    loaded.value = true
  })
const save = () =>
  run(async () => {
    const data = { ...form }
    if (creating.value) {
      fill(await productsApi.createProduct(data))
      await router.replace(`/seller/products/${product.value.id}/edit`)
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
  () => route.params.id,
  () => {
    if (!busy.value) load()
  },
)
onMounted(load)
</script>
<template>
  <section>
    <RouterLink to="/seller/products">← Sản phẩm của tôi</RouterLink>
    <h1 class="h3 mt-3">{{ creating ? 'Thêm sản phẩm' : 'Chi tiết / sửa sản phẩm' }}</h1>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div v-for="key in ['product', 'images', 'expected_updated_at']" :key="key" class="text-danger">
      <div v-for="message in errors[key] || []" :key="message">{{ message }}</div>
    </div>
    <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load">
      Tải lại dữ liệu
    </button>
    <template v-if="loaded">
      <p v-if="product">
        Trạng thái: <strong>{{ product.status }}</strong>
      </p>
      <p v-if="product?.moderation_reason" class="alert alert-info">
        Kiểm duyệt: {{ product.moderation_reason }}
      </p>
      <p v-if="!editable" class="alert alert-warning">Gian hàng hiện chỉ được xem sản phẩm.</p>
      <p v-if="product?.status === 'ACTIVE'" class="alert alert-info">
        Sửa nội dung hoặc tồn kho dương sẽ gửi duyệt lại. Đặt tồn kho bằng 0 chuyển sản phẩm sang
        SOLD.
      </p>
      <form style="max-width: 760px" @submit.prevent="save">
        <fieldset :disabled="busy || !editable">
          <DomainField
            v-model="form.name"
            name="name"
            label="Tên sản phẩm"
            required
            :maxlength="200"
            :errors="errors.name"
          />
          <DomainField
            v-model="form.description"
            name="description"
            label="Mô tả tình trạng thực tế"
            type="textarea"
            required
            :maxlength="10000"
            :errors="errors.description"
          />
          <DomainField
            v-model="form.category_id"
            name="category_id"
            label="Danh mục"
            type="select"
            required
            :options="categoryChoices"
            :errors="errors.category_id"
          />
          <DomainField
            v-model="form.price"
            name="price"
            label="Giá (VND, tối đa 2 chữ số sau dấu chấm)"
            required
            :errors="errors.price"
          />
          <DomainField
            v-model="form.stock_quantity"
            name="stock_quantity"
            label="Số lượng tồn"
            type="number"
            required
            :errors="errors.stock_quantity"
          />
          <DomainField
            v-model="form.condition"
            name="condition"
            label="Tình trạng"
            type="select"
            required
            :options="conditions"
            :errors="errors.condition"
          />
          <button v-if="editable" type="submit" class="btn btn-primary">
            {{ creating ? 'Tạo bản nháp' : 'Lưu thay đổi' }}
          </button>
        </fieldset>
      </form>
      <template v-if="product"
        ><div class="d-flex gap-2 flex-wrap mt-3">
          <button
            v-for="target in sellerActions(product)"
            :key="target"
            :data-action="target"
            class="btn btn-outline-primary"
            :disabled="busy"
            @click="action(target)"
          >
            {{ actionLabels[target] }}
          </button>
        </div>
        <ProductImagesEditor :product="product" :disabled="busy" @changed="refreshImages"
      /></template>
    </template>
  </section>
</template>

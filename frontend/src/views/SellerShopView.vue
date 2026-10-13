<script setup>
import FirstTimeGuide from '../components/onboarding/FirstTimeGuide.vue'
import { useSellerIdentity } from '../components/seller/useSellerIdentity'
import PageHeader from '../components/ui/PageHeader.vue'
import StatusBadge from '../components/ui/StatusBadge.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import * as shopsApi from '../api/shop.api'
import { useFeedback } from '../composables/useFeedback'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
const shop = ref(null),
  loaded = ref(false)
const form = reactive({ name: '', description: '' })
const { busy, error, errors, success, run } = useFeedback()
const suspended = computed(() => shop.value?.status === 'SUSPENDED')
function fill(value) {
  shop.value = value
  form.name = value?.name || ''
  form.description = value?.description || ''
}
const load = () =>
  run(async () => {
    try {
      fill(await shopsApi.getSellerShop())
    } catch (failure) {
      if (failure.response?.status !== 404) throw failure
      fill(null)
    }
    loaded.value = true
  })
const save = () =>
  run(async () => {
    const data = { name: form.name, description: form.description || null }
    fill(await (shop.value ? shopsApi.updateShop(data) : shopsApi.createShop(data)))
  }, 'Đã lưu gian hàng.')
const submit = () => run(async () => fill(await shopsApi.submitShop()), 'Đã gửi lại để xét duyệt.')
onMounted(load)
useSellerIdentity(() => shop.value)
</script>
<template>
  <section class="seller-form">
    <PageHeader
      title="Gian hàng của tôi"
      eyebrow="Không gian người bán"
      description="Thông tin giới thiệu và trạng thái hoạt động của gian hàng."
    />
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load">Tải lại</button>
    <template v-if="loaded">
      <FirstTimeGuide
        v-if="shop"
        name="shop-created"
        title="Gian hàng đã được tạo"
        :steps="[
          { title: 'Xét duyệt:', text: 'gian hàng cần được duyệt trước khi đăng sản phẩm công khai.' },
          { title: 'Sản phẩm:', text: 'soạn tin ở mục Sản phẩm của tôi hoặc bằng Đăng bán nhanh.' },
          { title: 'Đơn bán:', text: 'đơn mới xuất hiện ở mục Đơn bán và trên trang Thống kê.' },
        ]"
      />
      <div v-if="shop" class="workspace-panel">
        <h2 class="h4 mb-3">{{ shop.name }}</h2>
        <p>Trạng thái: <StatusBadge :status="shop.status" /></p>
        <p class="text-secondary">
          {{
            {
              PENDING: 'Gian hàng đang chờ xét duyệt trước khi hiển thị công khai.',
              ACTIVE: 'Gian hàng đang hoạt động và hiển thị trên sàn mua bán.',
              SUSPENDED: 'Gian hàng đang tạm ngừng, thông tin hiện ở chế độ chỉ xem.',
              REJECTED:
                'Gian hàng chưa được duyệt. Xem lý do, sửa thông tin và gửi lại để xét duyệt.',
            }[shop.status]
          }}
        </p>
        <p v-if="shop.moderation_reason">Thông tin kiểm duyệt: {{ shop.moderation_reason }}</p>
        <RouterLink v-if="shop.status === 'ACTIVE'" :to="`/shops/${shop.slug}`"
          >Xem gian hàng công khai</RouterLink
        >
      </div>
      <p v-else>
        Tạo gian hàng để bắt đầu bán đồ đã qua sử dụng. Gian hàng sẽ được xét duyệt trước khi hiển
        thị công khai.
      </p>
      <p v-if="suspended" class="alert alert-warning">
        Gian hàng đang tạm ngừng. Bạn có thể xem thông tin và liên hệ quản trị viên.
      </p>
      <form class="workspace-panel" @submit.prevent="save">
        <div class="workspace-panel-heading"><h2>Thông tin gian hàng</h2></div>
        <DomainField
          v-model="form.name"
          name="name"
          label="Tên gian hàng"
          required
          :maxlength="150"
          :disabled="suspended || busy"
          :errors="errors.name"
        />
        <DomainField
          v-model="form.description"
          name="description"
          label="Giới thiệu"
          type="textarea"
          :maxlength="10000"
          :disabled="suspended || busy"
          :errors="errors.description"
        />
        <button v-if="!suspended" type="submit" class="btn btn-primary" :disabled="busy">
          {{ shop ? 'Lưu gian hàng' : 'Tạo gian hàng' }}
        </button>
      </form>
      <button
        v-if="shop?.status === 'REJECTED'"
        class="btn btn-outline-primary mt-3"
        :disabled="busy"
        @click="submit"
      >
        Gửi lại để xét duyệt
      </button>
    </template>
  </section>
</template>

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
import { useArea } from '../composables/useArea'
import DetailSheet from '../components/ui/DetailSheet.vue'
import PicturePicker from '../components/account/PicturePicker.vue'
const shop = ref(null),
  loaded = ref(false),
  // An existing shop is shown as details; "Sửa thông tin" opens the form beside them.
  editing = ref(false)
const form = reactive({ name: '', description: '', province_code: '' })
const { provinces, loadProvinces } = useArea()
const { busy, error, errors, success, run } = useFeedback()
const suspended = computed(() => shop.value?.status === 'SUSPENDED')
function fill(value) {
  shop.value = value
  form.name = value?.name || ''
  form.description = value?.description || ''
  form.province_code = value?.province_code || ''
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
    const data = {
      name: form.name,
      description: form.description || null,
      // Sent only when an area is chosen or an existing one is cleared.
      ...(form.province_code || shop.value?.province_code
        ? { province_code: form.province_code || null }
        : {}),
    }
    fill(await (shop.value ? shopsApi.updateShop(data) : shopsApi.createShop(data)))
    editing.value = false
  }, 'Đã lưu gian hàng.')
function closeEdit() {
  fill(shop.value)
  editing.value = false
}
const submit = () => run(async () => fill(await shopsApi.submitShop()), 'Đã gửi lại để xét duyệt.')
onMounted(load)
onMounted(loadProvinces)
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
    <button v-if="error" class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load">
      Tải lại
    </button>
    <template v-if="loaded">
      <FirstTimeGuide
        v-if="shop"
        name="shop-created"
        title="Gian hàng đã được tạo"
        :steps="[
          { title: 'Hoạt động ngay:', text: 'gian hàng mở bán ngay, không phải chờ duyệt.' },
          { title: 'Sản phẩm:', text: 'soạn tin ở mục Sản phẩm của tôi hoặc bằng Đăng bán nhanh.' },
          { title: 'Đơn bán:', text: 'đơn mới xuất hiện ở mục Đơn bán và trên trang Thống kê.' },
        ]"
      />
      <PicturePicker
        v-if="shop && !suspended"
        class="mb-3"
        :name="shop.name"
        :url="shop.logo_url || ''"
        title="Ảnh gian hàng"
        hint="Người mua thấy ảnh này ở gian hàng và tin đăng của bạn."
        noun="ảnh gian hàng"
        :upload="(picture) => shopsApi.uploadShopLogo(picture)"
        :remove="() => shopsApi.removeShopLogo()"
        @changed="shop = $event"
      />
      <div v-if="shop" class="workspace-panel" data-shop-view>
        <div class="workspace-panel-heading">
          <h2 class="h4 m-0">{{ shop.name }}</h2>
          <StatusBadge :status="shop.status" />
          <button
            v-if="!suspended"
            type="button"
            class="btn btn-outline-primary btn-sm ms-auto"
            :disabled="busy"
            data-edit
            @click="editing = true"
          >
            Sửa thông tin
          </button>
        </div>
        <p class="text-secondary">
          {{
            {
              PENDING: 'Gian hàng đang chờ xét duyệt trước khi hiển thị công khai.',
              ACTIVE: 'Gian hàng đang hoạt động và hiển thị trên sàn mua bán.',
              SUSPENDED:
                'Gian hàng bị quản trị viên khoá (thường do nhiều báo cáo vi phạm); thông tin ở chế độ chỉ xem.',
              REJECTED:
                'Gian hàng chưa được duyệt. Xem lý do, sửa thông tin và gửi lại để xét duyệt.',
            }[shop.status]
          }}
        </p>
        <dl class="account-details">
          <dt>Giới thiệu</dt>
          <dd>{{ shop.description || 'Chưa có giới thiệu' }}</dd>
          <dt>Khu vực</dt>
          <dd>{{ shop.province_name || 'Chưa chọn' }}</dd>
        </dl>
        <p v-if="shop.moderation_reason">Quản trị viên: {{ shop.moderation_reason }}</p>
        <RouterLink v-if="shop.status === 'ACTIVE'" class="d-inline-block mt-3" :to="`/shops/${shop.slug}`"
          >Xem gian hàng công khai</RouterLink
        >
      </div>
      <p v-else>
        Tạo gian hàng để bắt đầu bán đồ đã qua sử dụng. Gian hàng hoạt động ngay, tin đăng hiện công
        khai khi bạn bấm Đăng bán.
      </p>
      <p v-if="suspended" class="alert alert-warning">
        Gian hàng đang tạm ngừng. Bạn có thể xem thông tin và liên hệ quản trị viên.
      </p>
      <!-- A new shop fills the form on the page; an existing one edits it in a side panel. -->
      <component
        :is="shop ? DetailSheet : 'div'"
        v-if="!shop || editing"
        v-bind="shop ? { id: 'shop-edit', open: editing, title: 'Sửa thông tin gian hàng', busy } : {}"
        @close="closeEdit"
      >
      <form :class="{ 'workspace-panel': !shop }" @submit.prevent="save">
        <div v-if="!shop" class="workspace-panel-heading"><h2>Thông tin gian hàng</h2></div>
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
        <DomainField
          v-model="form.province_code"
          name="province_code"
          label="Khu vực (tỉnh/thành)"
          type="select"
          :options="[
            { value: '', label: 'Chưa chọn khu vực' },
            ...provinces.map((row) => ({ value: row.code, label: row.name })),
          ]"
          :disabled="suspended || busy"
          :errors="errors.province_code"
        />
        <div class="d-flex gap-2">
          <button v-if="!suspended" type="submit" class="btn btn-primary" :disabled="busy">
            {{ shop ? 'Lưu gian hàng' : 'Tạo gian hàng' }}</button
          ><button v-if="shop" type="button" class="btn btn-outline-secondary" :disabled="busy" @click="closeEdit">
            Hủy
          </button>
        </div>
      </form>
      </component>
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

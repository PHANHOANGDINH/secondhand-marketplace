<script setup>
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
</script>
<template>
  <section style="max-width: 720px">
    <h1 class="h3">Gian hàng của tôi</h1>
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load">Tải lại</button>
    <template v-if="loaded">
      <div v-if="shop" class="mb-3">
        <p>
          Trạng thái: <span class="badge text-bg-secondary">{{ shop.status }}</span>
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
      <form @submit.prevent="save">
        <DomainField
          v-model="form.name"
          name="name"
          label="Tên gian hàng"
          required
          :maxlength="150"
          :disabled="suspended"
          :errors="errors.name"
        />
        <DomainField
          v-model="form.description"
          name="description"
          label="Giới thiệu"
          type="textarea"
          :maxlength="10000"
          :disabled="suspended"
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

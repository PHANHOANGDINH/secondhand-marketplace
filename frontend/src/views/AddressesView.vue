<script setup>
import PageHeader from '../components/ui/PageHeader.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ConfirmDelete from '../components/ui/ConfirmDelete.vue'
import { onMounted, reactive, ref } from 'vue'
import * as addressesApi from '../api/address.api'
import { useFeedback } from '../composables/useFeedback'
import DomainField from '../components/DomainField.vue'
import DomainFeedback from '../components/DomainFeedback.vue'
import CollectionPager from '../components/CollectionPager.vue'
const rows = ref([]),
  meta = ref(null),
  editing = ref(null),
  page = ref(1)
const blank = () => ({
  recipient_name: '',
  phone: '',
  address_line: '',
  ward: '',
  district: '',
  province: '',
  is_default: false,
})
const form = reactive(blank())
const fields = [
  ['recipient_name', 'Người nhận', 100],
  ['phone', 'Số điện thoại', 20],
  ['address_line', 'Địa chỉ', 255],
  ['ward', 'Phường / xã', 100],
  ['district', 'Quận / huyện (không bắt buộc)', 100],
  ['province', 'Tỉnh / thành phố', 100],
]
const { busy, error, errors, success, clear, run } = useFeedback()
async function refresh() {
  let result = await addressesApi.listAddresses({ page: page.value })
  if (page.value > result.meta.last_page) {
    page.value = result.meta.last_page
    result = await addressesApi.listAddresses({ page: page.value })
  }
  rows.value = result.data
  meta.value = result.meta
}
const load = (target = page.value) =>
  run(async () => {
    page.value = target
    await refresh()
  })
function cancelEdit() {
  reset()
  clear()
}
function reset() {
  editing.value = null
  Object.assign(form, blank())
}
function edit(row) {
  clear()
  editing.value = row.id
  for (const key of Object.keys(blank())) form[key] = row[key] ?? ''
  form.is_default = row.is_default
}
const save = () =>
  run(async () => {
    const data = { ...form, district: form.district || null, country_code: 'VN' }
    if (editing.value) await addressesApi.updateAddress(editing.value, data)
    else await addressesApi.createAddress(data)
    reset()
    await refresh()
  }, 'Đã lưu địa chỉ.')
const remove = (id) =>
  run(async () => {
    await addressesApi.deleteAddress(id)
    if (editing.value === id) reset()
    await refresh()
  }, 'Đã xóa địa chỉ.')
const makeDefault = (id) =>
  run(async () => {
    await addressesApi.setDefaultAddress(id)
    await refresh()
  }, 'Đã đổi địa chỉ mặc định.')
onMounted(() => load())
</script>
<template>
  <section>
    <PageHeader
      title="Địa chỉ của tôi"
      eyebrow="Tài khoản"
      description="Lưu địa chỉ nhận hàng và chọn địa chỉ mặc định cho lần mua tiếp theo."
    />
    <DomainFeedback :busy="busy" :error="error" :success="success" />
    <div class="row g-4">
      <div class="col-lg-7">
        <button class="btn btn-outline-secondary mb-3" :disabled="busy" @click="load()">
          Tải lại danh sách
        </button>
        <EmptyState
          v-if="!rows.length && !busy && !error"
          title="Chưa có địa chỉ."
          description="Thêm địa chỉ nhận hàng bằng biểu mẫu bên cạnh."
        />
        <article
          v-for="row in rows"
          :key="row.id"
          class="card mb-3 account-address-card"
          :class="{ 'is-default': row.is_default }"
          :data-address="row.id"
        >
          <div class="card-body">
            <h2 class="h5">
              {{ row.recipient_name }}
              <span v-if="row.is_default" class="badge text-bg-success">Mặc định</span>
            </h2>
            <p>
              {{ row.phone }}<br />{{
                [row.address_line, row.ward, row.district, row.province].filter(Boolean).join(', ')
              }}
            </p>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-outline-primary btn-sm" :disabled="busy" @click="edit(row)">
                Sửa
              </button>
              <button
                v-if="!row.is_default"
                class="btn btn-outline-success btn-sm"
                :disabled="busy"
                @click="makeDefault(row.id)"
              >
                Đặt mặc định
              </button>
              <ConfirmDelete :disabled="busy" @confirm="remove(row.id)" />
            </div>
          </div>
        </article>
        <CollectionPager :meta="meta" :busy="busy" @page="load" />
      </div>
      <div class="col-lg-5">
        <form class="account-address-form" @submit.prevent="save">
          <h2 class="h5">{{ editing ? 'Sửa địa chỉ' : 'Thêm địa chỉ' }}</h2>
          <DomainField
            v-for="[key, label, max] in fields"
            :key="key"
            v-model="form[key]"
            :name="key"
            :label="label"
            :maxlength="max"
            :type="key === 'phone' ? 'tel' : 'text'"
            :required="key !== 'district'"
            :errors="errors[key]"
            :disabled="busy"
          />
          <p class="text-secondary">Quốc gia: Việt Nam</p>
          <label class="form-check mb-3"
            ><input
              v-model="form.is_default"
              name="is_default"
              :disabled="busy"
              type="checkbox"
              class="form-check-input"
            />
            Đặt làm mặc định</label
          >
          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" :disabled="busy">Lưu địa chỉ</button
            ><button
              v-if="editing"
              type="button"
              class="btn btn-secondary"
              :disabled="busy"
              @click="cancelEdit"
            >
              Hủy sửa
            </button>
          </div>
        </form>
      </div>
    </div>
  </section>
</template>

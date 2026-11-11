<script setup>
import { useAuthStore } from '../../stores/auth'
import { removeAvatar, uploadAvatar } from '../../api/profile.api'
import PicturePicker from './PicturePicker.vue'
const emit = defineEmits(['changed'])
const auth = useAuthStore()
// The signed-in user is updated at once, so the header changes without a reload.
function changed(user) {
  auth.user = user
  emit('changed', user)
}
// Through arrows, so the calls stay replaceable in tests.
const upload = (picture) => uploadAvatar(picture)
const remove = () => removeAvatar()
</script>
<template>
  <PicturePicker
    :name="auth.user?.name"
    :url="auth.user?.avatar_url || ''"
    title="Ảnh đại diện"
    hint="Chọn ảnh bất kỳ, ảnh sẽ tự được cắt vuông."
    noun="ảnh đại diện"
    :upload="upload"
    :remove="remove"
    @changed="changed"
  />
</template>

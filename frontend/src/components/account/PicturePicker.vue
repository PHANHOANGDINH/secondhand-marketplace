<script setup>
import { ref } from 'vue'
import { shrinkPicture } from '../../api/profile.api'
import { useFeedback } from '../../composables/useFeedback'
import AppIcon from '../ui/AppIcon.vue'
import UserAvatar from '../ui/UserAvatar.vue'
import DomainFeedback from '../DomainFeedback.vue'
// One button: pick a picture and it is saved. The browser shrinks and squares it first, so any phone photo works.
const props = defineProps({
  name: { type: String, default: '' },
  url: { type: String, default: '' },
  title: { type: String, required: true },
  hint: { type: String, required: true },
  // Lower case, used inside the messages: "Đã đổi <noun>."
  noun: { type: String, required: true },
  upload: { type: Function, required: true },
  remove: { type: Function, required: true },
})
const emit = defineEmits(['changed'])
const input = ref(null)
const { busy, error, errors, success, run } = useFeedback()
const save = (action, message) => run(async () => emit('changed', await action()), message)
async function picked(event) {
  const file = event.target.files?.[0]
  // Choosing the same file again must fire "change" again.
  event.target.value = ''
  if (!file) return
  await save(async () => props.upload(await shrinkPicture(file)), `Đã đổi ${props.noun}.`)
}
const clear = () => save(props.remove, `Đã xóa ${props.noun}.`)
</script>
<template>
  <section class="workspace-panel avatar-picker" :aria-label="title">
    <UserAvatar class="avatar-picker-picture" :name="name" :url="url" />
    <div class="avatar-picker-body">
      <h2>{{ title }}</h2>
      <p>{{ hint }}</p>
      <div class="avatar-picker-actions">
        <input
          ref="input"
          class="visually-hidden"
          type="file"
          accept="image/*"
          tabindex="-1"
          aria-hidden="true"
          data-avatar-input
          @change="picked"
        />
        <button type="button" class="btn btn-primary" :disabled="busy" data-avatar-pick @click="input.click()">
          <AppIcon name="image" :size="18" />{{ busy ? 'Đang lưu…' : url ? 'Đổi ảnh' : 'Chọn ảnh' }}
        </button>
        <button
          v-if="url"
          type="button"
          class="btn btn-outline-secondary"
          :disabled="busy"
          data-avatar-remove
          @click="clear"
        >
          <AppIcon name="trash" :size="18" />Xóa ảnh
        </button>
      </div>
      <DomainFeedback :error="errors.avatar?.[0] || error" :success="success" />
    </div>
  </section>
</template>
<style scoped>
.avatar-picker {
  display: flex;
  align-items: center;
  gap: var(--space-5);
}
.avatar-picker-picture {
  flex: none;
  display: grid;
  place-items: center;
  width: 96px;
  height: 96px;
  border-radius: 50%;
  background: var(--primary-soft);
  color: var(--primary-ink);
  font-size: 1.75rem;
  font-weight: 700;
}
.avatar-picker-body {
  flex: 1;
  min-width: 0;
}
.avatar-picker-body h2 {
  margin: 0 0 var(--space-1);
  font-size: 1.05rem;
}
.avatar-picker-body p {
  margin: 0 0 var(--space-3);
  color: var(--muted);
}
.avatar-picker-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-2);
}
.avatar-picker-body :deep(.alert) {
  margin: var(--space-3) 0 0;
}
@media (max-width: 480px) {
  .avatar-picker {
    align-items: flex-start;
    gap: var(--space-4);
  }
  .avatar-picker-picture {
    width: 72px;
    height: 72px;
    font-size: 1.35rem;
  }
}
</style>

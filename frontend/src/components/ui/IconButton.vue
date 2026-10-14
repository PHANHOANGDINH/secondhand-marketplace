<script setup>
import { RouterLink } from 'vue-router'
import AppIcon from './AppIcon.vue'
// An action shown as an icon. The label is always in the element (visually hidden unless showLabel)
// and in the tooltip, so screen readers, tests and long-press users still read the words.
defineProps({
  icon: { type: String, required: true },
  label: { type: String, required: true },
  showLabel: Boolean,
  to: { type: [String, Object], default: null },
  type: { type: String, default: 'button' },
  size: { type: Number, default: 18 },
})
</script>
<template>
  <RouterLink v-if="to" :to="to" class="icon-btn" :class="{ 'is-labelled': showLabel }" :title="label"
    ><AppIcon :name="icon" :size="size" /><span :class="showLabel ? 'icon-btn-label' : 'visually-hidden'">{{
      label
    }}</span></RouterLink
  >
  <button v-else :type="type" class="icon-btn" :class="{ 'is-labelled': showLabel }" :title="label">
    <AppIcon :name="icon" :size="size" /><span :class="showLabel ? 'icon-btn-label' : 'visually-hidden'">{{
      label
    }}</span>
  </button>
</template>
<style scoped>
.icon-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  min-width: 40px;
  min-height: 40px;
  white-space: nowrap;
}
.icon-btn:not(.is-labelled) {
  width: 40px;
  padding: 0 !important;
}
.icon-btn :deep(svg) {
  flex: none;
}
</style>

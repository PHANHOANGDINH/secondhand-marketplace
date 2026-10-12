<script setup>
import { useMessaging } from '../../composables/useMessaging'
defineProps({ compact: Boolean })
const chat = useMessaging()
</script>
<template>
  <button
    v-if="chat?.auth.isAuthenticated"
    type="button"
    class="btn btn-outline-secondary chat-launcher"
    :aria-label="`Tin nhắn, ${chat.unreadTotal.value} chưa đọc`"
    :aria-expanded="chat.opened.value"
    aria-controls="marketplace-chat"
    @click="chat.open"
  >
    <svg
      v-if="compact"
      aria-hidden="true"
      width="20"
      height="20"
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="1.8"
    >
      <path d="M4 4h16v12H8l-4 4V4Z" /></svg
    ><span :class="{ 'visually-hidden': compact }">Tin nhắn</span>
    <span v-if="chat.unreadTotal.value" class="badge text-bg-primary ms-1">{{
      chat.unreadLabel.value
    }}</span>
  </button>
</template>

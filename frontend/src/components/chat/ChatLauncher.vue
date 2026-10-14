<script setup>
import AppIcon from '../ui/AppIcon.vue'
import { useMessaging } from '../../composables/useMessaging'
defineProps({ compact: Boolean })
const chat = useMessaging()
</script>
<template>
  <button
    v-if="chat?.auth.isAuthenticated"
    type="button"
    class="btn btn-outline-secondary chat-launcher btn-icon"
    :aria-label="`Tin nhắn, ${chat.unreadTotal.value} chưa đọc`"
    :aria-expanded="chat.opened.value"
    aria-controls="marketplace-chat"
    @click="chat.open"
  >
    <AppIcon name="message" :size="20" /><span :class="{ 'visually-hidden': compact }">Tin nhắn</span>
    <span v-if="chat.unreadTotal.value" class="badge text-bg-primary ms-1">{{
      chat.unreadLabel.value
    }}</span>
  </button>
</template>

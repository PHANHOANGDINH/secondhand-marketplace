<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import { useMessaging } from '../../composables/useMessaging'
import EmptyState from '../ui/EmptyState.vue'
import { formatPrice } from '../../composables/productPresentation'
const chat = useMessaging(),
  panel = ref(null),
  closeButton = ref(null),
  history = ref(null)
let previousFocus,
  previousOverflow,
  locked = false
const bodyLength = computed(() => Array.from(chat?.draft.value || '').length)
const time = (value) =>
  value
    ? new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
      }).format(new Date(value))
    : ''
function release() {
  if (!locked) return
  document.body.style.overflow = previousOverflow
  locked = false
  if (previousFocus?.isConnected) previousFocus.focus()
}
watch(
  () => chat?.opened.value,
  async (opened) => {
    if (!opened) {
      release()
      return
    }
    previousFocus = document.activeElement
    previousOverflow = document.body.style.overflow
    document.body.style.overflow = 'hidden'
    locked = true
    await nextTick()
    closeButton.value?.focus()
  },
)
watch(
  () => chat?.messages.value.at(-1)?.id,
  async () => {
    const atBottom =
      !history.value ||
      history.value.scrollHeight - history.value.scrollTop - history.value.clientHeight < 80
    await nextTick()
    if (atBottom && history.value) history.value.scrollTop = history.value.scrollHeight
  },
)
function keyboard(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    chat.close()
    return
  }
  if (event.key !== 'Tab') return
  const buttons = [
    ...panel.value.querySelectorAll('button:not(:disabled), a[href], textarea:not(:disabled)'),
  ]
  const first = buttons[0],
    last = buttons.at(-1)
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}
onUnmounted(release)
</script>
<template>
  <Teleport to="body">
    <div v-if="chat?.opened.value" class="chat-overlay" @click.self="chat.close">
      <section
        id="marketplace-chat"
        ref="panel"
        class="chat-drawer"
        role="dialog"
        aria-modal="true"
        aria-labelledby="chat-title"
        @keydown="keyboard"
      >
        <header class="chat-topbar">
          <div>
            <h2 id="chat-title" class="h5 mb-1">Tin nhắn</h2>
            <small class="text-muted">Trao đổi trực tiếp với người mua và người bán</small>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="btn btn-outline-secondary"
            aria-label="Đóng tin nhắn"
            @click="chat.close"
          >
            Đóng
          </button>
        </header>
        <div class="chat-workspace" :class="{ 'has-conversation': chat.active.value }">
          <aside class="chat-conversations" aria-label="Danh sách hội thoại">
            <p v-if="chat.listBusy.value && !chat.conversations.value.length" role="status">
              Đang tải hội thoại…
            </p>
            <div v-else-if="chat.listError.value" class="alert alert-danger" role="alert">
              {{ chat.listError.value }}
              <button class="btn btn-sm btn-outline-danger" @click="chat.refreshList()">
                Thử lại
              </button>
            </div>
            <EmptyState
              v-else-if="!chat.conversations.value.length"
              title="Chưa có hội thoại"
              description="Mở một sản phẩm và chọn Nhắn người bán để bắt đầu."
            />
            <button
              v-for="conversation in chat.conversations.value"
              :key="conversation.id"
              class="chat-conversation"
              :class="{ selected: conversation.id === chat.active.value?.id }"
              :aria-pressed="conversation.id === chat.active.value?.id"
              @click="chat.selectConversation(conversation)"
            >
              <span class="d-flex justify-content-between gap-2"
                ><strong>{{ conversation.partner.name }}</strong
                ><span v-if="conversation.unread_count" class="badge text-bg-primary">{{
                  conversation.unread_count
                }}</span></span
              >
              <span class="chat-context-name">{{
                conversation.product?.name ||
                (conversation.product_unavailable
                  ? 'Sản phẩm không còn công khai'
                  : 'Hội thoại trực tiếp')
              }}</span>
              <span class="chat-preview">{{
                conversation.last_message?.body || 'Bắt đầu cuộc trò chuyện'
              }}</span>
              <small v-if="conversation.last_message_at" class="text-muted">{{
                time(conversation.last_message_at)
              }}</small>
            </button>
            <button
              v-if="chat.hasMore.value"
              class="btn btn-outline-secondary m-3"
              :disabled="chat.listBusy.value"
              @click="chat.refreshList(true)"
            >
              Thêm hội thoại
            </button>
          </aside>
          <div class="chat-current">
            <template v-if="chat.active.value">
              <header class="chat-context">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-secondary chat-list-back"
                  @click="chat.back"
                >
                  ← Hội thoại
                </button>
                <strong>{{ chat.active.value.partner.name }}</strong>
                <RouterLink
                  v-if="chat.active.value.product"
                  :to="`/products/${chat.active.value.product.slug}`"
                  >{{ chat.active.value.product.name }} ·
                  {{ formatPrice(chat.active.value.product.price) }}</RouterLink
                >
                <small v-else-if="chat.active.value.product_unavailable" class="text-muted"
                  >Sản phẩm không còn công khai. Bạn vẫn có thể trao đổi tại đây.</small
                >
              </header>
              <div
                ref="history"
                class="chat-history"
                role="log"
                aria-label="Lịch sử tin nhắn"
                aria-live="polite"
                :aria-busy="chat.historyBusy.value"
              >
                <button
                  v-if="chat.hasOlder.value"
                  class="btn btn-sm btn-outline-secondary align-self-center"
                  :disabled="chat.historyBusy.value"
                  @click="chat.refreshMessages('older')"
                >
                  Tin nhắn trước đó
                </button>
                <p v-if="chat.historyBusy.value && !chat.messages.value.length" role="status">
                  Đang tải tin nhắn…
                </p>
                <p
                  v-else-if="!chat.messages.value.length && !chat.error.value"
                  class="text-muted text-center my-auto"
                >
                  Gửi lời chào để bắt đầu cuộc trò chuyện.
                </p>
                <article
                  v-for="message in chat.messages.value"
                  :key="message.id"
                  class="chat-bubble"
                  :class="{ mine: message.sender_id === String(chat.auth.user?.id) }"
                >
                  <p>{{ message.body }}</p>
                  <time :datetime="message.created_at">{{ time(message.created_at) }}</time>
                </article>
              </div>
              <div v-if="chat.error.value" class="alert alert-danger mx-3 mb-0" role="alert">
                {{ chat.error.value }}
                <button class="btn btn-sm btn-outline-danger" @click="chat.refreshMessages('poll')">
                  Tải lại
                </button>
              </div>
              <form class="chat-compose" @submit.prevent="chat.send">
                <label for="chat-body" class="visually-hidden">Nội dung tin nhắn</label>
                <textarea
                  id="chat-body"
                  v-model="chat.draft.value"
                  class="form-control"
                  rows="2"
                  maxlength="1000"
                  placeholder="Viết tin nhắn…"
                  :disabled="chat.sending.value || chat.historyBusy.value"
                  :aria-describedby="'chat-body-help'"
                />
                <div class="d-flex align-items-center justify-content-between gap-2">
                  <small id="chat-body-help" class="text-muted"
                    >{{ bodyLength }}/1000 · Chỉ gửi văn bản</small
                  ><button
                    class="btn btn-primary"
                    type="submit"
                    :disabled="
                      chat.sending.value ||
                      chat.historyBusy.value ||
                      !chat.draft.value.trim() ||
                      bodyLength > 1000
                    "
                  >
                    {{ chat.sending.value ? 'Đang gửi…' : 'Gửi' }}
                  </button>
                </div>
              </form>
            </template>
            <EmptyState
              v-else
              title="Chọn một hội thoại"
              description="Tin nhắn của bạn chỉ hiển thị với người cùng hội thoại."
            />
          </div>
        </div>
      </section>
    </div>
  </Teleport>
</template>
<style scoped>
.chat-overlay {
  position: fixed;
  inset: 0;
  z-index: 1060;
  background: rgb(0 0 0 / 30%);
}
.chat-drawer {
  position: absolute;
  right: 1rem;
  top: 1rem;
  bottom: 1rem;
  width: min(58rem, calc(100% - 2rem));
  display: flex;
  flex-direction: column;
  overflow: hidden;
  background: var(--bs-body-bg);
  color: var(--bs-body-color);
  border: 1px solid var(--bs-border-color);
  border-radius: 1rem;
  box-shadow: 0 1rem 3rem rgb(0 0 0 / 15%);
}
.chat-topbar,
.chat-context {
  padding: 1rem;
  border-bottom: 1px solid var(--bs-border-color);
}
.chat-topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
}
.chat-workspace {
  display: grid;
  grid-template-columns: minmax(0, 17rem) minmax(0, 1fr);
  min-height: 0;
  flex: 1;
}
.chat-conversations {
  overflow-y: auto;
  border-right: 1px solid var(--bs-border-color);
  min-width: 0;
}
.chat-conversations > [role='status'],
.chat-conversations > .alert {
  margin: 1rem;
}
.chat-conversation {
  display: block;
  width: 100%;
  text-align: left;
  border: 0;
  border-bottom: 1px solid var(--bs-border-color);
  background: transparent;
  color: inherit;
  padding: 1rem;
}
.chat-conversation.selected {
  background: var(--bs-primary-bg-subtle);
  box-shadow: inset 3px 0 var(--bs-primary);
}
.chat-context-name,
.chat-preview {
  display: block;
  overflow: hidden;
  white-space: nowrap;
  text-overflow: ellipsis;
  font-size: 0.85rem;
  color: var(--bs-secondary-color);
  margin-top: 0.3rem;
}
.chat-current {
  min-width: 0;
  min-height: 0;
  display: flex;
  flex-direction: column;
}
.chat-current > .buyer-empty {
  margin: auto;
  padding: 1.5rem;
}
.chat-context strong,
.chat-context a,
.chat-context small {
  display: block;
  overflow-wrap: anywhere;
}
.chat-context a {
  font-size: 0.85rem;
  margin-top: 0.25rem;
}
.chat-history {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  padding: 1rem;
  overscroll-behavior: contain;
}
.chat-bubble {
  align-self: flex-start;
  max-width: 85%;
  padding: 0.7rem 0.9rem;
  background: var(--bs-tertiary-bg);
  border-radius: 0.9rem 0.9rem 0.9rem 0.2rem;
}
.chat-bubble.mine {
  align-self: flex-end;
  background: var(--bs-primary-bg-subtle);
  border-radius: 0.9rem 0.9rem 0.2rem 0.9rem;
}
.chat-bubble p {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.chat-bubble time {
  display: block;
  font-size: 0.7rem;
  color: var(--bs-secondary-color);
  margin-top: 0.3rem;
}
.chat-compose {
  display: grid;
  gap: 0.5rem;
  padding: 1rem;
  border-top: 1px solid var(--bs-border-color);
}
.chat-compose textarea {
  resize: none;
}
.chat-list-back {
  display: none;
}
@media (max-width: 767px) {
  .chat-drawer {
    inset: 0;
    width: 100%;
    height: 100dvh;
    border: 0;
    border-radius: 0;
  }
  .chat-workspace {
    display: flex;
  }
  .chat-conversations {
    width: 100%;
    border: 0;
  }
  .chat-current {
    display: none;
    width: 100%;
  }
  .chat-workspace.has-conversation .chat-conversations {
    display: none;
  }
  .chat-workspace.has-conversation .chat-current {
    display: flex;
  }
  .chat-list-back {
    display: block;
    margin-bottom: 0.75rem;
  }
  .chat-topbar {
    padding: 0.75rem;
  }
  .chat-topbar small {
    font-size: 0.7rem;
  }
}
</style>

<script setup>
import { nextTick, onUnmounted, ref, watch } from 'vue'
// A record's details in a panel over the list: from the right on wide screens, full screen on phones.
// Rendered in place (no teleport) so the page keeps owning its controls; position: fixed covers the view.
const props = defineProps({
  open: Boolean,
  id: { type: String, required: true },
  title: { type: String, required: true },
  busy: Boolean,
})
const emit = defineEmits(['close'])
const panel = ref(null)
let previousFocus = null,
  previousOverflow = ''
const close = () => {
  if (!props.busy) emit('close')
}
function keyboard(event) {
  if (event.key === 'Escape') {
    event.stopPropagation()
    close()
  }
}
watch(
  () => props.open,
  async (open) => {
    if (typeof document === 'undefined') return
    if (open) {
      previousFocus = document.activeElement
      previousOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
      await nextTick()
      panel.value?.focus()
    } else {
      document.body.style.overflow = previousOverflow
      if (previousFocus?.isConnected) previousFocus.focus()
    }
  },
  { immediate: true },
)
onUnmounted(() => {
  if (props.open && typeof document !== 'undefined') document.body.style.overflow = previousOverflow
})
</script>
<template>
  <div v-if="open" class="detail-sheet-backdrop" @click.self="close" @keydown="keyboard">
    <section
      :id="id"
      ref="panel"
      class="detail-sheet"
      role="dialog"
      aria-modal="true"
      :aria-labelledby="`${id}-title`"
      :aria-busy="busy"
      tabindex="-1"
    >
      <header class="detail-sheet-header">
        <h2 :id="`${id}-title`" class="detail-sheet-title">{{ title }}</h2>
        <button
          type="button"
          class="btn btn-outline-secondary detail-sheet-close"
          :disabled="busy"
          :aria-label="`Đóng ${title.toLocaleLowerCase('vi-VN')}`"
          @click="close"
        >
          Đóng
        </button>
      </header>
      <div class="detail-sheet-body"><slot /></div>
    </section>
  </div>
</template>
<style scoped>
.detail-sheet-backdrop {
  position: fixed;
  inset: 0;
  z-index: var(--layer-dialog);
  display: flex;
  justify-content: flex-end;
  background: rgb(21 22 26 / 45%);
}
.detail-sheet {
  display: flex;
  flex-direction: column;
  width: min(36rem, 100%);
  height: 100%;
  background: var(--surface);
  box-shadow: var(--shadow-dialog);
  outline: none;
}
.detail-sheet-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-4) var(--space-5);
  border-bottom: 1px solid var(--border);
}
.detail-sheet-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
}
.detail-sheet-close {
  flex: none;
  min-height: var(--control-height);
}
.detail-sheet-body {
  flex: 1;
  min-height: 0;
  overflow-y: auto;
  padding: var(--space-4) var(--space-5) var(--space-6);
}
/* The record panels were built as side cards: inside the sheet they lose their own frame. */
.detail-sheet-body > :deep(*) {
  position: static !important;
  max-height: none !important;
  margin: 0;
  border: 0 !important;
  box-shadow: none !important;
  padding: 0 !important;
}
@media (max-width: 575px) {
  .detail-sheet {
    width: 100%;
  }
  .detail-sheet-header,
  .detail-sheet-body {
    padding-inline: var(--space-4);
  }
}
</style>

<script setup>
import { nextTick, onMounted, onUnmounted, ref } from 'vue'
defineOptions({ inheritAttrs: false })
const props = defineProps({
  id: { type: String, required: true },
  title: { type: String, required: true },
  description: String,
  busy: Boolean,
})
const emit = defineEmits(['dismiss'])
const overlay = ref(null),
  panel = ref(null),
  closeButton = ref(null)
let previousFocus, previousOverflow
const inertElements = []
const dismiss = () => {
  if (!props.busy) emit('dismiss')
}
function keyboard(event) {
  if (event.key === 'Escape') {
    event.preventDefault()
    dismiss()
    return
  }
  if (event.key !== 'Tab') return
  const controls = [
    ...panel.value.querySelectorAll(
      'a[href], button:not(:disabled), input:not(:disabled), textarea:not(:disabled), select:not(:disabled), video[controls], [tabindex="0"]',
    ),
  ].filter((node) => !node.closest('[hidden]'))
  if (!controls.length) {
    event.preventDefault()
    panel.value?.focus()
    return
  }
  const first = controls[0],
    last = controls.at(-1)
  if (document.activeElement === panel.value) {
    event.preventDefault()
    ;(event.shiftKey ? last : first)?.focus()
    return
  }
  if (event.shiftKey && document.activeElement === first) {
    event.preventDefault()
    last?.focus()
  } else if (!event.shiftKey && document.activeElement === last) {
    event.preventDefault()
    first?.focus()
  }
}
onMounted(async () => {
  previousFocus = document.activeElement
  previousOverflow = document.body.style.overflow
  document.body.style.overflow = 'hidden'
  for (const element of document.body.children) {
    if (element === overlay.value || ['SCRIPT', 'STYLE', 'LINK'].includes(element.tagName)) continue
    inertElements.push([element, element.hasAttribute('inert')])
    element.setAttribute('inert', '')
  }
  await nextTick()
  if (closeButton.value?.disabled) panel.value?.focus()
  else closeButton.value?.focus()
})
onUnmounted(() => {
  document.body.style.overflow = previousOverflow
  for (const [element, previouslyInert] of inertElements)
    if (!previouslyInert) element.removeAttribute('inert')
  if (previousFocus?.isConnected) previousFocus.focus()
})
defineExpose({ focusClose: () => closeButton.value?.focus() })
</script>
<template>
  <Teleport to="body"
    ><div ref="overlay" class="ui-dialog-backdrop" v-bind="$attrs" @click.self="dismiss">
      <section
        :id="id"
        ref="panel"
        tabindex="-1"
        class="ui-dialog"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="`${id}-title`"
        :aria-describedby="description ? `${id}-description` : undefined"
        :aria-busy="busy"
        @keydown="keyboard"
      >
        <header class="ui-dialog-header">
          <div>
            <h2 :id="`${id}-title`" class="h4 mb-1">{{ title }}</h2>
            <p v-if="description" :id="`${id}-description`" class="text-muted mb-0">
              {{ description }}
            </p>
          </div>
          <button
            ref="closeButton"
            type="button"
            class="btn btn-outline-secondary"
            :disabled="busy"
            :aria-label="`Đóng ${title.toLocaleLowerCase('vi-VN')}`"
            @click="dismiss"
          >
            Đóng
          </button>
        </header>
        <div class="ui-dialog-content"><slot /></div>
        <footer v-if="$slots.footer" class="ui-dialog-footer"><slot name="footer" /></footer>
      </section></div
  ></Teleport>
</template>

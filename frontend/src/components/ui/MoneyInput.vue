<script setup>
import { ref, watch } from 'vue'
import { formatMoneyInput, parseMoneyInput } from '../../utils/moneyInput'
// A text input for amounts in đồng: shows "1.500.000" while the model holds "1500000".
const props = defineProps({ modelValue: { type: [String, Number], default: '' } })
const emit = defineEmits(['update:modelValue'])
const element = ref(null)
const shown = ref(formatMoneyInput(props.modelValue))
let last = String(props.modelValue ?? '')
watch(
  () => props.modelValue,
  (value) => {
    // Only a change from outside (load, reset, quick-bid chips) rewrites what is shown.
    if (String(value ?? '') === last) return
    last = String(value ?? '')
    shown.value = formatMoneyInput(value)
  },
)
function onInput(event) {
  const input = event.target
  const caret = input.selectionStart ?? input.value.length
  // Digits (and the comma) left of the caret: the caret goes back after the same ones once regrouped.
  const before = input.value.slice(0, caret).replace(/[^\d,]/g, '').length
  const { value, text } = parseMoneyInput(input.value)
  shown.value = text
  input.value = text
  let position = 0
  for (let seen = 0; position < text.length && seen < before; position++)
    if (/[\d,]/.test(text[position])) seen++
  input.setSelectionRange?.(position, position)
  last = value
  emit('update:modelValue', value)
}
defineExpose({ focus: () => element.value?.focus() })
</script>
<template>
  <input
    ref="element"
    :value="shown"
    type="text"
    inputmode="decimal"
    autocomplete="off"
    @input="onInput"
  />
</template>

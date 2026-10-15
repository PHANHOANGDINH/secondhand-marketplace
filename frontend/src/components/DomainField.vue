<script setup>
import { computed } from 'vue'
import MoneyInput from './ui/MoneyInput.vue'
const props = defineProps({
  idPrefix: { type: String, default: '' },
  name: { type: String, required: true },
  label: { type: String, required: true },
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  errors: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] },
  required: Boolean,
  disabled: Boolean,
  maxlength: { type: Number, default: undefined },
})
const fieldId = computed(() => `${props.idPrefix}${props.name}`)
defineEmits(['update:modelValue'])
</script>
<template>
  <div class="mb-3">
    <label :for="fieldId" class="form-label"
      >{{ label }}<span v-if="required" class="ui-required" aria-hidden="true"> *</span></label
    >
    <select
      v-if="type === 'select'"
      :id="fieldId"
      :name="name"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      :aria-invalid="errors.length ? 'true' : undefined"
      :aria-describedby="errors.length ? `${fieldId}-errors` : undefined"
      class="form-select"
      :class="{ 'is-invalid': errors.length }"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>
    <textarea
      v-else-if="type === 'textarea'"
      :id="fieldId"
      :name="name"
      :value="modelValue"
      :maxlength="maxlength"
      :required="required"
      :disabled="disabled"
      :aria-invalid="errors.length ? 'true' : undefined"
      :aria-describedby="errors.length ? `${fieldId}-errors` : undefined"
      rows="3"
      class="form-control"
      :class="{ 'is-invalid': errors.length }"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <!-- type="money": thousands grouped with dots while typing; the model stays "1500000". -->
    <MoneyInput
      v-else-if="type === 'money'"
      :id="fieldId"
      :name="name"
      :model-value="modelValue"
      :required="required"
      :disabled="disabled"
      :aria-invalid="errors.length ? 'true' : undefined"
      :aria-describedby="errors.length ? `${fieldId}-errors` : undefined"
      class="form-control"
      :class="{ 'is-invalid': errors.length }"
      @update:model-value="$emit('update:modelValue', $event)"
    />
    <input
      v-else
      :id="fieldId"
      :name="name"
      :type="type"
      :value="modelValue"
      :maxlength="maxlength"
      :required="required"
      :disabled="disabled"
      :aria-invalid="errors.length ? 'true' : undefined"
      :aria-describedby="errors.length ? `${fieldId}-errors` : undefined"
      class="form-control"
      :class="{ 'is-invalid': errors.length }"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <div v-if="errors.length" :id="`${fieldId}-errors`" class="invalid-feedback d-block">
      <div v-for="message in errors" :key="message">{{ message }}</div>
    </div>
  </div>
</template>

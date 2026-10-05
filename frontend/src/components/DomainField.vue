<script setup>
defineProps({
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
defineEmits(['update:modelValue'])
</script>
<template>
  <div class="mb-3">
    <label :for="name" class="form-label">{{ label }}</label>
    <select
      v-if="type === 'select'"
      :id="name"
      :name="name"
      :value="modelValue"
      :disabled="disabled"
      :required="required"
      :aria-invalid="errors.length ? 'true' : undefined"
      :aria-describedby="errors.length ? `${name}-errors` : undefined"
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
      :id="name"
      :name="name"
      :value="modelValue"
      :maxlength="maxlength"
      :required="required"
      :disabled="disabled"
      :aria-invalid="errors.length ? 'true' : undefined"
      :aria-describedby="errors.length ? `${name}-errors` : undefined"
      rows="3"
      class="form-control"
      :class="{ 'is-invalid': errors.length }"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <input
      v-else
      :id="name"
      :name="name"
      :type="type"
      :value="modelValue"
      :maxlength="maxlength"
      :required="required"
      :disabled="disabled"
      :aria-invalid="errors.length ? 'true' : undefined"
      :aria-describedby="errors.length ? `${name}-errors` : undefined"
      class="form-control"
      :class="{ 'is-invalid': errors.length }"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <div v-if="errors.length" :id="`${name}-errors`" class="invalid-feedback d-block">
      <div v-for="message in errors" :key="message">{{ message }}</div>
    </div>
  </div>
</template>

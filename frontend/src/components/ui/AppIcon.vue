<script setup>
// Thin-stroke line icons (24×24 grid). Decorative: the control that holds one carries the label.
defineProps({
  name: { type: String, required: true },
  size: { type: [Number, String], default: 20 },
  filled: Boolean,
})
const paths = {
  search: 'M10.5 17a6.5 6.5 0 1 0 0-13 6.5 6.5 0 0 0 0 13Zm5-1.5L20 20',
  menu: 'M4 7h16M4 12h16M4 17h16',
  plus: 'M12 5v14M5 12h14',
  chevronDown: 'm6 9 6 6 6-6',
  heart:
    'M12 20s-7-4.4-7-9.6A4 4 0 0 1 12 8a4 4 0 0 1 7 2.4C19 15.6 12 20 12 20Z',
  star: 'm12 4 2.4 5 5.6.8-4 3.9.9 5.5L12 16.6 7.1 19.2l.9-5.5-4-3.9 5.6-.8L12 4Z',
  box: 'M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7ZM4 8.5l8 4.5 8-4.5M12 13v7',
  tag: 'M4 4h7.5l8.5 8.5-7.5 7.5L4 11.5V4Zm4.5 3.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z',
}
</script>
<template>
  <svg
    class="ui-icon"
    aria-hidden="true"
    focusable="false"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    :fill="filled ? 'currentColor' : 'none'"
    stroke="currentColor"
    stroke-width="1.6"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <path :d="paths[name]" />
  </svg>
</template>

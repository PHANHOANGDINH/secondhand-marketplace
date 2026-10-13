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
  arrowRight: 'M5 12h14m-5-5 5 5-5 5',
  device: 'M8 3.5h8a1.5 1.5 0 0 1 1.5 1.5v14a1.5 1.5 0 0 1-1.5 1.5H8A1.5 1.5 0 0 1 6.5 19V5A1.5 1.5 0 0 1 8 3.5Zm3 14h2',
  shirt: 'm8.5 4-5 3 2 3.5L8 9v11h8V9l2.5 1.5 2-3.5-5-3a3.5 3.5 0 0 1-7 0Z',
  home: 'm4 11 8-6.5 8 6.5v8.5h-5.5V14h-5v5.5H4V11Z',
  book: 'M5 4.5h10.5A2.5 2.5 0 0 1 18 7v12.5H7.5A2.5 2.5 0 0 1 5 17V4.5Zm0 12.5a2.5 2.5 0 0 1 2.5-2.5H18',
  sliders: 'M4 7h9m4 0h3M4 17h3m4 0h9M15 5v4M9 15v4',
  play: 'M8 5.5v13l10.5-6.5L8 5.5Z',
  gavel: 'm13.5 4.5 6 6M10.5 7.5l6 6M12 6l-6 6 3 3 6-6M9 15l-5 5M13 20h7',
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

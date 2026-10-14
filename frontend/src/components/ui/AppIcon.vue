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
  shield: 'M12 3.5 5 6v5.5c0 4.3 2.9 7.4 7 9 4.1-1.6 7-4.7 7-9V6l-7-2.5Zm-3 8.5 2.2 2.2L15.5 10',
  store: 'M4 9.5 5.5 4h13L20 9.5M4 9.5a2.7 2.7 0 0 0 5.3 0 2.7 2.7 0 0 0 5.4 0 2.7 2.7 0 0 0 5.3 0M5.5 12v8h13v-8M10 20v-4.5h4V20',
  pin: 'M12 21s-6.5-5.6-6.5-10.5a6.5 6.5 0 0 1 13 0C18.5 15.4 12 21 12 21Zm0-8a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
  chevronRight: 'm9 6 6 6-6 6',
  image: 'M4 5h16v14H4V5Zm0 10 4.5-4.5 4 4 3-3L20 16M9 9.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
  play: 'M8 5.5v13l10.5-6.5L8 5.5Z',
  gavel: 'm13.5 4.5 6 6M10.5 7.5l6 6M12 6l-6 6 3 3 6-6M9 15l-5 5M13 20h7',
  tag: 'M4 4h7.5l8.5 8.5-7.5 7.5L4 11.5V4Zm4.5 3.5a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z',
  motorbike:
    'M5.5 19a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM18.5 19a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM5.5 16h5l3-5h3l2 5M13.5 11 12 7H9M15 7h2.5',
  sofa: 'M4 11V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3M2.5 11h3v4h13v-4h3v7h-19ZM4.5 18v2M19.5 18v2',
  fridge: 'M6 2.5h12v19H6ZM6 9h12M9 5v2.5M9 11.5v4',
  stroller:
    'M5 4h5v7h10a7 7 0 0 1-15 0V4ZM10 4a8 8 0 0 1 8 7M8 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3ZM17 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z',
  dumbbell: 'M2.5 10v4M5 7.5v9h2.5v-9ZM16.5 7.5v9H19v-9ZM7.5 12h9M21.5 10v4',
  paw: 'M12 20c-3 0-5-1.5-5-3.5 0-2.5 2.5-4.5 5-4.5s5 2 5 4.5c0 2-2 3.5-5 3.5ZM6 10.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6ZM18 10.5a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6ZM9.5 7a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6ZM14.5 7a1.8 1.8 0 1 0 0-3.6 1.8 1.8 0 0 0 0 3.6Z',
  desk: 'M2.5 7h19v3h-19ZM4 10v10M20 10v10M13 10h6v5h-6Z',
  guitar:
    'M18.5 3.5 21 6l-2 1-6.5 6.5M12.5 13.5a3 3 0 0 0-2.3-2.9C9 9.5 6.5 10 5 12s-2 5 0 7 5 1.5 7 0c1.5-1 1.5-2.6.5-3.6ZM8.5 15.5a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
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

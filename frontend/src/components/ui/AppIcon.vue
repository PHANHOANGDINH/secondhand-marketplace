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
  // Actions
  eye: 'M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
  eyeOff:
    'M3 3l18 18M10.6 5.6A9.7 9.7 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3 3.8M6.6 6.6A16.5 16.5 0 0 0 2.5 12s3.5 6.5 9.5 6.5a9.4 9.4 0 0 0 4.4-1.1M9.9 9.9a3 3 0 0 0 4.2 4.2',
  edit: 'M4 20h4L19 9l-4-4L4 16v4ZM13.5 6.5l4 4',
  trash: 'M4 7h16M9.5 7V4.5h5V7M6 7l1 13h10l1-13M10 11v6M14 11v6',
  refresh: 'M20 12a8 8 0 1 1-2.3-5.6M20 4v5h-5',
  check: 'm5 12.5 4.5 4.5L19 7.5',
  close: 'M6 6l12 12M18 6 6 18',
  lock: 'M6.5 11h11v9h-11ZM8.5 11V8a3.5 3.5 0 0 1 7 0v3',
  unlock: 'M6.5 11h11v9h-11ZM8.5 11V8a3.5 3.5 0 0 1 6.8-1.2',
  ban: 'M12 20.5a8.5 8.5 0 1 0 0-17 8.5 8.5 0 0 0 0 17ZM6 6l12 12',
  flag: 'M5 21V4M5 4h11l-2 4 2 4H5',
  upload: 'M12 16V4M7 9l5-5 5 5M4 16v4h16v-4',
  send: 'M4 12 20 4l-4 16-4-6-8-2Zm8 2 8-10',
  bolt: 'M13 2.5 5 13.5h6l-1 8 8-11h-6l1-8Z',
  arrowLeft: 'M19 12H5m5-5-5 5 5 5',
  logout: 'M14 4h5v16h-5M10 8l-4 4 4 4M6 12h10',
  // Things
  cart: 'M3 4h2.5l2 11h10l2-8H6.5M9 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2ZM17 20a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
  message: 'M4 5h16v11H9l-5 4V5Z',
  bell: 'M6 16v-5a6 6 0 0 1 12 0v5l1.5 2h-15L6 16ZM10 20a2 2 0 0 0 4 0',
  truck:
    'M3 6h11v10H3ZM14 9h4l3 3.5V16h-7M7 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM17 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
  receipt: 'M6 3h12v18l-3-2-3 2-3-2-3 2V3ZM9 8h6M9 12h6M9 16h3',
  user: 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.5 20.5a7.5 7.5 0 0 1 15 0',
  users:
    'M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 6.5M18 14.5a6.5 6.5 0 0 1 3.5 5.5',
  dashboard: 'M4 4h7v9H4ZM13 4h7v5h-7ZM13 11h7v9h-7ZM4 15h7v5H4Z',
  inbox: 'M4 13h4.5l1.5 2.5h4l1.5-2.5H20M4 13l2.5-8h11L20 13v6H4v-6Z',
  grid: 'M4 4h6v6H4ZM14 4h6v6h-6ZM4 14h6v6H4ZM14 14h6v6h-6Z',
  wallet: 'M3.5 7h16v12h-16ZM3.5 7l12-3v3M15 13h2',
  ticket: 'M3.5 7h17v3a2 2 0 0 0 0 4v3h-17v-3a2 2 0 0 0 0-4V7ZM14 7v10',
  chart: 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3',
  clock: 'M12 20.5a8.5 8.5 0 1 0 0-17 8.5 8.5 0 0 0 0 17ZM12 7.5V12l3 2',
  map: 'M9 4 3.5 6v14L9 18l6 2 5.5-2V4L15 6 9 4Zm0 0v14m6-12v14',
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

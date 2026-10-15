<script setup>
// Passdi logo: the orange "pass it on" loop tile, plus the wordmark "pa·ss·di" (Baloo 2, "ss" in orange).
// variant="mark" shows the tile alone. At 32px and below the arrowheads are dropped and the loop is thicker,
// so it stays readable at favicon size.
import { computed } from 'vue'
import { brand } from '../../config/brand'
const props = defineProps({
  size: { type: Number, default: 0 },
  variant: {
    type: String,
    default: 'full',
    validator: (value) => ['full', 'mark'].includes(value),
  },
  // Older call sites: a smaller full logo for shells and the footer.
  compact: Boolean,
})
const markSize = computed(() => props.size || (props.compact ? 34 : 40))
const simple = computed(() => markSize.value <= 32)
</script>
<template>
  <span
    class="brand-logo"
    :class="{ 'is-mark': variant === 'mark' }"
    :style="{ '--brand-logo-size': `${markSize}px` }"
    role="img"
    :aria-label="brand.name"
    data-brand-logo
  >
    <svg
      class="brand-logo-mark"
      :width="markSize"
      :height="markSize"
      viewBox="0 0 64 64"
      aria-hidden="true"
      focusable="false"
      :data-simple="simple || undefined"
    >
      <rect width="64" height="64" rx="18" fill="#F26B3A" />
      <g transform="translate(8 20)">
        <path
          d="M24 12 C20 3 5 3 5 12 C5 21 20 21 24 12 C28 3 43 3 43 12 C43 21 28 21 24 12 Z"
          fill="none"
          stroke="#FFFFFF"
          :stroke-width="simple ? 6 : 5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <template v-if="!simple">
          <path d="M31 0.5 L39 4.8 L31.5 9.5 Z" fill="#2B2B2E" />
          <path d="M17 14.5 L9 19.2 L16.5 23.5 Z" fill="#2B2B2E" />
        </template>
      </g>
    </svg>
    <span v-if="variant === 'full'" class="brand-logo-word" aria-hidden="true"
      >pa<span class="brand-logo-ss">ss</span>di</span
    >
  </span>
</template>
<style scoped>
.brand-logo {
  display: inline-flex;
  align-items: center;
  gap: calc(var(--brand-logo-size) * 0.22);
  color: var(--text);
}
.brand-logo-mark {
  flex: none;
  display: block;
}
.brand-logo-word {
  font-family: 'Baloo 2', var(--font-sans, system-ui), sans-serif;
  font-size: calc(var(--brand-logo-size) * 0.72);
  font-weight: 800;
  letter-spacing: -0.02em;
  line-height: 1;
  white-space: nowrap;
  /* Baloo sits low in its line box; nudge it to the optical centre of the tile. */
  padding-top: calc(var(--brand-logo-size) * 0.08);
}
.brand-logo-ss {
  color: var(--brand-text);
}
</style>

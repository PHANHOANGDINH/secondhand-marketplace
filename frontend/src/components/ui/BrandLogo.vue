<script setup>
// Passdi logo: the round "pass it on" badge plus the hand-lettered wordmark "pass·di".
// The badge is a picture on its own paper disc. The wordmark is two alpha masks painted with theme colours
// ("pass" in the --text colour, "di" in --brand-text), so it stays readable in light, dark and auction themes.
// variant="mark" shows the badge alone.
import { computed } from 'vue'
import { brand } from '../../config/brand'
import markUrl from '../../assets/brand/passdi-mark.webp'
import inkUrl from '../../assets/brand/passdi-word-ink.png'
import accentUrl from '../../assets/brand/passdi-word-accent.png'
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
const markSize = computed(() => props.size || (props.compact ? 44 : 52))
const masks = { '--brand-logo-ink': `url(${inkUrl})`, '--brand-logo-accent': `url(${accentUrl})` }
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
    <img
      class="brand-logo-mark"
      :src="markUrl"
      :width="markSize"
      :height="markSize"
      alt=""
      aria-hidden="true"
      decoding="async"
    />
    <span v-if="variant === 'full'" class="brand-logo-word" :style="masks" aria-hidden="true">
      <span class="brand-logo-ink"></span><span class="brand-logo-accent"></span>
    </span>
  </span>
</template>
<style scoped>
.brand-logo {
  display: inline-flex;
  align-items: center;
  gap: calc(var(--brand-logo-size) * 0.18);
}
/* Workspace brand links style every span as a caption; the logo keeps its own box. */
.brand-logo,
.brand-logo span {
  margin: 0;
}
/* Not clipped to a circle: the paper plane flies out of the ring. */
.brand-logo-mark {
  flex: none;
  display: block;
}
/* Wordmark artwork is 1006 x 412. */
.brand-logo-word {
  position: relative;
  flex: none;
  display: block;
  height: calc(var(--brand-logo-size) * 0.86);
  aspect-ratio: 1006 / 412;
}
.brand-logo-ink,
.brand-logo-accent {
  position: absolute;
  inset: 0;
  -webkit-mask: var(--mask) center / contain no-repeat;
  mask: var(--mask) center / contain no-repeat;
}
.brand-logo-ink {
  --mask: var(--brand-logo-ink);
  background: var(--text);
}
.brand-logo-accent {
  --mask: var(--brand-logo-accent);
  background: var(--brand-text);
}
@media (forced-colors: active) {
  .brand-logo-ink,
  .brand-logo-accent {
    background: CanvasText;
  }
}
</style>

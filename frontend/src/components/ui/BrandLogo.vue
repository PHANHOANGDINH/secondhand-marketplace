<script setup>
// Interim logo: primary tile with an accent-outlined price tag, plus the wordmark.
import { brand } from '../../config/brand'
defineProps({ compact: Boolean })
</script>
<template>
  <span class="brand-logo" :class="{ 'is-compact': compact }">
    <span class="brand-logo-mark" aria-hidden="true">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" focusable="false">
        <path
          d="M4 4h7.5l8.5 8.5-7.5 7.5L4 11.5V4Z"
          stroke="var(--accent)"
          stroke-width="1.8"
          stroke-linejoin="round"
        />
        <circle cx="8.5" cy="8.5" r="1.3" fill="var(--accent)" />
      </svg>
    </span>
    <span class="brand-logo-word">{{ brand.name }}</span>
  </span>
</template>
<style scoped>
.brand-logo {
  display: inline-flex;
  align-items: center;
  gap: 0.6rem;
  color: var(--text);
}
.brand-logo-mark {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--primary);
}
.brand-logo-word {
  font-size: 1.2rem;
  font-weight: 800;
  letter-spacing: -0.04em;
  line-height: 1;
  white-space: nowrap;
}
.is-compact .brand-logo-mark {
  width: 34px;
  height: 34px;
}
.is-compact .brand-logo-word {
  font-size: 1.05rem;
}
</style>

<script setup>
import AppIcon from './AppIcon.vue'
import { useTheme } from '../../composables/useTheme'
// Sun/moon button for headers: switches between light and dark.
const { isDark, toggle } = useTheme()
</script>
<template>
  <button
    type="button"
    class="theme-toggle"
    :aria-label="isDark ? 'Chuyển sang giao diện sáng' : 'Chuyển sang giao diện tối'"
    :title="isDark ? 'Giao diện sáng' : 'Giao diện tối'"
    :aria-pressed="isDark"
    data-theme-toggle
    @click="toggle"
  >
    <AppIcon :name="isDark ? 'sun' : 'moon'" :size="20" />
  </button>
</template>
<style scoped>
.theme-toggle {
  display: inline-grid;
  place-items: center;
  flex: none;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 0;
  border-radius: 50%;
  background: var(--surface);
  color: var(--text);
  transition: background-color var(--transition-fast);
}
.theme-toggle:hover {
  background: var(--surface-2);
}
.theme-toggle:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 2px;
}
</style>

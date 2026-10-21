<script setup>
import AppIcon from './AppIcon.vue'
import { useTheme } from '../../composables/useTheme'
// The three choices for the account menu: follow the system, always light, always dark.
const { preference, setPreference } = useTheme()
const choices = [
  ['system', 'Theo hệ thống', 'monitor'],
  ['light', 'Sáng', 'sun'],
  ['dark', 'Tối', 'moon'],
]
</script>
<template>
  <div class="theme-choices" role="group" aria-label="Giao diện">
    <p class="theme-choices-title">Giao diện</p>
    <div class="theme-choices-row">
      <button
        v-for="[value, label, icon] in choices"
        :key="value"
        type="button"
        class="theme-choice"
        :class="{ 'is-active': preference === value }"
        :aria-pressed="preference === value"
        :data-theme-choice="value"
        @click.stop="setPreference(value)"
      >
        <AppIcon :name="icon" :size="16" />{{ label }}
      </button>
    </div>
  </div>
</template>
<style scoped>
.theme-choices {
  padding: 0.5rem 0.75rem;
  border-top: 1px solid var(--border);
  border-bottom: 1px solid var(--border);
  margin: 0.25rem 0;
}
.theme-choices-title {
  margin: 0 0 0.4rem;
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: none;
  letter-spacing: 0;
}
.theme-choices-row {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 0.35rem;
}
.theme-choice {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.2rem;
  min-height: 52px;
  padding: 0.35rem 0.25rem;
  border: 1px solid var(--border);
  border-radius: var(--radius-sm);
  background: var(--surface);
  color: var(--text);
  font-size: 0.72rem;
  font-weight: 600;
  line-height: 1.2;
}
.theme-choice:hover {
  background: var(--surface-2);
}
.theme-choice.is-active {
  border-color: var(--primary);
  background: var(--primary-soft);
  color: var(--primary-ink);
}
.theme-choice:focus-visible {
  outline: var(--focus-ring);
  outline-offset: 1px;
}
</style>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { useFirstTime } from '../../composables/useFirstTime'
import AccessibleDialog from '../ui/AccessibleDialog.vue'
import AppIcon from '../ui/AppIcon.vue'
// A short guide shown once per signed-in user.
//  - default: a note inside the page, so the main action stays usable;
//  - `tour`: a dialog (focus trap + ESC come from AccessibleDialog) that opens only when `active`.
const props = defineProps({
  name: { type: String, required: true },
  title: { type: String, required: true },
  steps: { type: Array, required: true },
  tour: Boolean,
  active: { type: Boolean, default: true },
})
const emit = defineEmits(['done'])
const { pending, markSeen } = useFirstTime(props.name)
const index = ref(0),
  body = ref(null)
const visible = computed(() => pending.value && props.active)
const step = computed(() => props.steps[index.value])
const last = computed(() => index.value >= props.steps.length - 1)
let context = null,
  disposed = false

// GSAP is fetched only when a guide is actually on screen, and only animates when the user
// has not asked for reduced motion. Without it the step is simply shown.
async function animate() {
  if (typeof window.matchMedia !== 'function') return
  try {
    const { gsap } = await import('gsap')
    await nextTick()
    if (disposed || !body.value) return
    context?.revert()
    context = gsap.context(() => {
      gsap.matchMedia().add('(prefers-reduced-motion: no-preference)', () => {
        gsap.from(body.value, {
          opacity: 0,
          y: 12,
          duration: 0.3,
          ease: 'power2.out',
          clearProps: 'opacity,transform',
        })
      })
    })
  } catch {
    /* static guide */
  }
}
function finish() {
  markSeen()
  index.value = 0
  emit('done')
}
function next() {
  if (last.value) finish()
  else index.value += 1
}
watch(
  () => [visible.value, index.value],
  ([shown]) => {
    if (shown) animate()
  },
  { immediate: true },
)
onBeforeUnmount(() => {
  disposed = true
  context?.revert()
})
</script>
<template>
  <AccessibleDialog
    v-if="visible && tour"
    :id="`guide-${name}`"
    class="first-time-tour"
    :title="title"
    @dismiss="finish"
  >
    <div ref="body" class="first-time-step" aria-live="polite">
      <!-- Decoration only: a large icon for the step on a soft brand panel. -->
      <div class="first-time-hero" aria-hidden="true">
        <span class="first-time-blob is-a"></span><span class="first-time-blob is-b"></span>
        <span class="first-time-icon"><AppIcon :name="step.icon || 'star'" :size="36" /></span>
      </div>
      <p class="first-time-count">Bước {{ index + 1 }} / {{ steps.length }}</p>
      <h3>{{ step.title }}</h3>
      <p class="first-time-text">{{ step.text }}</p>
      <div class="first-time-progress" aria-hidden="true">
        <span v-for="(item, position) in steps" :key="item.title" :class="{ 'is-done': position <= index }"></span>
      </div>
    </div>
    <template #footer>
      <div class="first-time-actions">
        <button type="button" class="btn btn-outline-secondary" data-guide-skip @click="finish">
          Bỏ qua
        </button>
        <button type="button" class="btn btn-primary" data-guide-next @click="next">
          {{ last ? 'Bắt đầu' : 'Tiếp tục' }}
        </button>
      </div>
    </template>
  </AccessibleDialog>
  <aside
    v-else-if="visible"
    class="first-time-note"
    :aria-label="title"
    @keydown.esc.stop="finish"
  >
    <div ref="body" class="first-time-step">
      <p class="first-time-count">{{ title }}</p>
      <ul>
        <li v-for="item in steps" :key="item.title"><strong>{{ item.title }}</strong> {{ item.text }}</li>
      </ul>
    </div>
    <button type="button" class="btn btn-sm btn-outline-secondary" data-guide-skip @click="finish">
      Bỏ qua
    </button>
  </aside>
</template>
<style scoped>
.first-time-step h3 {
  margin-bottom: var(--space-2);
  font-size: 1.5rem;
  font-weight: 800;
  letter-spacing: -0.02em;
}
.first-time-step p {
  margin: 0;
  line-height: 1.7;
}
.first-time-text {
  font-size: 1.05rem;
  color: var(--text);
}
.first-time-hero {
  position: relative;
  display: grid;
  place-items: center;
  height: 150px;
  margin-bottom: var(--space-5);
  overflow: hidden;
  border-radius: var(--radius-lg);
  background: linear-gradient(135deg, var(--brand-soft), var(--surface-2));
}
.first-time-blob {
  position: absolute;
  border-radius: 50%;
  background: var(--brand);
  opacity: 0.14;
}
.first-time-blob.is-a {
  width: 140px;
  height: 140px;
  top: -50px;
  right: -30px;
}
.first-time-blob.is-b {
  width: 90px;
  height: 90px;
  bottom: -35px;
  left: 18px;
}
.first-time-icon {
  position: relative;
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  border-radius: 24px;
  background: var(--surface);
  color: var(--brand-text);
  box-shadow: 0 10px 28px rgb(var(--shadow-rgb) / 12%);
  transform: rotate(-6deg);
}
.first-time-progress {
  display: flex;
  gap: 0.4rem;
  margin-top: var(--space-5);
}
.first-time-progress span {
  flex: 1;
  height: 6px;
  border-radius: var(--radius-pill);
  background: var(--surface-2);
  transition: background-color var(--transition-fast);
}
.first-time-progress span.is-done {
  background: var(--primary);
}
.first-time-count {
  display: inline-block;
  margin-bottom: var(--space-3) !important;
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius-pill);
  background: var(--accent);
  color: var(--accent-ink);
  font-size: 0.72rem;
  font-weight: 700;
}
.first-time-actions {
  display: flex;
  justify-content: space-between;
  gap: var(--space-3);
}
.first-time-actions .btn {
  min-height: 46px;
  padding-inline: 1.25rem;
}
.first-time-actions .btn-primary {
  min-width: 9rem;
}
.first-time-note {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-4);
  margin-bottom: var(--space-4);
  padding: var(--space-4);
  border: 1px solid var(--primary);
  border-radius: var(--radius-lg);
  background: var(--primary-soft);
  color: var(--text);
}
.first-time-note ul {
  margin: 0;
  padding-left: 1.1rem;
  font-size: 0.88rem;
  line-height: 1.7;
}
.first-time-note .btn {
  flex: none;
  --bs-btn-color: var(--text);
  --bs-btn-bg: var(--surface);
  --bs-btn-border-color: var(--border);
}
@media (max-width: 575px) {
  .first-time-note {
    flex-direction: column;
  }
}
</style>
<style>
.ui-dialog-backdrop.first-time-tour .ui-dialog {
  width: min(36rem, 100%);
}
@media (max-width: 767px) {
  .ui-dialog-backdrop.first-time-tour {
    padding: var(--space-4);
  }
  .ui-dialog-backdrop.first-time-tour .ui-dialog {
    height: auto;
    max-height: calc(100dvh - 2rem);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
  }
}
</style>

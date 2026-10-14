import { computed, ref } from 'vue'
import { read } from '../api/domain.api'

// The buyer's chosen area (a province). One shared choice for the whole app:
// kept in localStorage and mirrored to ?province= on the pages that filter by it.
const STORAGE_KEY = 'passlai:area'
const provinces = ref([])
const code = ref('')
let loading = null

try {
  const saved = window.localStorage.getItem(STORAGE_KEY)
  if (saved && /^\d{2}$/.test(saved)) code.value = saved
} catch {
  /* storage unavailable: no remembered area */
}

/** Loads the province list once; a failed request leaves it empty and can be retried. */
export function loadProvinces() {
  if (provinces.value.length) return Promise.resolve(provinces.value)
  loading ??= read('provinces')
    .then((result) => {
      provinces.value = Array.isArray(result?.data) ? result.data : []
      return provinces.value
    })
    .catch(() => [])
    .finally(() => {
      loading = null
    })
  return loading
}

export function useArea() {
  const area = computed(() => provinces.value.find((row) => row.code === code.value) || null)
  const label = computed(() => area.value?.name || 'Toàn quốc')
  function setArea(next) {
    code.value = /^\d{2}$/.test(String(next || '')) ? String(next) : ''
    try {
      if (code.value) window.localStorage.setItem(STORAGE_KEY, code.value)
      else window.localStorage.removeItem(STORAGE_KEY)
    } catch {
      /* the choice still applies to this page view */
    }
  }
  return { provinces, code, area, label, setArea, loadProvinces }
}

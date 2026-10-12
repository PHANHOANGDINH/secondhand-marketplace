import { localizeValidationErrors } from '../utils/validationLabels'
import { ref } from 'vue'
import { domainError } from '../api/domain.api'
export function useFeedback() {
  const busy = ref(false)
  const error = ref('')
  const errors = ref({})
  const success = ref('')
  function clear() {
    error.value = ''
    errors.value = {}
    success.value = ''
  }
  async function run(action, message = '') {
    if (busy.value) return false
    busy.value = true
    clear()
    try {
      await action()
      success.value = message
      return true
    } catch (failure) {
      error.value = domainError(failure)
      errors.value = localizeValidationErrors(failure.response?.data?.errors)
      return false
    } finally {
      busy.value = false
    }
  }
  return { busy, error, errors, success, clear, run }
}

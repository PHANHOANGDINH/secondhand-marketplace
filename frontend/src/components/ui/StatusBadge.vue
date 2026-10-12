<script setup>
import { getStatusLabel } from '../../utils/labels'
defineProps({ status: String, label: String, domain: String })
const tones = {
  IN_REVIEW: 'primary',
  RESOLVED: 'success',
  DISMISSED: 'neutral',
  LIVE: 'success',
  READY: 'success',
  SCHEDULED: 'warning',
  ENDED: 'neutral',
  PAID: 'success',
  PUBLISHED: 'success',
  ACTIVE: 'success',
  REJECTED: 'danger',
  SUSPENDED: 'warning',
  COMPLETED: 'success',
  CONFIRMED: 'primary',
  SHIPPING: 'primary',
  PROCESSING: 'warning',
  IN_PROGRESS: 'primary',
  PENDING: 'warning',
  PARTIALLY_COMPLETED: 'warning',
  FAILED: 'danger',
  CANCELLED: 'danger',
  REFUNDED: 'neutral',
  DRAFT: 'neutral',
  HIDDEN: 'warning',
}
</script>
<template>
  <span class="buyer-status" :class="`buyer-status-${tones[status] || 'neutral'}`">{{
    label && !/^[A-Z][A-Z_]*$/.test(label) ? label : getStatusLabel(status, domain)
  }}</span>
</template>

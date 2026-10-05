<script setup>
defineProps({ address: { type: Object, required: true } })
</script>
<template>
  <address class="border rounded p-3">
    <strong>{{ address.recipient_name }}</strong> — {{ address.phone }}<br />{{
      [address.address_line, address.ward, address.district, address.province, address.country_code]
        .filter(Boolean)
        .join(', ')
    }}
  </address>
</template>

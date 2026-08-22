<script setup>
import { defineProps } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
})

const STATUS_META = {
  SUBMITTED: { label: 'ລໍຖ້າອະນຸມັດ', cls: 'bg-amber-100 text-amber-700' }, // legacy: merged into PENDING_APPROVAL
  PENDING_APPROVAL: { label: 'ລໍຖ້າອະນຸມັດ', cls: 'bg-amber-100 text-amber-700' },
  PENDING_PAYMENT: { label: 'ລໍຖ້າຊຳລະ', cls: 'bg-orange-100 text-orange-700' },
  PAID: { label: 'ຊຳລະແລ້ວ', cls: 'bg-teal-100 text-teal-700' },
  SIGNED: { label: 'ລົງນາມແລ້ວ', cls: 'bg-indigo-100 text-indigo-700' },
  HANDOVER: { label: 'ພ້ອມຮັບເອກະສານ', cls: 'bg-green-100 text-green-700' },
  DOWNLOADED: { label: 'ດາວໂຫຼດແລ້ວ', cls: 'bg-slate-100 text-slate-700' },
  REJECTED: { label: 'ຖືກປະຕິເສດ', cls: 'bg-red-100 text-red-700' },
  CANCELED: { label: 'ຍົກເລີກ', cls: 'bg-slate-100 text-slate-500' },
}
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="STATUS_META[props.status]?.cls || 'bg-slate-100 text-slate-600'"
  >
    {{ STATUS_META[props.status]?.label || props.status }}
  </span>
</template>

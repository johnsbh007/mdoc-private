<script setup>
import { defineProps } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
})
</script>

<template>
  <div class="mb-2 text-xs font-medium uppercase tracking-wide text-slate-500">{{ props.label }}</div>
</template>

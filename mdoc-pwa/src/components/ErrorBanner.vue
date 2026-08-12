<script setup>
import { defineProps } from 'vue'

const props = defineProps({
  error: { type: [String, null], default: null },
})
</script>

<template>
  <div v-if="props.error" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
    {{ props.error }}
  </div>
</template>

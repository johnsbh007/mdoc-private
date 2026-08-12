<script setup>
import { ref, onMounted } from 'vue'
import api, { ApiError } from '../api/client'
import AppShell from '../components/AppShell.vue'
import StatusBadge from '../components/StatusBadge.vue'

const docs = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    const res = await api('documents')
    docs.value = res.data || res || []
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດ'
  } finally {
    loading.value = false
  }
})

function fmtDate(s) {
  if (!s) return ''
  const d = new Date(s)
  return d.toLocaleDateString('lo-LA')
}
</script>

<template>
  <AppShell>
    <header class="bg-teal-800 px-4 py-4 text-white">
      <h1 class="text-lg font-bold">ຄຳຮ້ອງຂອງຂ້ອຍ</h1>
    </header>

    <div class="px-4 py-4">
      <p v-if="loading" class="py-8 text-center text-sm text-slate-400">ກຳລັງໂຫຼດ...</p>
      <p v-if="error" class="py-8 text-center text-sm text-red-600">{{ error }}</p>
      <div v-else class="space-y-3">
        <RouterLink
          v-for="d in docs"
          :key="d.id"
          :to="{ name: 'doc-detail', params: { id: d.id } }"
          class="block rounded-xl border border-slate-200 bg-white p-4"
        >
          <div class="flex items-center justify-between">
            <div class="text-sm font-semibold text-slate-800">{{ d.doc_no }}</div>
            <StatusBadge :status="d.status" />
          </div>
          <div class="mt-1 text-sm text-slate-500">{{ d.doc_item_name }}</div>
          <div class="mt-2 flex items-center justify-between text-xs text-slate-400">
            <span>{{ fmtDate(d.submitted_at) }}</span>
            <span>{{ Number(d.amount || 0).toLocaleString('lo-LA') }} ກີບ</span>
          </div>
        </RouterLink>
        <p v-if="!docs.length && !loading" class="py-8 text-center text-sm text-slate-400">
          ຍັງບໍ່ມີຄຳຮ້ອງ —
          <RouterLink to="/catalog" class="text-teal-700">ຂໍເອກະສານໃໝ່</RouterLink>
        </p>
      </div>
    </div>
  </AppShell>
</template>

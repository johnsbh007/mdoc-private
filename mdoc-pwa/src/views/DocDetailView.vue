<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import api, { ApiError } from '../api/client'
import AppShell from '../components/AppShell.vue'
import StatusBadge from '../components/StatusBadge.vue'

const route = useRoute()
const doc = ref(null)
const loading = ref(true)
const error = ref(null)
const polling = ref(false)

async function load() {
  try {
    doc.value = await api(`documents/${route.params.id}`)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດ'
  } finally {
    loading.value = false
  }
}

async function pay() {
  // Opens the QR payment screen — the API returns a QR image URL
  // (implemented when the pay-qr endpoint goes live; placeholder for now)
  alert('ການຊຳລະຜ່ານ QR ຈະມີໃນໄວໆນີ້')
}

onMounted(() => {
  load()
  // Poll for status changes every 15s while this screen is open
  polling.value = setInterval(load, 15000)
})
onUnmounted(() => clearInterval(polling.value))

function fmtDate(s) {
  if (!s) return ''
  return new Date(s).toLocaleString('lo-LA')
}
</script>

<template>
  <AppShell>
    <div class="px-4 py-4">
      <p v-if="loading" class="py-8 text-center text-sm text-slate-400">ກຳລັງໂຫຼດ...</p>
      <p v-if="error" class="py-8 text-center text-sm text-red-600">{{ error }}</p>

      <template v-if="doc">
        <div class="rounded-xl bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between">
            <h1 class="text-base font-bold text-slate-900">{{ doc.doc_no }}</h1>
            <StatusBadge :status="doc.status" />
          </div>
          <p class="mt-1 text-sm text-slate-500">{{ doc.doc_item_name }}</p>
          <div class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">
            <p>ວັນທີສົ່ງ: {{ fmtDate(doc.submitted_at) }}</p>
            <p class="mt-1">ຈຳນວນເງິນ: <b>{{ Number(doc.amount || 0).toLocaleString('lo-LA') }} ກີບ</b></p>
            <p v-if="doc.invoice?.status" class="mt-1">
              ໃບແຈ້ງໜີ້: {{ doc.invoice.inv_no }} ({{ doc.invoice.status }})
            </p>
          </div>
        </div>

        <!-- Actions based on status -->
        <div v-if="['PENDING_PAYMENT', 'PENDING_APPROVAL'].includes(doc.status)" class="mt-4">
          <button
            @click="pay"
            class="w-full rounded-lg bg-teal-700 py-3 text-sm font-semibold text-white"
          >
            ຊຳລະຄ່າບໍລິການ (QR)
          </button>
        </div>

        <!-- Status history -->
        <div v-if="doc.status_history?.length" class="mt-4 rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">ປະຫວັດສະຖານະ</h2>
          <ol class="relative space-y-4 border-l border-slate-200 pl-4">
            <li v-for="s in [...doc.status_history].reverse()" :key="s.id" class="relative">
              <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-teal-600"></span>
              <div class="text-sm font-medium text-slate-700">{{ s.status }}</div>
              <div v-if="s.remark" class="text-xs text-slate-500">{{ s.remark }}</div>
              <div class="text-xs text-slate-400">{{ fmtDate(s.created_at) }}</div>
            </li>
          </ol>
        </div>
      </template>
    </div>
  </AppShell>
</template>

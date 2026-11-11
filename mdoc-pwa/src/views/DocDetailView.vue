<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import api, { ApiError } from '../api/client'
import AppShell from '../components/AppShell.vue'
import StatusBadge from '../components/StatusBadge.vue'

const route = useRoute()
const doc = ref(null)
const statusHistory = computed(() =>
  [...(doc.value?.history ?? doc.value?.status_history ?? [])].reverse()
)
const loading = ref(true)
const error = ref(null)
const slipFile = ref(null)
const slipError = ref('')
const slipAmount = ref('')
const slipSubmitting = ref(false)
const slipSuccess = ref(false)

function onSlipSelected(event) {
  const input = event.target
  const file = input.files?.[0]

  slipFile.value = null
  slipError.value = ''

  if (!file) return

  const allowedTypes = [
    'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf',
  ]

  if (!allowedTypes.includes(file.type)) {
    slipError.value = 'ກະລຸນາເລືອກໄຟລ໌ JPG, PNG, WEBP, GIF ຫຼື PDF'
  } else if (file.size > 8 * 1024 * 1024) {
    slipError.value = 'ຂະໜາດໄຟລ໌ຕ້ອງບໍ່ເກີນ 8 MB'
  } else {
    slipFile.value = file
    return
  }

  input.value = ''
}
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

async function submitSlip(event) {
  if (slipSubmitting.value || !slipFile.value) return

  slipError.value = ''
  slipSuccess.value = false
  const amount = Number(slipAmount.value)
  if (slipAmount.value === '' || !Number.isFinite(amount) || amount < doc.value.amount) {
    slipError.value = 'ກະລຸນາປ້ອນຈຳນວນເງິນໃຫ້ເທົ່າກັບ ຫຼື ສູງກວ່າຄ່າທຳນຽມ'
    return
  }

  const form = event.currentTarget
  const body = new FormData()
  body.append('file', slipFile.value)
  body.append('amount', String(amount))

  slipSubmitting.value = true
  try {
    await api(`documents/${route.params.id}/payment-evidence`, { method: 'POST', body })
    slipFile.value = null
    slipAmount.value = ''
    form.reset()
    slipSuccess.value = true
    await load()
  } catch (e) {
    slipError.value = e instanceof ApiError ? e.message : 'ບໍ່ສາມາດສົ່ງສະລິບໄດ້'
  } finally {
    slipSubmitting.value = false
  }
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
          <p class="mt-1 text-sm text-slate-500">{{ doc.item_name ?? doc.doc_item_name }}</p>
          <div class="mt-3 border-t border-slate-100 pt-3 text-sm text-slate-600">
            <p v-if="doc.created_at ?? doc.submitted_at">ວັນທີສົ່ງ: {{ fmtDate(doc.created_at ?? doc.submitted_at) }}</p>
            <p class="mt-1">ຈຳນວນເງິນ: <b>{{ Number(doc.amount || 0).toLocaleString('lo-LA') }} ກີບ</b></p>
            <p v-if="doc.invoice?.status" class="mt-1">
              ໃບແຈ້ງໜີ້: {{ doc.invoice.inv_no }} ({{ doc.invoice.status }})
            </p>
          </div>
        </div>

        <!-- Actions based on status -->
        <p v-if="doc.status === 'PENDING_APPROVAL'" class="mt-4 text-sm text-slate-600">
          ຄຳຮ້ອງກຳລັງລໍຖ້າການອະນຸມັດ
        </p>
        <form
          v-else-if="['ISSUED', 'PENDING_PAYMENT'].includes(doc.status)"
          class="mt-4 space-y-3"
          @submit.prevent="submitSlip"
        >
          <div>
            <label for="payment-slip" class="block text-sm">ແນບສະລິບການໂອນເງິນ</label>
            <input
              id="payment-slip"
              type="file"
              accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
              class="mt-2 block w-full text-sm"
              :disabled="slipSubmitting"
              @change="onSlipSelected"
            />
            <p v-if="slipFile" class="mt-2 text-sm text-slate-600">{{ slipFile.name }}</p>
          </div>
          <div>
            <label for="slip-amount" class="block text-sm">ຈຳນວນເງິນທີ່ໂອນ (ກີບ)</label>
            <input
              id="slip-amount"
              v-model.number="slipAmount"
              type="number"
              step="any"
              :min="doc.amount"
              required
              :disabled="slipSubmitting"
              class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"
            />
          </div>
          <button
            type="submit"
            :disabled="!slipFile || slipSubmitting"
            class="w-full rounded-lg bg-teal-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
          >
            {{ slipSubmitting ? 'ກຳລັງສົ່ງສະລິບ...' : 'ສົ່ງສະລິບ' }}
          </button>
        </form>
        <p v-if="slipError" role="alert" class="mt-2 text-sm text-red-600">{{ slipError }}</p>
        <p v-if="slipSuccess" role="status" class="mt-2 text-sm text-teal-700">ສົ່ງສະລິບສຳເລັດແລ້ວ</p>

        <!-- Status history -->
        <div v-if="statusHistory.length" class="mt-4 rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">ປະຫວັດສະຖານະ</h2>
          <ol class="relative space-y-4 border-l border-slate-200 pl-4">
            <li v-for="(s, index) in statusHistory" :key="s.id ?? index" class="relative">
              <span class="absolute -left-[21px] top-1 h-2.5 w-2.5 rounded-full bg-teal-600"></span>
              <div class="text-sm font-medium text-slate-700">{{ s.status }}</div>
              <div v-if="s.remark" class="text-xs text-slate-500">{{ s.remark }}</div>
              <div class="text-xs text-slate-400">{{ fmtDate(s.changed_at ?? s.created_at) }}</div>
            </li>
          </ol>
        </div>
      </template>
    </div>
  </AppShell>
</template>

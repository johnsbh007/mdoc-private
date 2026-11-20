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
const slipPreview = ref('')
const slipError = ref('')
const slipAmount = ref('')
const slipPayRef = ref('')
const slipNote = ref('')
const slipSubmitting = ref(false)
const slipSuccess = ref(false)
const receipt = ref(null)
const receiptLoading = ref(false)
const receiptError = ref('')
const downloading = ref(false)
const downloadError = ref('')
// Supporting attachments
const attachmentFile = ref(null)
const attachmentError = ref('')
const attachmentNote = ref('')
const attachmentSubmitting = ref(false)
const attachmentSuccess = ref('')

async function submitAttachment() {
  if (attachmentSubmitting.value) return

  attachmentError.value = ''
  attachmentSuccess.value = ''

  if (!canUploadAttachment.value) {
    attachmentError.value = 'ສະຖານະນີ້ບໍ່ອະນຸຍາດໃຫ້ແນບເອກະສານ'
    return
  }

  if (!attachmentFile.value) {
    attachmentError.value = 'ກະລຸນາເລືອກໄຟລ໌'
    return
  }

  const body = new FormData()
  body.append('file', attachmentFile.value)

  if (attachmentNote.value.trim()) {
    body.append('note', attachmentNote.value.trim())
  }

  attachmentSubmitting.value = true

  try {
    await api(`documents/${route.params.id}/attachments`, {
      method: 'POST',
      body,
    })

    attachmentFile.value = null
    attachmentNote.value = ''

    const input = document.getElementById('supporting-file')
    if (input) input.value = ''

    attachmentSuccess.value = 'ອັບໂຫຼດເອກະສານສຳເລັດແລ້ວ'
    await load()
  } catch (e) {
    attachmentError.value = e instanceof ApiError
      ? e.message
      : 'ບໍ່ສາມາດອັບໂຫຼດເອກະສານໄດ້'
  } finally {
    attachmentSubmitting.value = false
  }
}

const canUploadAttachment = computed(() =>
  [
    'DRAFT',
    'PENDING_APPROVAL',
    'NEEDS_REVISION',
    'ISSUED',
    'PENDING_PAYMENT',
  ].includes(doc.value?.status)
)

function onAttachmentSelected(event) {
  const input = event.target
  const file = input.files?.[0]

  attachmentFile.value = null
  attachmentError.value = ''
  attachmentSuccess.value = ''

  if (!file) return

  const allowedTypes = [
    'image/jpeg',
    'image/png',
    'image/webp',
    'image/gif',
    'application/pdf',
  ]

  if (!allowedTypes.includes(file.type)) {
    attachmentError.value =
      'ກະລຸນາເລືອກໄຟລ໌ JPG, PNG, WEBP, GIF ຫຼື PDF'
  } else if (file.size > 8 * 1024 * 1024) {
    attachmentError.value = 'ຂະໜາດໄຟລ໌ຕ້ອງບໍ່ເກີນ 8 MB'
  } else {
    attachmentFile.value = file
    return
  }

  input.value = ''
}

function onSlipSelected(event) {
  const input = event.target
  const file = input.files?.[0]

  if (slipPreview.value) {
    URL.revokeObjectURL(slipPreview.value)
    slipPreview.value = ''
  }

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
    if (file.type.startsWith('image/')) {
      slipPreview.value = URL.createObjectURL(file)
    }
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
  if (slipPayRef.value.trim()) {
  body.append('pay_ref', slipPayRef.value.trim())
}
if (slipNote.value.trim()) {
  body.append('note', slipNote.value.trim())
}

  slipSubmitting.value = true
  try {
    await api(`documents/${route.params.id}/payment-evidence`, { method: 'POST', body })
        if (slipPreview.value) {
      URL.revokeObjectURL(slipPreview.value)
      slipPreview.value = ''
    }
    slipFile.value = null
    slipAmount.value = ''
    slipPayRef.value = ''
    slipNote.value = ''
    form.reset()
    slipSuccess.value = true
    await load()
  } catch (e) {
  if (
    e instanceof ApiError &&
    e.status === 422 &&
    e.code === 'AMOUNT_TOO_LOW'
  ) {
    await load()
    slipError.value =
      `ກະລຸນາປ້ອນຈຳນວນເງິນຢ່າງໜ້ອຍ ${doc.value.amount.toLocaleString('lo-LA')} ກີບ`
  } else {
    slipError.value = e instanceof ApiError
      ? e.message
      : 'ບໍ່ສາມາດສົ່ງສະລິບໄດ້'
  }
} finally {
    slipSubmitting.value = false
  }
}
async function loadReceipt() {
  if (receiptLoading.value) return

  receiptError.value = ''
  receiptLoading.value = true

  try {
    receipt.value = await api(`documents/${route.params.id}/receipt`)
  } catch (e) {
    receiptError.value = e instanceof ApiError
      ? e.message
      : 'ບໍ່ສາມາດໂຫຼດໃບຮັບເງິນໄດ້'
  } finally {
    receiptLoading.value = false
  }
}
async function downloadDocument() {
  if (downloading.value) return
  if (!['SIGNED', 'HANDOVER'].includes(doc.value?.status)) return

  downloading.value = true
  downloadError.value = ''

  try {
    const id = route.params.id
    const blob = await api(`documents/${id}/download`, {
      responseType: 'blob',
      headers: { Accept: 'application/octet-stream' },
    })

    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `document-${id}.docx`

    document.body.appendChild(link)
    link.click()
    link.remove()

    setTimeout(() => URL.revokeObjectURL(url), 1000)
    } catch (e) {
    if (e instanceof ApiError && e.code === 'FILE_NOT_READY') {
      downloadError.value = 'ເອກະສານຍັງບໍ່ທັນສ້າງເທື່ອ'
    } else {
      downloadError.value = e instanceof ApiError
        ? e.message
        : 'ບໍ່ສາມາດດາວໂຫຼດເອກະສານໄດ້'
    }
  } finally {
    downloading.value = false
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
function fmtSize(bytes) {
  if (!bytes && bytes !== 0) return ''
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
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
  class="peer sr-only"
  :disabled="slipSubmitting"
  @change="onSlipSelected"
/>

<label
  for="payment-slip"
  class="mt-3 inline-flex cursor-pointer items-center gap-1.5 rounded-lg
         bg-teal-600 px-3 py-1.5 text-sm font-semibold text-white
         shadow-sm transition hover:bg-teal-700 active:bg-teal-800
         peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
         peer-focus-visible:outline-teal-600
         peer-disabled:pointer-events-none peer-disabled:opacity-50"
>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    class="h-4 w-4"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    stroke-width="2"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M12 16V4m0 0-4 4m4-4 4 4M4 16v4h16v-4"
    />
  </svg>
  ເລືອກໄຟລ໌
</label>
<p class="mt-2 text-xs text-slate-500">
  JPG, PNG, WEBP, GIF, PDF — ຂະໜາດບໍ່ເກີນ 8 MB
</p>
                        <div v-if="slipFile" class="mt-2 space-y-2">
              <!-- แสดงรูปตัวอย่าง ถ้าเป็นไฟล์รูปภาพ -->
              <div
                v-if="slipPreview"
                class="overflow-hidden rounded-lg border border-slate-200 bg-slate-50 p-1"
              >
                <img
                  :src="slipPreview"
                  alt="ຕົວຢ່າງສະລິບ"
                  class="max-h-60 w-full rounded object-contain"
                />
              </div>

              <!-- แสดงชื่อไฟล์และขนาดไฟล์ -->
              <p class="text-xs text-slate-600">
                {{ slipFile.name }} ({{ fmtSize(slipFile.size) }})
              </p>
            </div>
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
          <div>
  <label for="slip-pay-ref" class="block text-sm">
    ເລກອ້າງອີງການໂອນ (ບໍ່ບັງຄັບ)
  </label>
  <input
    id="slip-pay-ref"
    v-model="slipPayRef"
    type="text"
    :disabled="slipSubmitting"
    class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"
  />
</div>

<div>
  <label for="slip-note" class="block text-sm">
    ໝາຍເຫດ (ບໍ່ບັງຄັບ)
  </label>
  <textarea
    id="slip-note"
    v-model="slipNote"
    rows="2"
    :disabled="slipSubmitting"
    class="mt-2 w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"
  ></textarea>
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

        <!-- Receipt showonly PAID / SIGNED / HANDOVER -->
        <section
  v-if="['PAID', 'SIGNED', 'HANDOVER'].includes(doc.status)"
  class="mt-4"
>
  <button
    type="button"
    :disabled="receiptLoading"
    class="w-full rounded-lg bg-teal-700 px-4 py-3 text-white disabled:opacity-50"
    @click="loadReceipt"
  >
    {{ receiptLoading ? 'ກຳລັງໂຫຼດ...' : 'ເບິ່ງໃບຮັບເງິນ' }}
  </button>

  <p
    v-if="receiptError"
    role="alert"
    class="mt-2 text-sm text-red-600"
  >
    {{ receiptError }}
  </p>

  <div
    v-if="receipt"
    class="mt-3 space-y-2 rounded-xl bg-white p-5 shadow-sm"
  >
    <h2 class="font-semibold">ໃບຮັບເງິນ</h2>
    <p>ເລກທີ: {{ receipt.receipt_no }}</p>
    <p>ວັນທີ: {{ fmtDate(receipt.receipt_date) }}</p>
    <p>
      ຈຳນວນເງິນ:
      {{ (receipt.amount ?? 0).toLocaleString('lo-LA') }} ກີບ
    </p>
    <p v-if="receipt.inv_no">
      ເລກທີໃບແຈ້ງໜີ້: {{ receipt.inv_no }}
    </p>
    <p v-if="receipt.pay_no">
  ເລກທີການຊຳລະ: {{ receipt.pay_no }}
</p>
<p v-if="receipt.method">
  ວິທີຊຳລະ:
  {{ receipt.method === 'bank_transfer'
    ? 'ໂອນເງິນຜ່ານທະນາຄານ'
    : receipt.method }}
</p>
<p v-if="receipt.paid_at">
  ວັນທີຊຳລະ: {{ fmtDate(receipt.paid_at) }}
</p>
<p v-if="receipt.ref_no">
  ເລກອ້າງອີງ: {{ receipt.ref_no }}
</p>
  </div>
</section>

<!-- Download Only Status: SIGNED / HANDOVER -->
<section
  v-if="['SIGNED', 'HANDOVER'].includes(doc.status)"
  class="mt-4"
>
  <button
    type="button"
    :disabled="downloading"
    class="w-full rounded-lg bg-teal-700 px-4 py-3 text-white disabled:opacity-50"
    @click="downloadDocument"
  >
    {{ downloading
      ? 'ກຳລັງດາວໂຫຼດ...'
      : 'ດາວໂຫຼດເອກະສານ (.docx)' }}
  </button>

  <p
    v-if="downloadError"
    role="alert"
    class="mt-2 text-sm text-red-600"
  >
    {{ downloadError }}
  </p>
</section>

<!-- Payment slip Details -->
<section class="mt-4 rounded-xl bg-white p-5 shadow-sm">
  <h2 class="mb-3 text-sm font-semibold text-slate-700">
    ລາຍການສະລິບການຊຳລະເງິນ
  </h2>

  <p v-if="!doc.evidence?.length" class="text-sm text-slate-500">
    ຍັງບໍ່ມີສະລິບ
  </p>

  <ul v-else class="space-y-3">
    <li
      v-for="slip in doc.evidence"
      :key="slip.id"
      class="rounded-lg border border-slate-200 p-3 text-sm"
    >
      <p class="break-words font-medium">{{ slip.orig_name }}</p>
      <p class="mt-1 text-slate-500">{{ fmtDate(slip.created_at) }}</p>

      <p v-if="slip.superseded_at" class="mt-2 text-amber-700">
        ຖືກແທນທີ່ດ້ວຍສະລິບໃໝ່ແລ້ວ
      </p>

      <p v-if="slip.reject_reason" class="mt-2 text-red-600">
        ຖືກປະຕິເສດ: {{ slip.reject_reason }}
      </p>

      <p
        v-else-if="slip.verified === 1 || slip.verified === true"
        class="mt-2 text-teal-700"
      >
        ກວດສອບແລ້ວ
      </p>

      <p v-else-if="!slip.superseded_at" class="mt-2 text-slate-500">
        ລໍຖ້າການກວດສອບ
      </p>
    </li>
  </ul> 
  
</section>
        <!-- Attachments List -->
        <section class="mt-4 rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">
            ເອກະສານຕິດຄັດ
          </h2>

          <p v-if="!doc.attachments?.length" class="text-sm text-slate-500">
            ຍັງບໍ່ມີເອກະສານຕິດຄັດ
          </p>

          <ul v-else class="space-y-3">
            <li
              v-for="att in doc.attachments"
              :key="att.id"
              class="rounded-lg border border-slate-200 p-3 text-sm"
            >
              <div class="flex items-start justify-between gap-2">
                <p class="break-words font-medium text-slate-800">{{ att.orig_name }}</p>
                <span class="shrink-0 text-xs text-slate-400">{{ fmtSize(att.size) }}</span>
              </div>
              <p v-if="att.note" class="mt-1 text-xs text-slate-600">
                ໝາຍເຫດ: {{ att.note }}
              </p>
              <p class="mt-1 text-xs text-slate-400">
                {{ fmtDate(att.created_at) }}
              </p>
            </li>
          </ul>
          <div
  v-if="canUploadAttachment"
  class="mt-4 border-t border-slate-200 pt-4"
>
  <label for="supporting-file" class="block text-sm font-medium">
    ເພີ່ມເອກະສານຕິດຄັດ
  </label>

  <input
  id="supporting-file"
  type="file"
  :disabled="attachmentSubmitting"
  accept="image/jpeg,image/png,image/webp,image/gif,application/pdf"
  class="peer sr-only"
  @change="onAttachmentSelected"
/>

<label
  for="supporting-file"
  class="mt-3 inline-flex cursor-pointer items-center gap-1.5 rounded-lg
         bg-teal-600 px-3 py-1.5 text-sm font-semibold text-white
         shadow-sm transition hover:bg-teal-700 active:bg-teal-800
         peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2
         peer-focus-visible:outline-teal-600"
>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    class="h-4 w-4"
    fill="none"
    viewBox="0 0 24 24"
    stroke="currentColor"
    stroke-width="2"
    aria-hidden="true"
  >
    <path
      stroke-linecap="round"
      stroke-linejoin="round"
      d="M12 16V4m0 0-4 4m4-4 4 4M4 16v4h16v-4"
    />
  </svg>
  ເລືອກໄຟລ໌
</label>

  <p class="mt-2 text-xs text-slate-500">
    JPG, PNG, WEBP, GIF, PDF — ຂະໜາດບໍ່ເກີນ 8 MB
  </p>

  <p v-if="attachmentFile" class="mt-2 break-words text-sm">
    {{ attachmentFile.name }} — {{ fmtSize(attachmentFile.size) }}
  </p>

  <p
    v-if="attachmentError"
    role="alert"
    class="mt-2 text-sm text-red-600"
  >
    {{ attachmentError }}
  </p>
  <div class="mt-3">
  <label for="attachment-note" class="block text-sm">
    ໝາຍເຫດ (ບໍ່ບັງຄັບ)
  </label>

  <textarea
    id="attachment-note"
    :disabled="attachmentSubmitting"
    v-model="attachmentNote"
    rows="2"
    class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
  ></textarea>
</div>

<button
  type="button"
  :disabled="!attachmentFile || attachmentSubmitting"
  :aria-busy="attachmentSubmitting"
  class="mt-3 inline-flex items-center justify-center gap-2
         rounded-xl bg-teal-600 px-4 py-2.5
         text-sm font-semibold text-white
         hover:bg-teal-700 disabled:opacity-50"
  @click="submitAttachment"
>
  <span
    v-if="attachmentSubmitting"
    aria-hidden="true"
    class="h-4 w-4 animate-spin rounded-full
           border-2 border-white/40 border-t-white"
  ></span>

  <span>
    {{ attachmentSubmitting
      ? 'ກຳລັງອັບໂຫຼດ...'
      : 'ອັບໂຫຼດເອກະສານ' }}
  </span>
</button>

<p
  v-if="attachmentSuccess"
  role="status"
  class="mt-2 text-sm text-teal-700"
>
  {{ attachmentSuccess }}
</p>
</div>
        </section>

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

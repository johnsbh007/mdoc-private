<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { ApiError } from '../api/client'
import AppShell from '../components/AppShell.vue'
import ErrorBanner from '../components/ErrorBanner.vue'

const route = useRoute()
const router = useRouter()
const item = ref(null)
const fields = ref([])
const formData = ref({})
const loading = ref(true)
const submitting = ref(false)
const error = ref(null)

const HIERARCHY_KEY = 'mdoc_hierarchy_cache'
const hierarchy = ref({ provinces: [], districts: [], tassengs: [], villages: [] })
const selProvince = ref(null)
const selDistrict = ref(null)
const selTasseng = ref(null)
const villageId = ref(null)

onMounted(async () => {
  try {
    const [itemData, fieldData] = await Promise.all([
      api(`doc-items/${route.params.itemId}`),
      api(`doc-items/${route.params.itemId}/fields`),
    ])
    item.value = itemData
    fields.value = fieldData
    // Prefill defaults
    for (const f of fieldData) formData.value[f.field_name] = ''
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດ'
  } finally {
    loading.value = false
  }

  // Hierarchy for the "village" selector (cached to stay offline-friendly)
  const cached = localStorage.getItem(HIERARCHY_KEY)
  if (cached) {
    try { hierarchy.value = JSON.parse(cached) } catch { /* ignore */ }
  }
  try {
    const h = await api('hierarchy')
    hierarchy.value = h
    localStorage.setItem(HIERARCHY_KEY, JSON.stringify(h))
  } catch { /* offline: use cache */ }
})

function filteredDistricts() {
  return hierarchy.value.districts.filter((d) => d.province_id === Number(selProvince.value))
}
function filteredTassengs() {
  return hierarchy.value.tassengs.filter((t) => t.district_id === Number(selDistrict.value))
}
function filteredVillages() {
  return hierarchy.value.villages.filter((v) => v.tasseng_id === Number(selTasseng.value))
}

async function onSubmit() {
  error.value = null
  submitting.value = true
  try {
    const res = await api('documents', {
      method: 'POST',
      body: {
        doc_item_id: Number(route.params.itemId),
        village_id: villageId.value ? Number(villageId.value) : null,
        form_data: formData.value,
      },
    })
    router.push({ name: 'doc-detail', params: { id: res.doc_id } })
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດ'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <AppShell>
    <div class="px-4 py-4">
      <button class="mb-4 text-sm text-teal-700" @click="router.back()">← ກັບຄືນ</button>
      <h1 v-if="item" class="mb-4 text-lg font-bold text-slate-900">ຂໍເອກະສານ: {{ item.name_lo }}</h1>

      <p v-if="loading" class="py-8 text-center text-sm text-slate-400">ກຳລັງໂຫຼດ...</p>
      <ErrorBanner :error="error" />

      <form v-if="item && !loading" class="space-y-4" @submit.prevent="onSubmit">
        <div v-for="f in fields" :key="f.id">
          <label class="mb-1 block text-sm font-medium text-slate-700">
            {{ f.field_label }}
            <span v-if="f.is_required" class="text-red-500">*</span>
          </label>
          <input
            v-if="['text', 'number', 'date'].includes(f.field_type)"
            v-model="formData[f.field_name]"
            :type="f.field_type === 'date' ? 'date' : f.field_type === 'number' ? 'number' : 'text'"
            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"
          />
          <textarea
            v-else-if="f.field_type === 'textarea'"
            v-model="formData[f.field_name]"
            rows="3"
            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"
          ></textarea>
          <input
            v-else-if="f.field_type === 'file'"
            type="file"
            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
          />
          <input
            v-else
            v-model="formData[f.field_name]"
            class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm"
          />
        </div>

        <!-- Location cascade -->
        <div class="border-t border-slate-200 pt-4">
          <p class="mb-2 text-sm font-semibold text-slate-700">ສະຖານທີ່ຕິດຕໍ່</p>
          <div class="space-y-3">
            <select v-model="selProvince" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
              <option :value="null">— ເລືອກແຂວງ —</option>
              <option v-for="p in hierarchy.provinces" :key="p.id" :value="p.id">{{ p.name_lo }}</option>
            </select>
            <select v-if="selProvince" v-model="selDistrict" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
              <option :value="null">— ເລືອກເມືອງ —</option>
              <option v-for="d in filteredDistricts()" :key="d.id" :value="d.id">{{ d.name_lo }}</option>
            </select>
            <select v-if="selDistrict" v-model="selTasseng" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
              <option :value="null">— ເລືອກຕາແສງ —</option>
              <option v-for="t in filteredTassengs()" :key="t.id" :value="t.id">{{ t.name_lo }}</option>
            </select>
            <select v-if="selTasseng" v-model="villageId" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
              <option :value="null">— ເລືອກບ້ານ —</option>
              <option v-for="v in filteredVillages()" :key="v.id" :value="v.id">{{ v.name_lo }}</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-between rounded-xl bg-slate-100 px-4 py-3">
          <span class="text-sm text-slate-600">ຄ່າບໍລິການ</span>
          <span class="text-base font-bold text-teal-700">{{ Number(item.price || 0).toLocaleString('lo-LA') }} ກີບ</span>
        </div>

        <button
          type="submit"
          :disabled="submitting"
          class="w-full rounded-lg bg-teal-700 py-3 text-sm font-semibold text-white disabled:opacity-50"
        >
          {{ submitting ? 'ກຳລັງສົ່ງ...' : 'ສົ່ງຄຳຮ້ອງ' }}
        </button>
      </form>
    </div>
  </AppShell>
</template>

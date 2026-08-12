<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api, { ApiError } from '../api/client'
import AppShell from '../components/AppShell.vue'

const route = useRoute()
const items = ref([])
const docTypes = ref([])
const loading = ref(true)
const error = ref(null)
const selType = ref(route.query.type ? Number(route.query.type) : null)

onMounted(async () => {
  try {
    const [itemsData, typesData] = await Promise.all([api('doc-items'), api('doc-types')])
    items.value = itemsData
    docTypes.value = typesData
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດ'
  } finally {
    loading.value = false
  }
})

function filtered() {
  return items.value.filter((i) => !selType.value || i.doc_type_id === selType.value)
}

function fmt(n) {
  return Number(n || 0).toLocaleString('lo-LA')
}
</script>

<template>
  <AppShell>
    <header class="sticky top-0 z-30 bg-teal-800 px-4 py-3 text-white">
      <h1 class="text-lg font-bold">ບໍລິການເອກະສານ</h1>
      <div class="mt-2 flex gap-2 overflow-x-auto pb-1">
        <button
          @click="selType = null"
          class="shrink-0 rounded-full px-3 py-1 text-xs"
          :class="selType === null ? 'bg-white text-teal-800' : 'bg-teal-700 text-white'"
        >
          ທັງໝົດ
        </button>
        <button
          v-for="t in docTypes"
          :key="t.id"
          @click="selType = t.id"
          class="shrink-0 rounded-full px-3 py-1 text-xs"
          :class="selType === t.id ? 'bg-white text-teal-800' : 'bg-teal-700 text-white'"
        >
          {{ t.name_lo }}
        </button>
      </div>
    </header>

    <div class="px-4 py-4">
      <p v-if="loading" class="py-8 text-center text-sm text-slate-400">ກຳລັງໂຫຼດ...</p>
      <p v-if="error" class="py-8 text-center text-sm text-red-600">{{ error }}</p>
      <div v-else class="space-y-3">
        <RouterLink
          v-for="i in filtered()"
          :key="i.id"
          :to="{ name: 'doc-item', params: { id: i.id } }"
          class="flex items-center justify-between rounded-xl border border-slate-200 bg-white p-4"
        >
          <div>
            <div class="text-sm font-medium text-slate-800">{{ i.name_lo }}</div>
            <div class="mt-0.5 text-xs text-slate-400">{{ i.unit || '' }}</div>
          </div>
          <div class="text-right">
            <div class="text-sm font-bold text-teal-700">{{ fmt(i.price) }} ກີບ</div>
            <div class="text-xs text-slate-400">ເບິ່ງລາຍລະອຽດ →</div>
          </div>
        </RouterLink>
        <p v-if="!filtered().length" class="py-8 text-center text-sm text-slate-400">ບໍ່ມີເອກະສານໃນປະເພດນີ້</p>
      </div>
    </div>
  </AppShell>
</template>

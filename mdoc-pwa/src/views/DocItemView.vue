<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api, { ApiError } from '../api/client'
import AppShell from '../components/AppShell.vue'

const route = useRoute()
const router = useRouter()
const item = ref(null)
const loading = ref(true)
const error = ref(null)
const implementedItemIds = [
  1, 4, 5, 8, 20, 28, 30, 52, 53, 54, 55, 56, 57, 58,
]

onMounted(async () => {
  try {
    item.value = await api(`doc-items/${route.params.id}`)
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດ'
  } finally {
    loading.value = false
  }
})

function fmt(n) {
  return Number(n || 0).toLocaleString('lo-LA')
}
</script>

<template>
  <AppShell>
    <div class="px-4 py-4">
      <button class="mb-4 text-sm text-teal-700" @click="router.back()">← ກັບຄືນ</button>

      <p v-if="loading" class="py-8 text-center text-sm text-slate-400">ກຳລັງໂຫຼດ...</p>
      <p v-if="error" class="py-8 text-center text-sm text-red-600">{{ error }}</p>

      <template v-if="item">
        <div class="rounded-xl bg-white p-5 shadow-sm">
          <h1 class="text-lg font-bold text-slate-900">{{ item.name_lo }}</h1>
          <p v-if="item.name_en" class="mt-1 text-sm text-slate-500">{{ item.name_en }}</p>
          <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
            <div>
              <div class="text-xs text-slate-400">ຄ່າບໍລິການ</div>
              <div class="text-xl font-bold text-teal-700">{{ fmt(item.price) }} ກີບ</div>
            </div>
            <div class="text-right text-xs text-slate-400">
              <div>{{ item.unit || 'ຕໍ່ໃບ' }}</div>
            </div>
          </div>
        </div>

        <div v-if="item.fields?.length" class="mt-4 rounded-xl bg-white p-5 shadow-sm">
          <h2 class="mb-3 text-sm font-semibold text-slate-700">ເອກະສານ/ຂໍ້ມູນທີ່ຕ້ອງກຽມ</h2>
          <ul class="list-inside list-disc space-y-1 text-sm text-slate-600">
            <li v-for="f in item.fields" :key="f.id">{{ f.field_label }} ({{ f.field_type }})</li>
          </ul>
        </div>

        <RouterLink
          v-if="implementedItemIds.includes(Number(item.id))"
  :to="{ name: 'apply', params: { itemId: item.id } }"
  class="mt-6 block rounded-lg bg-teal-700 py-3 text-center text-sm font-semibold text-white"
>
  ຂໍເອກະສານນີ້
</RouterLink>

<p
  v-else
  class="mt-6 rounded-lg bg-amber-100 px-4 py-3 text-center text-sm font-semibold text-amber-800"
>
  ກຳລັງພັດທະນາ
</p>
      </template>
    </div>
  </AppShell>
</template>

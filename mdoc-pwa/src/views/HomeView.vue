<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '../stores/auth'
import api, { ApiError } from '../api/client'
import AppShell from '../components/AppShell.vue'

const auth = useAuthStore()
const docTypes = ref([])
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
  try {
    docTypes.value = await api('doc-types')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດ'
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <AppShell>
    <!-- Header -->
    <header class="bg-teal-800 px-6 pb-10 pt-8 text-white">
      <p class="text-sm text-teal-200">ສະບາຍດີ,</p>
      <h1 class="text-xl font-bold">{{ auth.fullName || 'ຜູ້ໃຊ້' }}</h1>
      <p class="mt-2 text-sm text-teal-100">ຍິນດີຕ້ອນຮັບສູ່ລະບົບບໍລິການເອກະສານເອເລັກໂຕຣນິກ</p>
    </header>

    <!-- Quick stats -->
    <div class="mx-4 -mt-6 grid grid-cols-2 gap-3">
      <RouterLink to="/catalog" class="rounded-xl bg-white p-4 shadow-sm">
        <div class="text-2xl">📋</div>
        <div class="mt-1 text-sm font-semibold text-slate-800">ຂໍເອກະສານ</div>
        <div class="text-xs text-slate-500">ສ້າງຄຳຮ້ອງໃໝ່</div>
      </RouterLink>
      <RouterLink to="/my-docs" class="rounded-xl bg-white p-4 shadow-sm">
        <div class="text-2xl">📁</div>
        <div class="mt-1 text-sm font-semibold text-slate-800">ຕິດຕາມສະຖານະ</div>
        <div class="text-xs text-slate-500">ເບິ່ງຄຳຮ້ອງຂອງທ່ານ</div>
      </RouterLink>
    </div>

    <!-- Document types -->
    <section class="mx-4 mt-6">
      <h2 class="mb-3 text-sm font-semibold text-slate-700">ປະເພດເອກະສານ</h2>
      <p v-if="loading" class="py-6 text-center text-sm text-slate-400">ກຳລັງໂຫຼດ...</p>
      <p v-if="error" class="py-6 text-center text-sm text-red-600">{{ error }}</p>
      <div v-else class="grid grid-cols-2 gap-3">
        <RouterLink
          v-for="t in docTypes"
          :key="t.id"
          :to="{ name: 'catalog', query: { type: t.id } }"
          class="rounded-xl border border-slate-200 bg-white p-4"
        >
          <div class="text-sm font-medium text-slate-800">{{ t.name_lo }}</div>
          <div class="mt-0.5 text-xs text-slate-400">ປະເພດ {{ t.doc_type_code }}</div>
        </RouterLink>
      </div>
    </section>
  </AppShell>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import api, { ApiError } from '../api/client'
import ErrorBanner from '../components/ErrorBanner.vue'

const auth = useAuthStore()
const router = useRouter()

const form = ref({
  phone: '',
  password: '',
  full_name: '',
  village_id: null,
  id_card: '',
  dob: '',
  gender: '',
})
const hierarchy = ref({ provinces: [], districts: [], tassengs: [], villages: [] })
const selProvince = ref(null)
const selDistrict = ref(null)
const selTasseng = ref(null)
const error = ref(null)

async function loadHierarchy() {
  try {
    hierarchy.value = await api('hierarchy')
  } catch {
    // hierarchy optional at registration time
  }
}

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
  try {
    await auth.register({
      phone: form.value.phone.trim(),
      password: form.value.password,
      full_name: form.value.full_name.trim(),
      village_id: form.value.village_id ? Number(form.value.village_id) : null,
      id_card: form.value.id_card || null,
      dob: form.value.dob || null,
      gender: form.value.gender || null,
    })
    router.push('/')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດບໍ່ຮູ້ຈັກ'
  }
}

onMounted(loadHierarchy)
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center px-6 py-8">
    <h1 class="mb-6 text-xl font-bold text-teal-800">ລົງທະບຽນຜູ້ໃຊ້</h1>

    <form class="w-full max-w-sm space-y-4" @submit.prevent="onSubmit">
      <ErrorBanner :error="error" />
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">ຊື່ ແລະ ນາມສະກຸນ *</label>
        <input v-model="form.full_name" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm" required />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">ເບີໂທລະສັບ *</label>
        <input v-model="form.phone" type="tel" inputmode="numeric" placeholder="020XXXXXXXX" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm" required />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">ລະຫັດຜ່ານ (ຢ່າງໜ້ອຍ 8 ຕົວ) *</label>
        <input v-model="form.password" type="password" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm" required minlength="8" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">ເລກບັດປະຈຳຕົວ</label>
        <input v-model="form.id_card" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm" />
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">ວັນເດືອນປີເກີດ</label>
          <input v-model="form.dob" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm" />
        </div>
        <div>
          <label class="mb-1 block text-sm font-medium text-slate-700">ເພດ</label>
          <select v-model="form.gender" class="w-full rounded-lg border border-slate-300 px-3 py-2.5 text-sm">
            <option value="">—</option>
            <option value="M">ຊາຍ</option>
            <option value="F">ຍິງ</option>
          </select>
        </div>
      </div>

      <!-- Location cascade: Province → District → Taseng → Village -->
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">ແຂວງ</label>
        <select v-model="selProvince" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
          <option :value="null">— ເລືອກແຂວງ —</option>
          <option v-for="p in hierarchy.provinces" :key="p.id" :value="p.id">{{ p.name_lo }}</option>
        </select>
      </div>
      <div v-if="selProvince">
        <label class="mb-1 block text-sm font-medium text-slate-700">ເມືອງ</label>
        <select v-model="selDistrict" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
          <option :value="null">— ເລືອກເມືອງ —</option>
          <option v-for="d in filteredDistricts()" :key="d.id" :value="d.id">{{ d.name_lo }}</option>
        </select>
      </div>
      <div v-if="selDistrict">
        <label class="mb-1 block text-sm font-medium text-slate-700">ເມືອງຍ່ອຍ (ຕາແສງ)</label>
        <select v-model="selTasseng" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
          <option :value="null">— ເລືອກຕາແສງ —</option>
          <option v-for="t in filteredTassengs()" :key="t.id" :value="t.id">{{ t.name_lo }}</option>
        </select>
      </div>
      <div v-if="selTasseng">
        <label class="mb-1 block text-sm font-medium text-slate-700">ບ້ານ</label>
        <select v-model="form.village_id" class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm">
          <option :value="null">— ເລືອກບ້ານ —</option>
          <option v-for="v in filteredVillages()" :key="v.id" :value="v.id">{{ v.name_lo }}</option>
        </select>
      </div>

      <button type="submit" :disabled="auth.loading" class="w-full rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
        {{ auth.loading ? 'ກຳລັງບັນທຶກ...' : 'ລົງທະບຽນ' }}
      </button>
    </form>

    <p class="mt-6 text-sm text-slate-600">
      ມີບັນຊີແລ້ວ?
      <RouterLink to="/login" class="font-semibold text-teal-700">ເຂົ້າສູ່ລະບົບ</RouterLink>
    </p>
  </div>
</template>

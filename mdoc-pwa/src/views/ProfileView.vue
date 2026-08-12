<script setup>
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppShell from '../components/AppShell.vue'

const auth = useAuthStore()
const router = useRouter()

function logout() {
  auth.logout()
  router.push('/login')
}
</script>

<template>
  <AppShell>
    <header class="bg-teal-800 px-4 py-6 text-white">
      <h1 class="text-lg font-bold">ບັນຊີຂອງຂ້ອຍ</h1>
    </header>

    <div class="px-4 py-4">
      <div class="rounded-xl bg-white p-5 shadow-sm">
        <div class="flex items-center gap-4">
          <div class="flex h-14 w-14 items-center justify-center rounded-full bg-teal-100 text-2xl">
            👤
          </div>
          <div>
            <div class="text-base font-bold text-slate-900">{{ auth.fullName || '—' }}</div>
            <div class="text-sm text-slate-500">{{ auth.citizen?.phone }}</div>
          </div>
        </div>
        <dl class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-sm">
          <div class="flex justify-between">
            <dt class="text-slate-500">ເລກບັດປະຈຳຕົວ</dt>
            <dd class="font-medium">{{ auth.citizen?.id_card || '—' }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-slate-500">ອີເມວ</dt>
            <dd class="font-medium">{{ auth.citizen?.email || '—' }}</dd>
          </div>
          <div class="flex justify-between">
            <dt class="text-slate-500">ບ້ານ</dt>
            <dd class="font-medium">{{ auth.citizen?.village_name || '—' }}</dd>
          </div>
        </dl>
      </div>

      <button
        @click="logout"
        class="mt-6 w-full rounded-lg border border-red-200 bg-red-50 py-3 text-sm font-semibold text-red-700"
      >
        ອອກຈາກລະບົບ
      </button>
    </div>
  </AppShell>
</template>

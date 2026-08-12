<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { ApiError } from '../api/client'
import ErrorBanner from '../components/ErrorBanner.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()

const phone = ref('')
const password = ref('')
const error = ref(null)

async function onSubmit() {
  error.value = null
  try {
    await auth.login(phone.value.trim(), password.value)
    router.push(route.query.redirect || '/')
  } catch (e) {
    error.value = e instanceof ApiError ? e.message : 'ເກີດຂໍ້ຜິດພາດບໍ່ຮູ້ຈັກ'
  }
}
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center px-6">
    <div class="mb-8 text-center">
      <div class="text-5xl">📄</div>
      <h1 class="mt-3 text-2xl font-bold text-teal-800">mDoc</h1>
      <p class="mt-1 text-sm text-slate-500">ລະບົບເອກະສານເອເລັກໂຕຣນິກ</p>
    </div>

    <form class="w-full max-w-sm space-y-4" @submit.prevent="onSubmit">
      <ErrorBanner :error="error" />
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">ເບີໂທລະສັບ</label>
        <input
          v-model="phone"
          type="tel"
          inputmode="numeric"
          placeholder="020XXXXXXXX"
          class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-teal-500 focus:outline-none"
          required
        />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-slate-700">ລະຫັດຜ່ານ</label>
        <input
          v-model="password"
          type="password"
          class="w-full rounded-lg border border-slate-300 px-4 py-2.5 text-sm focus:border-teal-500 focus:outline-none"
          required
        />
      </div>
      <button
        type="submit"
        :disabled="auth.loading"
        class="w-full rounded-lg bg-teal-700 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
      >
        {{ auth.loading ? 'ກຳລັງເຂົ້າສູ່ລະບົບ...' : 'ເຂົ້າສູ່ລະບົບ' }}
      </button>
    </form>

    <p class="mt-6 text-sm text-slate-600">
      ຍັງບໍ່ມີບັນຊີ?
      <RouterLink to="/register" class="font-semibold text-teal-700">ລົງທະບຽນ</RouterLink>
    </p>
  </div>
</template>

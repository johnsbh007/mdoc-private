<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const showNav = computed(() => !['login', 'register'].includes(route.name))
</script>

<template>
  <div class="min-h-screen bg-slate-50">
    <main class="mx-auto max-w-md pb-24">
      <slot />
    </main>

    <!-- Bottom navigation (mobile app shell) -->
    <nav
      v-if="showNav"
      class="pb-safe fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white"
    >
      <div class="mx-auto flex max-w-md items-center justify-around">
        <RouterLink
          to="/"
          class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px]"
          :class="route.name === 'home' ? 'text-teal-700 font-semibold' : 'text-slate-500'"
        >
          <span class="text-lg">🏠</span>
          ໜ້າຫຼັກ
        </RouterLink>
        <RouterLink
          to="/catalog"
          class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px]"
          :class="route.name === 'catalog' ? 'text-teal-700 font-semibold' : 'text-slate-500'"
        >
          <span class="text-lg">📋</span>
          ບໍລິການ
        </RouterLink>
        <RouterLink
          to="/my-docs"
          class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px]"
          :class="['my-docs', 'doc-detail'].includes(route.name) ? 'text-teal-700 font-semibold' : 'text-slate-500'"
        >
          <span class="text-lg">📁</span>
          ຄຳຮ້ອງຂອງຂ້ອຍ
        </RouterLink>
        <RouterLink
          to="/profile"
          class="flex flex-1 flex-col items-center gap-0.5 py-2 text-[11px]"
          :class="route.name === 'profile' ? 'text-teal-700 font-semibold' : 'text-slate-500'"
        >
          <span class="text-lg">👤</span>
          ບັນຊີ
        </RouterLink>
      </div>
    </nav>
  </div>
</template>

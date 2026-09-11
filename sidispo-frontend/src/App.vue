<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotifikasiStore } from '@/stores/notifikasi'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppTopbar from '@/components/layout/AppTopbar.vue'
import Toast from 'primevue/toast'

const route = useRoute()
const authStore = useAuthStore()
const notifStore = useNotifikasiStore()

// Default: terbuka di desktop (atau sesuai localStorage), tertutup di mobile
const getInitialSidebarState = () => {
  if (typeof window === 'undefined') return true
  if (window.innerWidth < 768) return false
  const saved = localStorage.getItem('sidispo_sidebar_open')
  return saved !== null ? saved === 'true' : true
}

const sidebarOpen = ref(getInitialSidebarState())

const toggleSidebar = () => {
  sidebarOpen.value = !sidebarOpen.value
  if (typeof window !== 'undefined' && window.innerWidth >= 768) {
    localStorage.setItem('sidispo_sidebar_open', String(sidebarOpen.value))
  }
}

// Hanya tutup sidebar secara otomatis di HP/mobile setiap kali rute berpindah
watch(() => route.fullPath, () => {
  if (typeof window !== 'undefined' && window.innerWidth < 768) {
    sidebarOpen.value = false
  }
})

const isAuthRoute = computed(() => route.name === 'login')
const isAuthenticated = computed(() => !!authStore.token)

let notifInterval = null

const startPolling = () => {
  if (!isAuthenticated.value) return
  notifStore.fetchList()
  if (!notifInterval) {
    notifInterval = setInterval(() => notifStore.fetchList(), 30000)
  }
}

const stopPolling = () => {
  if (notifInterval) {
    clearInterval(notifInterval)
    notifInterval = null
  }
}

const onKeyDown = (e) => {
  if (e.key === 'Escape' && sidebarOpen.value && typeof window !== 'undefined' && window.innerWidth < 768) {
    sidebarOpen.value = false
  }
}

onMounted(() => {
  startPolling()
  window.addEventListener('keydown', onKeyDown)
})
onUnmounted(() => {
  stopPolling()
  window.removeEventListener('keydown', onKeyDown)
})
watch(isAuthenticated, (v) => (v ? startPolling() : stopPolling()))
</script>

<template>
  <Toast position="top-right" />

  <div v-if="isAuthRoute" class="w-full h-screen overflow-hidden">
    <RouterView />
  </div>

  <div v-else class="flex w-full h-screen overflow-hidden relative">
    <!-- Backdrop overlay untuk mobile sidebar saja -->
    <transition
      enter-active-class="transition-opacity duration-300 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="sidebarOpen"
        class="sidebar-backdrop"
        @click="sidebarOpen = false"
      />
    </transition>

    <AppSidebar :open="sidebarOpen" @close="sidebarOpen = false" />
    <div class="app-main-bg transition-all duration-300">
      <AppTopbar @toggle-sidebar="toggleSidebar" />
      <RouterView />
    </div>
  </div>
</template>

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

const sidebarOpen = ref(false)

// Tutup sidebar mobile secara otomatis setiap kali rute berpindah
watch(() => route.fullPath, () => {
  sidebarOpen.value = false
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

onMounted(() => startPolling())
onUnmounted(() => stopPolling())
watch(isAuthenticated, (v) => (v ? startPolling() : stopPolling()))
</script>

<template>
  <Toast position="top-right" />

  <div v-if="isAuthRoute" class="w-full h-screen overflow-hidden">
    <RouterView />
  </div>

  <div v-else class="flex w-full h-screen overflow-hidden relative">
    <!-- Backdrop overlay untuk mobile sidebar -->
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
    <div class="app-main-bg">
      <AppTopbar @toggle-sidebar="sidebarOpen = !sidebarOpen" />
      <RouterView />
    </div>
  </div>
</template>

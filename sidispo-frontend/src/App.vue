<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotifikasiStore } from '@/stores/notifikasi'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppTopbar from '@/components/layout/AppTopbar.vue'

const route = useRoute()
const authStore = useAuthStore()
const notifStore = useNotifikasiStore()

const isAuthRoute = computed(() => route.name === 'login')
const isAuthenticated = computed(() => !!authStore.token)

// Polling interval reference
let notifInterval = null

const startPolling = () => {
  if (!isAuthenticated.value) return
  notifStore.fetchList()
  if (!notifInterval) {
    notifInterval = setInterval(() => {
      notifStore.fetchList()
    }, 30000) // Poll every 30 seconds
  }
}

const stopPolling = () => {
  if (notifInterval) {
    clearInterval(notifInterval)
    notifInterval = null
  }
}

onMounted(() => {
  startPolling()
})

onUnmounted(() => {
  stopPolling()
})

// Restart or stop polling when auth state changes
watch(isAuthenticated, (newVal) => {
  if (newVal) startPolling()
  else stopPolling()
})
</script>

<template>
  <!-- Login page — full width, no sidebar -->
  <div v-if="isAuthRoute" class="w-full h-screen bg-bg text-textMain font-sans overflow-hidden">
    <RouterView />
  </div>

  <!-- Main app layout -->
  <div v-else class="flex w-full h-screen bg-bg text-textMain font-sans overflow-hidden">
    <AppSidebar />
    <main class="flex-1 flex flex-col h-screen overflow-hidden">
      <AppTopbar />
      <RouterView />
    </main>
  </div>
</template>

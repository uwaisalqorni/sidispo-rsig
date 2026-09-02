<script setup>
import { computed, onMounted, onUnmounted, watch } from 'vue'
import { RouterView, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotifikasiStore } from '@/stores/notifikasi'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppTopbar from '@/components/layout/AppTopbar.vue'
import Toast from 'primevue/toast'

const route = useRoute()
const authStore = useAuthStore()
const notifStore = useNotifikasiStore()

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

  <div v-else class="flex w-full h-screen overflow-hidden">
    <AppSidebar />
    <div class="app-main-bg">
      <AppTopbar />
      <RouterView />
    </div>
  </div>
</template>

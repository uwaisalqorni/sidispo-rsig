<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotifikasiStore } from '@/stores/notifikasi'

const route  = useRoute()
const router = useRouter()
const notifStore = useNotifikasiStore()
const showNotif  = ref(false)

const pageTitle = computed(() => {
  const map = {
    'dashboard':       'Dashboard',
    'disposisi':       'Disposisi',
    'disposisi-detail':'Detail Disposisi',
    'surat-masuk':     'Surat Masuk',
    'selesai':         'Disposisi Selesai',
    'overdue':         'Disposisi Overdue',
  }
  return map[route.name] || 'SiDispo'
})

onMounted(() => notifStore.fetchList())

const toggleNotif = () => { showNotif.value = !showNotif.value }

const handleMarkRead = async (id) => { await notifStore.markRead(id) }
const handleMarkAll  = async ()     => { await notifStore.markAllRead() }

const goDisposisi = (notif) => {
  showNotif.value = false
  if (notif.disposisi_id) router.push({ name: 'disposisi-detail', params: { id: notif.disposisi_id } })
  if (!notif.dibaca_at) notifStore.markRead(notif.id)
}

function timeAgo(ts) {
  if (!ts) return ''
  const diff = Math.floor((Date.now() - new Date(ts)) / 60000)
  if (diff < 60) return `${diff}m lalu`
  if (diff < 1440) return `${Math.floor(diff/60)}j lalu`
  return `${Math.floor(diff/1440)}h lalu`
}
</script>

<template>
  <!-- Top bar: white bg, green left accent border, green hover states -->
  <div class="h-14 bg-surface border-b border-border flex items-center px-6 gap-4 shrink-0 z-10 relative shadow-sm">
    
    <!-- Page title with green accent left bar -->
    <div class="flex items-center gap-2.5">
      <div class="w-1 h-5 rounded-full bg-accent"></div>
      <div class="text-[15px] font-bold text-textMain">{{ pageTitle }}</div>
    </div>
    <div class="flex-1"></div>

    <!-- Search bar -->
    <div class="hidden md:flex items-center gap-2 bg-surface2 border border-border rounded-lg py-1.5 px-3 text-[13px] text-textMuted cursor-pointer transition-all hover:border-accent hover:text-textMain">
      <span>🔍</span>
      <span>&nbsp;Cari surat, perihal...</span>
      <span class="ml-2 text-[11px] text-textDim bg-surface3 px-1.5 py-0.5 rounded border border-border">Ctrl K</span>
    </div>

    <!-- Notification Bell -->
    <div class="relative">
      <button @click="toggleNotif"
        class="relative w-9 h-9 bg-surface2 border border-border rounded-lg flex items-center justify-center cursor-pointer text-base transition-all hover:border-accent hover:bg-accentGlow"
        :class="showNotif ? 'border-accent bg-accentGlow' : ''">
        🔔
        <span v-if="notifStore.unread > 0"
          class="absolute -top-1 -right-1 min-w-[17px] h-[17px] rounded-full bg-brandRed border-2 border-surface flex items-center justify-center text-[9px] font-bold text-white px-0.5">
          {{ notifStore.unread > 9 ? '9+' : notifStore.unread }}
        </span>
      </button>

      <!-- Dropdown -->
      <div v-if="showNotif"
        class="absolute right-0 top-11 w-[340px] bg-surface border border-border rounded-xl shadow-xl overflow-hidden z-50 animate-[fadeIn_0.15s_ease]">
        <div class="p-3 px-4 border-b border-border flex justify-between items-center bg-surface2">
          <span class="text-sm font-bold text-textMain">Notifikasi</span>
          <button v-if="notifStore.unread > 0" @click="handleMarkAll"
            class="text-xs text-accent hover:underline font-semibold">Tandai semua dibaca</button>
        </div>
        <div class="max-h-[320px] overflow-y-auto">
          <div v-if="notifStore.loading" class="p-4 text-sm text-textMuted text-center">Memuat...</div>
          <div v-else-if="notifStore.list.length === 0" class="p-6 text-sm text-textMuted text-center">Tidak ada notifikasi.</div>
          <div v-else v-for="n in notifStore.list" :key="n.id"
            @click="goDisposisi(n)"
            class="flex gap-3 p-3.5 px-4 border-b border-border cursor-pointer hover:bg-surface2 transition-all last:border-b-0"
            :class="!n.dibaca_at ? 'bg-accentGlow/40' : ''">
            <div class="w-2 h-2 rounded-full shrink-0 mt-[5px]"
              :class="!n.dibaca_at ? 'bg-accent' : 'bg-border'"></div>
            <div class="flex-1 min-w-0">
              <div class="text-[12.5px] font-semibold leading-snug text-textMain">{{ n.judul }}</div>
              <div class="text-[11.5px] text-textMuted mt-0.5 leading-snug">{{ n.pesan }}</div>
              <div class="text-[10.5px] text-textDim mt-1">{{ timeAgo(n.created_at) }}</div>
            </div>
            <button v-if="!n.dibaca_at" @click.stop="handleMarkRead(n.id)"
              class="text-[10px] text-accent hover:underline shrink-0 mt-0.5 font-bold">✓</button>
          </div>
        </div>
      </div>
    </div>

    <!-- Overlay -->
    <div v-if="showNotif" class="fixed inset-0 z-40" @click="showNotif = false"></div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotifikasiStore } from '@/stores/notifikasi'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'

const auth = useAuthStore()
const notifStore = useNotifikasiStore()
const router = useRouter()
const route  = useRoute()
const { user } = storeToRefs(auth)

const isAdmin = computed(() => user.value?.role === 'ADMIN')
const isSekretariat = computed(() => ['ADMIN', 'DIREKTUR'].includes(user.value?.role))

const menuItems = computed(() => {
  const items = [
    { label: 'Dashboard',  icon: '🏠',  id: 'dashboard',  to: { path: '/' } },
    { label: 'Surat Masuk', icon: '📥', id: 'surat-masuk', to: { path: '/surat-masuk' } },
    { label: 'Disposisi',  icon: '📤',  id: 'disposisi',  to: { path: '/disposisi' } },
    { label: 'Selesai',    icon: '✅',  id: 'selesai',    to: { path: '/disposisi', query: { tab: 'Selesai' } } },
    { label: 'Overdue',    icon: '⚠️', id: 'overdue',    to: { path: '/disposisi', query: { tab: 'Overdue' } } },
    { label: 'Dashboard RTL', icon: '📊', id: 'dashboard-rtl', to: { path: '/dashboard-rtl' } },
    { label: 'Daftar RTL', icon: '📋',  id: 'rtl',        to: { path: '/rtl' } },
  ]
  return isSekretariat.value ? items : items.filter(i => i.id !== 'surat-masuk')
})

// Custom isActive: tab-based routes need extra check
const isMenuActive = (item) => {
  if (item.id === 'selesai') return route.path === '/disposisi' && route.query.tab === 'Selesai'
  if (item.id === 'overdue') return route.path === '/disposisi' && route.query.tab === 'Overdue'
  if (item.id === 'disposisi') return route.path === '/disposisi' && !route.query.tab
  if (item.id === 'dashboard') return route.path === '/'
  if (item.id === 'dashboard-rtl') return route.path === '/dashboard-rtl'
  return route.path.startsWith('/' + item.id)
}

// Load folders dari API
const folders = ref([])
const loadingFolders = ref(false)

const parentFolders = computed(() => folders.value.filter(f => !f.parent_id))
const childOf = (parentId) => folders.value.filter(f => f.parent_id == parentId)

onMounted(async () => {
  loadingFolders.value = true
  try {
    const { data } = await api.get('/folder')
    folders.value = data.data || []
  } catch { /* silent */ } finally {
    loadingFolders.value = false
  }
})

const userInitials = computed(() => {
  const parts = (user.value?.nama_lengkap || '').split(' ')
  return parts.slice(0, 2).map(p => p[0]?.toUpperCase()).join('')
})

// Show overdue badge from notif store
const overdueBadge = computed(() => notifStore.list.filter(n => n.jenis === 'OVERDUE' && !n.dibaca_at).length || null)

const adminMenuItems = [
  { label: 'Pengguna', icon: '👥', id: 'admin-users', to: '/admin/users' },
  { label: 'Folder', icon: '📁', id: 'admin-folders', to: '/admin/folders' },
  { label: 'Konfigurasi', icon: '⚙️', id: 'admin-settings', to: '/admin/settings' },
]
</script>

<template>
  <aside class="w-[260px] min-w-[260px] bg-surface border-r border-border flex flex-col h-screen overflow-y-auto shadow-sm">
    
    <!-- Logo RSI -->
    <div class="p-4 pb-3 border-b border-border bg-gradient-to-r from-surface to-surface2">
      <div class="flex items-center gap-3">
        <img src="/src/assets/logorsi.png" alt="RSI Logo" class="w-10 h-10 object-contain drop-shadow-sm" />
        <div>
          <div class="text-base font-extrabold tracking-tight text-textMain">SiDispo</div>
          <div class="text-[10px] text-textMuted font-medium tracking-wide leading-tight">Sistem Disposisi Digital</div>
        </div>
      </div>
    </div>

    <!-- Menu Navigasi -->
    <div class="p-4 px-3 pt-4 pb-2">
      <div class="text-[10px] font-bold tracking-widest text-textDim uppercase px-2 mb-2">Menu Utama</div>
      <div
        v-for="item in menuItems" :key="item.id"
        @click="router.push(item.to)"
        class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg cursor-pointer text-[13.5px] font-medium transition-all duration-150 mb-0.5"
        :class="isMenuActive(item)
          ? 'bg-accentGlow text-accent border border-accent/20 font-semibold'
          : 'text-textMuted hover:bg-surface2 hover:text-textMain'"
      >
        <span class="text-[15px] w-5 text-center leading-none">{{ item.icon }}</span>
        {{ item.label }}
        <span v-if="item.id === 'overdue' && overdueBadge"
          class="ml-auto text-white text-[10px] font-bold px-1.5 py-[1px] rounded-full min-w-[18px] text-center bg-brandRed">
          {{ overdueBadge }}
        </span>
      </div>
    </div>

    <!-- Folder (Hanya Sekretariat/Admin/Direktur) -->
    <template v-if="isSekretariat">
      <div class="p-4 px-3 pt-3 pb-1">
        <div class="text-[10px] font-bold tracking-widest text-textDim uppercase px-2 mb-2">Folder</div>
      </div>
      <div class="px-3 pb-3 flex-1">
        <!-- Loading skeleton -->
        <div v-if="loadingFolders" class="flex flex-col gap-1 px-2">
          <div v-for="i in 3" :key="i" class="h-3 bg-surface3 rounded animate-pulse"></div>
        </div>

        <!-- Kosong -->
        <div v-else-if="folders.length === 0" class="px-2.5 py-2 text-[11px] text-textDim italic">
          Belum ada folder.
        </div>

        <!-- Parent folders + anak -->
        <template v-else v-for="parent in parentFolders" :key="parent.id">
          <!-- Parent -->
          <div
            @click="router.push({ path: '/surat-masuk', query: { folder: parent.id } })"
            class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg cursor-pointer text-[12.5px] transition-all duration-150 mb-[1px]"
            :class="route.query.folder == parent.id
              ? 'bg-accentGlow text-accent border border-accent/20 font-semibold'
              : 'text-textMuted hover:bg-surface2 hover:text-textMain'"
          >
            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ background: parent.warna || '#4a9e4a' }"></span>
            <span class="truncate font-medium">{{ parent.nama }}</span>
          </div>
          <!-- Children -->
          <div
            v-for="child in childOf(parent.id)" :key="child.id"
            @click="router.push({ path: '/surat-masuk', query: { folder: child.id } })"
            class="flex items-center gap-2 pl-6 pr-2.5 py-1.5 rounded-lg cursor-pointer text-[12px] transition-all duration-150 mb-[1px]"
            :class="route.query.folder == child.id
              ? 'bg-accentGlow text-accent border border-accent/20 font-semibold'
              : 'text-textMuted hover:bg-surface2 hover:text-textMain'"
          >
            <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: child.warna || '#4a9e4a' }"></span>
            <span class="truncate">{{ child.nama }}</span>
          </div>
        </template>

        <!-- Tambah Folder — hanya Admin -->
        <RouterLink v-if="isAdmin" to="/admin/folders"
          class="flex items-center gap-2 px-2.5 py-1.5 rounded-lg cursor-pointer text-[12.5px] text-accent hover:bg-accentGlow transition-all duration-150 mt-1 font-semibold">
          <span class="text-[13px]">➕</span>
          Kelola Folder
        </RouterLink>
      </div>
    </template>

    <!-- Admin Panel (hanya ADMIN) -->
    <div v-if="isAdmin" class="px-3 pt-3 pb-2">
      <div class="text-[10px] font-bold tracking-widest text-textDim uppercase px-2 mb-2">Admin Panel</div>
      <RouterLink 
        v-for="item in adminMenuItems" :key="item.id"
        :to="item.to"
        custom
        v-slot="{ navigate, isActive }"
      >
        <div 
          @click="navigate"
          class="flex items-center gap-2.5 px-3 py-2.5 rounded-lg cursor-pointer text-[13.5px] font-medium transition-all duration-150 mb-0.5"
          :class="isActive 
            ? 'bg-accentGlow text-accent border border-accent/20 font-semibold' 
            : 'text-textMuted hover:bg-surface2 hover:text-textMain'"
        >
          <span class="text-[15px] w-5 text-center leading-none">{{ item.icon }}</span>
          {{ item.label }}
        </div>
      </RouterLink>
    </div>

    <!-- User Profile + Logout -->
    <div class="px-4 py-3.5 border-t border-border bg-surface2">
      <div class="flex items-center gap-2.5 mb-2.5">
        <div class="w-[36px] h-[36px] rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center font-bold text-[13px] text-white shrink-0 shadow-sm">
          {{ userInitials }}
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-[13px] font-semibold truncate text-textMain">{{ user?.nama_lengkap }}</div>
          <div class="text-[11px] text-textMuted">{{ user?.jabatan ?? user?.role }}</div>
        </div>
      </div>
      <button @click="auth.logout()"
        class="w-full text-xs font-semibold flex items-center justify-center gap-1.5 py-2 rounded-lg border border-brandRed/30 text-brandRed hover:bg-brandRedBg transition-all">
        🚪 Keluar
      </button>
    </div>
  </aside>
</template>

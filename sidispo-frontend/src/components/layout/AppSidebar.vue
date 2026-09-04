<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useNotifikasiStore } from '@/stores/notifikasi'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'
import Menu from 'primevue/menu'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import Divider from 'primevue/divider'
import Skeleton from 'primevue/skeleton'
import Badge from 'primevue/badge'

const auth = useAuthStore()
const notifStore = useNotifikasiStore()
const router = useRouter()
const route  = useRoute()
const { user } = storeToRefs(auth)

const isAdmin = computed(() => user.value?.role === 'ADMIN')
const isSekretariat = computed(() => ['ADMIN', 'DIREKTUR'].includes(user.value?.role))

const folders = ref([])
const loadingFolders = ref(false)

const parentFolders = computed(() => folders.value.filter(f => !f.parent_id))
const childOf = (parentId) => folders.value.filter(f => f.parent_id == parentId)

const overdueBadge = computed(() =>
  notifStore.list.filter(n => n.jenis === 'OVERDUE' && !n.dibaca_at).length || 0
)

const userInitials = computed(() => {
  const parts = (user.value?.nama_lengkap || '').split(' ')
  return parts.slice(0, 2).map(p => p[0]?.toUpperCase()).join('')
})

const fotoProfilUrl = computed(() => auth.fotoProfilUrl)

const isMenuActive = (item) => {
  if (item.id === 'selesai') return route.path === '/disposisi' && route.query.tab === 'Selesai'
  if (item.id === 'overdue') return route.path === '/disposisi' && route.query.tab === 'Overdue'
  if (item.id === 'disposisi') return route.path === '/disposisi' && !route.query.tab
  if (item.id === 'dashboard') return route.path === '/'
  if (item.id === 'dashboard-rtl') return route.path === '/dashboard-rtl'
  return route.path.startsWith('/' + item.id)
}

const mainMenuItems = computed(() => {
  const items = [
    { label: 'Dashboard', icon: 'pi pi-home', id: 'dashboard', to: { path: '/' } },
    { label: 'Surat Masuk', icon: 'pi pi-inbox', id: 'surat-masuk', to: { path: '/surat-masuk' } },
    { label: 'Disposisi', icon: 'pi pi-send', id: 'disposisi', to: { path: '/disposisi' } },
    { label: 'Selesai', icon: 'pi pi-check-circle', id: 'selesai', to: { path: '/disposisi', query: { tab: 'Selesai' } } },
    { label: 'Overdue', icon: 'pi pi-exclamation-triangle', id: 'overdue', to: { path: '/disposisi', query: { tab: 'Overdue' } } },
    { label: 'Dashboard RTL', icon: 'pi pi-chart-bar', id: 'dashboard-rtl', to: { path: '/dashboard-rtl' } },
    { label: 'Daftar RTL', icon: 'pi pi-list', id: 'rtl', to: { path: '/rtl' } },
  ]
  const filtered = isSekretariat.value ? items : items.filter(i => i.id !== 'surat-masuk')
  return filtered.map(item => ({
    label: item.label,
    icon: item.icon,
    class: isMenuActive(item) ? 'sidispo-menu-active' : '',
    command: () => router.push(item.to),
  }))
})

const adminMenuItems = computed(() => [
  { label: 'Pengguna', icon: 'pi pi-users', command: () => router.push('/admin/users') },
  { label: 'Folder', icon: 'pi pi-folder', command: () => router.push('/admin/folders') },
  { label: 'Master Perihal', icon: 'pi pi-book', command: () => router.push('/admin/perihal') },
  { label: 'Master Asal Surat', icon: 'pi pi-building', command: () => router.push('/admin/asal-surat') },
  { label: 'Konfigurasi', icon: 'pi pi-cog', command: () => router.push('/admin/settings') },
])

onMounted(async () => {
  loadingFolders.value = true
  try {
    const { data } = await api.get('/folder')
    folders.value = data.data || []
  } catch { /* silent */ } finally {
    loadingFolders.value = false
  }
})

const goFolder = (id) => router.push({ path: '/surat-masuk', query: { folder: id } })
</script>

<template>
  <aside class="w-[272px] min-w-[272px] flex flex-col h-screen overflow-hidden bg-gradient-to-b from-sidebar-dark via-sidebar to-sidebar-light shadow-sidebar text-white">

    <!-- Brand -->
    <div class="p-5 border-b border-white/10">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-sidebar-accent to-emerald-300 flex items-center justify-center shadow-glow animate-float">
          <i class="pi pi-building text-sidebar-dark text-xl font-bold"></i>
        </div>
        <div>
          <div class="text-lg font-extrabold tracking-tight text-white">SiDispo</div>
          <div class="text-[10px] text-sidebar-accent font-semibold tracking-widest uppercase">RSI Gondanglegi</div>
        </div>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto py-4 px-3 sidispo-scroll">
      <div class="section-label !text-sidebar-accent/80">Menu Utama</div>
      <div class="sidispo-menu-panel">
        <Menu :model="mainMenuItems" class="sidispo-sidebar-menu border-0 w-full" />
      </div>

      <!-- Overdue badge floating on menu area -->
      <div v-if="overdueBadge" class="mx-2 mt-1 mb-2">
        <div
          class="flex items-center gap-2 px-3 py-2 rounded-xl bg-red-500/20 border border-red-400/30 cursor-pointer hover:bg-red-500/30 transition-all"
          @click="router.push({ path: '/disposisi', query: { tab: 'Overdue' } })"
        >
          <i class="pi pi-exclamation-triangle text-red-300 text-sm"></i>
          <span class="text-xs font-semibold text-red-200">{{ overdueBadge }} disposisi overdue</span>
          <Badge :value="overdueBadge" severity="danger" class="ml-auto" />
        </div>
      </div>

      <template v-if="isSekretariat">
        <Divider class="!my-3 !border-white/10" />
        <div class="section-label !text-sidebar-accent/80">Folder</div>

        <div v-if="loadingFolders" class="px-2 flex flex-col gap-2">
          <Skeleton v-for="i in 3" :key="i" height="1.75rem" class="rounded-lg !bg-white/10" />
        </div>

        <div v-else-if="folders.length === 0" class="px-3 text-xs text-white/40 italic">
          Belum ada folder.
        </div>

        <div v-else class="flex flex-col gap-0.5">
          <template v-for="parent in parentFolders" :key="parent.id">
            <button
              class="sidispo-folder-btn"
              :class="{ active: route.query.folder == parent.id }"
              @click="goFolder(parent.id)"
            >
              <span class="w-3 h-3 rounded-full shrink-0 ring-2 ring-white/20" :style="{ background: parent.warna || '#52b788' }"></span>
              <span class="truncate">{{ parent.nama }}</span>
            </button>
            <button
              v-for="child in childOf(parent.id)"
              :key="child.id"
              class="sidispo-folder-btn pl-7"
              :class="{ active: route.query.folder == child.id }"
              @click="goFolder(child.id)"
            >
              <span class="w-2 h-2 rounded-full shrink-0" :style="{ background: child.warna || '#52b788' }"></span>
              <span class="truncate text-sm">{{ child.nama }}</span>
            </button>
          </template>

          <button
            v-if="isAdmin"
            class="sidispo-folder-btn text-sidebar-accent mt-1"
            @click="router.push('/admin/folders')"
          >
            <i class="pi pi-plus text-xs"></i>
            <span>Kelola Folder</span>
          </button>
        </div>
      </template>

      <template v-if="isAdmin">
        <Divider class="!my-3 !border-white/10" />
        <div class="section-label !text-amber-300/80">Admin Panel</div>
        <div class="sidispo-menu-panel sidispo-menu-panel-admin">
          <Menu :model="adminMenuItems" class="sidispo-sidebar-menu sidispo-admin-menu border-0 w-full" />
        </div>
      </template>
    </div>

    <!-- User footer -->
    <div class="p-4 border-t border-white/10 bg-black/20">
      <div
        class="flex items-center gap-3 mb-3 p-2 rounded-xl bg-white/5 cursor-pointer hover:bg-white/10 transition-all"
        @click="router.push({ name: 'profile' })"
        title="Profil Saya"
      >
        <div class="w-9 h-9 rounded-full overflow-hidden shrink-0">
          <img
            v-if="fotoProfilUrl"
            :src="fotoProfilUrl"
            alt="Foto Profil"
            class="w-full h-full object-cover"
          />
          <Avatar v-else :label="userInitials" shape="circle" class="!bg-gradient-to-br from-sidebar-accent to-emerald-400 !text-sidebar-dark font-bold !w-9 !h-9" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-semibold truncate text-white">{{ user?.nama_lengkap }}</div>
          <div class="text-[11px] text-sidebar-accent font-medium">{{ user?.jabatan ?? user?.role }}</div>
        </div>
        <i class="pi pi-chevron-right text-white/30 text-xs"></i>
      </div>
      <Button
        label="Keluar"
        icon="pi pi-sign-out"
        severity="danger"
        size="small"
        class="w-full !bg-red-500/20 !border-red-400/40 !text-red-200 hover:!bg-red-500/40"
        @click="auth.logout()"
      />
    </div>
  </aside>
</template>

<style scoped>
.sidispo-menu-panel {
  @apply rounded-xl p-1.5 border border-white/10;
  background: rgba(0, 0, 0, 0.18);
}

.sidispo-menu-panel-admin {
  @apply border-amber-400/20;
  background: rgba(0, 0, 0, 0.22);
}

.sidispo-folder-btn {
  @apply flex items-center gap-2.5 w-full px-3 py-2 rounded-xl text-sm font-medium transition-all duration-150 text-left;
  color: #d8f3dc;
}
.sidispo-folder-btn:hover {
  @apply bg-white/10;
  color: #ffffff;
}
.sidispo-folder-btn.active {
  @apply font-semibold border;
  background: rgba(82, 183, 136, 0.22);
  color: #b7e4c7;
  border-color: rgba(82, 183, 136, 0.4);
}

/* ── PrimeVue Menu: hapus background putih, teks jelas di sidebar gelap ── */
:deep(.sidispo-sidebar-menu) {
  background: transparent !important;
  border: none !important;
  box-shadow: none !important;
}

:deep(.sidispo-sidebar-menu .p-menu-list) {
  padding: 0;
  gap: 3px;
  background: transparent !important;
  border: none !important;
}

:deep(.sidispo-sidebar-menu .p-menu-item-content) {
  border-radius: 0.75rem;
  transition: all 0.15s ease;
  background: transparent !important;
}

:deep(.sidispo-sidebar-menu .p-menu-item-link) {
  padding: 0.65rem 0.85rem;
  font-size: 0.875rem;
  font-weight: 500;
  color: #d8f3dc !important;
  background: transparent !important;
}

:deep(.sidispo-sidebar-menu .p-menu-item-label) {
  color: #d8f3dc !important;
}

:deep(.sidispo-sidebar-menu .p-menu-item-icon) {
  color: #74c69d !important;
  font-size: 1rem;
}

:deep(.sidispo-sidebar-menu .p-menu-item:not(.sidispo-menu-active) > .p-menu-item-content:hover) {
  background: rgba(255, 255, 255, 0.1) !important;
}

:deep(.sidispo-sidebar-menu .p-menu-item:not(.sidispo-menu-active):hover .p-menu-item-link),
:deep(.sidispo-sidebar-menu .p-menu-item:not(.sidispo-menu-active):hover .p-menu-item-label) {
  color: #ffffff !important;
}

:deep(.sidispo-sidebar-menu .sidispo-menu-active > .p-menu-item-content) {
  background: linear-gradient(90deg, rgba(82, 183, 136, 0.35) 0%, rgba(82, 183, 136, 0.12) 100%) !important;
  border: 1px solid rgba(82, 183, 136, 0.45);
}

:deep(.sidispo-sidebar-menu .sidispo-menu-active .p-menu-item-link),
:deep(.sidispo-sidebar-menu .sidispo-menu-active .p-menu-item-label) {
  color: #ffffff !important;
  font-weight: 600;
}

:deep(.sidispo-sidebar-menu .sidispo-menu-active .p-menu-item-icon) {
  color: #b7e4c7 !important;
}

:deep(.sidispo-admin-menu .p-menu-item-icon) {
  color: #fbbf24 !important;
}

:deep(.sidispo-admin-menu .sidispo-menu-active .p-menu-item-icon) {
  color: #fde68a !important;
}

.sidispo-scroll::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.15); }
</style>

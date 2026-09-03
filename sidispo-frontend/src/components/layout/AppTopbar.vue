<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotifikasiStore } from '@/stores/notifikasi'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import Toolbar from 'primevue/toolbar'
import Button from 'primevue/button'
import OverlayPanel from 'primevue/overlaypanel'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import InputText from 'primevue/inputtext'
import Avatar from 'primevue/avatar'

const route  = useRoute()
const router = useRouter()
const notifStore = useNotifikasiStore()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const notifPanel = ref()

const pageMeta = computed(() => {
  const map = {
    dashboard:       { title: 'Dashboard', icon: 'pi pi-home', color: 'from-emerald-500 to-green-600' },
    disposisi:       { title: 'Disposisi', icon: 'pi pi-send', color: 'from-blue-500 to-indigo-600' },
    'disposisi-detail': { title: 'Detail Disposisi', icon: 'pi pi-file', color: 'from-violet-500 to-purple-600' },
    'surat-masuk':   { title: 'Surat Masuk', icon: 'pi pi-inbox', color: 'from-cyan-500 to-teal-600' },
    rtl:             { title: 'Daftar RTL', icon: 'pi pi-list', color: 'from-orange-500 to-amber-600' },
    'rtl-detail':    { title: 'Detail RTL', icon: 'pi pi-file-edit', color: 'from-orange-500 to-amber-600' },
    'dashboard-rtl': { title: 'Dashboard RTL', icon: 'pi pi-chart-bar', color: 'from-pink-500 to-rose-600' },
    profile:         { title: 'Profil Saya', icon: 'pi pi-user', color: 'from-teal-500 to-cyan-600' },
    'admin-users':   { title: 'Kelola Pengguna', icon: 'pi pi-users', color: 'from-slate-500 to-gray-600' },
    'admin-folders': { title: 'Kelola Folder', icon: 'pi pi-folder', color: 'from-yellow-500 to-orange-500' },
    'admin-perihal': { title: 'Master Perihal', icon: 'pi pi-book', color: 'from-indigo-500 to-blue-600' },
    'admin-settings':{ title: 'Konfigurasi', icon: 'pi pi-cog', color: 'from-gray-500 to-slate-600' },
  }
  return map[route.name] || { title: 'SiDispo', icon: 'pi pi-building', color: 'from-emerald-500 to-green-600' }
})

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Selamat pagi'
  if (h < 17) return 'Selamat siang'
  return 'Selamat malam'
})

onMounted(() => notifStore.fetchList())

const toggleNotif = (event) => notifPanel.value.toggle(event)

const goDisposisi = (notif) => {
  notifPanel.value.hide()
  if (notif.disposisi_id) router.push({ name: 'disposisi-detail', params: { id: notif.disposisi_id } })
  if (!notif.dibaca_at) notifStore.markRead(notif.id)
}

function timeAgo(ts) {
  if (!ts) return ''
  const diff = Math.floor((Date.now() - new Date(ts)) / 60000)
  if (diff < 60) return `${diff}m lalu`
  if (diff < 1440) return `${Math.floor(diff / 60)}j lalu`
  return `${Math.floor(diff / 1440)}h lalu`
}

const userInitials = computed(() => {
  const parts = (user.value?.nama_lengkap || '').split(' ')
  return parts.slice(0, 2).map(p => p[0]?.toUpperCase()).join('')
})

const fotoProfilUrl = computed(() => authStore.fotoProfilUrl)
</script>

<template>
  <div class="relative z-20 shrink-0">
    <Toolbar class="!rounded-none !border-0 !px-4 md:!px-6 !py-3 !bg-white/80 !backdrop-blur-md border-b border-border/60 shadow-sm">
      <template #start>
        <div class="flex items-center gap-3">
          <div :class="['w-10 h-10 rounded-xl bg-gradient-to-br flex items-center justify-center shadow-sm text-white', pageMeta.color]">
            <i :class="pageMeta.icon"></i>
          </div>
          <div>
            <h1 class="text-base md:text-lg font-extrabold text-textMain m-0 leading-tight">{{ pageMeta.title }}</h1>
            <p class="text-[11px] text-textMuted m-0 hidden sm:block">{{ greeting }}, {{ user?.nama_lengkap?.split(' ')[0] }}</p>
          </div>
        </div>
      </template>

      <template #end>
        <div class="flex items-center gap-2">
          <IconField class="hidden md:flex">
            <InputIcon class="pi pi-search text-accent" />
            <InputText
              placeholder="Cari surat, perihal..."
              class="w-56 text-sm !bg-surface2 !border-border/60 focus:!border-accent"
              size="small"
            />
          </IconField>

          <div class="relative">
            <Button
              type="button"
              icon="pi pi-bell"
              rounded
              class="!bg-brandBlueBg !text-brandBlue !border-brandBlue/20 hover:!bg-brandBlue hover:!text-white"
              aria-label="Notifikasi"
              @click="toggleNotif"
            />
            <span
              v-if="notifStore.unread > 0"
              class="absolute -top-1 -right-1 min-w-[18px] h-[18px] rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center px-1 shadow-sm pointer-events-none animate-pulse"
            >
              {{ notifStore.unread > 9 ? '9+' : notifStore.unread }}
            </span>
          </div>

          <div
            class="!hidden sm:!flex cursor-pointer rounded-full overflow-hidden hover:ring-2 hover:ring-accent/50 transition-all"
            @click="router.push({ name: 'profile' })"
            title="Profil Saya"
          >
            <img
              v-if="fotoProfilUrl"
              :src="fotoProfilUrl"
              alt="Foto Profil"
              class="w-10 h-10 rounded-full object-cover"
            />
            <Avatar
              v-else
              :label="userInitials"
              shape="circle"
              class="!bg-gradient-to-br !from-accent !to-brandGreen !text-white font-bold"
            />
          </div>

          <OverlayPanel ref="notifPanel" class="w-[min(380px,92vw)] !rounded-2xl !shadow-card-hover">
            <div class="flex justify-between items-center mb-3 pb-3 border-b border-border/50">
              <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-brandBlueBg flex items-center justify-center">
                  <i class="pi pi-bell text-brandBlue"></i>
                </div>
                <span class="font-bold text-sm">Notifikasi</span>
                <span v-if="notifStore.unread > 0" class="text-[10px] font-bold bg-red-100 text-red-600 px-2 py-0.5 rounded-full">
                  {{ notifStore.unread }} baru
                </span>
              </div>
              <Button
                v-if="notifStore.unread > 0"
                label="Tandai dibaca"
                link
                size="small"
                class="!text-accent !p-0"
                @click="notifStore.markAllRead()"
              />
            </div>

            <div v-if="notifStore.loading" class="text-sm text-textMuted text-center py-8">
              <i class="pi pi-spin pi-spinner text-2xl block mb-2 text-accent"></i>
              Memuat...
            </div>
            <div v-else-if="notifStore.list.length === 0" class="text-sm text-textMuted text-center py-10">
              <div class="w-14 h-14 rounded-2xl bg-surface2 flex items-center justify-center mx-auto mb-3">
                <i class="pi pi-inbox text-2xl text-textDim"></i>
              </div>
              Tidak ada notifikasi.
            </div>
            <div v-else class="max-h-80 overflow-y-auto -mx-1">
              <div
                v-for="n in notifStore.list"
                :key="n.id"
                class="flex gap-3 px-2 py-3 cursor-pointer rounded-xl mb-1 transition-all"
                :class="!n.dibaca_at ? 'bg-accentGlow border border-accent/20' : 'hover:bg-surface2'"
                @click="goDisposisi(n)"
              >
                <div
                  class="w-9 h-9 rounded-xl shrink-0 flex items-center justify-center"
                  :class="!n.dibaca_at ? 'bg-accent text-white' : 'bg-surface3 text-textDim'"
                >
                  <i class="pi pi-bell text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-sm font-semibold leading-snug">{{ n.judul }}</div>
                  <div class="text-xs text-textMuted mt-0.5 line-clamp-2">{{ n.pesan }}</div>
                  <div class="text-[10px] text-textDim mt-1">{{ timeAgo(n.created_at) }}</div>
                </div>
                <Button
                  v-if="!n.dibaca_at"
                  icon="pi pi-check"
                  rounded
                  size="small"
                  class="!bg-brandGreenBg !text-brandGreen shrink-0"
                  @click.stop="notifStore.markRead(n.id)"
                />
              </div>
            </div>
          </OverlayPanel>
        </div>
      </template>
    </Toolbar>
    <!-- Gradient accent strip -->
    <div class="h-0.5 bg-gradient-to-r from-sidebar via-accent to-brandCyan"></div>
  </div>
</template>

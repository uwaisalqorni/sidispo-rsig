<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'
import Card from 'primevue/card'
import Tag from 'primevue/tag'
import ProgressBar from 'primevue/progressbar'
import Skeleton from 'primevue/skeleton'
import SelectButton from 'primevue/selectbutton'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'

const router = useRouter()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const stats = ref({ total_aktif: 0, selesai_bulan_ini: 0, sedang_proses: 0, total_overdue: 0 })
const unitProgress = ref([])
const aktivitas = ref([])
const disposisiList = ref([])
const activeTab = ref('Semua')
const loadingStats = ref(true)
const loadingList  = ref(true)
const tabs = ['Semua', 'Diproses', 'Menunggu', 'Selesai', 'Overdue']

const statusSeverity = { PROSES: 'info', TUNGGU: 'warn', SELESAI: 'success', OVERDUE: 'danger', AKTIF: 'info', ARSIP: 'secondary' }
const statusLabel = { PROSES: 'Diproses', TUNGGU: 'Menunggu', SELESAI: 'Selesai', OVERDUE: 'Overdue', AKTIF: 'Aktif', ARSIP: 'Arsip' }

const statCards = [
  { key: 'total_aktif', label: 'Total Disposisi Aktif', icon: 'pi pi-inbox', cardClass: 'color-card-blue', iconClass: 'stat-icon-blue', badge: 'Semua', badgeSev: 'info' },
  { key: 'selesai_bulan_ini', label: 'Selesai Bulan Ini', icon: 'pi pi-check-circle', cardClass: 'color-card-green', iconClass: 'stat-icon-green', badge: 'Bulan ini', badgeSev: 'success' },
  { key: 'sedang_proses', label: 'Sedang Diproses', icon: 'pi pi-clock', cardClass: 'color-card-orange', iconClass: 'stat-icon-orange', badge: 'Aktif', badgeSev: 'warn' },
  { key: 'total_overdue', label: 'Overdue / Terlambat', icon: 'pi pi-exclamation-triangle', cardClass: 'color-card-red', iconClass: 'stat-icon-red', badge: 'Segera!', badgeSev: 'danger' },
]

const filteredList = ref([])

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Selamat pagi'
  if (h < 17) return 'Selamat siang'
  return 'Selamat malam'
})

function applyFilter() {
  const map = { Diproses: 'PROSES', Menunggu: 'TUNGGU', Selesai: 'SELESAI', Overdue: 'OVERDUE' }
  filteredList.value = activeTab.value === 'Semua'
    ? disposisiList.value
    : disposisiList.value.filter(d => d.status_global === map[activeTab.value])
}

onMounted(async () => {
  try {
    const { data } = await api.get('/dashboard')
    stats.value = data.data
    unitProgress.value = data.data.unit_progress || []
    aktivitas.value = data.data.aktivitas_terbaru || []
  } catch { /* silent */ } finally { loadingStats.value = false }

  try {
    const { data } = await api.get('/disposisi')
    disposisiList.value = data.data || []
    filteredList.value = data.data || []
  } catch { /* silent */ } finally { loadingList.value = false }
})

const goDetail = (id) => router.push({ name: 'disposisi-detail', params: { id } })

function initials(nama) {
  return (nama || '').split(' ').slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}

function timeAgo(ts) {
  if (!ts) return ''
  const diff = Math.floor((Date.now() - new Date(ts)) / 60000)
  if (diff < 60) return `${diff} menit lalu`
  if (diff < 1440) return `${Math.floor(diff / 60)} jam lalu`
  return `${Math.floor(diff / 1440)} hari lalu`
}

function progressPct(row) {
  if (!row.total || row.total === 0) return 0
  return Math.round((row.selesai / row.total) * 100)
}

function progressSeverity(pct) {
  if (pct >= 70) return 'success'
  if (pct >= 40) return 'warn'
  return 'danger'
}
</script>

<template>
  <div class="page-container animate-fade-in">
    <!-- Welcome hero -->
    <div class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <p class="text-sidebar-accent text-xs font-bold uppercase tracking-widest mb-1 relative z-10">Dashboard</p>
        <h2 class="page-hero-title">{{ greeting }}, {{ user?.nama_lengkap?.split(' ')[0] }}! 👋</h2>
        <p class="page-hero-sub">Pantau ringkasan disposisi dan aktivitas terbaru hari ini.</p>
      </div>
      <div class="flex gap-2 relative z-10">
        <Button label="Surat Masuk" icon="pi pi-inbox" class="!bg-white/20 !border-white/30 !text-white hover:!bg-white/30" outlined size="small" @click="router.push('/surat-masuk')" />
        <Button label="Buat Disposisi" icon="pi pi-plus" class="!bg-white !text-sidebar !border-0" size="small" @click="router.push('/disposisi')" />
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
      <div v-for="card in statCards" :key="card.key" :class="['color-card p-5', card.cardClass]">
        <div class="flex items-center justify-between mb-4">
          <div :class="['stat-icon-wrap', card.iconClass]">
            <i :class="[card.icon, 'text-lg']"></i>
          </div>
          <Tag :value="card.badge" :severity="card.badgeSev" class="text-xs font-bold" />
        </div>
        <Skeleton v-if="loadingStats" width="3rem" height="2.5rem" />
        <div v-else class="text-4xl font-extrabold tracking-tight text-textMain">{{ stats[card.key] }}</div>
        <div class="text-xs text-textMuted mt-1.5 font-medium">{{ card.label }}</div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_300px] gap-5">
      <!-- Disposisi list -->
      <Card class="color-panel glass-card">
        <template #title>
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-brandBlueBg flex items-center justify-center">
              <i class="pi pi-list text-brandBlue"></i>
            </div>
            <span class="font-bold">Daftar Disposisi</span>
            <Tag :value="`${disposisiList.length} dokumen`" severity="info" />
          </div>
        </template>
        <template #content>
          <SelectButton v-model="activeTab" :options="tabs" class="mb-4 flex-wrap" @update:model-value="applyFilter" />

          <div v-if="loadingList" class="flex flex-col gap-3">
            <Skeleton v-for="i in 4" :key="i" height="4.5rem" class="rounded-xl" />
          </div>

          <div v-else-if="filteredList.length === 0" class="py-14 text-center">
            <div class="w-16 h-16 rounded-2xl bg-surface2 flex items-center justify-center mx-auto mb-3">
              <i class="pi pi-inbox text-3xl text-textDim"></i>
            </div>
            <p class="text-textMuted text-sm">Tidak ada data disposisi.</p>
          </div>

          <div v-else class="flex flex-col gap-2">
            <div
              v-for="item in filteredList"
              :key="item.id"
              class="flex items-start gap-3 p-3 rounded-xl cursor-pointer border border-transparent hover:border-accent/30 hover:bg-accentGlow/40 transition-all"
              @click="goDetail(item.id)"
            >
              <div
                class="w-2.5 h-2.5 rounded-full shrink-0 mt-2 ring-2 ring-offset-1"
                :class="item.prioritas === 'URGENT' || item.prioritas === 'TINGGI'
                  ? 'bg-red-500 ring-red-200'
                  : item.prioritas === 'NORMAL'
                    ? 'bg-orange-400 ring-orange-200'
                    : 'bg-gray-300 ring-gray-100'"
              ></div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1.5 flex-wrap">
                  <span class="font-mono text-xs font-bold text-brandBlue bg-brandBlueBg px-2 py-0.5 rounded-md">{{ item.nomor_disposisi }}</span>
                  <span class="text-sm font-semibold truncate">{{ item.perihal }}</span>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                  <Tag :value="statusLabel[item.status_global] || item.status_global" :severity="statusSeverity[item.status_global] || 'secondary'" />
                  <Tag v-if="item.nama_folder || item.folder_id" :value="item.nama_folder || item.folder_id" icon="pi pi-folder" severity="secondary" />
                </div>
              </div>
              <div class="flex flex-col items-end gap-2 shrink-0">
                <span class="text-[11px] font-medium text-textMuted bg-surface2 px-2 py-0.5 rounded-md">{{ item.batas_waktu || '—' }}</span>
                <Avatar :label="initials(item.pembuat || '')" shape="circle" size="small" class="!bg-gradient-to-br !from-accent !to-brandGreen !text-white" />
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Side panels -->
      <div class="hidden xl:flex flex-col gap-4">
        <Card class="glass-card overflow-hidden">
          <template #title>
            <div class="flex items-center gap-2 text-sm font-bold">
              <div class="w-7 h-7 rounded-lg bg-brandPurpleBg flex items-center justify-center">
                <i class="pi pi-bell text-brandPurple text-sm"></i>
              </div>
              Aktivitas Terbaru
            </div>
          </template>
          <template #content>
            <div v-if="aktivitas.length === 0" class="text-xs text-textMuted text-center py-6">Belum ada aktivitas.</div>
            <div v-else class="flex flex-col gap-3">
              <div v-for="(a, idx) in aktivitas.slice(0, 5)" :key="idx" class="flex gap-3 p-2 rounded-xl hover:bg-surface2 transition-colors">
                <div
                  class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center text-white text-xs font-bold"
                  :class="a.status_baru === 'SELESAI' ? 'bg-brandGreen' : a.status_baru === 'OVERDUE' ? 'bg-brandRed' : 'bg-brandBlue'"
                >
                  {{ a.nama_lengkap?.[0] }}
                </div>
                <div>
                  <div class="text-xs leading-snug">
                    <strong>{{ a.nama_lengkap }}</strong> →
                    <Tag :value="a.status_baru" :severity="statusSeverity[a.status_baru] || 'info'" class="text-[10px]" />
                  </div>
                  <div class="text-[11px] text-textMuted mt-1">{{ timeAgo(a.created_at) }}</div>
                </div>
              </div>
            </div>
          </template>
        </Card>

        <Card class="glass-card overflow-hidden">
          <template #title>
            <div class="flex items-center gap-2 text-sm font-bold">
              <div class="w-7 h-7 rounded-lg bg-brandCyanBg flex items-center justify-center">
                <i class="pi pi-chart-bar text-brandCyan text-sm"></i>
              </div>
              Progress per Unit
            </div>
          </template>
          <template #content>
            <div v-if="unitProgress.length === 0" class="text-xs text-textMuted text-center py-4">Tidak ada data.</div>
            <div v-for="row in unitProgress" :key="row.unit" class="mb-4 last:mb-0">
              <div class="flex justify-between text-xs mb-1.5">
                <span class="text-textMuted font-medium">{{ row.unit }}</span>
                <span class="font-extrabold font-mono" :class="progressPct(row) >= 70 ? 'text-brandGreen' : progressPct(row) >= 40 ? 'text-brandYellow' : 'text-brandRed'">
                  {{ progressPct(row) }}%
                </span>
              </div>
              <ProgressBar :value="progressPct(row)" :show-value="false" :severity="progressSeverity(progressPct(row))" class="!h-2.5 !rounded-full" />
            </div>
          </template>
        </Card>
      </div>
    </div>
  </div>
</template>

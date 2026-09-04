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

const stats = ref({
  total_disposisi: 0,
  total_aktif: 0,
  sedang_proses: 0,
  menunggu: 0,
  selesai: 0,
  total_selesai: 0,
  selesai_bulan_ini: 0,
  total_overdue: 0
})
const unitProgress = ref([])
const aktivitas = ref([])
const disposisiList = ref([])
const activeTab = ref('Semua')
const loadingStats = ref(true)
const loadingList  = ref(true)
const tabs = ['Semua', 'Diproses', 'Menunggu', 'Selesai', 'Overdue']

const statusSeverity = {
  PROSES:   'info',
  TUNGGU:   'warn',
  DITERIMA: 'warn',
  SELESAI:  'success',
  OVERDUE:  'danger',
  AKTIF:    'info',
  ARSIP:    'secondary'
}

const statusLabel = {
  PROSES:   'Diproses',
  TUNGGU:   'Menunggu',
  DITERIMA: 'Menunggu',
  SELESAI:  'Selesai',
  OVERDUE:  'Overdue',
  AKTIF:    'Aktif',
  ARSIP:    'Arsip'
}

const statCards = [
  {
    key: 'total_disposisi',
    fallbackKey: 'total_aktif',
    title: 'Semua Disposisi',
    subtitle: 'Total seluruh dokumen',
    icon: 'pi pi-inbox',
    borderClass: 'border-blue-200/80 hover:border-blue-400',
    accentBg: 'bg-gradient-to-br from-blue-50/60 via-white to-blue-50/20',
    topBar: 'bg-brandBlue',
    iconWrap: 'bg-blue-100/80 text-blue-600 border border-blue-200/60',
    badgeText: 'Total',
    badgeClass: 'bg-blue-50 text-blue-700 border-blue-200/60',
    tabTarget: 'Semua'
  },
  {
    key: 'sedang_proses',
    title: 'Sedang Diproses',
    subtitle: 'Dalam pengerjaan staf',
    icon: 'pi pi-sync',
    borderClass: 'border-amber-200/80 hover:border-amber-400',
    accentBg: 'bg-gradient-to-br from-amber-50/60 via-white to-amber-50/20',
    topBar: 'bg-brandYellow',
    iconWrap: 'bg-amber-100/80 text-amber-600 border border-amber-200/60',
    badgeText: 'Diproses',
    badgeClass: 'bg-amber-50 text-amber-700 border-amber-200/60',
    tabTarget: 'Diproses'
  },
  {
    key: 'menunggu',
    title: 'Menunggu Tindakan',
    subtitle: 'Belum diproses / antrean',
    icon: 'pi pi-hourglass',
    borderClass: 'border-purple-200/80 hover:border-purple-400',
    accentBg: 'bg-gradient-to-br from-purple-50/60 via-white to-purple-50/20',
    topBar: 'bg-brandPurple',
    iconWrap: 'bg-purple-100/80 text-purple-600 border border-purple-200/60',
    badgeText: 'Menunggu',
    badgeClass: 'bg-purple-50 text-purple-700 border-purple-200/60',
    tabTarget: 'Menunggu'
  },
  {
    key: 'selesai',
    fallbackKey: 'total_selesai',
    title: 'Disposisi Selesai',
    subtitle: 'Telah tuntas dikerjakan',
    icon: 'pi pi-check-circle',
    borderClass: 'border-emerald-200/80 hover:border-emerald-400',
    accentBg: 'bg-gradient-to-br from-emerald-50/60 via-white to-emerald-50/20',
    topBar: 'bg-brandGreen',
    iconWrap: 'bg-emerald-100/80 text-emerald-600 border border-emerald-200/60',
    badgeText: 'Selesai',
    badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-200/60',
    tabTarget: 'Selesai'
  },
  {
    key: 'total_overdue',
    title: 'Overdue / Terlambat',
    subtitle: 'Melewati batas waktu',
    icon: 'pi pi-exclamation-triangle',
    borderClass: 'border-rose-200/80 hover:border-rose-400',
    accentBg: 'bg-gradient-to-br from-rose-50/60 via-white to-rose-50/20',
    topBar: 'bg-brandRed',
    iconWrap: 'bg-rose-100/80 text-rose-600 border border-rose-200/60',
    badgeText: 'Perlu Aksi',
    badgeClass: 'bg-rose-50 text-rose-700 border-rose-200/60',
    tabTarget: 'Overdue'
  },
]

const filteredList = ref([])

const greeting = computed(() => {
  const h = new Date().getHours()
  if (h < 12) return 'Selamat pagi'
  if (h < 17) return 'Selamat siang'
  return 'Selamat malam'
})

// Tab count calculation
const tabCounts = computed(() => {
  const counts = { Semua: disposisiList.value.length }
  const map = { Diproses: 'PROSES', Menunggu: 'TUNGGU', Selesai: 'SELESAI', Overdue: 'OVERDUE' }
  for (const [label, key] of Object.entries(map)) {
    counts[label] = disposisiList.value.filter(d => {
      const st = d.status_display || d.status_global
      if (key === 'TUNGGU') return st === 'TUNGGU' || st === 'DITERIMA' || st === 'AKTIF'
      return st === key
    }).length
  }
  return counts
})

function getTabBadgeClass(tab) {
  if (activeTab.value === tab) return 'bg-white/30 text-white'
  if (tab === 'Overdue') return 'bg-rose-100 text-rose-700'
  if (tab === 'Diproses') return 'bg-blue-100 text-blue-700'
  if (tab === 'Menunggu') return 'bg-purple-100 text-purple-700'
  if (tab === 'Selesai') return 'bg-emerald-100 text-emerald-700'
  return 'bg-gray-200 text-gray-700'
}

function selectTab(tab) {
  if (!tab) return
  activeTab.value = tab
  applyFilter()
}

function applyFilter() {
  const map = { Diproses: 'PROSES', Menunggu: 'TUNGGU', Selesai: 'SELESAI', Overdue: 'OVERDUE' }
  if (activeTab.value === 'Semua') {
    filteredList.value = disposisiList.value
  } else {
    const key = map[activeTab.value]
    filteredList.value = disposisiList.value.filter(d => {
      const st = d.status_display || d.status_global
      if (key === 'TUNGGU') return st === 'TUNGGU' || st === 'DITERIMA' || st === 'AKTIF'
      return st === key
    })
  }
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
    applyFilter()
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
        <p class="page-hero-sub">Pantau ringkasan disposisi dan aktivitas terbaru hari ini secara realtime.</p>
      </div>
      <div class="flex gap-2 relative z-10">
        <Button label="Surat Masuk" icon="pi pi-inbox" class="!bg-white/20 !border-white/30 !text-white hover:!bg-white/30" outlined size="small" @click="router.push('/surat-masuk')" />
        <Button label="Buat Disposisi" icon="pi pi-plus" class="!bg-white !text-sidebar !border-0" size="small" @click="router.push('/disposisi')" />
      </div>
    </div>

    <!-- Stats Cards (Symmetrical, interactive & neat cards) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3.5 mb-6 items-stretch">
      <div
        v-for="(card, idx) in statCards"
        :key="card.key"
        :class="[
          'group relative overflow-hidden rounded-2xl border p-4 sm:p-5 cursor-pointer select-none transition-all duration-200 flex flex-col justify-between shadow-xs hover:shadow-md hover:-translate-y-0.5',
          card.accentBg,
          card.borderClass,
          activeTab === card.tabTarget
            ? 'ring-2 ring-accent ring-offset-2 shadow-md bg-white'
            : 'bg-white/95',
          { 'col-span-2 sm:col-span-1': idx === 4 }
        ]"
        @click="selectTab(card.tabTarget)"
      >
        <!-- Top color accent bar -->
        <div class="absolute top-0 left-0 right-0 h-1" :class="card.topBar"></div>

        <!-- Header: Icon & Badge -->
        <div class="flex items-center justify-between gap-2 mb-3">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 shadow-xs" :class="card.iconWrap">
            <i :class="[card.icon, 'text-base']"></i>
          </div>
          <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border shrink-0" :class="card.badgeClass">
            {{ card.badgeText }}
          </span>
        </div>

        <!-- Center: Number Counter -->
        <div class="mb-2">
          <Skeleton v-if="loadingStats" width="3.5rem" height="2.2rem" class="rounded-lg" />
          <div v-else class="flex items-baseline gap-1.5 flex-wrap">
            <span class="text-3xl sm:text-4xl font-black tracking-tight text-textMain font-mono">
              {{ stats[card.key] !== undefined ? stats[card.key] : (stats[card.fallbackKey] ?? 0) }}
            </span>
            <span v-if="card.key === 'selesai' && stats.selesai_bulan_ini > 0" class="text-[10px] font-bold text-brandGreen bg-brandGreenBg px-1.5 py-0.5 rounded border border-brandGreen/20">
              +{{ stats.selesai_bulan_ini }} bln ini
            </span>
          </div>
        </div>

        <!-- Footer: Title & Hint -->
        <div class="pt-2 border-t border-black/5 flex items-center justify-between gap-1 text-xs">
          <div class="min-w-0">
            <p class="font-bold text-textMain truncate leading-tight">{{ card.title }}</p>
            <p class="text-[10px] text-textMuted truncate mt-0.5">{{ card.subtitle }}</p>
          </div>
          <i
            class="pi text-[11px] shrink-0 transition-transform duration-200"
            :class="activeTab === card.tabTarget ? 'pi-check-circle text-accent font-bold' : 'pi-chevron-right text-textDim/50 group-hover:translate-x-0.5'"
          ></i>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_310px] gap-5">
      <!-- Disposisi list -->
      <Card class="color-panel glass-card">
        <template #title>
          <div class="flex items-center justify-between flex-wrap gap-2 w-full">
            <div class="flex items-center gap-2">
              <div class="w-8 h-8 rounded-lg bg-brandBlueBg flex items-center justify-center">
                <i class="pi pi-list text-brandBlue"></i>
              </div>
              <span class="font-bold">Daftar Disposisi</span>
              <Tag :value="`${disposisiList.length} dokumen`" severity="info" class="text-xs font-semibold" />
            </div>
            <Button
              label="Buka Menu Disposisi"
              icon="pi pi-arrow-up-right"
              icon-pos="right"
              size="small"
              text
              class="!text-xs !py-1 !px-2.5 !text-brandBlue hover:!bg-brandBlueBg"
              @click="router.push({ path: '/disposisi', query: { tab: activeTab !== 'Semua' ? activeTab : undefined } })"
            />
          </div>
        </template>
        <template #content>
          <!-- Status Tabs with live counts -->
          <SelectButton
            v-model="activeTab"
            :options="tabs"
            :allow-empty="false"
            class="mb-4 flex-wrap"
            @update:model-value="applyFilter"
          >
            <template #option="slotProps">
              <div class="flex items-center gap-1.5 py-0.5 px-1 font-semibold text-xs">
                <span>{{ slotProps.option }}</span>
                <span
                  v-if="tabCounts[slotProps.option] !== undefined"
                  class="text-[10px] font-bold px-1.5 py-0.5 rounded-full transition-colors"
                  :class="getTabBadgeClass(slotProps.option)"
                >
                  {{ tabCounts[slotProps.option] }}
                </span>
              </div>
            </template>
          </SelectButton>

          <div v-if="loadingList" class="flex flex-col gap-3">
            <Skeleton v-for="i in 4" :key="i" height="4.5rem" class="rounded-xl" />
          </div>

          <div v-else-if="filteredList.length === 0" class="py-14 text-center">
            <div class="w-16 h-16 rounded-2xl bg-surface2 flex items-center justify-center mx-auto mb-3">
              <i class="pi pi-inbox text-3xl text-textDim"></i>
            </div>
            <p class="text-textMuted text-sm">Tidak ada data disposisi pada status ini.</p>
          </div>

          <div v-else class="flex flex-col gap-2.5">
            <div
              v-for="item in filteredList"
              :key="item.id"
              class="group flex items-center gap-3.5 p-3.5 rounded-xl cursor-pointer border border-border/40 hover:border-accent/50 bg-white hover:bg-surface2/60 transition-all shadow-xs hover:shadow-sm"
              @click="goDetail(item.id)"
            >
              <!-- Priority indicator line -->
              <div
                class="w-1.5 h-10 rounded-full shrink-0"
                :class="item.prioritas === 'URGENT' || item.prioritas === 'TINGGI'
                  ? 'bg-rose-500 ring-2 ring-rose-100'
                  : item.prioritas === 'NORMAL'
                    ? 'bg-amber-400 ring-2 ring-amber-100'
                    : 'bg-slate-300 ring-2 ring-slate-100'"
              ></div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1 flex-wrap">
                  <span class="font-mono text-xs font-bold text-brandBlue bg-brandBlueBg px-2 py-0.5 rounded-md border border-brandBlue/15">
                    {{ item.nomor_disposisi }}
                  </span>
                  <span class="text-sm font-bold truncate text-textMain group-hover:text-accent transition-colors">
                    {{ item.perihal }}
                  </span>
                </div>
                <div class="flex items-center gap-2 flex-wrap text-xs">
                  <Tag
                    :value="statusLabel[item.status_display || item.status_global] || item.status_display || item.status_global"
                    :severity="statusSeverity[item.status_display || item.status_global] || 'secondary'"
                    class="text-[10px] font-bold"
                  />
                  <span v-if="item.asal_surat" class="text-textMuted flex items-center gap-1 text-[11px] truncate max-w-[220px]">
                    <i class="pi pi-send text-[9px] text-textDim"></i>
                    {{ item.asal_surat }}
                  </span>
                  <Tag v-if="item.nama_folder || item.folder_id" :value="item.nama_folder || item.folder_id" icon="pi pi-folder" severity="secondary" class="text-[10px]" />
                </div>
              </div>
              <div class="flex flex-col items-end gap-1.5 shrink-0">
                <span
                  class="text-[11px] font-mono font-medium px-2 py-0.5 rounded-md border"
                  :class="(item.status_display || item.status_global) === 'OVERDUE'
                    ? 'bg-rose-50 text-rose-700 font-bold border-rose-200'
                    : 'text-textMuted bg-surface2 border-border/40'"
                >
                  {{ item.batas_waktu || '—' }}
                </span>
                <Avatar :label="initials(item.pembuat || '')" shape="circle" size="small" class="!bg-gradient-to-br !from-accent !to-brandGreen !text-white !w-6 !h-6 !text-[10px]" />
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
              <div v-for="(a, idx) in aktivitas.slice(0, 5)" :key="idx" class="flex gap-3 p-2.5 rounded-xl hover:bg-surface2 transition-colors border border-transparent hover:border-border/30">
                <div
                  class="w-8 h-8 rounded-xl shrink-0 flex items-center justify-center text-white text-xs font-bold"
                  :class="a.status_baru === 'SELESAI' ? 'bg-brandGreen' : a.status_baru === 'OVERDUE' ? 'bg-brandRed' : a.status_baru === 'TUNGGU' || a.status_baru === 'DITERIMA' ? 'bg-brandPurple' : 'bg-brandBlue'"
                >
                  {{ a.nama_lengkap?.[0] }}
                </div>
                <div class="flex-1 min-w-0">
                  <div class="text-xs leading-snug">
                    <strong>{{ a.nama_lengkap }}</strong> →
                    <Tag :value="statusLabel[a.status_baru] || a.status_baru" :severity="statusSeverity[a.status_baru] || 'info'" class="text-[10px]" />
                  </div>
                  <div v-if="a.perihal" class="text-[11px] text-textMain/80 truncate max-w-[200px] mt-0.5" :title="a.perihal">
                    {{ a.perihal }}
                  </div>
                  <div class="text-[11px] text-textMuted mt-0.5">{{ timeAgo(a.created_at) }}</div>
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
            <div v-if="unitProgress.length === 0" class="text-xs text-textMuted text-center py-4">Tidak ada data unit.</div>
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

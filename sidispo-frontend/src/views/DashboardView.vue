<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api/axios'

const router = useRouter()
const stats = ref({ total_aktif: 0, selesai_bulan_ini: 0, sedang_proses: 0, total_overdue: 0 })
const unitProgress = ref([])
const aktivitas = ref([])
const disposisiList = ref([])
const activeTab = ref('Semua')
const loadingStats = ref(true)
const loadingList  = ref(true)
const tabs = ['Semua', 'Diproses', 'Menunggu', 'Selesai', 'Overdue']

const statusConfig = {
  PROSES:  { label: 'Diproses',  cls: 'bg-brandBlueBg text-brandBlue' },
  TUNGGU:  { label: 'Menunggu',  cls: 'bg-brandYellowBg text-brandYellow' },
  SELESAI: { label: 'Selesai',   cls: 'bg-brandGreenBg text-brandGreen' },
  OVERDUE: { label: 'Overdue',   cls: 'bg-brandRedBg text-brandRed' },
  AKTIF:   { label: 'Aktif',     cls: 'bg-brandBlueBg text-brandBlue' },
  ARSIP:   { label: 'Arsip',     cls: 'bg-surface3 text-textMuted' },
}

const filteredList = ref([])

function applyFilter() {
  const map = { 'Diproses': 'PROSES', 'Menunggu': 'TUNGGU', 'Selesai': 'SELESAI', 'Overdue': 'OVERDUE' }
  if (activeTab.value === 'Semua') {
    filteredList.value = disposisiList.value
  } else {
    filteredList.value = disposisiList.value.filter(d => d.status_global === map[activeTab.value])
  }
}

function setTab(tab) {
  activeTab.value = tab
  applyFilter()
}

onMounted(async () => {
  // Load dashboard stats
  try {
    const { data } = await api.get('/dashboard')
    stats.value       = data.data
    unitProgress.value = data.data.unit_progress || []
    aktivitas.value    = data.data.aktivitas_terbaru || []
  } catch (e) { /* silent */ } finally { loadingStats.value = false }

  // Load disposisi list
  try {
    const { data } = await api.get('/disposisi')
    disposisiList.value = data.data || []
    filteredList.value  = data.data || []
  } catch (e) { /* silent */ } finally { loadingList.value = false }
})

const goDetail = (id) => router.push({ name: 'disposisi-detail', params: { id } })

function initials(nama) {
  return (nama || '').split(' ').slice(0,2).map(w => w[0]?.toUpperCase()).join('')
}

function timeAgo(ts) {
  if (!ts) return ''
  const diff = Math.floor((Date.now() - new Date(ts)) / 60000)
  if (diff < 60) return `${diff} menit lalu`
  if (diff < 1440) return `${Math.floor(diff/60)} jam lalu`
  return `${Math.floor(diff/1440)} hari lalu`
}

function progressPct(row) {
  if (!row.total || row.total === 0) return 0
  return Math.round((row.selesai / row.total) * 100)
}

function progressColor(pct) {
  if (pct >= 70) return 'bg-brandGreen'
  if (pct >= 40) return 'bg-brandYellow'
  return 'bg-brandRed'
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6 animate-[fadeIn_0.4s_ease]">
    <!-- STATS GRID -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3.5 mb-6">
      <div class="bg-surface border border-border rounded-xl p-[18px] px-5 transition-all duration-200 hover:border-accent hover:-translate-y-px cursor-default">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base bg-brandBlueBg">📋</div>
          <span class="text-xs font-semibold text-accent">Semua</span>
        </div>
        <div class="text-[28px] font-extrabold tracking-tight">
          <span v-if="loadingStats" class="inline-block w-8 h-7 bg-surface3 rounded animate-pulse"></span>
          <span v-else>{{ stats.total_aktif }}</span>
        </div>
        <div class="text-xs text-textMuted mt-[3px]">Total Disposisi Aktif</div>
      </div>
      
      <div class="bg-surface border border-border rounded-xl p-[18px] px-5 transition-all duration-200 hover:border-accent hover:-translate-y-px cursor-default">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base bg-brandGreenBg">✅</div>
          <span class="text-xs font-semibold text-brandGreen">Bulan ini</span>
        </div>
        <div class="text-[28px] font-extrabold tracking-tight">
          <span v-if="loadingStats" class="inline-block w-8 h-7 bg-surface3 rounded animate-pulse"></span>
          <span v-else>{{ stats.selesai_bulan_ini }}</span>
        </div>
        <div class="text-xs text-textMuted mt-[3px]">Selesai Bulan Ini</div>
      </div>
      
      <div class="bg-surface border border-border rounded-xl p-[18px] px-5 transition-all duration-200 hover:border-accent hover:-translate-y-px cursor-default">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base bg-brandYellowBg">⏳</div>
          <span class="text-xs font-semibold text-brandYellow">Perlu perhatian</span>
        </div>
        <div class="text-[28px] font-extrabold tracking-tight">
          <span v-if="loadingStats" class="inline-block w-8 h-7 bg-surface3 rounded animate-pulse"></span>
          <span v-else>{{ stats.sedang_proses }}</span>
        </div>
        <div class="text-xs text-textMuted mt-[3px]">Sedang Diproses</div>
      </div>
      
      <div class="bg-surface border border-border rounded-xl p-[18px] px-5 transition-all duration-200 hover:border-accent hover:-translate-y-px cursor-default">
        <div class="flex items-center justify-between mb-3">
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base bg-brandRedBg">🚨</div>
          <span class="text-xs font-semibold text-brandRed">Segera!</span>
        </div>
        <div class="text-[28px] font-extrabold tracking-tight">
          <span v-if="loadingStats" class="inline-block w-8 h-7 bg-surface3 rounded animate-pulse"></span>
          <span v-else>{{ stats.total_overdue }}</span>
        </div>
        <div class="text-xs text-textMuted mt-[3px]">Overdue / Terlambat</div>
      </div>
    </div>

    <!-- PANEL GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5">
      <!-- DISPOSISI LIST -->
      <div class="bg-surface border border-border rounded-xl overflow-hidden flex flex-col">
        <div class="flex items-center gap-3 p-4 px-5 border-b border-border bg-surface">
          <div class="text-[14px] font-bold">📋 Daftar Disposisi</div>
          <div class="text-[12px] font-semibold text-textMuted bg-surface2 px-2 py-0.5 rounded-full">{{ disposisiList.length }} dokumen</div>
        </div>
        
        <div class="flex gap-1 p-3 px-5 border-b border-border bg-surface flex-wrap">
          <div v-for="tab in tabs" :key="tab" @click="setTab(tab)"
            class="px-3 py-1.5 rounded-lg text-xs font-semibold cursor-pointer transition-all duration-150"
            :class="activeTab === tab ? 'bg-accentGlow text-accent font-semibold border border-accent/20' : 'text-textMuted hover:bg-surface2 hover:text-textMain'">
            {{ tab }}
          </div>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loadingList" class="flex flex-col gap-0">
          <div v-for="i in 4" :key="i" class="flex items-start gap-3.5 p-4 px-5 border-b border-border">
            <div class="w-4 h-4 rounded bg-surface3 animate-pulse shrink-0 mt-1"></div>
            <div class="flex-1">
              <div class="h-3.5 bg-surface3 rounded animate-pulse mb-2 w-3/4"></div>
              <div class="h-3 bg-surface3 rounded animate-pulse w-1/2"></div>
            </div>
          </div>
        </div>

        <div v-else class="flex-1 flex flex-col">
          <div v-for="item in filteredList" :key="item.id"
            class="flex items-start gap-3.5 p-4 px-5 border-b border-border cursor-pointer hover:bg-surface2 transition-all duration-150 last:border-b-0"
            @click="goDetail(item.id)">
            <div class="w-[7px] h-[7px] rounded-full shrink-0 mt-[6px]"
              :class="item.prioritas === 'URGENT' || item.prioritas === 'TINGGI' ? 'bg-brandRed' : item.prioritas === 'NORMAL' ? 'bg-brandYellow' : 'bg-textDim'"></div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1.5">
                <span class="font-mono text-[11px] text-textMuted shrink-0">{{ item.nomor_disposisi }}</span>
                <span class="text-[13.5px] font-semibold truncate">{{ item.perihal }}</span>
              </div>
              <div class="flex items-center gap-2 flex-wrap">
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full inline-flex items-center gap-1"
                  :class="statusConfig[item.status_global]?.cls || 'bg-surface3 text-textMuted'">
                  {{ statusConfig[item.status_global]?.label || item.status_global }}
                </span>
                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full inline-flex items-center gap-1 bg-surface3 text-textMuted">
                  📁 {{ item.nama_folder || item.folder_id || '—' }}
                </span>
                <span v-if="item.asal_surat" class="text-[11px] text-textMuted">← {{ item.asal_surat }}</span>
              </div>
            </div>
            <div class="flex flex-col items-end gap-1.5 shrink-0">
              <span class="text-[11px] text-textMuted whitespace-nowrap">{{ item.batas_waktu || '—' }}</span>
              <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-[10px] font-bold text-white">
                {{ initials(item.pembuat || '') }}
              </div>
            </div>
          </div>
          <div v-if="filteredList.length === 0" class="py-12 text-center text-textMuted text-sm">
            Tidak ada data disposisi.
          </div>
        </div>
      </div>
      
      <!-- SIDE PANELS -->
      <div class="hidden lg:flex flex-col gap-4">
        <!-- Aktivitas Terbaru -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
          <div class="p-4 border-b border-border text-[13px] font-bold">🔔 Aktivitas Terbaru</div>
          <div v-if="aktivitas.length === 0" class="p-4 text-xs text-textMuted text-center">Belum ada aktivitas.</div>
          <div v-else>
            <div v-for="(a, idx) in aktivitas.slice(0,5)" :key="idx"
              class="flex gap-3 p-3 px-4 border-b border-border cursor-pointer hover:bg-surface2 transition-all last:border-b-0">
              <div class="w-2 h-2 rounded-full shrink-0 mt-[5px]"
                :class="a.status_baru === 'SELESAI' ? 'bg-brandGreen' : a.status_baru === 'OVERDUE' ? 'bg-brandRed' : 'bg-brandBlue'"></div>
              <div>
                <div class="text-[12.5px] leading-snug text-textMain"><strong>{{ a.nama_lengkap }}</strong> mengubah status menjadi <span :class="a.status_baru === 'SELESAI' ? 'text-brandGreen font-semibold' : a.status_baru === 'OVERDUE' ? 'text-brandRed font-semibold' : 'text-brandYellow font-semibold'">{{ a.status_baru }}</span>.</div>
                <div class="text-[11px] text-textMuted mt-[3px]">{{ timeAgo(a.created_at) }}</div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Progress per Unit -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
          <div class="p-4 border-b border-border text-[13px] font-bold">📊 Progress per Unit</div>
          <div class="p-4 px-5">
            <div v-if="unitProgress.length === 0" class="text-xs text-textMuted text-center py-2">Tidak ada data.</div>
            <div v-for="row in unitProgress" :key="row.unit" class="mb-3 last:mb-0">
              <div class="flex justify-between text-xs mb-1.5">
                <span class="text-textMuted text-[12px]">{{ row.unit }}</span>
                <span class="font-bold font-mono text-[11px]">{{ progressPct(row) }}%</span>
              </div>
              <div class="h-2 bg-surface3 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all" :class="progressColor(progressPct(row))" :style="{ width: progressPct(row) + '%' }"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

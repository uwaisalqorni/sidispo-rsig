<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/api/axios'

const router = useRouter()
const stats = ref({ total_aktif: 0, selesai_bulan_ini: 0, high_priority: 0, total_overdue: 0 })
const unitProgress = ref([])
const aktivitas = ref([])
const rtlList = ref([])
const activeTab = ref('Semua')
const loadingStats = ref(true)
const loadingList  = ref(true)
const tabs = ['Semua', 'TO_DO', 'ON_PROGRESS', 'REVIEW', 'DONE']

const statusConfig = {
  TO_DO:       { label: 'To Do',       cls: 'bg-surface3 text-textMuted' },
  ON_PROGRESS: { label: 'On Progress', cls: 'bg-brandBlueBg text-brandBlue' },
  REVIEW:      { label: 'Review',      cls: 'bg-brandYellowBg text-brandYellow' },
  DONE:        { label: 'Done',        cls: 'bg-brandGreenBg text-brandGreen' }
}

const getPrioritasColor = (prioritas) => {
  const map = {
    'Biasa': 'bg-surface3 text-textMuted border-border',
    'Penting': 'bg-brandBlueBg text-brandBlue border-brandBlue/30',
    'Segera': 'bg-brandYellowBg text-brandYellow border-brandYellow/50',
    'Rahasia': 'bg-brandRedBg text-brandRed border-brandRed/30'
  }
  return map[prioritas] || 'bg-surface3 text-textMuted border-border'
}

const filteredList = ref([])

function applyFilter() {
  if (activeTab.value === 'Semua') {
    filteredList.value = rtlList.value
  } else {
    filteredList.value = rtlList.value.filter(d => d.status_progress === activeTab.value)
  }
}

function setTab(tab) {
  activeTab.value = tab
  applyFilter()
}

onMounted(async () => {
  // Load dashboard rtl stats
  try {
    const { data } = await api.get('/dashboard-rtl')
    stats.value       = data.data
    aktivitas.value    = data.data.aktivitas_terbaru || []
    unitProgress.value = data.data.unit_progress || []
  } catch (e) { /* silent */ } finally { loadingStats.value = false }

  // Load rtl list
  try {
    const { data } = await api.get('/rtl')
    rtlList.value = data.data || []
    filteredList.value  = data.data || []
  } catch (e) { /* silent */ } finally { loadingList.value = false }
})

const goDetail = (id) => router.push({ name: 'rtl-detail', params: { id } })

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

function formatDateFull(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' })
}

function progressPct(row) {
  const t = Number(row.total) || 0
  const s = Number(row.selesai) || 0
  if (t === 0) return 0
  return Math.round((s / t) * 100)
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
        <div class="text-xs text-textMuted mt-[3px]">Total RTL Aktif</div>
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
          <div class="w-9 h-9 rounded-lg flex items-center justify-center text-base bg-brandYellowBg">⚡</div>
          <span class="text-xs font-semibold text-brandYellow">High Priority</span>
        </div>
        <div class="text-[28px] font-extrabold tracking-tight">
          <span v-if="loadingStats" class="inline-block w-8 h-7 bg-surface3 rounded animate-pulse"></span>
          <span v-else>{{ stats.high_priority }}</span>
        </div>
        <div class="text-xs text-textMuted mt-[3px]">Prioritas Penting / Segera</div>
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
      <!-- RTL LIST -->
      <div class="bg-surface border border-border rounded-xl overflow-hidden flex flex-col">
        <div class="flex items-center gap-3 p-4 px-5 border-b border-border bg-surface">
          <div class="text-[14px] font-bold">📋 Daftar Rencana Tindak Lanjut</div>
          <div class="text-[12px] font-semibold text-textMuted bg-surface2 px-2 py-0.5 rounded-full">{{ rtlList.length }} dokumen</div>
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
            
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1.5">
                <span class="font-mono text-[11px] text-textMuted shrink-0">{{ item.nomor_disposisi }}</span>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase border" :class="getPrioritasColor(item.prioritas)">
                  {{ item.prioritas || 'Biasa' }}
                </span>
              </div>
              <div class="text-[13.5px] font-semibold truncate mb-1.5">{{ item.deskripsi_rtl || 'Tanpa Deskripsi' }}</div>
              
              <div class="flex items-center gap-2 flex-wrap text-[11px]">
                <span class="font-semibold px-2 py-0.5 rounded-full inline-flex items-center gap-1 uppercase"
                  :class="statusConfig[item.status_progress]?.cls || 'bg-surface3 text-textMuted border border-border'">
                  {{ statusConfig[item.status_progress]?.label || item.status_progress }}
                </span>
                <span class="text-textMuted flex items-center gap-1">
                  👥 <span class="truncate max-w-[200px]" :title="item.penerima_names">{{ item.penerima_names || 'Belum ada penerima' }}</span>
                </span>
              </div>
            </div>
            
            <div class="flex flex-col items-end gap-1.5 shrink-0">
              <span class="text-[11px] whitespace-nowrap" :class="item.batas_waktu && new Date(item.batas_waktu) < new Date() && item.status_progress !== 'DONE' ? 'text-brandRed font-bold' : 'text-textMuted'">
                {{ item.batas_waktu && item.batas_waktu !== '0000-00-00' ? formatDateFull(item.batas_waktu).split(',')[0] : '—' }}
              </span>
              <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-[10px] font-bold text-white mt-1" :title="item.pembuat">
                {{ initials(item.pembuat || '') }}
              </div>
            </div>
            
          </div>
          <div v-if="filteredList.length === 0" class="py-12 text-center text-textMuted text-sm">
            Tidak ada RTL di tab ini.
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
              class="flex gap-3 p-3 px-4 border-b border-border cursor-pointer hover:bg-surface2 transition-all last:border-b-0"
              @click="goDetail(a.rtl_id)">
              <div class="w-2 h-2 rounded-full shrink-0 mt-[5px]"
                :class="a.status_baru === 'DONE' ? 'bg-brandGreen' : a.status_baru === 'REVIEW' ? 'bg-brandYellow' : 'bg-brandBlue'"></div>
              <div>
                <div class="text-[12.5px] leading-snug text-textMain"><strong>{{ a.nama_lengkap }}</strong> mengubah status RTL <span class="font-mono text-[10px] text-accent">{{ a.disposisi_id }}</span> menjadi <span class="uppercase text-[10px] px-1 font-bold rounded" :class="statusConfig[a.status_baru]?.cls || 'bg-surface3'">{{ a.status_baru }}</span>.</div>
                <div v-if="a.catatan" class="text-[11px] text-textMuted italic mt-1 line-clamp-1">"{{ a.catatan }}"</div>
                <div class="text-[10px] text-textDim mt-1">{{ timeAgo(a.dibuat_at) }}</div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Progress per RTL -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
          <div class="p-4 border-b border-border text-[13px] font-bold">📊 Progress RTL (Sesuai Penerima)</div>
          <div class="p-4 px-5">
            <div v-if="unitProgress.length === 0" class="text-xs text-textMuted text-center py-2">Tidak ada data.</div>
            <div v-for="row in unitProgress" :key="row.id" class="mb-3 last:mb-0 cursor-pointer group" @click="goDetail(row.id)">
              <div class="flex justify-between text-xs mb-1.5">
                <span class="text-textMuted text-[12px] group-hover:text-accent truncate transition-colors mr-2">{{ row.unit }}</span>
                <span class="font-bold font-mono text-[11px] shrink-0">{{ progressPct(row) }}%</span>
              </div>
              <div class="h-2 bg-surface3 rounded-full overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 ease-out" :class="progressColor(progressPct(row))" :style="{ width: progressPct(row) + '%' }"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'

import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tag from 'primevue/tag'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'

import CetakTandaTerimaModal from '@/components/ekspedisi/CetakTandaTerimaModal.vue'
import LembarDisposisiModal from '@/components/disposisi/LembarDisposisiModal.vue'
import BerkasSuratModal from '@/components/ekspedisi/BerkasSuratModal.vue'
import { useToast } from 'primevue/usetoast'

const auth = useAuthStore()
const { user } = storeToRefs(auth)
const toast = useToast()

const isAdmin = computed(() => ['ADMIN', 'DIREKTUR'].includes(user.value?.role))

// ── DATA ─────────────────────────────────────────────────────────────────────
const reportList = ref([])
const loading = ref(false)
const totalRows = ref(0)
const stats = ref({ total: 0, received: 0, pending: 0, rejected: 0, digital: 0, fisik: 0 })
const unitOptions = ref([])

// Pagination
const currentPage = ref(1)
const perPage = ref(20)
const totalPages = computed(() => Math.ceil(totalRows.value / perPage.value) || 1)

// Filters
const searchQuery = ref('')
const filterStatus = ref('')
const filterJenis = ref('')
const filterUnit = ref('')
const filterTanggalDari = ref('')
const filterTanggalSampai = ref('')

const statusOptions = [
  { label: 'Semua Status', value: '' },
  { label: 'Diterima', value: 'RECEIVED' },
  { label: 'Menunggu', value: 'PENDING' },
  { label: 'Ditolak', value: 'REJECTED' }
]

const jenisOptions = [
  { label: 'Semua Jenis', value: '' },
  { label: 'Digital', value: 'DIGITAL' },
  { label: 'Fisik', value: 'FISIK' }
]

// Modals
const showCetakModal = ref(false)
const selectedEkspedisiForCetak = ref({})

const showBerkasModal = ref(false)
const selectedEkspedisiForBerkas = ref(null)

const showLembarModal = ref(false)
const lembarDisposisiData = ref(null)
const loadingLembar = ref(false)

const showDetailModal = ref(false)
const detailEkspedisi = ref(null)
const loadingDetail = ref(false)

const fileUrl = (path) => {
  if (!path) return '#'
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/SiDispo/api/v1')
    .replace('/api/v1', '')
  return base + '/' + path
}

// ── FETCH DATA ───────────────────────────────────────────────────────────────

let searchTimeout = null

const loadReport = async () => {
  loading.value = true
  try {
    const params = {
      limit: perPage.value,
      offset: (currentPage.value - 1) * perPage.value
    }
    if (searchQuery.value) params.q = searchQuery.value
    if (filterStatus.value) params.status = filterStatus.value
    if (filterJenis.value) params.jenis = filterJenis.value
    if (filterUnit.value) params.unit = filterUnit.value
    if (filterTanggalDari.value) params.tanggal_dari = filterTanggalDari.value
    if (filterTanggalSampai.value) params.tanggal_sampai = filterTanggalSampai.value

    const { data } = await api.get('/ekspedisi/report', { params })
    reportList.value = data.data || []
    totalRows.value = data.total || 0
    stats.value = data.stats || { total: 0, received: 0, pending: 0, rejected: 0, digital: 0, fisik: 0 }
    unitOptions.value = (data.units || []).map(u => ({ label: u, value: u }))
    unitOptions.value.unshift({ label: 'Semua Unit', value: '' })
  } catch (e) {
    console.error('Gagal memuat report ekspedisi', e)
    toast.add({
      severity: 'error',
      summary: 'Gagal',
      detail: 'Gagal memuat data laporan ekspedisi.',
      life: 3000
    })
  } finally {
    loading.value = false
  }
}

const debouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    loadReport()
  }, 400)
}

const applyFilters = () => {
  currentPage.value = 1
  loadReport()
}

const resetFilters = () => {
  searchQuery.value = ''
  filterStatus.value = ''
  filterJenis.value = ''
  filterUnit.value = ''
  filterTanggalDari.value = ''
  filterTanggalSampai.value = ''
  currentPage.value = 1
  loadReport()
}

const goPage = (page) => {
  if (page < 1 || page > totalPages.value) return
  currentPage.value = page
  loadReport()
}

onMounted(() => {
  loadReport()
})

// ── MODAL ACTIONS ────────────────────────────────────────────────────────────

const openCetakModal = (item) => {
  selectedEkspedisiForCetak.value = item
  showCetakModal.value = true
}

const openBerkasModal = (item) => {
  selectedEkspedisiForBerkas.value = item
  showBerkasModal.value = true
}

const openLembarDisposisi = async (disposisiId) => {
  loadingLembar.value = true
  try {
    const { data } = await api.get(`/ekspedisi/lembar-disposisi/${disposisiId}`)
    lembarDisposisiData.value = data.data
    showLembarModal.value = true
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Gagal',
      detail: e.response?.data?.message || 'Gagal memuat lembar disposisi.',
      life: 3000
    })
  } finally {
    loadingLembar.value = false
  }
}

const openDetail = async (id) => {
  loadingDetail.value = true
  showDetailModal.value = true
  try {
    const { data } = await api.get(`/ekspedisi/${id}`)
    detailEkspedisi.value = data.data
  } catch (e) {
    console.error('Gagal mengambil detail ekspedisi', e)
    toast.add({
      severity: 'error',
      summary: 'Gagal',
      detail: 'Gagal memuat detail ekspedisi.',
      life: 3000
    })
  } finally {
    loadingDetail.value = false
  }
}

// ── EXPORT CSV ───────────────────────────────────────────────────────────────

const exportCSV = () => {
  if (reportList.value.length === 0) {
    toast.add({ severity: 'warn', summary: 'Kosong', detail: 'Tidak ada data untuk diekspor.', life: 3000 })
    return
  }
  const headers = [
    'No', 'No. Resi', 'Tgl Kirim', 'No. Surat', 'No. Agenda', 'Asal Surat', 'Perihal',
    'Penerima / Unit', 'Jabatan', 'Jenis', 'Status', 'Diterima Pada', 'Diterima Oleh',
    'Ditolak Pada', 'Alasan Tolak', 'Keterangan'
  ]
  const rows = reportList.value.map((item, idx) => [
    idx + 1,
    `"${item.nomor_ekspedisi || ''}"`,
    `"${item.tanggal_kirim || ''}"`,
    `"${item.nomor_surat || ''}"`,
    `"${item.nomor_agenda || ''}"`,
    `"${item.asal_surat || ''}"`,
    `"${(item.perihal || '').replace(/"/g, '""')}"`,
    `"${item.nama_user_tujuan || item.unit_tujuan || ''}"`,
    `"${item.jabatan_user_tujuan || ''}"`,
    item.jenis_pengiriman || '',
    item.status_tujuan || '',
    `"${item.received_at || ''}"`,
    `"${item.nama_penerima || ''}"`,
    `"${item.rejected_at || ''}"`,
    `"${(item.alasan_tolak || '').replace(/"/g, '""')}"`,
    `"${(item.keterangan_surat || '').replace(/"/g, '""')}"`
  ])
  const csvContent = [headers.join(','), ...rows.map(r => r.join(','))].join('\n')
  const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  const timestamp = new Date().toISOString().slice(0, 10)
  link.href = url
  link.download = `Report_Ekspedisi_${timestamp}.csv`
  link.click()
  URL.revokeObjectURL(url)
  toast.add({ severity: 'success', summary: 'Berhasil', detail: 'File CSV berhasil diunduh.', life: 3000 })
}

// ── PRINT ────────────────────────────────────────────────────────────────────

const printReport = () => {
  window.print()
}

// ── FORMATTERS ───────────────────────────────────────────────────────────────

const formatDate = (val) => {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch { return val }
}

const formatDateTime = (val) => {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    })
  } catch { return val }
}

// Active filter count for badge
const activeFilterCount = computed(() => {
  let count = 0
  if (filterStatus.value) count++
  if (filterJenis.value) count++
  if (filterUnit.value) count++
  if (filterTanggalDari.value) count++
  if (filterTanggalSampai.value) count++
  if (searchQuery.value) count++
  return count
})

// Visible page numbers for pagination
const visiblePages = computed(() => {
  const pages = []
  const tp = totalPages.value
  const cp = currentPage.value
  let start = Math.max(1, cp - 2)
  let end = Math.min(tp, cp + 2)
  if (end - start < 4) {
    if (start === 1) end = Math.min(tp, start + 4)
    else start = Math.max(1, end - 4)
  }
  for (let i = start; i <= end; i++) pages.push(i)
  return pages
})

// Percentage calculations
const receivedPct = computed(() => stats.value.total ? Math.round((stats.value.received / stats.value.total) * 100) : 0)
const pendingPct = computed(() => stats.value.total ? Math.round((stats.value.pending / stats.value.total) * 100) : 0)
const rejectedPct = computed(() => stats.value.total ? Math.round((stats.value.rejected / stats.value.total) * 100) : 0)
</script>

<template>
  <div class="page-container animate-fade-in pb-16">

    <!-- HERO BANNER -->
    <div
      class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4 print:!bg-white print:!text-black print:!shadow-none"
      style="background: linear-gradient(135deg, #1a1a2e 0%, #16213e 40%, #0f3460 80%, #533483 100%);"
    >
      <div>
        <div class="flex items-center gap-2 flex-wrap relative z-10">
          <h2 class="page-hero-title">Report Ekspedisi</h2>
          <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full border border-white/30 bg-white/15 text-white">
            <span class="w-2 h-2 rounded-full bg-purple-300 animate-pulse"></span>
            LAPORAN
          </span>
        </div>
        <p class="page-hero-sub">Rekap lengkap distribusi & serah terima ekspedisi surat disposisi seluruh unit RSI Gondanglegi.</p>
      </div>

      <div class="flex items-center gap-2.5 relative z-10 print:hidden">
        <Button
          label="Ekspor CSV"
          icon="pi pi-download"
          class="!bg-white/15 !text-white !border-white/30 hover:!bg-white/25 font-bold text-xs !py-2.5 !px-4 !rounded-xl transition-all cursor-pointer"
          @click="exportCSV"
        />
        <Button
          label="Cetak"
          icon="pi pi-print"
          class="!bg-white !text-[#16213e] !border-0 shadow-glow font-bold text-xs !py-2.5 !px-4 !rounded-xl hover:!bg-white/95 transition-all cursor-pointer"
          @click="printReport"
        />
      </div>
    </div>

    <!-- KPI STAT CARDS -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5 print:grid-cols-6">
      <!-- Total -->
      <div class="glass-card p-4 flex flex-col gap-1 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
          <span class="text-[10px] uppercase tracking-wider font-bold text-textMuted">Total Ekspedisi</span>
          <div class="w-8 h-8 rounded-xl bg-blue-500/10 flex items-center justify-center">
            <i class="pi pi-inbox text-blue-500 text-sm"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-textMain">{{ stats.total }}</div>
      </div>
      <!-- Diterima -->
      <div class="glass-card p-4 flex flex-col gap-1 border-l-4 border-emerald-500">
        <div class="flex items-center justify-between">
          <span class="text-[10px] uppercase tracking-wider font-bold text-textMuted">Diterima</span>
          <div class="w-8 h-8 rounded-xl bg-emerald-500/10 flex items-center justify-center">
            <i class="pi pi-check-circle text-emerald-500 text-sm"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ stats.received }}</div>
        <div class="text-[10px] text-textMuted font-semibold">{{ receivedPct }}% dari total</div>
      </div>
      <!-- Menunggu -->
      <div class="glass-card p-4 flex flex-col gap-1 border-l-4 border-amber-500">
        <div class="flex items-center justify-between">
          <span class="text-[10px] uppercase tracking-wider font-bold text-textMuted">Menunggu</span>
          <div class="w-8 h-8 rounded-xl bg-amber-500/10 flex items-center justify-center">
            <i class="pi pi-clock text-amber-500 text-sm"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ stats.pending }}</div>
        <div class="text-[10px] text-textMuted font-semibold">{{ pendingPct }}% dari total</div>
      </div>
      <!-- Ditolak -->
      <div class="glass-card p-4 flex flex-col gap-1 border-l-4 border-red-500">
        <div class="flex items-center justify-between">
          <span class="text-[10px] uppercase tracking-wider font-bold text-textMuted">Ditolak</span>
          <div class="w-8 h-8 rounded-xl bg-red-500/10 flex items-center justify-center">
            <i class="pi pi-times-circle text-red-500 text-sm"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-red-600 dark:text-red-400">{{ stats.rejected }}</div>
        <div class="text-[10px] text-textMuted font-semibold">{{ rejectedPct }}% dari total</div>
      </div>
      <!-- Digital -->
      <div class="glass-card p-4 flex flex-col gap-1 border-l-4 border-cyan-500">
        <div class="flex items-center justify-between">
          <span class="text-[10px] uppercase tracking-wider font-bold text-textMuted">Digital</span>
          <div class="w-8 h-8 rounded-xl bg-cyan-500/10 flex items-center justify-center">
            <i class="pi pi-desktop text-cyan-500 text-sm"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-cyan-600 dark:text-cyan-400">{{ stats.digital }}</div>
      </div>
      <!-- Fisik -->
      <div class="glass-card p-4 flex flex-col gap-1 border-l-4 border-orange-500">
        <div class="flex items-center justify-between">
          <span class="text-[10px] uppercase tracking-wider font-bold text-textMuted">Fisik</span>
          <div class="w-8 h-8 rounded-xl bg-orange-500/10 flex items-center justify-center">
            <i class="pi pi-box text-orange-500 text-sm"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-orange-600 dark:text-orange-400">{{ stats.fisik }}</div>
      </div>
    </div>

    <!-- FILTERS PANEL -->
    <div class="glass-card p-4 mb-5 print:hidden">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <i class="pi pi-filter text-accent text-sm"></i>
          <span class="text-xs font-bold text-textMain uppercase tracking-wider">Filter & Pencarian</span>
          <span v-if="activeFilterCount" class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent text-white text-[10px] font-bold">{{ activeFilterCount }}</span>
        </div>
        <button
          v-if="activeFilterCount"
          @click="resetFilters"
          class="text-xs text-red-500 font-bold hover:text-red-600 transition-colors cursor-pointer bg-transparent border-0 flex items-center gap-1"
        >
          <i class="pi pi-filter-slash text-xs"></i>
          Reset Filter
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6 gap-3">
        <!-- Search -->
        <div class="lg:col-span-2">
          <label class="text-[10px] uppercase tracking-wider font-bold text-textMuted mb-1 block">Kata Kunci</label>
          <IconField class="w-full">
            <InputIcon class="pi pi-search text-accent" />
            <InputText
              v-model="searchQuery"
              @input="debouncedSearch"
              placeholder="Cari no. resi, surat, perihal, penerima..."
              class="w-full text-xs !rounded-xl !bg-surface2"
            />
          </IconField>
        </div>

        <!-- Status -->
        <div>
          <label class="text-[10px] uppercase tracking-wider font-bold text-textMuted mb-1 block">Status</label>
          <Select
            v-model="filterStatus"
            :options="statusOptions"
            option-label="label"
            option-value="value"
            placeholder="Semua Status"
            class="w-full !rounded-xl text-xs !bg-surface2"
            @change="applyFilters"
          />
        </div>

        <!-- Jenis -->
        <div>
          <label class="text-[10px] uppercase tracking-wider font-bold text-textMuted mb-1 block">Jenis</label>
          <Select
            v-model="filterJenis"
            :options="jenisOptions"
            option-label="label"
            option-value="value"
            placeholder="Semua Jenis"
            class="w-full !rounded-xl text-xs !bg-surface2"
            @change="applyFilters"
          />
        </div>

        <!-- Unit -->
        <div>
          <label class="text-[10px] uppercase tracking-wider font-bold text-textMuted mb-1 block">Unit / Bidang</label>
          <Select
            v-model="filterUnit"
            :options="unitOptions"
            option-label="label"
            option-value="value"
            placeholder="Semua Unit"
            class="w-full !rounded-xl text-xs !bg-surface2"
            @change="applyFilters"
          />
        </div>

        <!-- Date Range -->
        <div class="flex gap-2">
          <div class="flex-1">
            <label class="text-[10px] uppercase tracking-wider font-bold text-textMuted mb-1 block">Dari Tanggal</label>
            <input
              type="date"
              v-model="filterTanggalDari"
              @change="applyFilters"
              class="w-full text-xs rounded-xl bg-surface2 border border-border text-textMain px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-accent/50"
            />
          </div>
          <div class="flex-1">
            <label class="text-[10px] uppercase tracking-wider font-bold text-textMuted mb-1 block">Sampai</label>
            <input
              type="date"
              v-model="filterTanggalSampai"
              @change="applyFilters"
              class="w-full text-xs rounded-xl bg-surface2 border border-border text-textMain px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-accent/50"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- DATA TABLE -->
    <div class="glass-card overflow-hidden shadow-card mb-4">
      <!-- Table Header Info -->
      <div class="flex items-center justify-between px-4 py-3 border-b border-border bg-surface2/40 print:hidden">
        <div class="text-xs text-textMuted">
          Menampilkan <span class="font-bold text-textMain">{{ reportList.length }}</span> dari <span class="font-bold text-textMain">{{ totalRows }}</span> data ekspedisi
        </div>
        <div class="flex items-center gap-2">
          <Select
            v-model="perPage"
            :options="[{ label: '10 / halaman', value: 10 }, { label: '20 / halaman', value: 20 }, { label: '50 / halaman', value: 50 }, { label: '100 / halaman', value: 100 }]"
            option-label="label"
            option-value="value"
            class="!rounded-xl text-xs !bg-surface2 w-36"
            @change="applyFilters"
          />
        </div>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="p-10 text-center text-xs text-textMuted">
        <i class="pi pi-spin pi-spinner text-2xl text-purple-600 mb-2"></i>
        <div>Memuat data laporan ekspedisi...</div>
      </div>

      <!-- Empty -->
      <div v-else-if="reportList.length === 0" class="p-12 text-center text-xs text-textMuted flex flex-col items-center">
        <div class="w-14 h-14 rounded-2xl bg-surface2 flex items-center justify-center mb-3 text-textMuted">
          <i class="pi pi-folder-open text-3xl text-purple-400"></i>
        </div>
        <div class="font-bold text-textMain text-sm">Tidak Ada Data Ekspedisi</div>
        <div class="text-xs text-textMuted mt-1">Tidak ditemukan data ekspedisi sesuai filter yang aktif.</div>
        <Button
          v-if="activeFilterCount"
          label="Reset Semua Filter"
          icon="pi pi-filter-slash"
          severity="secondary"
          size="small"
          class="mt-3 !rounded-xl text-xs"
          @click="resetFilters"
        />
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-xs border-collapse report-table">
          <thead>
            <tr class="bg-surface2/80 border-b border-border text-[11px] uppercase tracking-wider font-bold text-textMuted">
              <th class="p-3.5 text-center w-12">No</th>
              <th class="p-3.5 min-w-[160px]">No. Resi & Tgl Kirim</th>
              <th class="p-3.5 min-w-[180px]">Surat Masuk & Asal</th>
              <th class="p-3.5 min-w-[200px]">Perihal</th>
              <th class="p-3.5 min-w-[160px]">Penerima / Unit</th>
              <th class="p-3.5 text-center w-24">Jenis</th>
              <th class="p-3.5 text-center w-28">Berkas Surat</th>
              <th class="p-3.5 text-center min-w-[220px]">Status & Konfirmasi</th>
              <th class="p-3.5 text-center w-32 print:hidden">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border/40">
            <tr
              v-for="(item, idx) in reportList"
              :key="item.ekspedisi_tujuan_id"
              class="hover:bg-surface2/50 transition-colors text-textMain"
              :class="{
                'bg-amber-500/5 dark:bg-amber-500/10': item.status_tujuan === 'PENDING',
                'bg-red-500/5 dark:bg-red-500/5': item.status_tujuan === 'REJECTED'
              }"
            >
              <!-- No -->
              <td class="p-3.5 text-center text-textMuted font-bold">{{ (currentPage - 1) * perPage + idx + 1 }}</td>

              <!-- No. Resi & Tgl Kirim -->
              <td class="p-3.5">
                <div class="font-mono font-bold text-textMain text-xs">{{ item.nomor_ekspedisi }}</div>
                <div class="text-[10px] text-textMuted font-semibold mt-0.5">Tgl Kirim: {{ formatDate(item.tanggal_kirim) }}</div>
                <div class="text-[9.5px] text-textMuted mt-0.5">Oleh: {{ item.nama_pengirim || 'Sekretariat' }}</div>
              </td>

              <!-- Surat & Asal -->
              <td class="p-3.5 max-w-[200px]">
                <div class="font-bold text-textMain truncate">{{ item.nomor_surat }}</div>
                <div class="text-[11px] text-textMuted font-medium truncate">{{ item.asal_surat }}</div>
                <div class="text-[10px] text-textMuted/80">
                  <span v-if="item.nomor_agenda" class="font-mono">Agenda: {{ item.nomor_agenda }}</span>
                  <span v-if="item.nomor_agenda && item.tanggal_surat"> · </span>
                  <span v-if="item.tanggal_surat">Tgl: {{ formatDate(item.tanggal_surat) }}</span>
                </div>
              </td>

              <!-- Perihal -->
              <td class="p-3.5 max-w-xs">
                <div class="font-medium text-textMain line-clamp-2 leading-relaxed">{{ item.perihal }}</div>
                <div v-if="item.keterangan_surat" class="text-[10px] text-textMuted mt-0.5 italic line-clamp-1">{{ item.keterangan_surat }}</div>
              </td>

              <!-- Penerima / Unit -->
              <td class="p-3.5">
                <div class="font-bold text-textMain">{{ item.nama_user_tujuan || item.unit_tujuan }}</div>
                <div class="text-[10px] text-textMuted">{{ item.jabatan_user_tujuan || item.unit_tujuan }}</div>
                <div v-if="item.nip_user_tujuan" class="text-[9.5px] text-textMuted/70 font-mono">NIP. {{ item.nip_user_tujuan }}</div>
              </td>

              <!-- Jenis -->
              <td class="p-3.5 text-center">
                <span
                  v-if="item.jenis_pengiriman === 'FISIK'"
                  class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 font-bold text-[10px] inline-flex items-center gap-1"
                >
                  <i class="pi pi-box text-[9px]"></i> Fisik
                </span>
                <span
                  v-else
                  class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 font-bold text-[10px] inline-flex items-center gap-1"
                >
                  <i class="pi pi-desktop text-[9px]"></i> Digital
                </span>
              </td>

              <!-- Berkas Surat -->
              <td class="p-3.5 text-center">
                <button
                  type="button"
                  @click="openBerkasModal(item)"
                  class="px-2.5 py-1.5 rounded-xl bg-blue-500/10 text-blue-700 dark:text-blue-400 hover:bg-blue-500/20 border border-blue-500/20 font-bold text-xs inline-flex items-center gap-1.5 cursor-pointer shadow-2xs transition-all"
                >
                  <i class="pi pi-paperclip text-xs"></i>
                  <span>{{ (item.files || []).length }} Berkas</span>
                </button>
              </td>

              <!-- Status & Konfirmasi -->
              <td class="p-3.5 text-center">
                <!-- RECEIVED -->
                <div v-if="item.status_tujuan === 'RECEIVED'" class="inline-block text-left p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 shadow-2xs max-w-[260px]">
                  <div class="flex items-center gap-1.5 text-emerald-800 dark:text-emerald-300 font-bold text-[11px] leading-tight">
                    <i class="pi pi-check-circle text-emerald-600 dark:text-emerald-400 text-sm shrink-0"></i>
                    <span>Diterima: {{ formatDateTime(item.received_at) }}</span>
                  </div>
                  <div class="text-[10px] text-textMain font-bold mt-0.5 truncate">
                    oleh {{ item.nama_penerima || 'Staf Penerima' }}
                    <span v-if="item.nip_penerima" class="text-textMuted font-normal">(NIP. {{ item.nip_penerima }})</span>
                  </div>
                </div>

                <!-- PENDING -->
                <div v-else-if="item.status_tujuan === 'PENDING'" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 font-bold text-[11px]">
                  <i class="pi pi-clock text-amber-500 text-sm animate-pulse"></i>
                  <span>Menunggu Konfirmasi</span>
                </div>

                <!-- REJECTED -->
                <div v-else-if="item.status_tujuan === 'REJECTED'" class="inline-block text-left p-2 rounded-xl bg-red-500/10 border border-red-500/20 text-red-800 dark:text-red-300 max-w-[260px]">
                  <div class="flex items-center gap-1.5 font-bold text-[10.5px]">
                    <i class="pi pi-times-circle text-red-600 text-sm shrink-0"></i>
                    <span>Ditolak: {{ formatDateTime(item.rejected_at) }}</span>
                  </div>
                  <div class="text-[10px] text-red-700 dark:text-red-400 font-medium mt-0.5 italic truncate">
                    "{{ item.alasan_tolak || 'Tidak sesuai' }}"
                  </div>
                  <div class="text-[9.5px] text-textMuted mt-0.5">oleh {{ item.nama_penolak || 'Staf' }}</div>
                </div>
              </td>

              <!-- Aksi -->
              <td class="p-3.5 text-center print:hidden">
                <div class="flex items-center justify-center gap-1.5">
                  <Button
                    icon="pi pi-eye"
                    severity="info"
                    size="small"
                    outlined
                    @click="openDetail(item.ekspedisi_id)"
                    v-tooltip.top="'Detail & Pelacakan'"
                    class="!w-8 !h-8 !p-0 !rounded-lg"
                  />
                  <Button
                    icon="pi pi-file-check"
                    severity="success"
                    size="small"
                    outlined
                    @click="openLembarDisposisi(item.disposisi_id)"
                    v-tooltip.top="'Lembar Disposisi'"
                    class="!w-8 !h-8 !p-0 !rounded-lg"
                  />
                  <Button
                    v-if="item.jenis_pengiriman === 'FISIK'"
                    icon="pi pi-print"
                    severity="secondary"
                    size="small"
                    outlined
                    @click="openCetakModal(item)"
                    v-tooltip.top="'Tanda Terima Fisik'"
                    class="!w-8 !h-8 !p-0 !rounded-lg"
                  />
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- PAGINATION -->
      <div v-if="reportList.length > 0 && totalPages > 1" class="flex items-center justify-between px-4 py-3 border-t border-border bg-surface2/40 print:hidden">
        <div class="text-xs text-textMuted">
          Halaman <span class="font-bold text-textMain">{{ currentPage }}</span> dari <span class="font-bold text-textMain">{{ totalPages }}</span>
        </div>
        <div class="flex items-center gap-1">
          <button
            @click="goPage(1)"
            :disabled="currentPage === 1"
            class="w-8 h-8 rounded-lg text-xs font-bold flex items-center justify-center transition-all border-0 cursor-pointer"
            :class="currentPage === 1 ? 'text-textMuted/40 bg-transparent cursor-not-allowed' : 'text-textMuted bg-surface2 hover:bg-accent hover:text-white'"
          >
            <i class="pi pi-angle-double-left text-xs"></i>
          </button>
          <button
            @click="goPage(currentPage - 1)"
            :disabled="currentPage === 1"
            class="w-8 h-8 rounded-lg text-xs font-bold flex items-center justify-center transition-all border-0 cursor-pointer"
            :class="currentPage === 1 ? 'text-textMuted/40 bg-transparent cursor-not-allowed' : 'text-textMuted bg-surface2 hover:bg-accent hover:text-white'"
          >
            <i class="pi pi-angle-left text-xs"></i>
          </button>
          <button
            v-for="p in visiblePages"
            :key="p"
            @click="goPage(p)"
            class="w-8 h-8 rounded-lg text-xs font-bold flex items-center justify-center transition-all border-0 cursor-pointer"
            :class="p === currentPage
              ? 'bg-accent text-white shadow-sm'
              : 'text-textMuted bg-surface2 hover:bg-accent/20 hover:text-accent'"
          >
            {{ p }}
          </button>
          <button
            @click="goPage(currentPage + 1)"
            :disabled="currentPage === totalPages"
            class="w-8 h-8 rounded-lg text-xs font-bold flex items-center justify-center transition-all border-0 cursor-pointer"
            :class="currentPage === totalPages ? 'text-textMuted/40 bg-transparent cursor-not-allowed' : 'text-textMuted bg-surface2 hover:bg-accent hover:text-white'"
          >
            <i class="pi pi-angle-right text-xs"></i>
          </button>
          <button
            @click="goPage(totalPages)"
            :disabled="currentPage === totalPages"
            class="w-8 h-8 rounded-lg text-xs font-bold flex items-center justify-center transition-all border-0 cursor-pointer"
            :class="currentPage === totalPages ? 'text-textMuted/40 bg-transparent cursor-not-allowed' : 'text-textMuted bg-surface2 hover:bg-accent hover:text-white'"
          >
            <i class="pi pi-angle-double-right text-xs"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL DETAIL & PELACAKAN EKSPEDISI -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <Dialog
      v-model:visible="showDetailModal"
      modal
      :style="{ width: '720px', maxWidth: '95vw' }"
      class="detail-ekspedisi-dialog"
    >
      <template #header>
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold">
            <i class="pi pi-box text-lg"></i>
          </div>
          <div>
            <h3 class="text-base font-bold text-textMain leading-tight">Detail & Pelacakan Ekspedisi</h3>
            <p class="text-xs text-textMuted font-mono">{{ detailEkspedisi?.nomor_ekspedisi || 'Memuat...' }}</p>
          </div>
        </div>
      </template>

      <div v-if="loadingDetail" class="p-10 text-center text-xs text-textMuted">
        <i class="pi pi-spin pi-spinner text-2xl text-purple-600 mb-2"></i>
        <div>Memuat data detail pelacakan...</div>
      </div>

      <div v-else-if="detailEkspedisi" class="flex flex-col gap-4 py-1 text-xs">
        <!-- Metadata Card -->
        <div class="p-4 rounded-2xl bg-surface2/60 border border-border grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Nomor Resi & Jenis</div>
            <div class="font-mono font-black text-purple-700 dark:text-purple-400 text-sm mt-0.5">{{ detailEkspedisi.nomor_ekspedisi }}</div>
            <div class="mt-1">
              <span
                v-if="detailEkspedisi.jenis_pengiriman === 'FISIK'"
                class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 font-bold text-[10px] inline-flex items-center gap-1"
              >
                <i class="pi pi-box text-[9px]"></i> Dokumen Fisik
              </span>
              <span
                v-else
                class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 font-bold text-[10px] inline-flex items-center gap-1"
              >
                <i class="pi pi-desktop text-[9px]"></i> Digital
              </span>
            </div>
          </div>
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Surat & Disposisi</div>
            <div class="font-bold text-textMain mt-0.5">{{ detailEkspedisi.nomor_surat }}</div>
            <div class="text-textMuted">{{ detailEkspedisi.asal_surat }}</div>
            <div class="text-textMuted mt-0.5">Disposisi: <span class="font-mono font-bold">{{ detailEkspedisi.nomor_disposisi || '-' }}</span></div>
          </div>
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Perihal</div>
            <div class="text-textMain font-medium leading-relaxed mt-0.5">{{ detailEkspedisi.perihal }}</div>
          </div>
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Tanggal Kirim</div>
            <div class="text-textMain font-bold mt-0.5">{{ formatDateTime(detailEkspedisi.tanggal_kirim) }}</div>
            <div class="text-textMuted">Oleh: {{ detailEkspedisi.nama_pengirim || 'Sekretariat' }}</div>
          </div>
        </div>

        <!-- Catatan Pengirim -->
        <div v-if="detailEkspedisi.catatan" class="p-3 rounded-xl bg-blue-500/5 border border-blue-500/20">
          <div class="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400 tracking-wider mb-1">Catatan Pengirim</div>
          <div class="text-textMain leading-relaxed">{{ detailEkspedisi.catatan }}</div>
        </div>

        <!-- Tracking Penerima -->
        <div>
          <div class="font-bold text-textMain uppercase tracking-wider text-[11px] mb-2">Daftar Penerima & Status:</div>
          <div class="overflow-x-auto rounded-xl border border-border">
            <table class="w-full text-xs text-left border-collapse">
              <thead>
                <tr class="bg-surface2 text-[10px] uppercase tracking-wider text-textMuted">
                  <th class="p-2.5 font-bold">No</th>
                  <th class="p-2.5 font-bold">Penerima / Unit</th>
                  <th class="p-2.5 font-bold text-center">Status</th>
                  <th class="p-2.5 font-bold">Waktu</th>
                  <th class="p-2.5 font-bold">Keterangan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border/30">
                <tr v-for="(t, i) in (detailEkspedisi.tujuan || [])" :key="t.id" class="hover:bg-surface2/30">
                  <td class="p-2.5 text-textMuted font-bold">{{ i + 1 }}</td>
                  <td class="p-2.5">
                    <div class="font-bold text-textMain">{{ t.nama_lengkap || t.unit_tujuan }}</div>
                    <div class="text-[10px] text-textMuted">{{ t.jabatan || t.unit_tujuan }}</div>
                  </td>
                  <td class="p-2.5 text-center">
                    <Tag
                      v-if="t.status === 'RECEIVED'"
                      value="Diterima"
                      severity="success"
                      class="text-[10px] !rounded-lg !font-bold"
                    />
                    <Tag
                      v-else-if="t.status === 'PENDING'"
                      value="Menunggu"
                      severity="warn"
                      class="text-[10px] !rounded-lg !font-bold"
                    />
                    <Tag
                      v-else-if="t.status === 'REJECTED'"
                      value="Ditolak"
                      severity="danger"
                      class="text-[10px] !rounded-lg !font-bold"
                    />
                  </td>
                  <td class="p-2.5 text-textMuted">
                    <div v-if="t.received_at">{{ formatDateTime(t.received_at) }}</div>
                    <div v-else-if="t.rejected_at">{{ formatDateTime(t.rejected_at) }}</div>
                    <span v-else class="text-textMuted">-</span>
                  </td>
                  <td class="p-2.5">
                    <div v-if="t.alasan_tolak" class="text-red-600 italic font-medium leading-tight">"{{ t.alasan_tolak }}"</div>
                    <div v-else-if="t.catatan" class="text-textMain">{{ t.catatan }}</div>
                    <span v-else class="text-textMuted">-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Lampiran Dokumen Surat -->
        <div v-if="detailEkspedisi.files && detailEkspedisi.files.length">
          <div class="font-bold text-textMain uppercase tracking-wider text-[11px] mb-2">Berkas Lampiran Surat:</div>
          <div class="flex flex-col gap-2">
            <a
              v-for="f in detailEkspedisi.files"
              :key="f.id"
              :href="fileUrl(f.path_file)"
              target="_blank"
              class="flex items-center gap-2.5 p-2.5 rounded-xl bg-surface2/50 border border-border hover:border-blue-500/40 hover:bg-surface2 transition-all text-textMain no-underline group"
            >
              <i class="pi pi-file-pdf text-red-500 text-base"></i>
              <span class="font-medium truncate flex-1 text-xs group-hover:text-blue-600 transition-colors">{{ f.nama_asli || f.nama_file || f.path_file }}</span>
              <i class="pi pi-external-link text-xs text-textMuted group-hover:text-blue-500"></i>
            </a>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between items-center w-full pt-3 border-t border-border">
          <div class="flex items-center gap-2">
            <Button
              v-if="detailEkspedisi?.jenis_pengiriman === 'FISIK'"
              label="Cetak Tanda Terima"
              icon="pi pi-print"
              severity="secondary"
              size="small"
              class="!rounded-xl text-xs"
              @click="openCetakModal(detailEkspedisi)"
            />
            <Button
              v-if="detailEkspedisi?.disposisi_id"
              label="Lembar Disposisi"
              icon="pi pi-file-check"
              severity="success"
              size="small"
              outlined
              class="!rounded-xl text-xs"
              @click="openLembarDisposisi(detailEkspedisi.disposisi_id)"
            />
          </div>
          <Button label="Tutup" severity="secondary" text class="!rounded-xl text-xs" @click="showDetailModal = false" />
        </div>
      </template>
    </Dialog>

    <!-- SUB-MODALS -->
    <CetakTandaTerimaModal
      v-model:visible="showCetakModal"
      :ekspedisi="selectedEkspedisiForCetak"
    />

    <BerkasSuratModal
      v-model:visible="showBerkasModal"
      :ekspedisi="selectedEkspedisiForBerkas"
      @openLembarDisposisi="openLembarDisposisi"
    />

    <LembarDisposisiModal
      v-if="lembarDisposisiData"
      v-model:visible="showLembarModal"
      :disposisi="lembarDisposisiData"
    />

  </div>
</template>

<style scoped>
/* Print styles */
@media print {
  .page-container {
    padding: 0 !important;
    margin: 0 !important;
  }
  .glass-card {
    box-shadow: none !important;
    border: 1px solid #ddd !important;
    break-inside: avoid;
  }
  .report-table th,
  .report-table td {
    border: 1px solid #ddd !important;
    padding: 6px 8px !important;
    font-size: 9px !important;
  }
  .report-table thead tr {
    background: #f5f5f5 !important;
  }
}
</style>

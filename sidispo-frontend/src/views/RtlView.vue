<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'

import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Paginator from 'primevue/paginator'

const router = useRouter()
const auth = useAuthStore()
const { user } = storeToRefs(auth)

// State
const rtlList = ref([])
const totalFiltered = ref(0)
const loading = ref(true)

// Form & Selection State
const disposisiList = ref([])
const usersList = ref([])
const selectedPenerima = ref([])
const showModal = ref(false)
const submitting = ref(false)
const submitMsg = ref({ type: '', text: '' })

// Role Checks
const isAdmin = computed(() => user.value?.role === 'ADMIN')

const form = ref({
  disposisi_id: '',
  prioritas: 'Biasa',
  deskripsi_rtl: '',
  batas_waktu: ''
})

// Filter & Search State
const searchQuery = ref('')
const activeTab = ref('Semua') // 'Semua', 'TO_DO', 'ON_PROGRESS', 'REVIEW', 'DONE'
const selectedPrioritas = ref('Semua')
const filterDari = ref('')
const filterSampai = ref('')
const dateBy = ref('batas_waktu') // 'batas_waktu' | 'dibuat_at'
const viewMode = ref('table') // 'table' | 'grid'

// Paginator for Grid Mode
const gridFirst = ref(0)
const gridRows = ref(10)

const tabs = ['Semua', 'TO_DO', 'ON_PROGRESS', 'REVIEW', 'DONE']

const prioritasOptions = [
  { label: '⚡ Semua Prioritas', value: 'Semua' },
  { label: 'Biasa', value: 'Biasa' },
  { label: 'Penting', value: 'Penting' },
  { label: 'Segera', value: 'Segera' },
  { label: 'Rahasia', value: 'Rahasia' }
]

const dateByOptions = [
  { label: 'Batas Waktu', value: 'batas_waktu' },
  { label: 'Tgl Registrasi', value: 'dibuat_at' }
]

// Tab Counts
const tabCounts = computed(() => {
  const counts = { Semua: rtlList.value.length }
  const statusKeys = ['TO_DO', 'ON_PROGRESS', 'REVIEW', 'DONE']
  for (const key of statusKeys) {
    counts[key] = rtlList.value.filter(r => r.status_progress === key).length
  }
  return counts
})

const filterActive = computed(() => {
  return !!(
    searchQuery.value.trim() ||
    activeTab.value !== 'Semua' ||
    selectedPrioritas.value !== 'Semua' ||
    filterDari.value ||
    filterSampai.value
  )
})

// Kombinasi filter server & client
const displayList = computed(() => {
  let list = rtlList.value

  // Filter Status Tab
  if (activeTab.value !== 'Semua') {
    list = list.filter(r => r.status_progress === activeTab.value)
  }

  // Filter Prioritas
  if (selectedPrioritas.value !== 'Semua') {
    list = list.filter(r => r.prioritas === selectedPrioritas.value)
  }

  // Search Filter
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(r => {
      const noDispo = (r.nomor_disposisi || '').toLowerCase()
      const deskripsi = (r.deskripsi_rtl || '').toLowerCase()
      const perihal = (r.perihal_surat || '').toLowerCase()
      const penerima = (r.penerima_names || '').toLowerCase()
      const pembuat = (r.pembuat || '').toLowerCase()
      return (
        noDispo.includes(q) ||
        deskripsi.includes(q) ||
        perihal.includes(q) ||
        penerima.includes(q) ||
        pembuat.includes(q)
      )
    })
  }

  // Filter Tanggal
  if (filterDari.value) {
    list = list.filter(r => {
      const val = dateBy.value === 'dibuat_at' ? r.dibuat_at : r.batas_waktu
      if (!val || val === '0000-00-00') return false
      return val.substring(0, 10) >= filterDari.value
    })
  }

  if (filterSampai.value) {
    list = list.filter(r => {
      const val = dateBy.value === 'dibuat_at' ? r.dibuat_at : r.batas_waktu
      if (!val || val === '0000-00-00') return false
      return val.substring(0, 10) <= filterSampai.value
    })
  }

  return list
})

// Paginated item untuk grid mode
const paginatedGridList = computed(() => {
  return displayList.value.slice(gridFirst.value, gridFirst.value + gridRows.value)
})

const getStatusLabel = (status) => {
  const map = {
    'TO_DO': 'To Do',
    'ON_PROGRESS': 'On Progress',
    'REVIEW': 'Review',
    'DONE': 'Done'
  }
  return map[status] || status
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

const getStatusColor = (status) => {
  const map = {
    'TO_DO': 'bg-surface3 text-textMuted border-border',
    'ON_PROGRESS': 'bg-brandBlueBg text-brandBlue border-brandBlue/30',
    'REVIEW': 'bg-brandYellowBg text-brandYellow border-brandYellow/50',
    'DONE': 'bg-brandGreenBg text-brandGreen border-brandGreen/40'
  }
  return map[status] || 'bg-surface3 text-textMuted border-border'
}

function formatDate(d) {
  if (!d || d === '0000-00-00' || d === '1970-01-01') return '—'
  const dateObj = new Date(d)
  if (isNaN(dateObj)) return '—'
  return dateObj.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function isOverdue(batasWaktu, status) {
  if (!batasWaktu || batasWaktu === '0000-00-00' || status === 'DONE') return false
  const target = new Date(batasWaktu)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return target < today
}

const fetchData = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/rtl')
    rtlList.value = data.data || []
    totalFiltered.value = data.total ?? rtlList.value.length
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const applyFilter = async () => {
  loading.value = true
  gridFirst.value = 0
  try {
    const params = {}
    if (searchQuery.value.trim()) params.q = searchQuery.value.trim()
    if (activeTab.value !== 'Semua') params.status = activeTab.value
    if (selectedPrioritas.value !== 'Semua') params.prioritas = selectedPrioritas.value
    if (filterDari.value) params.tanggal_dari = filterDari.value
    if (filterSampai.value) params.tanggal_sampai = filterSampai.value
    if (dateBy.value) params.date_by = dateBy.value

    const { data } = await api.get('/rtl', { params })
    rtlList.value = data.data || []
    totalFiltered.value = data.total ?? rtlList.value.length
  } catch (e) {
    console.error('Failed to filter RTL', e)
  } finally {
    loading.value = false
  }
}

const resetFilter = () => {
  searchQuery.value = ''
  activeTab.value = 'Semua'
  selectedPrioritas.value = 'Semua'
  filterDari.value = ''
  filterSampai.value = ''
  dateBy.value = 'batas_waktu'
  gridFirst.value = 0
  fetchData()
}

const setTab = (tab) => {
  activeTab.value = tab
  gridFirst.value = 0
}

const fetchDisposisiSelesai = async () => {
  if (!isAdmin.value) return
  try {
    const { data } = await api.get('/rtl/disposisi-selesai')
    disposisiList.value = data.data || []
  } catch (e) {
    console.error('Failed to fetch disposisi selesai', e)
  }
}

onMounted(() => {
  fetchData()
  fetchDisposisiSelesai()
})

const togglePenerima = (userId) => {
  const idx = selectedPenerima.value.indexOf(userId)
  if (idx === -1) selectedPenerima.value.push(userId)
  else selectedPenerima.value.splice(idx, 1)
}

const openModal = async () => {
  form.value = { disposisi_id: '', prioritas: 'Biasa', deskripsi_rtl: '', batas_waktu: '' }
  selectedPenerima.value = []
  submitMsg.value = { type: '', text: '' }
  showModal.value = true

  if (usersList.value.length === 0) {
    try {
      const { data } = await api.get('/users')
      usersList.value = data.data || []
    } catch (e) {
      console.error('Failed to fetch users', e)
    }
  }
}

const handleSubmit = async () => {
  if (!form.value.disposisi_id || !form.value.deskripsi_rtl) {
    submitMsg.value = { type: 'error', text: 'Disposisi dan Deskripsi wajib diisi.' }
    return
  }
  if (selectedPenerima.value.length === 0) {
    submitMsg.value = { type: 'error', text: 'Pilih minimal satu penerima.' }
    return
  }
  
  submitting.value = true
  submitMsg.value = { type: '', text: '' }

  try {
    const payload = { ...form.value, penerima: selectedPenerima.value }
    const { data } = await api.post('/rtl', payload)
    submitMsg.value = { type: 'success', text: 'RTL berhasil dibuat!' }
    rtlList.value.unshift(data.data)
    totalFiltered.value++
    setTimeout(() => { showModal.value = false }, 1500)
  } catch (err) {
    submitMsg.value = { type: 'error', text: err.response?.data?.message || 'Gagal menyimpan RTL.' }
  } finally {
    submitting.value = false
  }
}

const goDetail = (id) => {
  router.push({ name: 'rtl-detail', params: { id } })
}
</script>

<template>
  <div class="page-container animate-fade-in">
    <!-- Header Hero Banner -->
    <div class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap relative z-10">
          <h2 class="page-hero-title">Rencana Tindak Lanjut (RTL)</h2>
        </div>
        <p class="page-hero-sub">
          Pencarian, monitoring progres tindak lanjut, dan evaluasi hasil disposisi RSIG.
        </p>
      </div>
      <Button
        v-if="isAdmin"
        label="Buat RTL"
        icon="pi pi-plus"
        class="relative z-10 !bg-white !text-sidebar !border-0 shadow-glow"
        @click="openModal"
      />
    </div>

    <!-- Tabs Filter Status -->
    <div class="flex gap-2 mb-4 overflow-x-auto pb-1 scrollbar-thin">
      <button
        v-for="tab in tabs"
        :key="tab"
        @click="setTab(tab)"
        class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all whitespace-nowrap border flex items-center gap-1.5"
        :class="activeTab === tab
          ? 'bg-accent text-white border-accent shadow-xs'
          : 'bg-surface hover:bg-surface2 text-textMuted border-border'"
      >
        <span>{{ getStatusLabel(tab) }}</span>
        <span
          class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
          :class="activeTab === tab ? 'bg-white/25 text-white' : 'bg-surface3 text-textDim'"
        >
          {{ tabCounts[tab] }}
        </span>
      </button>
    </div>

    <!-- Panel Pencarian & Filter Canggih -->
    <div class="filter-panel">
      <div class="flex flex-col gap-3.5">
        <!-- Baris 1: Search Box & Prioritas -->
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
          <div class="flex-1">
            <IconField class="w-full">
              <InputIcon class="pi pi-search text-accent" />
              <InputText
                v-model="searchQuery"
                placeholder="Cari nomor disposisi, perihal, deskripsi RTL, penerima, pembuat..."
                class="w-full !bg-surface2 !text-textMain !rounded-xl text-sm"
                @keyup.enter="applyFilter"
              />
            </IconField>
          </div>

          <div class="w-full md:w-52 shrink-0">
            <Select
              v-model="selectedPrioritas"
              :options="prioritasOptions"
              option-label="label"
              option-value="value"
              placeholder="⚡ Semua Prioritas"
              class="w-full !rounded-xl text-sm"
              @change="applyFilter"
            />
          </div>
        </div>

        <!-- Baris 2: Rentang Tanggal & Tombol Aksi -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-border/40">
          <div class="flex flex-wrap items-center gap-2.5">
            <div class="flex items-center gap-1.5 text-xs font-bold text-textMuted uppercase">
              <i class="pi pi-calendar text-brandCyan"></i>
              <span>Tanggal:</span>
            </div>

            <div class="w-36 shrink-0">
              <Select
                v-model="dateBy"
                :options="dateByOptions"
                option-label="label"
                option-value="value"
                class="w-full !text-xs !rounded-lg"
                @change="applyFilter"
              />
            </div>

            <div class="flex items-center gap-2">
              <InputText v-model="filterDari" type="date" class="!bg-surface2 text-xs !py-1.5 !rounded-lg" />
              <span class="text-xs text-textMuted">s/d</span>
              <InputText v-model="filterSampai" type="date" class="!bg-surface2 text-xs !py-1.5 !rounded-lg" />
            </div>

            <div class="flex items-center gap-2">
              <Button label="Cari" icon="pi pi-filter" size="small" class="btn-gradient !py-1.5 !px-3.5" @click="applyFilter" />
              <Button v-if="filterActive" label="Reset Filter" icon="pi pi-times" severity="secondary" outlined size="small" class="!py-1.5 !px-3" @click="resetFilter" />
            </div>
          </div>

          <div class="flex items-center gap-3 ml-auto">
            <!-- Toggle Grid / Table view -->
            <div class="flex items-center bg-surface2 rounded-lg p-0.5 border border-border">
              <button
                type="button"
                @click="viewMode = 'table'"
                class="p-1.5 px-2.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all"
                :class="viewMode === 'table' ? 'bg-surface text-accent shadow-xs' : 'text-textMuted hover:text-textMain'"
                title="Tampilan Tabel"
              >
                <i class="pi pi-table"></i>
                <span class="hidden sm:inline">Tabel</span>
              </button>
              <button
                type="button"
                @click="viewMode = 'grid'"
                class="p-1.5 px-2.5 rounded-md text-xs font-semibold flex items-center gap-1.5 transition-all"
                :class="viewMode === 'grid' ? 'bg-surface text-accent shadow-xs' : 'text-textMuted hover:text-textMain'"
                title="Tampilan Kartu"
              >
                <i class="pi pi-th-large"></i>
                <span class="hidden sm:inline">Kartu</span>
              </button>
            </div>

            <div v-if="!loading" class="text-xs text-textMuted">
              Menampilkan <strong class="text-accent text-sm">{{ displayList.length }}</strong> dari <strong class="text-textMain">{{ totalFiltered }}</strong> RTL
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TAMPILAN TABEL (Default - Sama seperti menu Surat Masuk) -->
    <Card v-if="viewMode === 'table'" class="color-panel glass-card">
      <template #title>
        <div class="flex items-center justify-between font-bold">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-accentGlow/30 flex items-center justify-center">
              <i class="pi pi-list-check text-accent"></i>
            </div>
            <span>Daftar Rencana Tindak Lanjut</span>
          </div>
          <Tag v-if="filterActive" value="FILTER AKTIF" severity="info" class="text-[11px]" />
        </div>
      </template>

      <template #content>
        <DataTable
          :value="displayList"
          :loading="loading"
          striped-rows
          row-hover
          paginator
          :rows="10"
          :rows-per-page-options="[10, 25, 50, 100]"
          currentPageReportTemplate="Menampilkan {first} sampai {last} dari {totalRecords} RTL"
          paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          class="text-sm"
          @row-click="(e) => goDetail(e.data.id)"
        >
          <template #empty>
            <div class="text-center py-14">
              <div class="w-16 h-16 rounded-2xl bg-surface2 flex items-center justify-center mx-auto mb-3">
                <i class="pi pi-inbox text-3xl text-textDim"></i>
              </div>
              <p class="text-textMuted text-sm font-semibold">
                {{ filterActive ? 'Tidak ada RTL yang cocok dengan filter atau kata kunci.' : 'Belum ada Rencana Tindak Lanjut yang terdaftar.' }}
              </p>
              <Button v-if="filterActive" label="Reset Pencarian" icon="pi pi-refresh" severity="secondary" size="small" class="mt-2" @click="resetFilter" />
            </div>
          </template>

          <Column field="nomor_disposisi" header="No. Disposisi" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-accent bg-accentGlow/20 px-2.5 py-1 rounded-md border border-accent/30 inline-block">
                {{ data.nomor_disposisi }}
              </span>
            </template>
          </Column>

          <Column field="deskripsi_rtl" header="Deskripsi RTL" style="min-width: 230px">
            <template #body="{ data }">
              <span class="font-semibold text-textMain line-clamp-2 cursor-help" :title="data.deskripsi_rtl">
                {{ data.deskripsi_rtl }}
              </span>
            </template>
          </Column>

          <Column field="perihal_surat" header="Perihal / Ref Disposisi" style="min-width: 200px">
            <template #body="{ data }">
              <div class="flex items-center gap-1.5 text-textMuted max-w-[220px] truncate" :title="data.perihal_surat">
                <i class="pi pi-file text-xs text-textDim shrink-0"></i>
                <span class="truncate text-xs">{{ data.perihal_surat }}</span>
              </div>
            </template>
          </Column>

          <Column field="penerima_names" header="Penerima RTL" style="min-width: 170px">
            <template #body="{ data }">
              <div class="flex items-center gap-1.5 max-w-[190px] truncate" :title="data.penerima_names">
                <span class="text-xs text-textMain font-medium truncate">
                  {{ data.penerima_names || '—' }}
                </span>
              </div>
            </template>
          </Column>

          <Column field="prioritas" header="Prioritas" style="width: 110px">
            <template #body="{ data }">
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border inline-block"
                :class="getPrioritasColor(data.prioritas)">
                {{ data.prioritas || 'Biasa' }}
              </span>
            </template>
          </Column>

          <Column field="batas_waktu" header="Batas Waktu" style="width: 130px">
            <template #body="{ data }">
              <div class="flex flex-col">
                <span class="text-xs font-semibold"
                  :class="isOverdue(data.batas_waktu, data.status_progress) ? 'text-brandRed font-bold' : 'text-textMain'">
                  {{ formatDate(data.batas_waktu) }}
                </span>
                <span v-if="isOverdue(data.batas_waktu, data.status_progress)" class="text-[9px] text-brandRed font-extrabold tracking-wide uppercase">
                  Terlambat
                </span>
              </div>
            </template>
          </Column>

          <Column field="status_progress" header="Status Progress" style="width: 130px">
            <template #body="{ data }">
              <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full uppercase border inline-block"
                :class="getStatusColor(data.status_progress)">
                {{ getStatusLabel(data.status_progress) }}
              </span>
            </template>
          </Column>

          <Column header="Aksi" style="width: 100px" class="text-center">
            <template #body="{ data }">
              <Button
                icon="pi pi-arrow-right"
                label="Detail"
                size="small"
                severity="secondary"
                outlined
                class="!py-1 !px-2.5 !text-xs hover:!bg-accent hover:!text-white hover:!border-accent transition-colors"
                @click.stop="goDetail(data.id)"
              />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- TAMPILAN KARTU (Grid View) -->
    <div v-else-if="viewMode === 'grid'">
      <div v-if="displayList.length === 0" class="bg-surface border border-border rounded-xl p-10 text-center flex flex-col items-center justify-center">
        <div class="text-4xl mb-3 opacity-30">📭</div>
        <h3 class="text-lg font-bold text-textMain mb-1">Tidak ada RTL</h3>
        <p class="text-sm text-textMuted">Belum ada data yang cocok dengan kriteria filter Anda.</p>
      </div>

      <div v-else class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <div
            v-for="rtl in paginatedGridList"
            :key="rtl.id" 
            class="bg-surface border border-border rounded-xl p-5 hover:border-accent hover:shadow-card transition-all duration-200 flex flex-col cursor-pointer"
            @click="goDetail(rtl.id)"
          >
            <div class="flex justify-between items-start mb-3 gap-2">
              <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-accent font-mono bg-accentGlow/20 px-2 py-0.5 rounded border border-accent/30">{{ rtl.nomor_disposisi }}</span>
                <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase border" :class="getPrioritasColor(rtl.prioritas)">
                  {{ rtl.prioritas || 'Biasa' }}
                </span>
              </div>
              <div class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border" :class="getStatusColor(rtl.status_progress)">
                {{ getStatusLabel(rtl.status_progress) }}
              </div>
            </div>
            
            <div class="text-sm font-semibold text-textMain line-clamp-2 mb-3 cursor-help" :title="rtl.deskripsi_rtl">
              {{ rtl.deskripsi_rtl }}
            </div>
            
            <div class="text-xs text-textMuted mb-4 flex-1">
              <span class="opacity-60">Ref:</span> {{ rtl.perihal_surat }}
            </div>

            <div class="flex items-baseline justify-between text-[11px] text-textMuted pt-3 border-t border-border">
              <div class="flex flex-col gap-0.5 flex-1 min-w-0 pr-3">
                <span class="opacity-60">Penerima ({{ rtl.penerima_names ? rtl.penerima_names.split(',').length : 0 }})</span>
                <span class="font-medium text-textMain truncate leading-tight" :title="rtl.penerima_names">{{ rtl.penerima_names || '—' }}</span>
              </div>
              <div class="flex flex-col gap-0.5 text-right shrink-0">
                <span class="opacity-60">Batas Waktu</span>
                <span class="font-medium" :class="isOverdue(rtl.batas_waktu, rtl.status_progress) ? 'text-brandRed font-bold' : 'text-textMain'">
                  {{ formatDate(rtl.batas_waktu) }}
                </span>
              </div>
            </div>

            <!-- Tombol Detail -->
            <div class="mt-4 pt-3 border-t border-border/50">
              <button
                @click.stop="goDetail(rtl.id)"
                class="w-full py-1.5 text-xs font-bold rounded-lg border border-border/50 text-textMain hover:bg-surface2 hover:border-accent hover:text-accent transition-all flex items-center justify-center gap-1.5"
              >
                <i class="pi pi-eye text-xs"></i> Lihat Detail
              </button>
            </div>
          </div>
        </div>

        <!-- Paginator untuk Mode Grid -->
        <Paginator
          v-if="displayList.length > 0"
          v-model:first="gridFirst"
          v-model:rows="gridRows"
          :totalRecords="displayList.length"
          :rowsPerPageOptions="[10, 25, 50, 100]"
          currentPageReportTemplate="Menampilkan {first} sampai {last} dari {totalRecords} RTL"
          template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          class="glass-card mt-4 border border-border/60"
        />
      </div>
    </div>
  </div>

  <!-- Modal Buat RTL -->
  <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModal = false"></div>
    <div class="bg-surface w-full max-w-2xl rounded-2xl shadow-2xl relative flex flex-col max-h-[90vh] animate-[fadeIn_0.2s_ease]">
      
      <div class="p-5 px-6 border-b border-border flex justify-between items-center bg-surface2/50 rounded-t-2xl">
        <h3 class="text-lg font-bold text-textMain flex items-center gap-2">
          <span>📋</span> Buat Rencana Tindak Lanjut
        </h3>
        <button @click="showModal = false" class="text-textMuted hover:text-brandRed text-2xl leading-none">&times;</button>
      </div>

      <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
        <div v-if="submitMsg.text" class="mb-5 p-3 rounded-lg text-sm font-semibold border"
          :class="submitMsg.type === 'error' ? 'bg-brandRedBg text-brandRed border-brandRed/20' : 'bg-brandGreenBg text-brandGreen border-brandGreen/20'">
          {{ submitMsg.text }}
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Pilih Disposisi Selesai *</label>
            <select v-model="form.disposisi_id" required class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm focus:border-accent outline-none text-textMain appearance-none">
              <option value="" disabled>-- Pilih Disposisi --</option>
              <option v-for="disp in disposisiList" :key="disp.id" :value="disp.id">
                [{{ disp.nomor_disposisi }}] {{ disp.perihal }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Deskripsi RTL *</label>
            <textarea v-model="form.deskripsi_rtl" required rows="3" placeholder="Tuliskan detail Rencana Tindak Lanjut..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm focus:border-accent outline-none text-textMain"></textarea>
          </div>

          <div class="grid grid-cols-[1fr_200px] gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Prioritas</label>
              <select v-model="form.prioritas" class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm focus:border-accent outline-none text-textMain appearance-none">
                <option value="Biasa">Biasa</option>
                <option value="Penting">Penting</option>
                <option value="Segera">Segera</option>
                <option value="Rahasia">Rahasia</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Batas Waktu</label>
              <input type="date" v-model="form.batas_waktu"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm focus:border-accent outline-none text-textMain appearance-none" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Penerima RTL *</label>
            <div class="bg-surface2 border border-border rounded-xl p-3 max-h-48 overflow-y-auto custom-scrollbar flex flex-col gap-2">
              <label v-for="u in usersList" :key="u.id" 
                class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-all border border-transparent"
                :class="selectedPenerima.includes(u.id) ? 'bg-brandGreenBg border-brandGreen/40' : 'hover:bg-surface'">
                <input type="checkbox" :value="u.id" @change="togglePenerima(u.id)"
                  :checked="selectedPenerima.includes(u.id)"
                  class="w-4 h-4 rounded text-brandGreen focus:ring-brandGreen/30 accent-brandGreen bg-surface border-border" />
                <div>
                  <div class="text-sm font-bold text-textMain leading-tight">{{ u.nama_lengkap }}</div>
                  <div class="text-[11px] text-textMuted mt-0.5">{{ u.jabatan || 'Staff' }}</div>
                </div>
              </label>
            </div>
            <div class="text-[11px] text-textMuted mt-2">{{ selectedPenerima.length }} penerima dipilih</div>
          </div>
        </form>
      </div>

      <div class="p-5 px-6 border-t border-border flex justify-end gap-3 bg-surface2/50 rounded-b-2xl shrink-0">
        <button type="button" @click="showModal = false" :disabled="submitting"
          class="px-5 py-2 text-sm font-bold text-textMuted bg-surface hover:bg-surface3 border border-border rounded-lg transition-all">
          Batal
        </button>
        <button type="button" @click="handleSubmit" :disabled="submitting"
          class="px-6 py-2 text-sm font-bold text-white bg-brandGreen hover:bg-brandGreen/90 disabled:opacity-50 rounded-lg shadow-sm transition-all flex items-center gap-2">
          <span v-if="submitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          Simpan RTL
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: var(--color-border);
  border-radius: 10px;
}
</style>

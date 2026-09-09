<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/api/axios'
import { useAuthStore } from '@/stores/auth'
import { useToast } from 'primevue/usetoast'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import SelectButton from 'primevue/selectbutton'
import Checkbox from 'primevue/checkbox'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import LembarDisposisiModal from '@/components/disposisi/LembarDisposisiModal.vue'

const toast  = useToast()
const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()

const showModal  = ref(false)
const editMode   = ref(false)
const editId     = ref(null)
const submitting = ref(false)
const submitError = ref('')

// Lembar Disposisi Report state
const showReportModal = ref(false)
const selectedReportDisposisi = ref(null)
const selectedReportTimeline = ref([])
const loadingReport = ref(false)

const openReportModal = async (disp) => {
  try {
    loadingReport.value = true
    const [detailRes, timelineRes] = await Promise.all([
      api.get(`/disposisi/${disp.id}`),
      api.get(`/progress/disposisi/${disp.id}`)
    ])
    selectedReportDisposisi.value = detailRes.data.data
    selectedReportTimeline.value = timelineRes.data.data || []
    showReportModal.value = true
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat dokumen lembar disposisi', life: 3000 })
  } finally {
    loadingReport.value = false
  }
}

const tabs = ['Semua', 'Diproses', 'Menunggu', 'Selesai', 'Overdue']
const activeTab = ref('Semua')
const disposisiList = ref([])
const loading = ref(true)

// Filter & Search
const searchQuery      = ref('')
const filterPrioritas  = ref('SEMUA')
const filterDari       = ref('')
const filterSampai     = ref('')

// Delete state
const showDeleteDialog = ref(false)
const deleteTarget     = ref(null)
const deleting         = ref(false)

// Users & Surat list for create/edit
const usersList           = ref([])
const selectedPenerima    = ref([])  // array of user ids
const penerimaSearch      = ref('')
const penerimaRoleFilter  = ref('SEMUA')
const suratList           = ref([])
const suratSearch         = ref('')

// Filter pencarian staf penerima
const filteredUsers = computed(() => {
  let list = usersList.value || []

  if (penerimaRoleFilter.value && penerimaRoleFilter.value !== 'SEMUA') {
    list = list.filter(u => u.role === penerimaRoleFilter.value)
  }

  if (penerimaSearch.value.trim()) {
    const q = penerimaSearch.value.trim().toLowerCase()
    list = list.filter(u => {
      const nama = (u.nama_lengkap || '').toLowerCase()
      const nip = (u.nip || '').toLowerCase()
      const jabatan = (u.jabatan || '').toLowerCase()
      const unit = (u.unit || '').toLowerCase()
      const role = (u.role || '').toLowerCase()
      return nama.includes(q) || nip.includes(q) || jabatan.includes(q) || unit.includes(q) || role.includes(q)
    })
  }

  return list
})

// Objek staf yang terpilih untuk chip pratinjau
const selectedUsersObjects = computed(() => {
  if (!usersList.value || selectedPenerima.value.length === 0) return []
  const idSet = new Set(selectedPenerima.value.map(Number))
  return usersList.value.filter(u => idSet.has(Number(u.id)))
})

// Helper pilih semua hasil pencarian / batalkan
const toggleSelectFiltered = () => {
  const currentFilteredIds = filteredUsers.value.map(u => Number(u.id))
  if (currentFilteredIds.length === 0) return
  const allSelected = currentFilteredIds.every(id => selectedPenerima.value.includes(id))

  if (allSelected) {
    selectedPenerima.value = selectedPenerima.value.filter(id => !currentFilteredIds.includes(id))
  } else {
    const newSet = new Set([...selectedPenerima.value, ...currentFilteredIds])
    selectedPenerima.value = Array.from(newSet)
  }
}

const clearAllPenerima = () => {
  selectedPenerima.value = []
}

const removePenerima = (userId) => {
  selectedPenerima.value = selectedPenerima.value.filter(id => Number(id) !== Number(userId))
}

// Check if user can manage (create/edit/delete) disposisi
const canManage = computed(() => {
  return ['ADMIN', 'DIREKTUR'].includes(auth.role)
})

// Pencarian dan filter surat masuk untuk disposisi
const loadingSuratSearch = ref(false)
let searchSuratTimeout = null

const onSuratSearchInput = (val) => {
  if (searchSuratTimeout) clearTimeout(searchSuratTimeout)
  const q = (val || '').trim()
  if (!q || q.length < 2) return

  searchSuratTimeout = setTimeout(async () => {
    try {
      loadingSuratSearch.value = true
      const { data } = await api.get('/disposisi/surat-options', { params: { q } })
        .catch(() => api.get('/surat', { params: { q } }))
      if (data?.data && Array.isArray(data.data)) {
        const existingIds = new Set(suratList.value.map(s => Number(s.id)))
        const newItems = data.data.filter(s => !existingIds.has(Number(s.id)))
        if (newItems.length > 0) {
          suratList.value = [...newItems, ...suratList.value]
        }
      }
    } catch {
      // silent
    } finally {
      loadingSuratSearch.value = false
    }
  }, 350)
}

watch(suratSearch, (newVal) => {
  onSuratSearchInput(newVal)
})

const filteredSurat = computed(() => {
  if (!suratSearch.value.trim()) return suratList.value
  const q = suratSearch.value.trim().toLowerCase()
  return suratList.value.filter(s =>
    (s.nomor_agenda || '').toLowerCase().includes(q) ||
    (s.nomor_surat || '').toLowerCase().includes(q) ||
    (s.perihal || '').toLowerCase().includes(q) ||
    (s.asal_surat || '').toLowerCase().includes(q) ||
    (s.keterangan || '').toLowerCase().includes(q) ||
    (s.nama_folder || '').toLowerCase().includes(q)
  )
})

// Objek surat masuk terpilih untuk pratinjau
const selectedSuratObject = computed(() => {
  if (!formDisposisi.value.surat_masuk_id) return null
  return suratList.value.find(s => Number(s.id) === Number(formDisposisi.value.surat_masuk_id)) || null
})

const masterJabatanList = ref([])

const formDisposisi = ref({
  surat_masuk_id: '',
  isi_disposisi: '',
  prioritas: 'NORMAL',
  batas_waktu: '',
  catatan_direktur: '',
  is_berjenjang: 0
})

// Pratinjau simulasi rantai hierarki saat mode berjenjang aktif
const hierarkiPreview = computed(() => {
  if (!formDisposisi.value.is_berjenjang || selectedUsersObjects.value.length === 0) return []
  
  const usersWithLevel = selectedUsersObjects.value.map(u => {
    const mj = masterJabatanList.value.find(j => j.id == u.jabatan_id)
    let lv = mj ? Number(mj.level) : 1
    if (!mj) {
      if (u.role === 'STAF') lv = 1
      else if (u.role === 'PEJABAT') lv = 3
      else if (u.role === 'DIREKTUR') lv = 5
      else if (u.role === 'ADMIN') lv = 99
    }
    return {
      ...u,
      level: lv,
      jabatan_label: mj?.nama || u.jabatan || u.role
    }
  })

  // Kelompokkan penerima berdasarkan level
  const groups = {}
  usersWithLevel.forEach(u => {
    if (!groups[u.level]) groups[u.level] = []
    groups[u.level].push(u)
  })

  return Object.keys(groups).sort((a, b) => Number(a) - Number(b)).map(lv => ({
    level: Number(lv),
    users: groups[lv]
  }))
})

const statusSeverity = {
  PROSES: 'info',
  TUNGGU: 'warn',
  DITERIMA: 'warn',
  SELESAI: 'success',
  OVERDUE: 'danger',
  AKTIF: 'info',
}

const statusLabel = {
  PROSES: 'Diproses',
  TUNGGU: 'Menunggu',
  DITERIMA: 'Menunggu',
  SELESAI: 'Selesai',
  OVERDUE: 'Overdue',
  AKTIF: 'Aktif',
}

const filterActive = computed(() => {
  return !!(
    searchQuery.value.trim() ||
    (filterPrioritas.value && filterPrioritas.value !== 'SEMUA') ||
    filterDari.value ||
    filterSampai.value
  )
})

// Filtered list (kombinasi Tab Status + Search Box + Filter Prioritas + Rentang Tanggal)
const filteredList = computed(() => {
  let list = disposisiList.value

  // 1. Filter Tab
  const map = { 'Diproses': 'PROSES', 'Menunggu': 'TUNGGU', 'Selesai': 'SELESAI', 'Overdue': 'OVERDUE' }
  if (activeTab.value !== 'Semua') {
    const key = map[activeTab.value]
    list = list.filter(d => {
      const st = d.status_display || d.status_global
      if (key === 'TUNGGU') return st === 'TUNGGU' || st === 'DITERIMA' || st === 'AKTIF'
      return st === key
    })
  }

  // 2. Filter Prioritas
  if (filterPrioritas.value && filterPrioritas.value !== 'SEMUA') {
    list = list.filter(d => d.prioritas === filterPrioritas.value)
  }

  // 3. Filter Search Keyword
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(d => {
      const noDisp   = (d.nomor_disposisi || '').toLowerCase()
      const noSurat  = (d.nomor_surat || '').toLowerCase()
      const perihal  = (d.perihal || '').toLowerCase()
      const asal     = (d.asal_surat || '').toLowerCase()
      const isi      = (d.isi_disposisi || '').toLowerCase()
      const pembuat  = (d.pembuat || '').toLowerCase()
      const penerima = (d.nama_penerima_list || '').toLowerCase()
      return (
        noDisp.includes(q) ||
        noSurat.includes(q) ||
        perihal.includes(q) ||
        asal.includes(q) ||
        isi.includes(q) ||
        pembuat.includes(q) ||
        penerima.includes(q)
      )
    })
  }

  // 4. Filter Rentang Tanggal Disposisi
  if (filterDari.value) {
    list = list.filter(d => d.tanggal_disposisi && d.tanggal_disposisi >= filterDari.value)
  }
  if (filterSampai.value) {
    list = list.filter(d => d.tanggal_disposisi && d.tanggal_disposisi.slice(0, 10) <= filterSampai.value)
  }

  return list
})

// Jumlah per tab untuk badge
const tabCounts = computed(() => {
  const counts = { Semua: disposisiList.value.length }
  const map = { 'Diproses': 'PROSES', 'Menunggu': 'TUNGGU', 'Selesai': 'SELESAI', 'Overdue': 'OVERDUE' }
  for (const [label, key] of Object.entries(map)) {
    counts[label] = disposisiList.value.filter(d => {
      const st = d.status_display || d.status_global
      if (key === 'TUNGGU') return st === 'TUNGGU' || st === 'DITERIMA' || st === 'AKTIF'
      return st === key
    }).length
  }
  return counts
})

// Sinkronkan ?tab= query param
function syncTabFromQuery() {
  const q = route.query.tab
  if (q && tabs.includes(q)) activeTab.value = q
}

const loadDisposisi = async () => {
  loading.value = true
  try {
    const params = {}
    if (searchQuery.value.trim()) params.q = searchQuery.value.trim()
    if (filterPrioritas.value && filterPrioritas.value !== 'SEMUA') params.prioritas = filterPrioritas.value
    if (filterDari.value) params.tanggal_dari = filterDari.value
    if (filterSampai.value) params.tanggal_sampai = filterSampai.value

    const { data } = await api.get('/disposisi', { params })
    disposisiList.value = data.data || []
  } catch (e) { /* silent */ } finally { loading.value = false }
}

const resetFilter = () => {
  searchQuery.value = ''
  filterPrioritas.value = 'SEMUA'
  filterDari.value = ''
  filterSampai.value = ''
  loadDisposisi()
}

onMounted(async () => {
  syncTabFromQuery()
  await loadDisposisi()
})

watch(() => route.query.tab, syncTabFromQuery)

// Load data master (surat, users, jabatan)
const loadFormData = async () => {
  const promises = []
  if (suratList.value.length === 0) {
    promises.push(
      api.get('/disposisi/surat-options')
        .catch(() => api.get('/surat'))
        .then(({ data }) => { suratList.value = data.data || [] })
        .catch(() => {})
    )
  }
  if (usersList.value.length === 0) {
    promises.push(
      api.get('/disposisi/penerima-options')
        .catch(() => api.get('/users'))
        .then(({ data }) => { usersList.value = data.data || [] })
        .catch(() => {})
    )
  }
  if (masterJabatanList.value.length === 0) {
    promises.push(
      api.get('/jabatan')
        .then(({ data }) => { masterJabatanList.value = data.data || [] })
        .catch(() => {})
    )
  }
  await Promise.all(promises)
}

// ── Buka Modal Tambah ─────────────────────────────────────────────
const openModal = async () => {
  editMode.value = false
  editId.value   = null
  submitError.value = ''
  suratSearch.value = ''
  penerimaSearch.value = ''
  penerimaRoleFilter.value = 'SEMUA'
  selectedPenerima.value = []
  formDisposisi.value = {
    surat_masuk_id: '',
    isi_disposisi: '',
    prioritas: 'NORMAL',
    batas_waktu: '',
    catatan_direktur: '',
    is_berjenjang: 0
  }
  showModal.value = true
  await loadFormData()
}

// ── Buka Modal Edit ───────────────────────────────────────────────
const openEdit = async (item) => {
  editMode.value = true
  editId.value   = item.id
  submitError.value = ''
  suratSearch.value = ''
  penerimaSearch.value = ''
  penerimaRoleFilter.value = 'SEMUA'
  selectedPenerima.value = []

  formDisposisi.value = {
    surat_masuk_id: item.surat_masuk_id || '',
    isi_disposisi: item.isi_disposisi || '',
    prioritas: item.prioritas || 'NORMAL',
    batas_waktu: item.batas_waktu ? item.batas_waktu.slice(0, 10) : '',
    catatan_direktur: item.catatan_direktur || '',
    is_berjenjang: Number(item.is_berjenjang || 0)
  }

  showModal.value = true
  await loadFormData()

  // Ambil detail lengkap termasuk penerima
  try {
    const { data } = await api.get(`/disposisi/${item.id}`)
    if (data.data) {
      const d = data.data
      formDisposisi.value.surat_masuk_id   = d.surat_masuk_id || item.surat_masuk_id
      formDisposisi.value.isi_disposisi    = d.isi_disposisi || ''
      formDisposisi.value.prioritas        = d.prioritas || 'NORMAL'
      formDisposisi.value.batas_waktu      = d.batas_waktu ? d.batas_waktu.slice(0, 10) : ''
      formDisposisi.value.catatan_direktur = d.catatan_direktur || ''
      formDisposisi.value.is_berjenjang    = Number(d.is_berjenjang ?? item.is_berjenjang ?? 0)
      if (d.penerima && Array.isArray(d.penerima)) {
        selectedPenerima.value = d.penerima.map(p => Number(p.user_id))
      }
    }
  } catch (err) { /* silent */ }
}

// ── Submit Create / Update ────────────────────────────────────────
const handleSubmit = async () => {
  if (!formDisposisi.value.isi_disposisi || (!editMode.value && !formDisposisi.value.surat_masuk_id)) {
    submitError.value = 'Mohon lengkapi surat masuk dan instruksi disposisi.'
    return
  }
  if (selectedPenerima.value.length === 0) {
    submitError.value = 'Pilih minimal 1 penerima disposisi.'
    return
  }

  submitting.value = true
  submitError.value = ''
  try {
    const payload = {
      ...formDisposisi.value,
      penerima_ids: selectedPenerima.value
    }

    if (editMode.value) {
      await api.put(`/disposisi/${editId.value}`, payload)
      toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Disposisi berhasil diperbarui.', life: 3000 })
    } else {
      await api.post('/disposisi', payload)
      toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Disposisi berhasil dibuat dan dikirim.', life: 3000 })
    }

    showModal.value = false
    await loadDisposisi()
  } catch (err) {
    if (err.code === 'ECONNABORTED' || err.message?.includes('timeout')) {
      toast.add({ severity: 'info', summary: 'Sedang Diproses', detail: 'Disposisi berhasil dikirim dan notifikasi email sedang diproses di server.', life: 5000 })
      showModal.value = false
      await loadDisposisi()
    } else {
      submitError.value = err.response?.data?.message || 'Gagal menyimpan disposisi.'
    }
  } finally {
    submitting.value = false
  }
}


// ── Delete Handlers ───────────────────────────────────────────────
const confirmDelete = (item) => {
  deleteTarget.value = item
  showDeleteDialog.value = true
}

const handleDelete = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await api.delete(`/disposisi/${deleteTarget.value.id}`)
    showDeleteDialog.value = false
    toast.add({ severity: 'success', summary: 'Terhapus', detail: 'Disposisi berhasil dihapus.', life: 3000 })
    deleteTarget.value = null
    await loadDisposisi()
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus disposisi.')
  } finally {
    deleting.value = false
  }
}

const goDetail = (id) => router.push({ name: 'disposisi-detail', params: { id } })

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
}
</script>

<template>
  <div class="page-container animate-fade-in">
    <!-- Hero Header -->
    <div
      class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4"
      style="background: linear-gradient(135deg, #1565c0 0%, #1976d2 50%, #42a5f5 100%);"
    >
      <div>
        <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1 relative z-10">Manajemen</p>
        <h2 class="page-hero-title">Disposisi</h2>
        <p class="page-hero-sub">Pencarian, pemantauan status, dan pengelolaan instruksi disposisi Direktur.</p>
      </div>
      <Button
        v-if="canManage"
        label="Tulis Disposisi"
        icon="pi pi-pencil"
        class="relative z-10 !bg-white !text-brandBlue !border-0 shadow-glow font-bold"
        @click="openModal"
      />
    </div>

    <!-- Panel Pencarian & Filter Canggih -->
    <div class="filter-panel mb-4">
      <div class="flex flex-col gap-3.5">
        <!-- Baris 1: Search Box & Filter Prioritas -->
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
          <div class="flex-1">
            <IconField class="w-full">
              <InputIcon class="pi pi-search text-accent" />
              <InputText
                v-model="searchQuery"
                placeholder="Cari nomor disposisi, no. surat, perihal, asal surat, penerima, atau instruksi..."
                class="w-full !bg-surface2 !text-textMain !rounded-xl text-sm"
                @keyup.enter="loadDisposisi"
              />
            </IconField>
          </div>

          <div class="w-full md:w-56 shrink-0">
            <Select
              v-model="filterPrioritas"
              :options="[
                { label: '⚡ Semua Prioritas', value: 'SEMUA' },
                { label: 'Biasa', value: 'BIASA' },
                { label: 'Normal', value: 'NORMAL' },
                { label: 'Segera / Urgent', value: 'URGENT' },
              ]"
              option-label="label"
              option-value="value"
              placeholder="Filter Prioritas"
              class="w-full !rounded-xl text-sm"
            />
          </div>
        </div>

        <!-- Baris 2: Rentang Tanggal & Tombol Aksi -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-border/40">
          <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1.5 text-xs font-bold text-textMuted uppercase">
              <i class="pi pi-calendar text-brandPurple"></i>
              <span>Tanggal Disposisi:</span>
            </div>

            <div class="flex items-center gap-2">
              <InputText v-model="filterDari" type="date" class="!bg-surface2 text-xs !py-1.5 !rounded-lg" />
              <span class="text-xs text-textMuted">s/d</span>
              <InputText v-model="filterSampai" type="date" class="!bg-surface2 text-xs !py-1.5 !rounded-lg" />
            </div>

            <div class="flex items-center gap-2">
              <Button label="Cari" icon="pi pi-filter" size="small" class="btn-gradient !py-1.5 !px-3.5" @click="loadDisposisi" />
              <Button
                v-if="filterActive"
                label="Reset Filter"
                icon="pi pi-times"
                severity="secondary"
                outlined
                size="small"
                class="!py-1.5 !px-3"
                @click="resetFilter"
              />
            </div>
          </div>

          <div v-if="!loading" class="text-xs text-textMuted ml-auto">
            Menampilkan <strong class="text-accent text-sm">{{ filteredList.length }}</strong> dari <strong class="text-textMain">{{ disposisiList.length }}</strong> disposisi
          </div>
        </div>
      </div>
    </div>

    <!-- Tab Filter Status -->
    <div class="flex items-center gap-2 mb-4 overflow-x-auto pb-1">
      <button
        v-for="t in tabs"
        :key="t"
        class="px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2 border whitespace-nowrap"
        :class="activeTab === t ? 'bg-brandBlue text-white border-brandBlue shadow-sm' : 'bg-surface text-textMuted border-border hover:bg-surface2 hover:text-textMain'"
        @click="activeTab = t"
      >
        <span>{{ t }}</span>
        <span
          class="px-1.5 py-0.2 rounded-full text-[10px]"
          :class="activeTab === t ? 'bg-white/20 text-white' : 'bg-surface2 text-textMuted'"
        >
          {{ tabCounts[t] ?? 0 }}
        </span>
      </button>
    </div>

    <!-- Tabel Daftar Disposisi -->
    <Card class="color-panel glass-card">
      <template #title>
        <div class="flex items-center justify-between font-bold">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-brandPurpleBg flex items-center justify-center">
              <i class="pi pi-send text-brandPurple"></i>
            </div>
            <span>Daftar Disposisi</span>
          </div>
          <Tag v-if="filterActive" value="FILTER AKTIF" severity="info" class="text-[11px]" />
        </div>
      </template>

      <template #content>
        <DataTable
          :value="filteredList"
          :loading="loading"
          striped-rows
          row-hover
          paginator
          :rows="10"
          :rows-per-page-options="[10, 25, 50]"
          class="text-sm"
          @row-click="(e) => goDetail(e.data.id)"
        >
          <template #empty>
            <div class="text-center py-14">
              <div class="w-16 h-16 rounded-2xl bg-surface2 flex items-center justify-center mx-auto mb-3">
                <i class="pi pi-send text-3xl text-textDim"></i>
              </div>
              <p class="text-textMuted text-sm font-semibold">
                {{ filterActive ? 'Tidak ada disposisi yang sesuai dengan filter pencarian.' : 'Belum ada data disposisi.' }}
              </p>
              <Button v-if="filterActive" label="Reset Pencarian" icon="pi pi-refresh" severity="secondary" size="small" class="mt-2" @click="resetFilter" />
            </div>
          </template>

          <Column field="nomor_disposisi" header="No. Disposisi" style="width: 140px">
            <template #body="{ data }">
              <div class="flex flex-col gap-1 items-start">
                <span class="font-mono text-xs font-bold text-brandPurple bg-brandPurpleBg px-2 py-0.5 rounded-md">
                  {{ data.nomor_disposisi }}
                </span>
                <span v-if="Number(data.is_berjenjang) === 1" class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-600 border border-amber-500/20 flex items-center gap-1">
                  <i class="pi pi-sitemap text-[9px]"></i> Berjenjang
                </span>
              </div>
            </template>
          </Column>

          <Column field="perihal" header="Perihal / Surat" style="min-width: 220px">
            <template #body="{ data }">
              <div>
                <span class="font-semibold text-textMain max-w-[260px] truncate block" :title="data.perihal">
                  {{ data.perihal }}
                </span>
                <span class="text-[11px] text-textMuted block truncate">No. {{ data.nomor_surat || '—' }}</span>
              </div>
            </template>
          </Column>

          <Column field="asal_surat" header="Asal Surat" style="min-width: 140px">
            <template #body="{ data }">
              <span class="text-textMain text-xs font-medium">{{ data.asal_surat }}</span>
            </template>
          </Column>

          <Column header="Penerima" style="min-width: 160px">
            <template #body="{ data }">
              <span v-if="data.nama_penerima_list" class="text-xs text-textMain font-medium truncate block max-w-[180px]" :title="data.nama_penerima_list">
                {{ data.nama_penerima_list }}
              </span>
              <span v-else class="text-xs text-textDim">—</span>
            </template>
          </Column>

          <Column header="Prioritas" style="width: 110px">
            <template #body="{ data }">
              <Tag
                :value="data.prioritas"
                :severity="(data.prioritas === 'URGENT' || data.prioritas === 'TINGGI') ? 'danger' : 'secondary'"
                class="text-[11px]"
              />
            </template>
          </Column>

          <Column header="Status" style="width: 110px">
            <template #body="{ data }">
              <Tag
                :value="statusLabel[data.status_display || data.status_global] || data.status_global"
                :severity="statusSeverity[data.status_display || data.status_global] || 'secondary'"
                class="text-[11px]"
              />
            </template>
          </Column>

          <Column header="Batas Waktu" style="width: 115px">
            <template #body="{ data }">
              <span class="text-xs font-mono" :class="data.status_display === 'OVERDUE' ? 'text-red-500 font-bold' : 'text-textMuted'">
                {{ formatDate(data.batas_waktu) }}
              </span>
            </template>
          </Column>

          <Column header="Aksi" style="width: 155px; text-align: center">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5" @click.stop>
                <!-- Tombol Lembar Disposisi (Khusus status SELESAI atau untuk melihat lembar disposisi resmi) -->
                <Button
                  v-if="data.status_display === 'SELESAI' || data.status_global === 'SELESAI'"
                  icon="pi pi-print"
                  size="small"
                  class="!w-8 !h-8 !p-0 !rounded-lg !bg-emerald-50 !text-emerald-700 !border-emerald-300 hover:!bg-emerald-100 shadow-2xs"
                  v-tooltip.top="'Cetak Lembar Disposisi'"
                  @click.stop="openReportModal(data)"
                />
                <Button
                  icon="pi pi-arrow-right"
                  size="small"
                  class="!w-8 !h-8 !p-0 !rounded-lg !bg-brandBlueBg !text-brandBlue !border-brandBlue/20 hover:!bg-brandBlue/20"
                  v-tooltip.top="'Lihat Detail'"
                  @click.stop="goDetail(data.id)"
                />
                <Button
                  v-if="canManage"
                  icon="pi pi-pencil"
                  size="small"
                  class="!w-8 !h-8 !p-0 !rounded-lg !bg-brandGreenBg !text-brandGreen !border-brandGreen/20 hover:!bg-brandGreen/20"
                  v-tooltip.top="'Edit Disposisi'"
                  @click.stop="openEdit(data)"
                />
                <Button
                  v-if="canManage"
                  icon="pi pi-trash"
                  size="small"
                  class="!w-8 !h-8 !p-0 !rounded-lg !bg-brandRedBg !text-brandRed !border-brandRed/20 hover:!bg-brandRed/20"
                  v-tooltip.top="'Hapus Disposisi'"
                  @click.stop="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Dialog Form (Tulis / Edit Disposisi) -->
    <Dialog
      v-model:visible="showModal"
      modal
      :header="editMode ? 'Edit Disposisi' : 'Tulis Disposisi Baru'"
      :style="{ width: 'min(660px, 95vw)' }"
      :draggable="false"
      class="!rounded-2xl"
    >
      <form @submit.prevent="handleSubmit" class="flex flex-col gap-4 py-2">
        <Message v-if="submitError" severity="error" :closable="false">{{ submitError }}</Message>

        <!-- Pilih Surat Masuk (Hanya mode tambah atau info di mode edit) -->
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <label class="text-xs font-bold text-textMuted uppercase flex items-center gap-1.5">
              <i class="pi pi-inbox text-brandBlue"></i>
              <span>Surat Masuk Terkait <span class="text-red-500">*</span></span>
            </label>
            <span v-if="!editMode && filteredSurat.length > 0" class="text-[11px] text-textMuted">
              {{ filteredSurat.length }} surat tersedia
            </span>
          </div>

          <template v-if="!editMode">
            <!-- Search Box cepat -->
            <div class="relative">
              <IconField class="w-full">
                <InputIcon :class="loadingSuratSearch ? 'pi pi-spin pi-spinner text-brandBlue' : 'pi pi-search text-textMuted'" />
                <InputText
                  v-model="suratSearch"
                  placeholder="Cari nomor surat, agenda, perihal, keterangan, atau pengirim..."
                  class="w-full !bg-surface2 !text-textMain text-xs !rounded-xl !pl-8"
                />
              </IconField>
              <button
                v-if="suratSearch"
                type="button"
                class="absolute right-2.5 top-1/2 -translate-y-1/2 text-textMuted hover:text-textMain text-xs"
                @click="suratSearch = ''"
              >
                <i class="pi pi-times-circle"></i>
              </button>
            </div>

            <!-- Select Dropdown dengan filter multi-fields -->
            <Select
              v-model="formDisposisi.surat_masuk_id"
              :options="filteredSurat"
              option-label="perihal"
              option-value="id"
              placeholder="-- Pilih Surat Masuk Terkait --"
              class="w-full !bg-surface2 !rounded-xl text-xs"
              filter
              :filter-fields="['nomor_agenda', 'nomor_surat', 'perihal', 'asal_surat', 'keterangan']"
              filter-placeholder="Ketik cari no. surat, agenda, perihal, keterangan..."
            >
              <template #value="{ value, placeholder }">
                <div v-if="selectedSuratObject" class="flex items-center gap-2 text-xs truncate">
                  <span v-if="selectedSuratObject.nomor_agenda" class="font-mono font-bold text-brandBlue bg-brandBlueBg px-1.5 py-0.5 rounded shrink-0">
                    [{{ selectedSuratObject.nomor_agenda }}]
                  </span>
                  <span class="font-semibold text-textMain shrink-0">{{ selectedSuratObject.nomor_surat }}</span>
                  <span class="text-textMuted truncate">— {{ selectedSuratObject.perihal }}</span>
                </div>
                <span v-else class="text-textMuted">{{ placeholder }}</span>
              </template>

              <template #option="{ option }">
                <div class="flex flex-col gap-1 py-1.5 text-xs w-full border-b border-border/30 last:border-0">
                  <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-1.5 flex-wrap">
                      <span v-if="option.nomor_agenda" class="font-mono font-bold text-[10px] text-brandBlue bg-brandBlueBg px-1.5 py-0.2 rounded border border-brandBlue/30">
                        {{ option.nomor_agenda }}
                      </span>
                      <span class="font-bold text-textMain text-xs">{{ option.nomor_surat }}</span>
                    </div>
                    <span v-if="option.nama_folder" class="text-[10px] px-1.5 py-0.2 rounded bg-surface2 text-textMuted border border-border/50 shrink-0">
                      📁 {{ option.nama_folder }}
                    </span>
                  </div>

                  <div class="text-textMain font-medium text-[11px] leading-snug line-clamp-2">
                    {{ option.perihal }}
                  </div>

                  <div class="flex items-center justify-between text-[10px] text-textMuted pt-0.5">
                    <span>Asal: <strong class="text-textMain">{{ option.asal_surat }}</strong></span>
                    <span v-if="option.tanggal_terima">{{ formatDate(option.tanggal_terima) }}</span>
                  </div>

                  <!-- Highlight Keterangan jika ada -->
                  <div v-if="option.keterangan" class="text-[10px] text-amber-700 dark:text-amber-300 bg-amber-500/10 px-2 py-0.5 rounded flex items-start gap-1">
                    <i class="pi pi-comment text-[9px] mt-0.5 shrink-0"></i>
                    <span class="italic truncate" :title="option.keterangan">Ket: {{ option.keterangan }}</span>
                  </div>
                </div>
              </template>

              <template #empty>
                <div class="text-center py-4 text-xs text-textMuted">
                  Tidak ada surat masuk yang cocok dengan pencarian.
                </div>
              </template>
            </Select>

            <!-- Card Pratinjau Surat yang Dipilih -->
            <div v-if="selectedSuratObject" class="p-3 rounded-xl bg-surface2/70 border border-brandBlue/30 text-xs flex flex-col gap-1.5 animate-fade-in shadow-2xs">
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-brandBlue uppercase tracking-wide flex items-center gap-1">
                  <i class="pi pi-check-circle"></i> Surat Terpilih
                </span>
                <Button
                  icon="pi pi-times"
                  label="Ganti / Batal"
                  size="small"
                  severity="secondary"
                  text
                  class="!text-[10px] !py-0.5 !px-1.5"
                  @click="formDisposisi.surat_masuk_id = ''"
                />
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 border-t border-border/50">
                <div>
                  <span class="text-textMuted text-[10px] block">No. Agenda:</span>
                  <span class="font-mono font-bold text-brandBlue">{{ selectedSuratObject.nomor_agenda || '— (Tanpa Agenda)' }}</span>
                </div>
                <div>
                  <span class="text-textMuted text-[10px] block">No. Surat:</span>
                  <span class="font-bold text-textMain">{{ selectedSuratObject.nomor_surat }}</span>
                </div>
                <div>
                  <span class="text-textMuted text-[10px] block">Asal Surat:</span>
                  <span class="text-textMain font-medium">{{ selectedSuratObject.asal_surat }}</span>
                </div>
                <div>
                  <span class="text-textMuted text-[10px] block">Tgl Terima:</span>
                  <span class="text-textMain">{{ formatDate(selectedSuratObject.tanggal_terima) }}</span>
                </div>
                <div class="sm:col-span-2">
                  <span class="text-textMuted text-[10px] block">Perihal:</span>
                  <span class="text-textMain font-semibold">{{ selectedSuratObject.perihal }}</span>
                </div>
                <div v-if="selectedSuratObject.keterangan" class="sm:col-span-2 p-1.5 rounded bg-amber-500/10 border border-amber-500/20 text-[11px] text-amber-800 dark:text-amber-300">
                  <span class="font-bold">Keterangan:</span> {{ selectedSuratObject.keterangan }}
                </div>
              </div>
            </div>
          </template>

          <template v-else>
            <!-- Mode Edit -->
            <div class="p-3 rounded-xl bg-surface2 border border-border text-xs flex flex-col gap-1.5">
              <span class="text-textMuted font-bold">Surat Masuk Terkait:</span>
              <div class="font-semibold text-textMain text-sm">
                {{ selectedSuratObject?.perihal || suratList.find(s => s.id == formDisposisi.surat_masuk_id)?.perihal || 'ID Surat: ' + formDisposisi.surat_masuk_id }}
              </div>
              <div v-if="selectedSuratObject" class="flex flex-wrap gap-2 text-[11px] text-textMuted pt-1 border-t border-border/40">
                <span>Agenda: <strong class="font-mono text-brandBlue">{{ selectedSuratObject.nomor_agenda || '—' }}</strong></span>
                <span>•</span>
                <span>No. Surat: <strong class="text-textMain">{{ selectedSuratObject.nomor_surat }}</strong></span>
                <span>•</span>
                <span>Asal: <strong>{{ selectedSuratObject.asal_surat }}</strong></span>
              </div>
            </div>
          </template>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Instruksi / Isi Disposisi *</label>
          <Textarea v-model="formDisposisi.isi_disposisi" rows="3" placeholder="Tuliskan arahan / instruksi disposisi..." class="w-full !bg-surface2" required />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">Prioritas</label>
            <Select
              v-model="formDisposisi.prioritas"
              :options="[
                { label: 'Biasa', value: 'BIASA' },
                { label: 'Normal', value: 'NORMAL' },
                { label: 'Segera / Urgent', value: 'URGENT' },
              ]"
              option-label="label"
              option-value="value"
              class="w-full !bg-surface2"
            />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">Batas Waktu (Deadline)</label>
            <InputText v-model="formDisposisi.batas_waktu" type="date" class="w-full !bg-surface2" />
          </div>
        </div>

        <!-- Mode Berjenjang Toggle & Preview -->
        <div class="flex flex-col gap-2.5 p-3.5 rounded-2xl border border-border/80 bg-surface2/40">
          <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold text-sm shrink-0 border border-amber-500/20">
                <i class="pi pi-sitemap"></i>
              </div>
              <div>
                <div class="text-xs font-bold text-textMain flex items-center gap-1.5">
                  <span>Alur Validasi Berjenjang</span>
                  <span class="text-[10px] px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-600 font-semibold border border-amber-500/20">Hierarki</span>
                </div>
                <div class="text-[11px] text-textMuted">Validasi berurutan dari staf/unit terbawah hingga pejabat/direktur.</div>
              </div>
            </div>
            <label class="relative inline-flex items-center cursor-pointer shrink-0">
              <input type="checkbox" v-model="formDisposisi.is_berjenjang" :true-value="1" :false-value="0" class="sr-only peer">
              <div class="w-10 h-5 bg-surface3 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-amber-500"></div>
            </label>
          </div>

          <!-- Preview Rantai Validasi Berjenjang -->
          <div v-if="formDisposisi.is_berjenjang" class="mt-1 pt-2.5 border-t border-border/60">
            <div class="text-[11px] font-bold text-amber-700 dark:text-amber-400 mb-2 flex items-center gap-1.5">
              <i class="pi pi-sort-amount-up text-xs"></i>
              <span>Simulasi Urutan Rantai Validasi:</span>
            </div>
            <div v-if="hierarkiPreview.length > 0" class="flex flex-wrap items-center gap-2">
              <template v-for="(step, idx) in hierarkiPreview" :key="step.level">
                <div class="flex items-center gap-2 px-2.5 py-1.5 rounded-xl bg-surface border border-border text-xs shadow-2xs">
                  <span class="w-5 h-5 rounded-full bg-amber-500 text-white font-bold text-[10px] flex items-center justify-center shrink-0">
                    {{ idx + 1 }}
                  </span>
                  <div>
                    <div class="font-bold text-textMain text-[11px]">
                      {{ step.users.map(u => u.nama_lengkap).join(', ') }}
                    </div>
                    <div class="text-[10px] text-textMuted">
                      Lv.{{ step.level }} ({{ step.users.map(u => u.jabatan_label).join(', ') }})
                    </div>
                  </div>
                </div>
                <i v-if="idx < hierarkiPreview.length - 1" class="pi pi-arrow-right text-[10px] text-amber-500 shrink-0"></i>
              </template>
            </div>
            <div v-else class="text-[11px] text-textMuted italic bg-surface/60 p-2 rounded-lg border border-border/60">
              Pilih beberapa staf penerima di bawah untuk melihat urutan rantai validasinya.
            </div>
          </div>
        </div>

        <!-- Daftar Penerima Disposisi -->
        <div class="flex flex-col gap-2.5 bg-surface2/60 border border-border/80 rounded-2xl p-3.5">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
              <label class="text-xs font-bold text-textMain uppercase tracking-wider flex items-center gap-1.5">
                <i class="pi pi-users text-accent text-xs"></i>
                Penerima Disposisi *
              </label>
              <span
                class="text-[11px] font-bold px-2 py-0.5 rounded-full border transition-all"
                :class="selectedPenerima.length > 0 ? 'bg-accent text-white border-accent' : 'bg-surface3 text-textMuted border-border'"
              >
                {{ selectedPenerima.length }} dipilih
              </span>
            </div>

            <!-- Quick actions: Pilih Semua / Bersihkan -->
            <div class="flex items-center gap-2 text-xs">
              <button
                type="button"
                @click="toggleSelectFiltered"
                :disabled="filteredUsers.length === 0"
                class="text-[11px] font-semibold text-accent hover:underline disabled:opacity-40 disabled:no-underline flex items-center gap-1 cursor-pointer"
              >
                <i class="pi" :class="filteredUsers.length > 0 && filteredUsers.every(u => selectedPenerima.includes(Number(u.id))) ? 'pi-minus-circle' : 'pi-check-circle'"></i>
                <span>{{ filteredUsers.length > 0 && filteredUsers.every(u => selectedPenerima.includes(Number(u.id))) ? 'Batal Pilih Semua' : 'Pilih Semua' }}</span>
              </button>
              <span v-if="selectedPenerima.length > 0" class="text-border">|</span>
              <button
                v-if="selectedPenerima.length > 0"
                type="button"
                @click="clearAllPenerima"
                class="text-[11px] font-semibold text-rose-600 hover:underline flex items-center gap-1 cursor-pointer"
              >
                <i class="pi pi-trash text-[10px]"></i>
                <span>Bersihkan ({{ selectedPenerima.length }})</span>
              </button>
            </div>
          </div>

          <!-- Form Search & Role Tabs -->
          <div class="space-y-2">
            <!-- Search Bar -->
            <div class="relative flex items-center">
              <i class="pi pi-search absolute left-3 text-textMuted text-xs pointer-events-none"></i>
              <input
                v-model="penerimaSearch"
                type="text"
                placeholder="Cari nama staff, NIP, jabatan, atau unit..."
                class="w-full bg-surface border border-border rounded-xl pl-8 pr-8 py-2 text-xs text-textMain placeholder-textMuted focus:border-accent focus:outline-none transition-colors shadow-2xs"
              />
              <button
                v-if="penerimaSearch"
                type="button"
                @click="penerimaSearch = ''"
                class="absolute right-2.5 p-1 text-textMuted hover:text-textMain text-xs cursor-pointer"
                title="Hapus pencarian"
              >
                <i class="pi pi-times"></i>
              </button>
            </div>

            <!-- Role Filter Pills -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5 text-[11px]">
              <button
                v-for="rf in [
                  { label: 'Semua Role', value: 'SEMUA' },
                  { label: 'Pejabat', value: 'PEJABAT' },
                  { label: 'Staf', value: 'STAF' },
                  { label: 'Direktur', value: 'DIREKTUR' },
                  { label: 'Admin', value: 'ADMIN' },
                ]"
                :key="rf.value"
                type="button"
                @click="penerimaRoleFilter = rf.value"
                class="px-2.5 py-1 rounded-lg font-semibold whitespace-nowrap transition-all cursor-pointer"
                :class="penerimaRoleFilter === rf.value ? 'bg-accent text-white shadow-2xs' : 'bg-surface hover:bg-surface3 text-textMuted border border-border/70'"
              >
                {{ rf.label }}
              </button>
            </div>
          </div>

          <!-- Selected Staff Chips Preview -->
          <div v-if="selectedUsersObjects.length > 0" class="flex flex-wrap items-center gap-1.5 max-h-20 overflow-y-auto p-2 bg-surface rounded-xl border border-border/60">
            <span class="text-[10px] font-bold text-textMuted uppercase tracking-wider mr-1">Terpilih:</span>
            <span
              v-for="su in selectedUsersObjects"
              :key="su.id"
              class="inline-flex items-center gap-1 bg-accent/10 border border-accent/30 text-accent text-[11px] font-semibold px-2 py-0.5 rounded-md"
            >
              <span>{{ su.nama_lengkap }}</span>
              <button
                type="button"
                @click.stop="removePenerima(su.id)"
                class="hover:text-rose-600 ml-0.5 text-xs font-bold leading-none cursor-pointer"
                title="Hapus"
              >
                &times;
              </button>
            </span>
          </div>

          <!-- List Staff Checkboxes -->
          <div v-if="usersList.length === 0" class="text-xs text-amber-600 p-3 bg-amber-50 rounded-xl border border-amber-200">
            Memuat daftar pengguna atau belum ada pengguna terdaftar...
          </div>
          <div v-else-if="filteredUsers.length === 0" class="text-xs text-textMuted text-center py-6 bg-surface rounded-xl border border-dashed border-border flex flex-col items-center gap-1">
            <i class="pi pi-search text-lg text-textDim"></i>
            <span>Tidak ada staff yang cocok dengan pencarian "<strong>{{ penerimaSearch }}</strong>"</span>
            <button
              type="button"
              @click="penerimaSearch = ''; penerimaRoleFilter = 'SEMUA'"
              class="mt-1 text-xs text-accent font-semibold hover:underline cursor-pointer"
            >
              Reset Filter Pencarian
            </button>
          </div>
          <div v-else class="flex flex-col gap-1 max-h-52 overflow-y-auto border border-border/80 rounded-xl p-2 bg-surface divide-y divide-border/40">
            <div
              v-for="u in filteredUsers"
              :key="u.id"
              class="flex items-center gap-3 p-2 rounded-lg transition-all hover:bg-surface2 cursor-pointer"
              :class="selectedPenerima.includes(Number(u.id)) ? 'bg-accent/10 border border-accent/30' : ''"
              @click="() => {
                const uid = Number(u.id)
                if (selectedPenerima.includes(uid)) {
                  selectedPenerima = selectedPenerima.filter(x => x !== uid)
                } else {
                  selectedPenerima.push(uid)
                }
              }"
            >
              <Checkbox
                :input-id="`user-${u.id}`"
                :value="Number(u.id)"
                v-model="selectedPenerima"
                @click.stop
              />
              <label :for="`user-${u.id}`" class="flex-1 cursor-pointer flex items-center justify-between gap-2" @click.stop>
                <div class="min-w-0">
                  <div class="text-xs font-bold text-textMain truncate">{{ u.nama_lengkap }}</div>
                  <div class="text-[11px] text-textMuted truncate flex items-center gap-1.5 flex-wrap">
                    <span v-if="u.nip" class="font-mono text-textDim">NIP: {{ u.nip }}</span>
                    <span v-if="u.nip && u.jabatan">•</span>
                    <span>{{ u.jabatan || u.role }}</span>
                    <span v-if="u.unit" class="text-textDim">({{ u.unit }})</span>
                  </div>
                </div>
                <Tag
                  v-if="u.role === 'DIREKTUR'"
                  value="Direktur"
                  severity="warn"
                  class="text-[9px] uppercase px-1.5 py-0 shrink-0"
                />
                <Tag
                  v-else-if="u.role === 'PEJABAT'"
                  value="Pejabat"
                  severity="success"
                  class="text-[9px] uppercase px-1.5 py-0 shrink-0"
                />
                <Tag
                  v-else-if="u.role === 'STAF'"
                  value="Staf"
                  severity="info"
                  class="text-[9px] uppercase px-1.5 py-0 shrink-0"
                />
                <Tag
                  v-else-if="u.role === 'ADMIN'"
                  value="Admin"
                  severity="secondary"
                  class="text-[9px] uppercase px-1.5 py-0 shrink-0"
                />
              </label>
            </div>
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Catatan Tambahan Direktur</label>
          <Textarea v-model="formDisposisi.catatan_direktur" rows="2" placeholder="(Opsional)" class="w-full !bg-surface2" />
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-border">
          <Button label="Batal" severity="secondary" outlined type="button" @click="showModal = false" />
          <Button
            type="submit"
            :label="editMode ? 'Simpan Perubahan' : 'Kirim Disposisi'"
            :icon="editMode ? 'pi pi-save' : 'pi pi-send'"
            class="btn-gradient"
            :loading="submitting"
          />
        </div>
      </form>
    </Dialog>

    <!-- Dialog Konfirmasi Hapus Disposisi -->
    <Dialog
      v-model:visible="showDeleteDialog"
      modal
      header="Konfirmasi Hapus Disposisi"
      :style="{ width: 'min(440px, 95vw)' }"
      :draggable="false"
      class="!rounded-2xl"
    >
      <div v-if="deleteTarget" class="flex flex-col gap-4 py-2">
        <div class="flex items-start gap-3">
          <div class="w-12 h-12 rounded-2xl bg-red-100 text-red-600 flex items-center justify-center shrink-0">
            <i class="pi pi-exclamation-triangle text-2xl"></i>
          </div>
          <div>
            <h4 class="font-bold text-textMain text-base m-0">Hapus Disposisi?</h4>
            <p class="text-xs text-textMuted mt-1 mb-0">Disposisi dan seluruh progres tindak lanjut penerima akan dihapus secara permanen.</p>
          </div>
        </div>

        <div class="p-3.5 rounded-xl bg-surface2 border border-border/80 text-xs flex flex-col gap-1.5">
          <div><span class="text-textMuted">No. Disposisi:</span> <span class="font-mono font-bold text-brandPurple">{{ deleteTarget.nomor_disposisi }}</span></div>
          <div><span class="text-textMuted">Perihal:</span> <strong class="text-textMain">{{ deleteTarget.perihal }}</strong></div>
          <div><span class="text-textMuted">Asal Surat:</span> <span class="text-textMain">{{ deleteTarget.asal_surat }}</span></div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <Button label="Batal" severity="secondary" outlined @click="showDeleteDialog = false" :disabled="deleting" />
          <Button
            label="Ya, Hapus Disposisi"
            icon="pi pi-trash"
            severity="danger"
            :loading="deleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </Dialog>

    <!-- Modal Cetak Lembar Disposisi Selesai Resmi RSI -->
    <LembarDisposisiModal
      v-if="selectedReportDisposisi"
      v-model:visible="showReportModal"
      :disposisi="selectedReportDisposisi"
      :timeline="selectedReportTimeline"
    />
  </div>
</template>

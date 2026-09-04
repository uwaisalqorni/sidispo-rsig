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

const toast  = useToast()
const auth   = useAuthStore()
const router = useRouter()
const route  = useRoute()

const showModal  = ref(false)
const editMode   = ref(false)
const editId     = ref(null)
const submitting = ref(false)
const submitError = ref('')

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

const filteredSurat = computed(() => {
  if (!suratSearch.value) return suratList.value
  const q = suratSearch.value.toLowerCase()
  return suratList.value.filter(s =>
    s.nomor_agenda?.toLowerCase().includes(q) ||
    s.perihal?.toLowerCase().includes(q) ||
    s.asal_surat?.toLowerCase().includes(q) ||
    s.nomor_surat?.toLowerCase().includes(q)
  )
})

const formDisposisi = ref({
  surat_masuk_id: '',
  isi_disposisi: '',
  prioritas: 'NORMAL',
  batas_waktu: '',
  catatan_direktur: ''
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

// Load data master (surat & users)
const loadFormData = async () => {
  const promises = []
  if (suratList.value.length === 0) {
    promises.push(api.get('/surat').then(({ data }) => { suratList.value = data.data || [] }).catch(() => {}))
  }
  if (usersList.value.length === 0) {
    promises.push(
      api.get('/disposisi/penerima-options')
        .catch(() => api.get('/users'))
        .then(({ data }) => { usersList.value = data.data || [] })
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
    catatan_direktur: ''
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
    catatan_direktur: item.catatan_direktur || ''
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

          <Column field="nomor_disposisi" header="No. Disposisi" style="width: 130px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-brandPurple bg-brandPurpleBg px-2 py-0.5 rounded-md">
                {{ data.nomor_disposisi }}
              </span>
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

          <Column header="Aksi" style="width: 140px; text-align: center">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5" @click.stop>
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
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Surat Masuk Terkait *</label>
          <template v-if="!editMode">
            <InputText v-model="suratSearch" placeholder="Ketik untuk mencari agenda, perihal, atau pengirim..." class="w-full !bg-surface2 text-xs mb-1.5" />
            <Select
              v-model="formDisposisi.surat_masuk_id"
              :options="filteredSurat"
              option-label="perihal"
              option-value="id"
              placeholder="Pilih surat masuk"
              class="w-full !bg-surface2"
              filter
            >
              <template #option="{ option }">
                <span class="text-xs">
                  <strong class="font-mono text-brandBlue">[{{ option.nomor_agenda }}]</strong> {{ option.perihal }} — <span class="text-textMuted">{{ option.asal_surat }}</span>
                </span>
              </template>
            </Select>
          </template>
          <template v-else>
            <div class="p-3 rounded-xl bg-surface2 border border-border text-xs flex flex-col gap-1">
              <span class="text-textMuted font-bold">Surat Masuk Terkait:</span>
              <span class="font-semibold text-textMain text-sm">
                {{ suratList.find(s => s.id == formDisposisi.surat_masuk_id)?.perihal || 'ID Surat: ' + formDisposisi.surat_masuk_id }}
              </span>
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
  </div>
</template>

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
const usersList        = ref([])
const selectedPenerima = ref([])  // array of user ids
const suratList        = ref([])
const suratSearch      = ref('')

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
  SELESAI: 'success',
  OVERDUE: 'danger',
  AKTIF: 'info',
}

const statusLabel = {
  PROSES: 'Diproses',
  TUNGGU: 'Menunggu',
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
    list = list.filter(d => (d.status_display || d.status_global) === key)
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
    counts[label] = disposisiList.value.filter(d => (d.status_display || d.status_global) === key).length
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
    promises.push(api.get('/users').then(({ data }) => { usersList.value = data.data || [] }).catch(() => {}))
  }
  await Promise.all(promises)
}

// ── Buka Modal Tambah ─────────────────────────────────────────────
const openModal = async () => {
  editMode.value = false
  editId.value   = null
  submitError.value = ''
  suratSearch.value = ''
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
    submitError.value = err.response?.data?.message || 'Gagal menyimpan disposisi.'
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
        <div class="flex flex-col gap-2">
          <div class="flex items-center justify-between">
            <label class="text-xs font-bold text-textMuted uppercase">Penerima Disposisi (Staff / Pejabat) *</label>
            <span class="text-xs text-accent font-bold">{{ selectedPenerima.length }} dipilih</span>
          </div>

          <div v-if="usersList.length === 0" class="text-xs text-orange-600">Daftar pengguna belum tersedia.</div>
          <div v-else class="flex flex-col gap-1.5 max-h-48 overflow-y-auto border border-border rounded-xl p-3 bg-surface2">
            <div
              v-for="u in usersList"
              :key="u.id"
              class="flex items-center gap-3 p-2 rounded-lg transition-all hover:bg-surface"
              :class="selectedPenerima.includes(Number(u.id)) ? 'bg-accentGlow/30 border border-accent/30' : ''"
            >
              <Checkbox :input-id="`user-${u.id}`" :value="Number(u.id)" v-model="selectedPenerima" />
              <label :for="`user-${u.id}`" class="flex-1 cursor-pointer flex items-center justify-between">
                <div>
                  <div class="text-xs font-semibold text-textMain">{{ u.nama_lengkap }}</div>
                  <div class="text-[11px] text-textMuted">{{ u.jabatan || u.role }} <span v-if="u.unit">• {{ u.unit }}</span></div>
                </div>
                <Tag v-if="u.role === 'DIREKTUR'" value="Direktur" severity="warning" class="text-[10px]" />
                <Tag v-else-if="u.role === 'ADMIN'" value="Admin" severity="info" class="text-[10px]" />
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

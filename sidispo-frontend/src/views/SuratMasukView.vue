<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api/axios'
import Card from 'primevue/card'
import Button from 'primevue/button'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import Drawer from 'primevue/drawer'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import AutoComplete from 'primevue/autocomplete'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import { useToast } from 'primevue/usetoast'

const toast = useToast()
const route = useRoute()
const router = useRouter()

const suratList = ref([])
const loading   = ref(true)
const showModal = ref(false)
const editMode  = ref(false)
const editId    = ref(null)
const submitting = ref(false)
const submitMsg  = ref({ type: '', text: '' })
const preview   = ref(null)
const showPreview = computed({
  get: () => !!preview.value,
  set: (v) => { if (!v) preview.value = null },
})

// Folders & Perihal lists – loaded from API
const folders     = ref([])
const perihalList = ref([])

// Filter & Search
const searchQuery       = ref('')
const selectedFolderId  = ref(null)
const filterDari        = ref('')
const filterSampai      = ref('')
const totalFiltered     = ref(0)

// Active Folder dari query string (?folder=...)
const activeFolder = computed(() => {
  const id = selectedFolderId.value || route.query.folder
  if (!id) return null
  return folders.value.find(f => f.id == id) || null
})

const filterActive = computed(() => {
  return !!(searchQuery.value.trim() || selectedFolderId.value || filterDari.value || filterSampai.value || route.query.folder)
})

// Filtered list (kombinasi filter instan frontend dan server)
const displayList = computed(() => {
  let list = suratList.value

  const targetFolder = selectedFolderId.value || route.query.folder
  if (targetFolder) {
    list = list.filter(s => s.folder_id == targetFolder)
  }

  if (searchQuery.value.trim()) {
    const q = searchQuery.value.trim().toLowerCase()
    list = list.filter(s => {
      const noSurat  = (s.nomor_surat || '').toLowerCase()
      const noAgenda = (s.nomor_agenda || '').toLowerCase()
      const perihal  = (s.perihal || '').toLowerCase()
      const asal     = (s.asal_surat || '').toLowerCase()
      const ket      = (s.keterangan || '').toLowerCase()
      const folder   = (s.nama_folder || '').toLowerCase()
      return noSurat.includes(q) || noAgenda.includes(q) || perihal.includes(q) || asal.includes(q) || ket.includes(q) || folder.includes(q)
    })
  }

  return list
})

// Form state
const form = ref({
  nomor_agenda: '',
  nomor_surat: '',
  tanggal_surat: '',
  tanggal_terima: '',
  asal_surat: '',
  perihal: '',
  folder_id: null,
  keterangan: ''
})
const files = ref([])
const existingFiles = ref([])

// Delete state
const showDeleteDialog = ref(false)
const deleteTarget     = ref(null)
const deleting         = ref(false)

// ── Fungsi load data ──────────────────────────────────────────────
const loadSurat = async () => {
  loading.value = true
  try {
    const params = {}
    if (searchQuery.value.trim()) params.q = searchQuery.value.trim()
    const targetFolder = selectedFolderId.value || route.query.folder
    if (targetFolder) params.folder_id = targetFolder
    if (filterDari.value)   params.tanggal_dari    = filterDari.value
    if (filterSampai.value) params.tanggal_sampai  = filterSampai.value

    const { data } = await api.get('/surat', { params })
    suratList.value = data.data || []
    totalFiltered.value = data.total ?? suratList.value.length
  } catch { /* silent */ } finally { loading.value = false }
}

const applyFilter = () => {
  loadSurat()
}

const resetFilter = () => {
  searchQuery.value = ''
  selectedFolderId.value = null
  filterDari.value = ''
  filterSampai.value = ''
  if (route.query.folder) {
    router.push({ path: '/surat-masuk' })
  }
  loadSurat()
}

onMounted(async () => {
  try {
    const [folderRes, perihalRes] = await Promise.all([
      api.get('/folder'),
      api.get('/perihal?active=1')
    ])
    folders.value     = folderRes.data.data  || []
    perihalList.value = perihalRes.data.data || []
    if (route.query.folder) {
      selectedFolderId.value = Number(route.query.folder)
    }
  } catch { /* silent */ }
  await loadSurat()
})

const onFileChange = (e) => {
  files.value = Array.from(e.target.files)
}

// ── Modal Create / Edit Handlers ──────────────────────────────────
const openCreate = () => {
  editMode.value = false
  editId.value   = null
  submitMsg.value = { type: '', text: '' }
  form.value = {
    nomor_agenda: '',
    nomor_surat: '',
    tanggal_surat: new Date().toISOString().slice(0, 10),
    tanggal_terima: new Date().toISOString().slice(0, 10),
    asal_surat: '',
    perihal: '',
    folder_id: activeFolder.value ? activeFolder.value.id : null,
    keterangan: ''
  }
  files.value = []
  existingFiles.value = []
  showModal.value = true
}

const openEdit = async (item) => {
  editMode.value = true
  editId.value   = item.id
  submitMsg.value = { type: '', text: '' }
  files.value = []
  existingFiles.value = []

  // Pre-populate data awal dari item
  form.value = {
    nomor_agenda: item.nomor_agenda || '',
    nomor_surat: item.nomor_surat || '',
    tanggal_surat: item.tanggal_surat || '',
    tanggal_terima: item.tanggal_terima || '',
    asal_surat: item.asal_surat || '',
    perihal: item.perihal || '',
    folder_id: item.folder_id ? Number(item.folder_id) : null,
    keterangan: item.keterangan || ''
  }

  showModal.value = true

  // Ambil detail lengkap termasuk file lampiran
  try {
    const { data } = await api.get(`/surat/${item.id}`)
    if (data.data) {
      const d = data.data
      form.value.nomor_agenda   = d.nomor_agenda || ''
      form.value.nomor_surat    = d.nomor_surat || ''
      form.value.tanggal_surat  = d.tanggal_surat || ''
      form.value.tanggal_terima = d.tanggal_terima || ''
      form.value.asal_surat     = d.asal_surat || ''
      form.value.perihal        = d.perihal || ''
      form.value.folder_id      = d.folder_id ? Number(d.folder_id) : null
      form.value.keterangan     = d.keterangan || ''
      existingFiles.value       = d.files || []
    }
  } catch { /* silent */ }
}

const deleteExistingFile = async (fileId) => {
  if (!confirm('Hapus lampiran ini?')) return
  try {
    await api.delete(`/surat/file/${fileId}`)
    existingFiles.value = existingFiles.value.filter(f => f.id !== fileId)
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Lampiran dihapus.', life: 3000 })
    // Update preview jika sedang terbuka
    if (preview.value) {
      preview.value.files = (preview.value.files || []).filter(f => f.id !== fileId)
    }
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus file.')
  }
}

const handleSubmit = async () => {
  if (!form.value.nomor_surat || !form.value.perihal || !form.value.asal_surat) {
    submitMsg.value = { type: 'error', text: 'Mohon lengkapi Nomor Surat, Perihal, dan Asal Surat.' }
    return
  }
  submitting.value = true
  submitMsg.value = { type: '', text: '' }

  const fd = new FormData()
  Object.entries(form.value).forEach(([k, v]) => {
    if (v !== null && v !== undefined && v !== '') fd.append(k, v)
  })
  files.value.forEach(f => fd.append('files[]', f))

  try {
    if (editMode.value) {
      await api.post(`/surat/${editId.value}`, fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      submitMsg.value = { type: 'success', text: 'Surat masuk berhasil diperbarui!' }
      toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Surat masuk diperbarui.', life: 3000 })
    } else {
      await api.post('/surat', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
      submitMsg.value = { type: 'success', text: 'Surat masuk berhasil diregistrasi!' }
      toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Surat masuk berhasil diregistrasi.', life: 3000 })
    }

    await loadSurat()
    setTimeout(() => {
      showModal.value = false
      submitMsg.value = { type: '', text: '' }
    }, 1200)
  } catch (err) {
    submitMsg.value = { type: 'error', text: err.response?.data?.message || 'Gagal menyimpan surat masuk.' }
  } finally { submitting.value = false }
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
    await api.delete(`/surat/${deleteTarget.value.id}`)
    showDeleteDialog.value = false
    toast.add({ severity: 'success', summary: 'Terhapus', detail: 'Surat masuk berhasil dihapus.', life: 3000 })
    if (preview.value?.id === deleteTarget.value.id) {
      preview.value = null
    }
    deleteTarget.value = null
    await loadSurat()
  } catch (err) {
    alert(err.response?.data?.message || 'Gagal menghapus surat masuk.')
  } finally {
    deleting.value = false
  }
}

const openDetail = async (id) => {
  try {
    const { data } = await api.get(`/surat/${id}`)
    preview.value = data.data
  } catch (e) { preview.value = null }
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
}

function fileUrl(path) {
  const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/SiDispo/api/v1')
    .replace('/api/v1', '')
  return base + '/' + path
}
</script>

<template>
  <div class="page-container animate-fade-in">
    <!-- Header Hero -->
    <div class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap relative z-10">
          <h2 class="page-hero-title">Surat Masuk</h2>
          <span
            v-if="activeFolder"
            class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full border border-white/30 bg-white/15"
            :style="{ color: activeFolder.warna || '#38bdf8' }"
          >
            <span class="w-2 h-2 rounded-full" :style="{ background: activeFolder.warna || '#38bdf8' }"></span>
            {{ activeFolder.nama }}
            <button class="ml-1 hover:opacity-70 text-sm leading-none" @click="resetFilter">×</button>
          </span>
        </div>
        <p class="page-hero-sub">
          <template v-if="activeFolder">{{ displayList.length }} surat di folder ini.</template>
          <template v-else>Pencarian, registrasi, dan pengelolaan surat masuk RSIG.</template>
        </p>
      </div>
      <Button label="Registrasi Surat" icon="pi pi-inbox" class="relative z-10 !bg-white !text-sidebar !border-0 shadow-glow" @click="openCreate" />
    </div>

    <!-- Panel Pencarian & Filter Canggih -->
    <div class="filter-panel">
      <div class="flex flex-col gap-3.5">
        <!-- Baris 1: Search Box & Kategori Folder -->
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center">
          <div class="flex-1">
            <IconField class="w-full">
              <InputIcon class="pi pi-search text-accent" />
              <InputText
                v-model="searchQuery"
                placeholder="Cari nomor surat, agenda, perihal, asal surat..."
                class="w-full !bg-surface2 !text-textMain !rounded-xl text-sm"
                @keyup.enter="applyFilter"
              />
            </IconField>
          </div>

          <div class="w-full md:w-56 shrink-0">
            <Select
              v-model="selectedFolderId"
              :options="folders"
              option-label="nama"
              option-value="id"
              placeholder="📁 Semua Folder"
              class="w-full !rounded-xl text-sm"
              show-clear
              @change="applyFilter"
            />
          </div>
        </div>

        <!-- Baris 2: Rentang Tanggal & Tombol Aksi -->
        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-border/40">
          <div class="flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-1.5 text-xs font-bold text-textMuted uppercase">
              <i class="pi pi-calendar text-brandCyan"></i>
              <span>Tanggal Terima:</span>
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

          <div v-if="!loading" class="text-xs text-textMuted ml-auto">
            Menampilkan <strong class="text-accent text-sm">{{ displayList.length }}</strong> dari <strong class="text-textMain">{{ totalFiltered }}</strong> surat
          </div>
        </div>
      </div>
    </div>

    <!-- Tabel Daftar Surat Masuk -->
    <Card class="color-panel glass-card">
      <template #title>
        <div class="flex items-center justify-between font-bold">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-brandBlueBg flex items-center justify-center">
              <i class="pi pi-table text-brandBlue"></i>
            </div>
            <span>Daftar Surat Masuk</span>
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
          :rows-per-page-options="[10, 25, 50]"
          class="text-sm"
          @row-click="(e) => openDetail(e.data.id)"
        >
          <template #empty>
            <div class="text-center py-14">
              <div class="w-16 h-16 rounded-2xl bg-surface2 flex items-center justify-center mx-auto mb-3">
                <i class="pi pi-inbox text-3xl text-textDim"></i>
              </div>
              <p class="text-textMuted text-sm font-semibold">
                {{ filterActive ? 'Tidak ada surat masuk yang cocok dengan filter atau kata kunci.' : 'Belum ada surat masuk yang terdaftar.' }}
              </p>
              <Button v-if="filterActive" label="Reset Pencarian" icon="pi pi-refresh" severity="secondary" size="small" class="mt-2" @click="resetFilter" />
            </div>
          </template>

          <Column field="nomor_agenda" header="No. Agenda" style="width: 140px">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-brandBlue bg-brandBlueBg px-2 py-0.5 rounded-md">
                {{ data.nomor_agenda }}
              </span>
            </template>
          </Column>

          <Column field="nomor_surat" header="No. Surat" style="min-width: 150px">
            <template #body="{ data }">
              <span class="font-semibold text-textMain">{{ data.nomor_surat }}</span>
            </template>
          </Column>

          <Column field="perihal" header="Perihal" style="min-width: 220px">
            <template #body="{ data }">
              <span class="font-medium text-textMain max-w-[280px] truncate block" :title="data.perihal">
                {{ data.perihal }}
              </span>
            </template>
          </Column>

          <Column field="asal_surat" header="Asal Surat" style="min-width: 160px">
            <template #body="{ data }">
              <span class="text-textMain">{{ data.asal_surat }}</span>
            </template>
          </Column>

          <Column header="Tgl Terima" style="width: 120px">
            <template #body="{ data }">
              <span class="text-xs text-textMuted">{{ formatDate(data.tanggal_terima) }}</span>
            </template>
          </Column>

          <Column header="Folder" style="width: 130px">
            <template #body="{ data }">
              <span
                v-if="data.nama_folder"
                class="inline-flex items-center gap-1 text-[11px] font-bold px-2 py-0.5 rounded-md border"
                :style="{ background: (data.warna_folder || '#3b82f6') + '15', color: data.warna_folder || '#3b82f6', borderColor: (data.warna_folder || '#3b82f6') + '40' }"
              >
                <i class="pi pi-folder text-[10px]"></i>
                {{ data.nama_folder }}
              </span>
              <span v-else class="text-textDim text-xs">—</span>
            </template>
          </Column>

          <Column header="Lampiran" style="width: 90px; text-align: center">
            <template #body="{ data }">
              <Tag v-if="data.jumlah_file > 0" :value="String(data.jumlah_file)" icon="pi pi-paperclip" severity="info" class="text-xs" />
              <span v-else class="text-textDim text-xs">—</span>
            </template>
          </Column>

          <Column header="Disposisi" style="width: 110px">
            <template #body="{ data }">
              <span v-if="data.jumlah_disposisi > 0" class="text-[11px] font-bold px-2 py-0.5 rounded-md bg-emerald-100 text-emerald-700 border border-emerald-200 inline-flex items-center gap-1">
                <i class="pi pi-check text-[10px]"></i> Ada
              </span>
              <span v-else class="text-[11px] font-medium px-2 py-0.5 rounded-md bg-surface2 text-textMuted border border-border/40">
                Belum
              </span>
            </template>
          </Column>

          <Column header="Aksi" style="width: 140px; text-align: center">
            <template #body="{ data }">
              <div class="flex items-center justify-center gap-1.5" @click.stop>
                <Button
                  icon="pi pi-eye"
                  size="small"
                  class="!w-8 !h-8 !p-0 !rounded-lg !bg-brandBlueBg !text-brandBlue !border-brandBlue/20 hover:!bg-brandBlue/20"
                  v-tooltip.top="'Lihat Detail'"
                  @click.stop="openDetail(data.id)"
                />
                <Button
                  icon="pi pi-pencil"
                  size="small"
                  class="!w-8 !h-8 !p-0 !rounded-lg !bg-brandGreenBg !text-brandGreen !border-brandGreen/20 hover:!bg-brandGreen/20"
                  v-tooltip.top="'Edit Surat'"
                  @click.stop="openEdit(data)"
                />
                <Button
                  icon="pi pi-trash"
                  size="small"
                  class="!w-8 !h-8 !p-0 !rounded-lg !bg-brandRedBg !text-brandRed !border-brandRed/20 hover:!bg-brandRed/20"
                  v-tooltip.top="data.jumlah_disposisi > 0 ? 'Tidak bisa dihapus (ada disposisi)' : 'Hapus Surat'"
                  :disabled="data.jumlah_disposisi > 0"
                  @click.stop="confirmDelete(data)"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Drawer Preview Detail Surat -->
    <Drawer v-model:visible="showPreview" position="right" header="Detail Surat Masuk" class="w-full max-w-lg">
      <div v-if="preview" class="flex flex-col gap-5">
        <div class="grid grid-cols-2 gap-3 text-sm p-4 rounded-xl bg-surface2 border border-border/60">
          <div><span class="text-textMuted text-xs block mb-1">No. Agenda</span><span class="font-mono font-bold text-accent">{{ preview.nomor_agenda }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">No. Surat</span><span class="font-semibold text-textMain">{{ preview.nomor_surat }}</span></div>
          <div class="col-span-2"><span class="text-textMuted text-xs block mb-1">Perihal</span><span class="font-bold text-textMain text-base">{{ preview.perihal }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">Asal Surat</span><span class="text-textMain">{{ preview.asal_surat }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">Folder / Kategori</span><span class="text-textMain">{{ preview.nama_folder || '—' }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">Tgl Surat</span><span class="text-textMain">{{ formatDate(preview.tanggal_surat) }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">Tgl Terima</span><span class="text-textMain">{{ formatDate(preview.tanggal_terima) }}</span></div>
          <div v-if="preview.keterangan" class="col-span-2"><span class="text-textMuted text-xs block mb-1">Keterangan</span><span class="text-textMain italic">{{ preview.keterangan }}</span></div>
        </div>

        <!-- Lampiran Files -->
        <div>
          <div class="text-xs font-bold text-textMuted uppercase mb-2 flex items-center justify-between">
            <span>Lampiran Dokumen ({{ preview.files?.length || 0 }})</span>
          </div>
          <div v-if="preview.files?.length" class="flex flex-col gap-2">
            <a
              v-for="f in preview.files"
              :key="f.id"
              :href="fileUrl(f.path_file)"
              target="_blank"
              class="flex items-center gap-3 p-3 rounded-xl border border-border bg-surface hover:border-accent hover:bg-surface2 transition-all no-underline text-inherit group"
            >
              <div class="w-9 h-9 rounded-lg bg-accentGlow text-accent flex items-center justify-center shrink-0">
                <i class="pi pi-file text-lg"></i>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold truncate text-textMain group-hover:text-accent">{{ f.nama_asli }}</div>
                <div class="text-[11px] text-textMuted">{{ Math.round(f.ukuran_bytes / 1024) }} KB</div>
              </div>
              <i class="pi pi-external-link text-xs text-textMuted group-hover:text-accent"></i>
            </a>
          </div>
          <div v-else class="text-xs text-textMuted p-3 rounded-lg border border-dashed border-border text-center">
            Tidak ada lampiran dokumen untuk surat ini.
          </div>
        </div>

        <!-- Tombol Aksi di Drawer -->
        <div class="flex gap-2 pt-4 border-t border-border">
          <Button label="Edit Surat" icon="pi pi-pencil" severity="warning" class="flex-1" @click="openEdit(preview)" />
          <Button label="Hapus" icon="pi pi-trash" severity="danger" outlined @click="confirmDelete(preview)" />
        </div>
      </div>
    </Drawer>

    <!-- Dialog Form (Registrasi / Edit Surat) -->
    <Dialog
      v-model:visible="showModal"
      modal
      :header="editMode ? 'Edit Surat Masuk' : 'Registrasi Surat Masuk'"
      :style="{ width: 'min(680px, 95vw)' }"
      :draggable="false"
      class="!rounded-2xl"
    >
      <form @submit.prevent="handleSubmit" class="flex flex-col gap-4 py-2">
        <Message v-if="submitMsg.text" :severity="submitMsg.type === 'success' ? 'success' : 'error'" :closable="false">
          {{ submitMsg.text }}
        </Message>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">No. Agenda</label>
            <InputText v-model="form.nomor_agenda" :disabled="editMode" placeholder="Auto / SM-2026-00001" class="w-full !bg-surface2" />
            <p v-if="editMode" class="text-[11px] text-textMuted">Nomor agenda bersifat permanen dari sistem.</p>
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">No. Surat *</label>
            <InputText v-model="form.nomor_surat" placeholder="BPJS/KES/2026/001" class="w-full !bg-surface2" required />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Perihal *</label>
          <AutoComplete
            v-model="form.perihal"
            :suggestions="perihalList.map(p => p.nama)"
            placeholder="Pilih dari daftar atau ketik perihal..."
            class="w-full"
            input-class="w-full !bg-surface2"
            :complete-on-focus="true"
            @complete="(e) => e.suggestions = perihalList.map(p => p.nama).filter(n => n.toLowerCase().includes((e.query || '').toLowerCase()))"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Asal Surat *</label>
          <InputText v-model="form.asal_surat" placeholder="Nama instansi / pengirim surat" class="w-full !bg-surface2" required />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">Tanggal Surat *</label>
            <InputText v-model="form.tanggal_surat" type="date" class="w-full !bg-surface2" required />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">Tanggal Terima *</label>
            <InputText v-model="form.tanggal_terima" type="date" class="w-full !bg-surface2" required />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Folder / Kategori</label>
          <Select
            v-model="form.folder_id"
            :options="folders"
            option-label="nama"
            option-value="id"
            placeholder="Pilih folder (opsional)"
            class="w-full !bg-surface2"
            show-clear
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Keterangan</label>
          <Textarea v-model="form.keterangan" rows="2" placeholder="(Opsional) Catatan tambahan mengenai surat masuk ini..." class="w-full !bg-surface2" />
        </div>

        <!-- Lampiran yang Sudah Ada (Mode Edit) -->
        <div v-if="editMode && existingFiles.length" class="flex flex-col gap-2 p-3.5 rounded-xl bg-surface2 border border-border">
          <label class="text-xs font-bold text-textMuted uppercase">Lampiran Tersimpan ({{ existingFiles.length }})</label>
          <div class="flex flex-col gap-1.5">
            <div
              v-for="f in existingFiles"
              :key="f.id"
              class="flex items-center justify-between p-2 rounded-lg bg-surface border border-border/70 text-xs"
            >
              <div class="flex items-center gap-2 truncate flex-1 min-w-0 pr-2">
                <i class="pi pi-file text-accent"></i>
                <span class="truncate font-medium text-textMain">{{ f.nama_asli }}</span>
                <span class="text-[10px] text-textMuted">({{ Math.round(f.ukuran_bytes / 1024) }} KB)</span>
              </div>
              <div class="flex items-center gap-1.5 shrink-0">
                <a :href="fileUrl(f.path_file)" target="_blank" class="text-accent hover:underline font-semibold text-xs mr-2">
                  Buka
                </a>
                <button
                  type="button"
                  class="w-6 h-6 rounded flex items-center justify-center text-red-500 hover:bg-red-50"
                  title="Hapus lampiran ini"
                  @click="deleteExistingFile(f.id)"
                >
                  <i class="pi pi-trash text-xs"></i>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Upload File Baru -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">
            {{ editMode ? 'Tambah Lampiran Baru (Opsional)' : 'Unggah Lampiran Dokumen' }}
          </label>
          <input
            type="file"
            multiple
            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
            class="text-sm file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-accentGlow file:text-accent hover:file:bg-accent/20 cursor-pointer"
            @change="onFileChange"
          />
          <p class="text-[11px] text-textMuted">Format didukung: PDF, Word (DOC/DOCX), JPG, PNG. Maks. 10MB per file.</p>
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-border">
          <Button label="Batal" severity="secondary" outlined @click="showModal = false" type="button" />
          <Button
            type="submit"
            :label="editMode ? 'Simpan Perubahan' : 'Simpan Surat'"
            icon="pi pi-save"
            class="btn-gradient"
            :loading="submitting"
          />
        </div>
      </form>
    </Dialog>

    <!-- Dialog Konfirmasi Hapus Surat -->
    <Dialog
      v-model:visible="showDeleteDialog"
      modal
      header="Konfirmasi Hapus Surat"
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
            <h4 class="font-bold text-textMain text-base m-0">Hapus Surat Masuk?</h4>
            <p class="text-xs text-textMuted mt-1 mb-0">Tindakan ini tidak dapat dibatalkan. Berkas lampiran fisik juga akan dihapus dari sistem.</p>
          </div>
        </div>

        <div class="p-3.5 rounded-xl bg-surface2 border border-border/80 text-xs flex flex-col gap-1.5">
          <div><span class="text-textMuted">No. Surat:</span> <strong class="text-textMain">{{ deleteTarget.nomor_surat }}</strong></div>
          <div><span class="text-textMuted">No. Agenda:</span> <span class="font-mono font-bold text-brandBlue">{{ deleteTarget.nomor_agenda }}</span></div>
          <div><span class="text-textMuted">Perihal:</span> <span class="text-textMain font-medium">{{ deleteTarget.perihal }}</span></div>
          <div><span class="text-textMuted">Asal Surat:</span> <span class="text-textMain">{{ deleteTarget.asal_surat }}</span></div>
        </div>

        <Message v-if="deleteTarget.jumlah_disposisi > 0" severity="error" :closable="false" class="!my-0">
          Surat ini sudah memiliki <strong>{{ deleteTarget.jumlah_disposisi }} disposisi</strong> terkait. Anda harus menghapus atau menyelesaikan disposisi terkait terlebih dahulu.
        </Message>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 w-full">
          <Button label="Batal" severity="secondary" outlined @click="showDeleteDialog = false" :disabled="deleting" />
          <Button
            v-if="deleteTarget?.jumlah_disposisi == 0"
            label="Ya, Hapus Surat"
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

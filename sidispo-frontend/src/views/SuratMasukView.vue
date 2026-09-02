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
import DatePicker from 'primevue/datepicker'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import AutoComplete from 'primevue/autocomplete'

const suratList = ref([])
const loading   = ref(true)
const showModal = ref(false)
const submitting = ref(false)
const submitMsg  = ref({ type: '', text: '' })
const preview   = ref(null)
const showPreview = computed({
  get: () => !!preview.value,
  set: (v) => { if (!v) preview.value = null },
})
const route  = useRoute()
const router = useRouter()

// Folders list – loaded from API
const folders   = ref([])
// Master Perihal list – loaded from API
const perihalList = ref([])

// Filter tanggal
const filterDari    = ref('')
const filterSampai  = ref('')
const filterActive  = ref(false)  // true bila sedang ada filter tanggal aktif
const totalFiltered = ref(0)

// Filtered list berdasarkan ?folder= query param
const activeFolder = computed(() => {
  const id = route.query.folder
  if (!id) return null
  return folders.value.find(f => f.id == id) || null
})

const displayList = computed(() => {
  const id = route.query.folder
  if (!id) return suratList.value
  return suratList.value.filter(s => s.folder_id == id)
})

const form = ref({
  nomor_agenda: '',
  nomor_surat: '',
  tanggal_surat: '',
  tanggal_terima: '',
  asal_surat: '',
  perihal: '',
  folder_id: '',
  keterangan: ''
})
const files = ref([])

// ── Fungsi load data (dengan/tanpa filter) ──────────────────────────────
const loadSurat = async () => {
  loading.value = true
  try {
    const params = {}
    if (filterDari.value)   params.tanggal_dari    = filterDari.value
    if (filterSampai.value) params.tanggal_sampai  = filterSampai.value
    const { data } = await api.get('/surat', { params })
    suratList.value  = data.data  || []
    totalFiltered.value = data.total ?? suratList.value.length
  } catch { /* silent */ } finally { loading.value = false }
}

const applyFilter = () => {
  filterActive.value = !!(filterDari.value || filterSampai.value)
  loadSurat()
}

const resetFilter = () => {
  filterDari.value   = ''
  filterSampai.value = ''
  filterActive.value = false
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
  } catch { /* silent */ }
  await loadSurat()
})

const onFileChange = (e) => {
  files.value = Array.from(e.target.files)
}

const handleSubmit = async () => {
  if (!form.value.nomor_surat || !form.value.perihal || !form.value.asal_surat) {
    submitMsg.value = { type: 'error', text: 'Mohon lengkapi Nomor Surat, Perihal, dan Asal Surat.' }
    return
  }
  submitting.value = true
  submitMsg.value = { type: '', text: '' }

  const fd = new FormData()
  Object.entries(form.value).forEach(([k, v]) => { if (v) fd.append(k, v) })
  files.value.forEach(f => fd.append('files[]', f))

  try {
    await api.post('/surat', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    submitMsg.value = { type: 'success', text: 'Surat masuk berhasil diregistrasi!' }
    // Refresh list dengan filter yang sedang aktif
    await loadSurat()
    // Reset form
    Object.keys(form.value).forEach(k => form.value[k] = '')
    files.value = []
    setTimeout(() => { showModal.value = false; submitMsg.value = { type: '', text: '' } }, 1500)
  } catch (err) {
    submitMsg.value = { type: 'error', text: err.response?.data?.message || 'Gagal menyimpan surat masuk.' }
  } finally { submitting.value = false }
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
    <div class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap relative z-10">
          <h2 class="page-hero-title">Surat Masuk</h2>
          <span
            v-if="activeFolder"
            class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full border border-white/30 bg-white/15"
            :style="{ color: activeFolder.warna }"
          >
            <span class="w-2 h-2 rounded-full" :style="{ background: activeFolder.warna }"></span>
            {{ activeFolder.nama }}
            <button class="ml-1 hover:opacity-70" @click="router.push('/surat-masuk')">×</button>
          </span>
        </div>
        <p class="page-hero-sub">
          <template v-if="activeFolder">{{ displayList.length }} surat di folder ini.</template>
          <template v-else>Registrasi dan arsip surat masuk ke sistem.</template>
        </p>
      </div>
      <Button label="Registrasi Surat" icon="pi pi-inbox" class="relative z-10 !bg-white !text-sidebar !border-0 shadow-glow" @click="showModal = true" />
    </div>

    <div class="filter-panel">
        <div class="flex flex-wrap items-end gap-4">
          <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-lg bg-brandCyanBg flex items-center justify-center">
              <i class="pi pi-calendar text-brandCyan"></i>
            </div>
            <span class="text-sm font-bold text-textMain">Filter Tanggal Terima</span>
            <Tag v-if="filterActive" value="AKTIF" severity="success" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-textMuted uppercase">Dari</label>
            <InputText v-model="filterDari" type="date" class="w-full" size="small" />
          </div>
          <div class="flex flex-col gap-1">
            <label class="text-xs font-bold text-textMuted uppercase">Sampai</label>
            <InputText v-model="filterSampai" type="date" class="w-full" size="small" />
          </div>
          <div class="flex gap-2">
            <Button label="Terapkan" icon="pi pi-search" size="small" class="btn-gradient" @click="applyFilter" />
            <Button v-if="filterActive" label="Reset" icon="pi pi-times" severity="secondary" outlined size="small" @click="resetFilter" />
          </div>
          <div v-if="!loading" class="ml-auto text-xs text-textMuted">
            <strong class="text-accent text-base">{{ totalFiltered }}</strong> surat ditemukan
          </div>
        </div>
    </div>

    <Card class="color-panel glass-card">
      <template #title>
        <div class="flex items-center gap-2 font-bold">
          <div class="w-8 h-8 rounded-lg bg-brandBlueBg flex items-center justify-center">
            <i class="pi pi-table text-brandBlue"></i>
          </div>
          Daftar Surat Masuk
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
              <p class="text-textMuted text-sm">{{ filterActive ? 'Tidak ada surat dalam rentang tanggal ini.' : 'Belum ada surat masuk.' }}</p>
            </div>
          </template>
          <Column field="nomor_agenda" header="No. Agenda">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-brandBlue bg-brandBlueBg px-2 py-0.5 rounded-md">{{ data.nomor_agenda }}</span>
            </template>
          </Column>
          <Column field="nomor_surat" header="No. Surat" />
          <Column field="perihal" header="Perihal">
            <template #body="{ data }">
              <span class="font-medium max-w-[240px] truncate block" :title="data.perihal">{{ data.perihal }}</span>
            </template>
          </Column>
          <Column field="asal_surat" header="Asal Surat" />
          <Column header="Tgl Terima">
            <template #body="{ data }">{{ formatDate(data.tanggal_terima) }}</template>
          </Column>
          <Column header="Lampiran">
            <template #body="{ data }">
              <Tag v-if="data.jumlah_file > 0" :value="String(data.jumlah_file)" icon="pi pi-paperclip" severity="info" />
              <span v-else class="text-textDim">—</span>
            </template>
          </Column>
          <Column header="Aksi" style="width: 100px">
            <template #body="{ data }">
              <Button label="Lihat" icon="pi pi-eye" size="small" class="!bg-accentGlow !text-accent !border-accent/20" @click.stop="openDetail(data.id)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Drawer v-model:visible="showPreview" position="right" header="Detail Surat" class="w-full max-w-lg">
      <div v-if="preview" class="flex flex-col gap-4">
        <div class="grid grid-cols-2 gap-3 text-sm">
          <div><span class="text-textMuted text-xs block mb-1">No. Agenda</span><span class="font-mono text-primary">{{ preview.nomor_agenda }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">No. Surat</span><span>{{ preview.nomor_surat }}</span></div>
          <div class="col-span-2"><span class="text-textMuted text-xs block mb-1">Perihal</span><span class="font-semibold">{{ preview.perihal }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">Asal Surat</span><span>{{ preview.asal_surat }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">Tgl Surat</span><span>{{ formatDate(preview.tanggal_surat) }}</span></div>
          <div><span class="text-textMuted text-xs block mb-1">Tgl Terima</span><span>{{ formatDate(preview.tanggal_terima) }}</span></div>
          <div v-if="preview.keterangan" class="col-span-2"><span class="text-textMuted text-xs block mb-1">Keterangan</span><span>{{ preview.keterangan }}</span></div>
        </div>
        <div v-if="preview.files?.length">
          <div class="text-xs font-bold text-textMuted uppercase mb-2">Lampiran ({{ preview.files.length }})</div>
          <a
            v-for="f in preview.files"
            :key="f.id"
            :href="fileUrl(f.path_file)"
            target="_blank"
            class="flex items-center gap-3 p-3 mb-2 rounded-lg border border-surface-200 hover:border-primary hover:bg-surface-50 transition-all no-underline text-inherit"
          >
            <i class="pi pi-file text-xl text-primary"></i>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate">{{ f.nama_asli }}</div>
              <div class="text-xs text-textMuted">{{ Math.round(f.ukuran_bytes / 1024) }} KB</div>
            </div>
          </a>
        </div>
      </div>
    </Drawer>

    <Dialog v-model:visible="showModal" modal header="Registrasi Surat Masuk" :style="{ width: 'min(640px, 95vw)' }" :draggable="false">
      <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
        <Message v-if="submitMsg.text" :severity="submitMsg.type === 'success' ? 'success' : 'error'" :closable="false">{{ submitMsg.text }}</Message>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">No. Agenda</label>
            <InputText v-model="form.nomor_agenda" placeholder="SM-2026-00001" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">No. Surat *</label>
            <InputText v-model="form.nomor_surat" placeholder="BPJS/KES/2026/001" class="w-full" required />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Perihal *</label>
          <AutoComplete
            v-model="form.perihal"
            :suggestions="perihalList.map(p => p.nama)"
            placeholder="Pilih dari daftar atau ketik perihal..."
            class="w-full"
            :complete-on-focus="true"
            @complete="(e) => e.suggestions = perihalList.map(p => p.nama).filter(n => n.toLowerCase().includes((e.query || '').toLowerCase()))"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Asal Surat *</label>
          <InputText v-model="form.asal_surat" placeholder="Nama instansi / pengirim" class="w-full" required />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">Tanggal Surat</label>
            <InputText v-model="form.tanggal_surat" type="date" class="w-full" />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">Tanggal Terima</label>
            <InputText v-model="form.tanggal_terima" type="date" class="w-full" />
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
            class="w-full"
            show-clear
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Keterangan</label>
          <Textarea v-model="form.keterangan" rows="2" placeholder="(Opsional)" class="w-full" />
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Lampiran</label>
          <input type="file" multiple accept=".pdf,.doc,.docx,.jpg,.png" class="text-sm" @change="onFileChange" />
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-surface-200">
          <Button label="Batal" severity="secondary" outlined @click="showModal = false" type="button" />
          <Button type="submit" label="Simpan Surat" icon="pi pi-save" class="btn-gradient" :loading="submitting" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

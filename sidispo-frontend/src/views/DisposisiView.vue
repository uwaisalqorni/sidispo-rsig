<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/api/axios'
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

const router = useRouter()
const route  = useRoute()
const showModal = ref(false)
const tabs = ['Semua', 'Diproses', 'Menunggu', 'Selesai', 'Overdue']
const activeTab = ref('Semua')
const disposisiList = ref([])
const loading = ref(true)
const submitting = ref(false)
const submitError = ref('')

// Users list – fetched from API for recipient selection
const usersList = ref([])
const selectedPenerima = ref([])  // array of user ids

// Daftar surat masuk untuk dropdown
const suratList = ref([])
const suratSearch = ref('')

const filteredSurat = computed(() => {
  if (!suratSearch.value) return suratList.value
  const q = suratSearch.value.toLowerCase()
  return suratList.value.filter(s =>
    s.nomor_agenda?.toLowerCase().includes(q) ||
    s.perihal?.toLowerCase().includes(q) ||
    s.asal_surat?.toLowerCase().includes(q)
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

// status_display adalah field baru dari backend yang sudah di-compute
const filteredList = computed(() => {
  const map = { 'Diproses': 'PROSES', 'Menunggu': 'TUNGGU', 'Selesai': 'SELESAI', 'Overdue': 'OVERDUE' }
  if (activeTab.value === 'Semua') return disposisiList.value
  const key = map[activeTab.value]
  return disposisiList.value.filter(d => {
    const s = d.status_display || d.status_global
    return s === key
  })
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

// Sinkronkan ?tab= query param (dari link sidebar Selesai / Overdue)
function syncTabFromQuery() {
  const q = route.query.tab
  if (q && tabs.includes(q)) activeTab.value = q
}

onMounted(async () => {
  syncTabFromQuery()
  try {
    const { data } = await api.get('/disposisi')
    disposisiList.value = data.data || []
  } catch (e) { /* silent */ } finally { loading.value = false }
})

// Watch perubahan route (navigasi /selesai & /overdue keduanya redirect kesini)
watch(() => route.query.tab, syncTabFromQuery)

const togglePenerima = (userId) => {
  const idx = selectedPenerima.value.indexOf(userId)
  if (idx === -1) selectedPenerima.value.push(userId)
  else selectedPenerima.value.splice(idx, 1)
}

const openModal = async () => {
  showModal.value = true
  submitError.value = ''
  suratSearch.value = ''
  selectedPenerima.value = []
  formDisposisi.value.surat_masuk_id = ''

  // Load surat masuk & users concurrently
  const promises = []
  if (suratList.value.length === 0) {
    promises.push(api.get('/surat').then(({ data }) => { suratList.value = data.data || [] }).catch(() => {}))
  }
  if (usersList.value.length === 0) {
    promises.push(api.get('/users').then(({ data }) => { usersList.value = data.data || [] }).catch(() => {}))
  }
  await Promise.all(promises)
}

const handleSubmit = async () => {
  if (!formDisposisi.value.isi_disposisi || !formDisposisi.value.surat_masuk_id || selectedPenerima.value.length === 0) {
    submitError.value = 'Mohon lengkapi semua field dan pilih minimal 1 penerima.'
    return
  }
  submitting.value = true
  submitError.value = ''
  try {
    await api.post('/disposisi', {
      ...formDisposisi.value,
      penerima_ids: selectedPenerima.value
    })
    showModal.value = false
    selectedPenerima.value = []
    Object.keys(formDisposisi.value).forEach(k => formDisposisi.value[k] = k === 'prioritas' ? 'NORMAL' : '')
    // Refresh list
    const { data } = await api.get('/disposisi')
    disposisiList.value = data.data || []
  } catch (err) {
    submitError.value = err.response?.data?.message || 'Gagal membuat disposisi.'
  } finally {
    submitting.value = false
  }
}

const goDetail = (id) => router.push({ name: 'disposisi-detail', params: { id } })

function initials(nama) {
  return (nama || '').split(' ').slice(0,2).map(w => w[0]?.toUpperCase()).join('')
}
</script>

<template>
  <div class="page-container animate-fade-in">
    <div class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4" style="background: linear-gradient(135deg, #1565c0 0%, #1976d2 50%, #42a5f5 100%);">
      <div>
        <p class="text-blue-200 text-xs font-bold uppercase tracking-widest mb-1 relative z-10">Manajemen</p>
        <h2 class="page-hero-title">Disposisi</h2>
        <p class="page-hero-sub">Buat, lacak, dan kelola disposisi surat dari Direktur.</p>
      </div>
      <Button label="Tulis Disposisi" icon="pi pi-pencil" class="relative z-10 !bg-white !text-brandBlue !border-0 shadow-glow" @click="openModal" />
    </div>

    <SelectButton v-model="activeTab" :options="tabs" class="mb-4 flex-wrap" />

    <Card class="color-panel glass-card">
      <template #title>
        <div class="flex items-center gap-2 font-bold">
          <div class="w-8 h-8 rounded-lg bg-brandPurpleBg flex items-center justify-center">
            <i class="pi pi-send text-brandPurple"></i>
          </div>
          Daftar Disposisi
          <Tag v-if="!loading" :value="`${filteredList.length} data`" severity="info" />
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
          class="text-sm"
          @row-click="(e) => goDetail(e.data.id)"
        >
          <template #empty>
            <div class="text-center py-14">
              <div class="w-16 h-16 rounded-2xl bg-surface2 flex items-center justify-center mx-auto mb-3">
                <i class="pi pi-send text-3xl text-textDim"></i>
              </div>
              <p class="text-textMuted text-sm">Tidak ada data dengan filter ini.</p>
            </div>
          </template>
          <Column field="nomor_disposisi" header="Nomor">
            <template #body="{ data }">
              <span class="font-mono text-xs font-bold text-brandPurple bg-brandPurpleBg px-2 py-0.5 rounded-md">{{ data.nomor_disposisi }}</span>
            </template>
          </Column>
          <Column field="perihal" header="Perihal">
            <template #body="{ data }">
              <span class="font-medium max-w-[260px] truncate block">{{ data.perihal }}</span>
            </template>
          </Column>
          <Column field="asal_surat" header="Asal Surat" />
          <Column header="Prioritas">
            <template #body="{ data }">
              <Tag
                :value="data.prioritas"
                :severity="(data.prioritas === 'URGENT' || data.prioritas === 'TINGGI') ? 'danger' : 'secondary'"
              />
            </template>
          </Column>
          <Column header="Status">
            <template #body="{ data }">
              <Tag
                :value="statusLabel[data.status_global] || data.status_global"
                :severity="statusSeverity[data.status_global] || 'secondary'"
              />
            </template>
          </Column>
          <Column field="batas_waktu" header="Batas Waktu" />
          <Column header="Aksi" style="width: 100px">
            <template #body="{ data }">
              <Button label="Detail" icon="pi pi-arrow-right" icon-pos="right" size="small" class="!bg-brandBlueBg !text-brandBlue" @click.stop="goDetail(data.id)" />
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <Dialog v-model:visible="showModal" modal header="Tulis Disposisi" :style="{ width: 'min(640px, 95vw)' }" :draggable="false">
      <form @submit.prevent="handleSubmit" class="flex flex-col gap-4">
        <Message v-if="submitError" severity="error" :closable="false">{{ submitError }}</Message>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Pilih Surat Masuk *</label>
          <InputText v-model="suratSearch" placeholder="Cari nomor agenda, perihal, atau pengirim..." class="w-full mb-2" />
          <Select
            v-model="formDisposisi.surat_masuk_id"
            :options="filteredSurat"
            option-label="perihal"
            option-value="id"
            placeholder="Pilih surat masuk"
            class="w-full"
            filter
          >
            <template #option="{ option }">
              [{{ option.nomor_agenda }}] {{ option.perihal }} — {{ option.asal_surat }}
            </template>
          </Select>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Isi Disposisi *</label>
          <Textarea v-model="formDisposisi.isi_disposisi" rows="3" placeholder="Tuliskan instruksi disposisi..." class="w-full" required />
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
              class="w-full"
            />
          </div>
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold text-textMuted uppercase">Batas Waktu</label>
            <InputText v-model="formDisposisi.batas_waktu" type="date" class="w-full" />
          </div>
        </div>

        <div class="flex flex-col gap-2">
          <label class="text-xs font-bold text-textMuted uppercase">Penerima Disposisi *</label>
          <div v-if="usersList.length === 0" class="text-xs text-orange-600">Daftar pengguna belum tersedia.</div>
          <div v-else class="flex flex-col gap-2 max-h-40 overflow-y-auto border border-surface-200 rounded-lg p-3">
            <div v-for="u in usersList" :key="u.id" class="flex items-center gap-3">
              <Checkbox :input-id="`user-${u.id}`" :value="u.id" v-model="selectedPenerima" />
              <label :for="`user-${u.id}`" class="flex-1 cursor-pointer">
                <div class="text-sm font-semibold">{{ u.nama_lengkap }}</div>
                <div class="text-xs text-textMuted">{{ u.jabatan || u.role }}</div>
              </label>
            </div>
          </div>
          <p class="text-xs text-textMuted">{{ selectedPenerima.length }} penerima dipilih</p>
        </div>

        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMuted uppercase">Catatan Tambahan</label>
          <Textarea v-model="formDisposisi.catatan_direktur" rows="2" placeholder="(Opsional)" class="w-full" />
        </div>

        <div class="flex justify-end gap-2 pt-4 border-t border-surface-200">
          <Button label="Batal" severity="secondary" outlined type="button" @click="showModal = false" />
          <Button type="submit" label="Kirim Disposisi" icon="pi pi-send" class="btn-gradient" :loading="submitting" />
        </div>
      </form>
    </Dialog>
  </div>
</template>

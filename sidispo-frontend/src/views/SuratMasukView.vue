<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api/axios'

const suratList = ref([])
const loading   = ref(true)
const showModal = ref(false)
const submitting = ref(false)
const submitMsg  = ref({ type: '', text: '' })
const preview   = ref(null)
const route  = useRoute()
const router = useRouter()

// Folders list – loaded from API
const folders   = ref([])

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

onMounted(async () => {
  try {
    const [suratRes, folderRes] = await Promise.all([
      api.get('/surat'),
      api.get('/folder')
    ])
    suratList.value = suratRes.data.data || []
    folders.value   = folderRes.data.data || []
  } catch (e) { /* silent */ } finally { loading.value = false }
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
    // Refresh list
    const { data } = await api.get('/surat')
    suratList.value = data.data || []
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
  <div class="flex-1 overflow-y-auto p-6 animate-[fadeIn_0.4s_ease]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <h1 class="text-2xl font-bold text-textMain">Surat Masuk</h1>
          <!-- Filter folder chip -->
          <span v-if="activeFolder"
            class="flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-full border"
            :style="{ background: activeFolder.warna + '22', borderColor: activeFolder.warna, color: activeFolder.warna }">
            <span class="w-2 h-2 rounded-full" :style="{ background: activeFolder.warna }"></span>
            {{ activeFolder.nama }}
            <button @click="router.push('/surat-masuk')" class="ml-1 hover:opacity-70 text-sm leading-none">×</button>
          </span>
        </div>
        <p class="text-sm text-textMuted">
          <template v-if="activeFolder">{{ displayList.length }} surat di folder ini.</template>
          <template v-else>Registrasi dan arsip surat masuk ke sistem.</template>
        </p>
      </div>
      <button @click="showModal = true"
        class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold transition-all flex items-center gap-2 shadow-sm">
        <span>📥</span> Registrasi Surat
      </button>
    </div>

    <!-- Table -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead class="text-xs text-textMuted uppercase bg-surface3 border-b border-border">
            <tr>
              <th class="px-5 py-3">No. Agenda</th>
              <th class="px-5 py-3">No. Surat</th>
              <th class="px-5 py-3">Perihal</th>
              <th class="px-5 py-3">Asal Surat</th>
              <th class="px-5 py-3">Tgl Terima</th>
              <th class="px-5 py-3">Lampiran</th>
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" v-for="i in 5" :key="i" class="border-b border-border">
              <td colspan="7" class="px-5 py-4">
                <div class="h-3 bg-surface3 rounded animate-pulse w-full"></div>
              </td>
            </tr>
            <tr v-else v-for="surat in suratList" :key="surat.id"
              class="border-b border-border hover:bg-surface2 transition-all cursor-pointer"
              @click="openDetail(surat.id)">
              <td class="px-5 py-3 font-mono text-accent text-xs">{{ surat.nomor_agenda }}</td>
              <td class="px-5 py-3 text-xs text-textMuted">{{ surat.nomor_surat }}</td>
              <td class="px-5 py-3 font-medium max-w-[240px] truncate" :title="surat.perihal">{{ surat.perihal }}</td>
              <td class="px-5 py-3 text-xs text-textMuted">{{ surat.asal_surat }}</td>
              <td class="px-5 py-3 text-xs text-textMuted">{{ formatDate(surat.tanggal_terima) }}</td>
              <td class="px-5 py-3 text-xs">
                <span v-if="surat.jumlah_file > 0" class="text-accent">📎 {{ surat.jumlah_file }}</span>
                <span v-else class="text-textDim">—</span>
              </td>
              <td class="px-5 py-3 text-right">
                <button @click.stop="openDetail(surat.id)"
                  class="text-xs bg-accentGlow text-accent px-3 py-1.5 rounded-md font-semibold hover:bg-accent hover:text-white transition-all">
                  Lihat →
                </button>
              </td>
            </tr>
            <tr v-if="!loading && suratList.length === 0">
              <td colspan="7" class="px-5 py-10 text-center text-textMuted">Belum ada surat masuk.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Preview Drawer -->
    <div v-if="preview" class="fixed inset-0 z-50 flex" @click.self="preview = null">
      <div class="ml-auto w-full max-w-lg bg-surface border-l border-border h-full overflow-y-auto shadow-2xl animate-[slideInRight_0.25s_ease]">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="font-bold text-base">Detail Surat</h3>
          <button @click="preview = null" class="text-textMuted hover:text-textMain text-xl">&times;</button>
        </div>
        <div class="p-5 flex flex-col gap-4">
          <div class="grid grid-cols-2 gap-3 text-sm">
            <div><span class="text-textMuted text-xs block mb-1">No. Agenda</span><span class="font-mono text-accent">{{ preview.nomor_agenda }}</span></div>
            <div><span class="text-textMuted text-xs block mb-1">No. Surat</span><span>{{ preview.nomor_surat }}</span></div>
            <div class="col-span-2"><span class="text-textMuted text-xs block mb-1">Perihal</span><span class="font-semibold">{{ preview.perihal }}</span></div>
            <div><span class="text-textMuted text-xs block mb-1">Asal Surat</span><span>{{ preview.asal_surat }}</span></div>
            <div><span class="text-textMuted text-xs block mb-1">Tgl Surat</span><span>{{ formatDate(preview.tanggal_surat) }}</span></div>
            <div><span class="text-textMuted text-xs block mb-1">Tgl Terima</span><span>{{ formatDate(preview.tanggal_terima) }}</span></div>
            <div v-if="preview.keterangan" class="col-span-2"><span class="text-textMuted text-xs block mb-1">Keterangan</span><span>{{ preview.keterangan }}</span></div>
          </div>
          <div v-if="preview.files?.length" class="border-t border-border pt-4">
            <div class="text-xs font-bold text-textMuted uppercase mb-2">Lampiran ({{ preview.files.length }})</div>
            <a v-for="f in preview.files" :key="f.id"
              :href="fileUrl(f.path_file)" target="_blank"
              class="flex items-center gap-3 p-2.5 mb-2 rounded-lg border border-border hover:border-accent hover:bg-surface2 transition-all">
              <span class="text-xl">📄</span>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate">{{ f.nama_asli }}</div>
                <div class="text-xs text-textMuted">{{ Math.round(f.ukuran_bytes / 1024) }} KB</div>
              </div>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Modal Registrasi Surat -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-2xl shadow-2xl overflow-hidden animate-[fadeIn_0.2s_ease] flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2 shrink-0">
          <h3 class="text-lg font-bold">📥 Registrasi Surat Masuk</h3>
          <button @click="showModal = false" class="text-textMuted hover:text-textMain text-xl leading-none">&times;</button>
        </div>
        <form @submit.prevent="handleSubmit" class="p-6 overflow-y-auto flex flex-col gap-4">
          <div v-if="submitMsg.text" class="p-3 rounded-lg text-sm"
            :class="submitMsg.type === 'success' ? 'bg-brandGreenBg text-brandGreen' : 'bg-brandRedBg text-brandRed'">
            {{ submitMsg.text }}
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">No. Agenda</label>
              <input v-model="form.nomor_agenda" type="text" placeholder="SM-2026-00001"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">No. Surat *</label>
              <input v-model="form.nomor_surat" type="text" placeholder="BPJS/KES/2026/001"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" required />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Perihal *</label>
            <input v-model="form.perihal" type="text" placeholder="Perihal surat..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" required />
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Asal Surat *</label>
            <input v-model="form.asal_surat" type="text" placeholder="Nama instansi / pengirim"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" required />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Tanggal Surat</label>
              <input v-model="form.tanggal_surat" type="date"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Tanggal Terima</label>
              <input v-model="form.tanggal_terima" type="date"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" />
            </div>
          </div>

          <!-- Folder dropdown – loaded from API, no longer hardcoded -->
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Folder / Kategori</label>
            <select v-model="form.folder_id"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none">
              <option value="">— Pilih folder (opsional) —</option>
              <option v-for="f in folders" :key="f.id" :value="f.id">
                {{ f.parent_id ? '↳ ' : '' }}{{ f.nama }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Keterangan</label>
            <textarea v-model="form.keterangan" rows="2"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent"
              placeholder="(Opsional)"></textarea>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Lampiran</label>
            <input @change="onFileChange" type="file" multiple accept=".pdf,.doc,.docx,.jpg,.png"
              class="w-full text-sm text-textMuted file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:font-semibold file:bg-accentGlow file:text-accent hover:file:cursor-pointer" />
            <p class="text-xs text-textMuted mt-1">PDF, DOC, DOCX, JPG, PNG — maks 10MB per file</p>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-border">
            <button type="button" @click="showModal = false"
              class="px-5 py-2.5 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain">Batal</button>
            <button type="submit" :disabled="submitting"
              class="px-5 py-2.5 text-sm font-semibold rounded-lg bg-accent text-white hover:bg-accentHover shadow-[0_0_15px_var(--accentGlow)] disabled:opacity-60">
              <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-1"></span>
              {{ submitting ? 'Menyimpan...' : 'Simpan Surat' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

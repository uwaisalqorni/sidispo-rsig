<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/api/axios'

const folders     = ref([])
const loading     = ref(true)
const showModal   = ref(false)
const editMode    = ref(false)
const submitting  = ref(false)
const submitError = ref('')
const selectedFolder = ref(null)

const COLORS = [
  { label: 'Hijau',  hex: '#4a9e4a' },
  { label: 'Biru',   hex: '#1565c0' },
  { label: 'Ungu',   hex: '#6a1b9a' },
  { label: 'Merah',  hex: '#c62828' },
  { label: 'Oranye', hex: '#e65100' },
  { label: 'Cyan',   hex: '#00838f' },
  { label: 'Kuning', hex: '#f9a825' },
  { label: 'Abu',    hex: '#546e7a' },
]

const form = ref({ nama: '', deskripsi: '', parent_id: '', warna: '#4a9e4a', urutan: 99 })

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/admin/folders')
    folders.value = data.data || []
  } catch { /* silent */ } finally { loading.value = false }
}

onMounted(load)

const openCreate = () => {
  editMode.value = false
  submitError.value = ''
  Object.assign(form.value, { nama: '', deskripsi: '', parent_id: '', warna: '#4a9e4a', urutan: 99 })
  showModal.value = true
}

const openEdit = (folder) => {
  editMode.value = true
  submitError.value = ''
  selectedFolder.value = folder
  Object.assign(form.value, {
    nama: folder.nama, deskripsi: folder.deskripsi || '',
    parent_id: folder.parent_id || '', warna: folder.warna || '#4a9e4a', urutan: folder.urutan || 99
  })
  showModal.value = true
}

const handleSubmit = async () => {
  if (!form.value.nama) { submitError.value = 'Nama folder wajib diisi.'; return }
  submitting.value = true; submitError.value = ''
  try {
    const payload = { ...form.value, parent_id: form.value.parent_id || null }
    if (editMode.value) {
      await api.put(`/admin/folders/${selectedFolder.value.id}`, payload)
    } else {
      await api.post('/admin/folders', payload)
    }
    showModal.value = false
    await load()
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Gagal menyimpan folder.'
  } finally { submitting.value = false }
}

const handleDelete = async (folder) => {
  if (!confirm(`Hapus folder "${folder.nama}"? Aksi ini tidak dapat dibatalkan.`)) return
  try {
    await api.delete(`/admin/folders/${folder.id}`)
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menghapus folder.')
  }
}

// Build parent-tree for display — proper computed via Composition API
const parentFolders = computed(() => folders.value.filter(f => !f.parent_id))
const childFolders  = (parentId) => folders.value.filter(f => f.parent_id == parentId)
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-textMain mb-1">📁 Manajemen Folder</h1>
        <p class="text-sm text-textMuted">Kelola folder dan kategori arsip surat.</p>
      </div>
      <button @click="openCreate"
        class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold flex items-center gap-2 shadow-sm transition-all">
        ➕ Tambah Folder
      </button>
    </div>

    <!-- Folder List -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden">
      <div class="p-4 border-b border-border bg-surface2 flex items-center justify-between">
        <span class="text-sm font-bold text-textMain">Daftar Folder ({{ folders.length }})</span>
      </div>
      
      <div v-if="loading" class="p-6 text-center text-textMuted text-sm">Memuat...</div>
      <div v-else-if="folders.length === 0" class="p-10 text-center text-textMuted text-sm">Belum ada folder.</div>

      <div v-else class="divide-y divide-border">
        <!-- Parent folders -->
        <template v-for="folder in parentFolders" :key="folder.id">
          <!-- Parent row -->
          <div class="flex items-center gap-3 px-5 py-3 hover:bg-surface2 transition-all group">
            <div class="w-3 h-3 rounded-full shrink-0" :style="{ background: folder.warna }"></div>
            <div class="flex-1 min-w-0">
              <div class="font-semibold text-[13.5px] text-textMain">{{ folder.nama }}</div>
              <div v-if="folder.deskripsi" class="text-[11px] text-textMuted truncate">{{ folder.deskripsi }}</div>
            </div>
            <span class="text-xs text-textDim font-mono">urutan: {{ folder.urutan }}</span>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
              <button @click="openEdit(folder)"
                class="text-xs px-2.5 py-1 rounded bg-accentGlow text-accent hover:bg-accent hover:text-white transition-all font-semibold">✏️</button>
              <button @click="handleDelete(folder)"
                class="text-xs px-2.5 py-1 rounded bg-brandRedBg text-brandRed hover:bg-brandRed hover:text-white transition-all font-semibold">🗑️</button>
            </div>
          </div>

          <!-- Child folders -->
          <div v-for="child in childFolders(folder.id)" :key="child.id"
            class="flex items-center gap-3 px-5 py-2.5 pl-10 hover:bg-surface2 transition-all group bg-surface2/40">
            <div class="w-2 h-2 rounded-full shrink-0" :style="{ background: child.warna }"></div>
            <div class="flex-1 min-w-0">
              <div class="text-[13px] text-textMuted">↳ {{ child.nama }}</div>
              <div v-if="child.deskripsi" class="text-[11px] text-textDim truncate">{{ child.deskripsi }}</div>
            </div>
            <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-all">
              <button @click="openEdit(child)"
                class="text-xs px-2.5 py-1 rounded bg-accentGlow text-accent hover:bg-accent hover:text-white transition-all font-semibold">✏️</button>
              <button @click="handleDelete(child)"
                class="text-xs px-2.5 py-1 rounded bg-brandRedBg text-brandRed hover:bg-brandRed hover:text-white transition-all font-semibold">🗑️</button>
            </div>
          </div>
        </template>
      </div>
    </div>

    <!-- Modal Create/Edit -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-md shadow-2xl">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="text-base font-bold text-textMain">{{ editMode ? '✏️ Edit Folder' : '➕ Tambah Folder' }}</h3>
          <button @click="showModal = false" class="text-textMuted hover:text-textMain text-xl">×</button>
        </div>
        <form @submit.prevent="handleSubmit" class="p-5 flex flex-col gap-4">
          <div v-if="submitError" class="p-3 rounded-lg bg-brandRedBg border border-brandRed/20 text-sm text-brandRed">{{ submitError }}</div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Nama Folder *</label>
            <input v-model="form.nama" type="text" placeholder="Nama folder"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" required />
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Deskripsi</label>
            <input v-model="form.deskripsi" type="text" placeholder="Keterangan singkat folder"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Parent Folder</label>
              <select v-model="form.parent_id"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none">
                <option value="">— Folder Utama —</option>
                <option v-for="f in folders.filter(f2 => !f2.parent_id && f2.id !== selectedFolder?.id)" :key="f.id" :value="f.id">{{ f.nama }}</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Urutan</label>
              <input v-model.number="form.urutan" type="number" min="1"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-2">Warna Label</label>
            <div class="flex flex-wrap gap-2">
              <button type="button" v-for="c in COLORS" :key="c.hex"
                @click="form.warna = c.hex"
                class="w-8 h-8 rounded-full border-2 transition-all flex items-center justify-center"
                :style="{ background: c.hex }"
                :class="form.warna === c.hex ? 'border-textMain scale-110 shadow-md' : 'border-transparent'">
                <span v-if="form.warna === c.hex" class="text-white text-xs font-bold">✓</span>
              </button>
              <input v-model="form.warna" type="color" class="w-8 h-8 rounded-full border-2 border-border cursor-pointer" title="Pilih warna custom" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-3 border-t border-border">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain">Batal</button>
            <button type="submit" :disabled="submitting"
              class="px-5 py-2 text-sm font-semibold rounded-lg bg-accent text-white hover:bg-accentHover disabled:opacity-60 flex items-center gap-2">
              <span v-if="submitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              {{ submitting ? 'Menyimpan...' : 'Simpan' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

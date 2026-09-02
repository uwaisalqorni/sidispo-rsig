<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/api/axios'

const perihalList  = ref([])
const loading      = ref(true)
const showModal    = ref(false)
const showDelModal = ref(false)
const submitting   = ref(false)
const editMode     = ref(false)
const editId       = ref(null)
const deleteTarget = ref(null)
const msg          = ref({ type: '', text: '' })
const searchQuery  = ref('')

const form = ref({ nama: '', keterangan: '', is_active: true })

// Filter berdasarkan search
const filteredList = computed(() => {
  const q = searchQuery.value.toLowerCase()
  if (!q) return perihalList.value
  return perihalList.value.filter(p =>
    p.nama.toLowerCase().includes(q) ||
    (p.keterangan || '').toLowerCase().includes(q)
  )
})

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/perihal')
    perihalList.value = data.data || []
  } catch { /* silent */ } finally { loading.value = false }
}

onMounted(load)

function openCreate() {
  editMode.value = false
  editId.value   = null
  form.value     = { nama: '', keterangan: '', is_active: true }
  msg.value      = { type: '', text: '' }
  showModal.value = true
}

function openEdit(p) {
  editMode.value = true
  editId.value   = p.id
  form.value     = { nama: p.nama, keterangan: p.keterangan || '', is_active: !!Number(p.is_active) }
  msg.value      = { type: '', text: '' }
  showModal.value = true
}

function openDelete(p) {
  deleteTarget.value = p
  showDelModal.value = true
}

const handleSubmit = async () => {
  if (!form.value.nama.trim()) {
    msg.value = { type: 'error', text: 'Nama perihal wajib diisi.' }
    return
  }
  submitting.value = true
  msg.value = { type: '', text: '' }
  try {
    const payload = {
      nama:       form.value.nama.trim(),
      keterangan: form.value.keterangan,
      is_active:  form.value.is_active ? 1 : 0
    }
    if (editMode.value) {
      await api.put(`/admin/perihal/${editId.value}`, payload)
      msg.value = { type: 'success', text: 'Perihal berhasil diperbarui.' }
    } else {
      await api.post('/admin/perihal', payload)
      msg.value = { type: 'success', text: 'Perihal berhasil ditambahkan.' }
    }
    await load()
    setTimeout(() => { showModal.value = false; msg.value = { type: '', text: '' } }, 1200)
  } catch (e) {
    msg.value = { type: 'error', text: e.response?.data?.message || 'Gagal menyimpan perihal.' }
  } finally { submitting.value = false }
}

const handleDelete = async () => {
  if (!deleteTarget.value) return
  submitting.value = true
  try {
    await api.delete(`/admin/perihal/${deleteTarget.value.id}`)
    showDelModal.value = false
    deleteTarget.value = null
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menghapus perihal.')
  } finally { submitting.value = false }
}

const toggleActive = async (p) => {
  try {
    await api.put(`/admin/perihal/${p.id}/toggle`)
    await load()
  } catch { /* silent */ }
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6 animate-[fadeIn_0.4s_ease]">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-textMain mb-1">📋 Master Perihal</h1>
        <p class="text-sm text-textMuted">Kelola daftar perihal surat yang dapat dipilih saat registrasi surat masuk.</p>
      </div>
      <button @click="openCreate"
        class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold transition-all flex items-center gap-2 shadow-sm">
        <span>＋</span> Tambah Perihal
      </button>
    </div>

    <!-- Search bar -->
    <div class="mb-4">
      <div class="relative max-w-sm">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-textMuted text-sm">🔍</span>
        <input v-model="searchQuery" type="text" placeholder="Cari perihal..."
          class="w-full bg-surface border border-border rounded-lg pl-9 pr-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
      </div>
    </div>

    <!-- Table -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="text-xs text-textMuted uppercase bg-surface3 border-b border-border">
            <tr>
              <th class="px-5 py-3 w-10">#</th>
              <th class="px-5 py-3">Nama Perihal</th>
              <th class="px-5 py-3">Keterangan</th>
              <th class="px-5 py-3">Dibuat</th>
              <th class="px-5 py-3 text-center">Status</th>
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <!-- Skeleton loading -->
            <tr v-if="loading" v-for="i in 5" :key="i" class="border-b border-border">
              <td colspan="6" class="px-5 py-4">
                <div class="h-3 bg-surface3 rounded animate-pulse w-full"></div>
              </td>
            </tr>

            <!-- Data rows -->
            <tr v-else v-for="(p, idx) in filteredList" :key="p.id"
              class="border-b border-border hover:bg-surface2 transition-all">
              <td class="px-5 py-3 text-textDim text-xs font-mono">{{ idx + 1 }}</td>
              <td class="px-5 py-3">
                <span class="font-semibold text-textMain">{{ p.nama }}</span>
              </td>
              <td class="px-5 py-3 text-xs text-textMuted max-w-[280px] truncate" :title="p.keterangan">
                {{ p.keterangan || '—' }}
              </td>
              <td class="px-5 py-3 text-xs text-textMuted">{{ formatDate(p.created_at) }}</td>
              <td class="px-5 py-3 text-center">
                <button @click="toggleActive(p)"
                  class="relative w-10 h-5 rounded-full transition-all duration-300 focus:outline-none"
                  :class="Number(p.is_active) ? 'bg-accent' : 'bg-border'"
                  :title="Number(p.is_active) ? 'Aktif — klik untuk nonaktifkan' : 'Nonaktif — klik untuk aktifkan'">
                  <span class="absolute top-0.5 w-4 h-4 bg-white rounded-full shadow transition-all duration-300"
                    :class="Number(p.is_active) ? 'left-5' : 'left-0.5'"></span>
                </button>
              </td>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="openEdit(p)"
                    class="text-xs bg-accentGlow text-accent px-3 py-1.5 rounded-md font-semibold hover:bg-accent hover:text-white transition-all">
                    ✏️ Edit
                  </button>
                  <button @click="openDelete(p)"
                    class="text-xs bg-brandRedBg text-brandRed px-3 py-1.5 rounded-md font-semibold hover:bg-brandRed hover:text-white transition-all">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>

            <!-- Empty state -->
            <tr v-if="!loading && filteredList.length === 0">
              <td colspan="6" class="px-5 py-12 text-center text-textMuted">
                <div class="text-4xl mb-3">📭</div>
                <div class="font-semibold">Belum ada data perihal.</div>
                <div class="text-xs mt-1">Klik "Tambah Perihal" untuk menambahkan data baru.</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Footer count -->
      <div v-if="!loading" class="px-5 py-3 border-t border-border text-xs text-textMuted bg-surface2">
        Menampilkan {{ filteredList.length }} dari {{ perihalList.length }} perihal
      </div>
    </div>

    <!-- ── Modal Tambah / Edit ─────────────────────────────────────────── -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-lg shadow-2xl overflow-hidden animate-[fadeIn_0.2s_ease]">
        <!-- Header -->
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="text-lg font-bold text-textMain">
            {{ editMode ? '✏️ Edit Perihal' : '➕ Tambah Perihal Baru' }}
          </h3>
          <button @click="showModal = false" class="text-textMuted hover:text-textMain text-xl leading-none">&times;</button>
        </div>

        <!-- Form -->
        <form @submit.prevent="handleSubmit" class="p-6 flex flex-col gap-4">
          <!-- Alert -->
          <div v-if="msg.text" class="p-3 rounded-lg text-sm font-medium"
            :class="msg.type === 'success' ? 'bg-brandGreenBg text-brandGreen' : 'bg-brandRedBg text-brandRed'">
            {{ msg.text }}
          </div>

          <!-- Nama -->
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">
              Nama Perihal <span class="text-brandRed">*</span>
            </label>
            <input v-model="form.nama" type="text" placeholder="Contoh: Undangan Rapat Koordinasi"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none"
              required autofocus />
          </div>

          <!-- Keterangan -->
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Keterangan (Opsional)</label>
            <textarea v-model="form.keterangan" rows="3"
              placeholder="Deskripsi singkat perihal ini..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none resize-none">
            </textarea>
          </div>

          <!-- Status aktif -->
          <div class="flex items-center justify-between p-3.5 rounded-lg border border-border bg-surface2">
            <div>
              <div class="text-sm font-semibold text-textMain">Status Aktif</div>
              <div class="text-xs text-textMuted mt-0.5">Perihal nonaktif tidak akan muncul di dropdown form surat</div>
            </div>
            <button type="button" @click="form.is_active = !form.is_active"
              class="relative w-12 h-6 rounded-full transition-all duration-300 focus:outline-none shrink-0 ml-4"
              :class="form.is_active ? 'bg-accent' : 'bg-border'">
              <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-300"
                :class="form.is_active ? 'left-6' : 'left-0.5'"></span>
            </button>
          </div>

          <!-- Actions -->
          <div class="flex justify-end gap-3 pt-2 border-t border-border">
            <button type="button" @click="showModal = false"
              class="px-5 py-2.5 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain">
              Batal
            </button>
            <button type="submit" :disabled="submitting"
              class="px-5 py-2.5 text-sm font-semibold rounded-lg bg-accent text-white hover:bg-accentHover shadow-[0_0_15px_var(--accentGlow)] disabled:opacity-60 flex items-center gap-2">
              <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              {{ submitting ? 'Menyimpan...' : (editMode ? 'Simpan Perubahan' : 'Tambah Perihal') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ── Modal Konfirmasi Hapus ────────────────────────────────────────── -->
    <div v-if="showDelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-sm shadow-2xl animate-[fadeIn_0.2s_ease]">
        <div class="p-5 border-b border-border bg-surface2">
          <h3 class="font-bold text-textMain">🗑️ Hapus Perihal</h3>
        </div>
        <div class="p-5">
          <p class="text-sm text-textMuted">Apakah Anda yakin ingin menghapus perihal:</p>
          <p class="mt-2 font-semibold text-textMain bg-surface2 px-3 py-2 rounded-lg border border-border text-sm">
            {{ deleteTarget?.nama }}
          </p>
          <p class="text-xs text-brandRed mt-3">⚠️ Aksi ini tidak dapat dibatalkan.</p>
          <div class="flex justify-end gap-3 mt-5">
            <button @click="showDelModal = false; deleteTarget = null"
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2">
              Batal
            </button>
            <button @click="handleDelete" :disabled="submitting"
              class="px-4 py-2 text-sm font-semibold rounded-lg bg-brandRed text-white hover:opacity-90 disabled:opacity-60 flex items-center gap-2">
              <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              {{ submitting ? 'Menghapus...' : 'Ya, Hapus' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

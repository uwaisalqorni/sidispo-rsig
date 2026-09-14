<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import Paginator from 'primevue/paginator'
import api from '@/api/axios'

const asalList     = ref([])
const loading      = ref(true)
const showModal    = ref(false)
const showDelModal = ref(false)
const submitting   = ref(false)
const editMode     = ref(false)
const editId       = ref(null)
const deleteTarget = ref(null)
const msg          = ref({ type: '', text: '' })
const searchQuery  = ref('')

const form = ref({
  nama: '',
  kode: '',
  kategori: '',
  alamat: '',
  kontak: '',
  keterangan: '',
  is_active: true
})

// Kategori options default
const kategoriOptions = [
  'Pemerintah',
  'Asuransi',
  'Fasilitas Kesehatan',
  'Organisasi Profesi',
  'Pendidikan / Universitas',
  'Vendor / Rekanan',
  'Perusahaan Swasta',
  'Internal RS',
  'Lainnya'
]

// Filter berdasarkan search
const filteredList = computed(() => {
  const q = searchQuery.value.toLowerCase()
  if (!q) return asalList.value
  return asalList.value.filter(a =>
    a.nama.toLowerCase().includes(q) ||
    (a.kode || '').toLowerCase().includes(q) ||
    (a.kategori || '').toLowerCase().includes(q) ||
    (a.keterangan || '').toLowerCase().includes(q)
  )
})

// ── Pagination ─────────────────────────────────────────────────────────────
const first = ref(0)
const rows = ref(10)

const paginatedList = computed(() => {
  return filteredList.value.slice(first.value, first.value + rows.value)
})

watch(searchQuery, () => {
  first.value = 0
})

const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/asal-surat')
    asalList.value = data.data || []
  } catch { /* silent */ } finally { loading.value = false }
}

onMounted(load)

function openCreate() {
  editMode.value = false
  editId.value   = null
  form.value     = {
    nama: '',
    kode: '',
    kategori: 'Pemerintah',
    alamat: '',
    kontak: '',
    keterangan: '',
    is_active: true
  }
  msg.value      = { type: '', text: '' }
  showModal.value = true
}

function openEdit(a) {
  editMode.value = true
  editId.value   = a.id
  form.value     = {
    nama: a.nama,
    kode: a.kode || '',
    kategori: a.kategori || 'Pemerintah',
    alamat: a.alamat || '',
    kontak: a.kontak || '',
    keterangan: a.keterangan || '',
    is_active: !!Number(a.is_active)
  }
  msg.value      = { type: '', text: '' }
  showModal.value = true
}

function openDelete(a) {
  deleteTarget.value = a
  showDelModal.value = true
}

const handleSubmit = async () => {
  if (!form.value.nama.trim()) {
    msg.value = { type: 'error', text: 'Nama instansi/asal surat wajib diisi.' }
    return
  }
  submitting.value = true
  msg.value = { type: '', text: '' }
  try {
    const payload = {
      nama:       form.value.nama.trim(),
      kode:       form.value.kode.trim() || null,
      kategori:   form.value.kategori || null,
      alamat:     form.value.alamat.trim() || null,
      kontak:     form.value.kontak.trim() || null,
      keterangan: form.value.keterangan.trim() || null,
      is_active:  form.value.is_active ? 1 : 0
    }
    if (editMode.value) {
      await api.put(`/admin/asal-surat/${editId.value}`, payload)
      msg.value = { type: 'success', text: 'Asal surat berhasil diperbarui.' }
    } else {
      await api.post('/admin/asal-surat', payload)
      msg.value = { type: 'success', text: 'Asal surat berhasil ditambahkan.' }
    }
    await load()
    setTimeout(() => { showModal.value = false; msg.value = { type: '', text: '' } }, 1200)
  } catch (e) {
    msg.value = { type: 'error', text: e.response?.data?.message || 'Gagal menyimpan asal surat.' }
  } finally { submitting.value = false }
}

const handleDelete = async () => {
  if (!deleteTarget.value) return
  submitting.value = true
  try {
    await api.delete(`/admin/asal-surat/${deleteTarget.value.id}`)
    showDelModal.value = false
    deleteTarget.value = null
    await load()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal menghapus asal surat.')
  } finally { submitting.value = false }
}

const toggleActive = async (a) => {
  try {
    await api.put(`/admin/asal-surat/${a.id}/toggle`)
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
        <h1 class="text-2xl font-bold text-textMain mb-1">🏛️ Master Asal Surat</h1>
        <p class="text-sm text-textMuted">Kelola daftar instansi dan pihak pengirim surat resmi untuk mempermudah registrasi dan pencarian surat masuk.</p>
      </div>
      <button @click="openCreate"
        class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold transition-all flex items-center gap-2 shadow-sm">
        <span>＋</span> Tambah Asal Surat
      </button>
    </div>

    <!-- Search bar -->
    <div class="mb-4">
      <div class="relative max-w-sm">
        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-textMuted text-sm">🔍</span>
        <input v-model="searchQuery" type="text" placeholder="Cari nama instansi, kode, kategori..."
          class="w-full bg-surface border border-border rounded-lg pl-9 pr-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
      </div>
    </div>

    <!-- Table -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="text-xs text-textMuted uppercase bg-surface3 border-b border-border">
            <tr>
              <th class="px-5 py-3.5 w-12 text-center">#</th>
              <th class="px-5 py-3.5">Nama Instansi / Pengirim</th>
              <th class="px-5 py-3.5 w-28">Kode</th>
              <th class="px-5 py-3.5 w-40">Kategori</th>
              <th class="px-5 py-3.5">Kontak / Alamat</th>
              <th class="px-5 py-3.5 w-28 text-center">Status</th>
              <th class="px-5 py-3.5 w-32 text-center">Aksi</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-border">
            <tr v-if="loading">
              <td colspan="7" class="text-center py-12 text-textMuted">
                <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-accent mb-2"></div>
                <div>Memuat data asal surat...</div>
              </td>
            </tr>

            <tr v-else-if="filteredList.length === 0">
              <td colspan="7" class="text-center py-12 text-textMuted">
                <div class="text-3xl mb-2">🏛️</div>
                <div>{{ searchQuery ? 'Tidak ada data yang cocok dengan pencarian.' : 'Belum ada data asal surat.' }}</div>
              </td>
            </tr>

            <tr v-for="(a, idx) in paginatedList" :key="a.id"
              class="hover:bg-surface3/50 transition-colors"
              :class="{ 'opacity-50 bg-surface3/20': !Number(a.is_active) }">
              <td class="px-5 py-3.5 text-center text-textMuted font-mono text-xs">{{ first + idx + 1 }}</td>
              <td class="px-5 py-3.5 font-medium text-textMain">
                <div class="font-semibold">{{ a.nama }}</div>
                <div v-if="a.keterangan" class="text-xs text-textMuted mt-0.5">{{ a.keterangan }}</div>
              </td>
              <td class="px-5 py-3.5">
                <span v-if="a.kode" class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-brandBlueBg text-brandBlue border border-brandBlue/30">
                  {{ a.kode }}
                </span>
                <span v-else class="text-textMuted text-xs">—</span>
              </td>
              <td class="px-5 py-3.5">
                <span v-if="a.kategori" class="text-xs font-medium px-2 py-0.5 rounded-full bg-surface2 text-textMain border border-border">
                  {{ a.kategori }}
                </span>
                <span v-else class="text-textMuted text-xs">—</span>
              </td>
              <td class="px-5 py-3.5 text-xs text-textMuted">
                <div v-if="a.kontak" class="text-textMain font-medium">{{ a.kontak }}</div>
                <div v-if="a.alamat" class="truncate max-w-xs" :title="a.alamat">{{ a.alamat }}</div>
                <span v-if="!a.kontak && !a.alamat">—</span>
              </td>
              <td class="px-5 py-3.5 text-center">
                <button @click="toggleActive(a)"
                  :title="Number(a.is_active) ? 'Klik untuk nonaktifkan' : 'Klik untuk aktifkan'"
                  class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all"
                  :class="Number(a.is_active)
                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-500/20'
                    : 'bg-red-500/10 text-red-600 dark:text-red-400 hover:bg-red-500/20'">
                  <span class="w-1.5 h-1.5 rounded-full" :class="Number(a.is_active) ? 'bg-emerald-500' : 'bg-red-500'"></span>
                  {{ Number(a.is_active) ? 'Aktif' : 'Nonaktif' }}
                </button>
              </td>
              <td class="px-5 py-3.5 text-center">
                <div class="flex items-center justify-center gap-1">
                  <button @click="openEdit(a)"
                    title="Edit"
                    class="p-1.5 hover:bg-surface2 rounded text-textMuted hover:text-accent transition-colors">
                    ✏️
                  </button>
                  <button @click="openDelete(a)"
                    title="Hapus"
                    class="p-1.5 hover:bg-red-500/10 rounded text-textMuted hover:text-red-500 transition-colors">
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginator -->
      <div v-if="!loading && filteredList.length > 0" class="border-t border-border bg-surface2 px-4 py-2">
        <Paginator
          v-model:first="first"
          v-model:rows="rows"
          :totalRecords="filteredList.length"
          :rowsPerPageOptions="[10, 25, 50]"
          currentPageReportTemplate="Menampilkan {first} sampai {last} dari {totalRecords} asal surat"
          template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
          class="!bg-transparent !p-0 text-xs"
        />
      </div>

      <!-- Table footer info -->
      <div class="px-5 py-3 border-t border-border bg-surface3 text-xs text-textMuted flex items-center justify-between">
        <span>Total: {{ filteredList.length }} instansi / pengirim</span>
        <span>{{ asalList.filter(a => Number(a.is_active)).length }} aktif, {{ asalList.filter(a => !Number(a.is_active)).length }} nonaktif</span>
      </div>
    </div>

    <!-- Modal Tambah / Edit -->
    <div v-if="showModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <div class="bg-surface border border-border rounded-2xl w-full max-w-lg shadow-2xl p-6 animate-[scaleIn_0.2s_ease]">
        <div class="flex items-center justify-between mb-5">
          <h3 class="text-lg font-bold text-textMain">
            {{ editMode ? '✏️ Edit Asal Surat' : '🏛️ Tambah Asal Surat Baru' }}
          </h3>
          <button @click="showModal = false" class="text-textMuted hover:text-textMain text-xl leading-none">&times;</button>
        </div>

        <div v-if="msg.text" class="mb-4 p-3 rounded-lg text-sm"
          :class="msg.type === 'success' ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'bg-red-500/10 text-red-600 border border-red-500/20'">
          {{ msg.text }}
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
          <div>
            <label class="block text-xs font-semibold text-textMuted uppercase mb-1">
              Nama Instansi / Pengirim <span class="text-red-500">*</span>
            </label>
            <input v-model="form.nama" type="text" required
              placeholder="Contoh: BPJS Kesehatan Cabang Malang"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none" />
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-textMuted uppercase mb-1">Kode Singkatan</label>
              <input v-model="form.kode" type="text"
                placeholder="Contoh: BPJS / DINKES"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-textMuted uppercase mb-1">Kategori</label>
              <select v-model="form.kategori"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none">
                <option v-for="k in kategoriOptions" :key="k" :value="k">{{ k }}</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-semibold text-textMuted uppercase mb-1">Kontak / No. Telp</label>
              <input v-model="form.kontak" type="text"
                placeholder="(0341) 395xxx / info@instansi.go.id"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-semibold text-textMuted uppercase mb-1">Status</label>
              <div class="flex items-center gap-2 mt-2">
                <input id="activeCheck" v-model="form.is_active" type="checkbox"
                  class="rounded border-border text-accent focus:ring-accent" />
                <label for="activeCheck" class="text-sm text-textMain cursor-pointer">
                  Aktif (dapat dipilih di form)
                </label>
              </div>
            </div>
          </div>

          <div>
            <label class="block text-xs font-semibold text-textMuted uppercase mb-1">Alamat Instansi</label>
            <textarea v-model="form.alamat" rows="2"
              placeholder="Alamat lengkap instansi..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none"></textarea>
          </div>

          <div>
            <label class="block text-xs font-semibold text-textMuted uppercase mb-1">Keterangan Tambahan</label>
            <textarea v-model="form.keterangan" rows="2"
              placeholder="Catatan opsional..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none"></textarea>
          </div>

          <div class="flex items-center justify-end gap-3 pt-3 border-t border-border">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 rounded-lg text-sm font-medium text-textMuted hover:text-textMain hover:bg-surface2 transition-colors">
              Batal
            </button>
            <button type="submit" :disabled="submitting"
              class="bg-accent hover:bg-accentHover disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-all">
              {{ submitting ? 'Menyimpan...' : (editMode ? 'Simpan Perubahan' : 'Tambah Instansi') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Konfirmasi Hapus -->
    <div v-if="showDelModal"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <div class="bg-surface border border-border rounded-2xl w-full max-w-md shadow-2xl p-6 animate-[scaleIn_0.2s_ease]">
        <div class="text-center mb-4">
          <div class="text-4xl mb-2">⚠️</div>
          <h3 class="text-lg font-bold text-textMain">Hapus Asal Surat?</h3>
          <p class="text-sm text-textMuted mt-1">
            Yakin ingin menghapus <strong class="text-textMain">"{{ deleteTarget?.nama }}"</strong> dari master asal surat?
          </p>
          <p class="text-xs text-textMuted mt-1">Surat masuk yang sudah terdaftar tidak akan terpengaruh.</p>
        </div>

        <div class="flex items-center justify-center gap-3 pt-2">
          <button @click="showDelModal = false"
            class="px-4 py-2 rounded-lg text-sm font-medium text-textMuted hover:text-textMain hover:bg-surface2 transition-colors">
            Batal
          </button>
          <button @click="handleDelete" :disabled="submitting"
            class="bg-red-500 hover:bg-red-600 disabled:opacity-50 text-white px-5 py-2 rounded-lg text-sm font-semibold transition-all">
            {{ submitting ? 'Menghapus...' : 'Ya, Hapus' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

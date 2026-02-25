<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import api from '@/api/axios'

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

const statusConfig = {
  PROSES:  { label: 'Diproses',  cls: 'bg-brandBlueBg text-brandBlue' },
  TUNGGU:  { label: 'Menunggu',  cls: 'bg-brandYellowBg text-brandYellow' },
  SELESAI: { label: 'Selesai',   cls: 'bg-brandGreenBg text-brandGreen' },
  OVERDUE: { label: 'Overdue',   cls: 'bg-brandRedBg text-brandRed' },
  AKTIF:   { label: 'Aktif',     cls: 'bg-brandBlueBg text-brandBlue' },
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
  <div class="flex-1 overflow-y-auto p-6 animate-[fadeIn_0.4s_ease]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-textMain mb-1">Disposisi</h1>
        <p class="text-sm text-textMuted">Buat, lacak, dan kelola disposisi surat dari Direktur.</p>
      </div>
      <button @click="openModal" class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold transition-all flex items-center gap-2 shadow-sm">
        <span>✍️</span> Tulis Disposisi
      </button>
    </div>

    <!-- Tab Filters -->
    <div class="flex gap-1 mb-4 flex-wrap">
      <button v-for="tab in tabs" :key="tab" @click="activeTab = tab"
        class="px-3.5 py-2 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5"
        :class="activeTab === tab
          ? (tab === 'Overdue' ? 'bg-brandRed text-white shadow-sm' : tab === 'Selesai' ? 'bg-brandGreen text-white shadow-sm' : 'bg-accent text-white shadow-sm')
          : 'bg-surface border border-border text-textMuted hover:text-textMain'">
        {{ tab }}
        <span v-if="!loading && tabCounts[tab] > 0"
          class="text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
          :class="activeTab === tab ? 'bg-white/25 text-white' : (tab === 'Overdue' ? 'bg-brandRedBg text-brandRed' : tab === 'Selesai' ? 'bg-brandGreenBg text-brandGreen' : 'bg-accentGlow text-accent')">
          {{ tabCounts[tab] }}
        </span>
      </button>
    </div>

    <!-- Table -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead class="text-xs text-textMuted uppercase bg-surface3 border-b border-border">
            <tr>
              <th class="px-5 py-3">Nomor</th>
              <th class="px-5 py-3">Perihal</th>
              <th class="px-5 py-3">Asal Surat</th>
              <th class="px-5 py-3">Prioritas</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3">Batas Waktu</th>
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <!-- Loading skeleton -->
            <tr v-if="loading" v-for="i in 5" :key="i" class="border-b border-border">
              <td colspan="7" class="px-5 py-4">
                <div class="h-3 bg-surface3 rounded animate-pulse w-full"></div>
              </td>
            </tr>
            <tr v-else v-for="item in filteredList" :key="item.id"
              class="border-b border-border hover:bg-surface2 transition-all cursor-pointer"
              @click="goDetail(item.id)">
              <td class="px-5 py-3 font-mono text-accent text-xs">{{ item.nomor_disposisi }}</td>
              <td class="px-5 py-3 font-medium max-w-[260px] truncate" :title="item.perihal">{{ item.perihal }}</td>
              <td class="px-5 py-3 text-textMuted text-xs">{{ item.asal_surat || '—' }}</td>
              <td class="px-5 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold"
                  :class="(item.prioritas === 'URGENT' || item.prioritas === 'TINGGI') ? 'bg-brandRedBg text-brandRed' : 'bg-surface3 text-textMuted'">
                  {{ item.prioritas }}
                </span>
              </td>
              <td class="px-5 py-3">
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold"
                  :class="statusConfig[item.status_global]?.cls ?? 'bg-surface3 text-textMuted'">
                  {{ statusConfig[item.status_global]?.label ?? item.status_global }}
                </span>
              </td>
              <td class="px-5 py-3 text-textMuted text-xs">{{ item.batas_waktu || '—' }}</td>
              <td class="px-5 py-3 text-right">
                <button @click.stop="goDetail(item.id)"
                  class="text-xs bg-accentGlow text-accent px-3 py-1.5 rounded-md font-semibold hover:bg-accent hover:text-white transition-all">
                  Detail →
                </button>
              </td>
            </tr>
            <tr v-if="!loading && filteredList.length === 0">
              <td colspan="7" class="px-5 py-10 text-center text-textMuted">Tidak ada data dengan filter ini.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Tulis Disposisi -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-2xl shadow-2xl overflow-hidden animate-[fadeIn_0.2s_ease] flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2 shrink-0">
          <h3 class="text-lg font-bold">✍️ Tulis Disposisi</h3>
          <button @click="showModal = false" class="text-textMuted hover:text-textMain text-xl leading-none">&times;</button>
        </div>
        <form @submit.prevent="handleSubmit" class="p-6 overflow-y-auto flex flex-col gap-5">

          <div v-if="submitError" class="p-3 rounded-lg bg-brandRedBg border border-brandRed/20 text-sm text-brandRed">{{ submitError }}</div>

          <!-- Pilih Surat Masuk -->
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Pilih Surat Masuk *</label>
            <input v-model="suratSearch" type="text" placeholder="🔍 Cari nomor agenda, perihal, atau pengirim..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none mb-1.5" />
            <select v-model="formDisposisi.surat_masuk_id"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" required size="4">
              <option value="" disabled>-- Pilih surat masuk --</option>
              <option v-for="s in filteredSurat" :key="s.id" :value="s.id">
                [{{ s.nomor_agenda }}] {{ s.perihal }} — {{ s.asal_surat }}
              </option>
            </select>
            <p v-if="suratList.length === 0" class="text-xs text-brandYellow mt-1">⚠️ Belum ada surat masuk. Registrasi surat dulu di menu Surat Masuk.</p>
            <p v-else class="text-xs text-textMuted mt-1">{{ filteredSurat.length }} surat masuk tersedia. Ketik untuk menyaring.</p>
          </div>

          <!-- Isi Disposisi -->
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Isi Disposisi *</label>
            <textarea v-model="formDisposisi.isi_disposisi" rows="3"
              placeholder="Tuliskan instruksi disposisi dari Direktur..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" required></textarea>
          </div>

          <!-- Prioritas & Batas Waktu -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Prioritas</label>
              <select v-model="formDisposisi.prioritas" class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none">
                <option value="BIASA">Biasa</option>
                <option value="NORMAL">Normal</option>
                <option value="URGENT">Segera / Urgent</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Batas Waktu</label>
              <input v-model="formDisposisi.batas_waktu" type="date"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" />
            </div>
          </div>

          <!-- Penerima – checkboxes dari daftar user -->
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-2">Penerima Disposisi *</label>
            <div v-if="usersList.length === 0" class="text-xs text-brandYellow py-2">
              ⚠️ Daftar pengguna belum tersedia. Pastikan Anda login sebagai Admin atau hubungi administrator.
            </div>
            <div v-else class="flex flex-col gap-1.5 max-h-40 overflow-y-auto border border-border rounded-lg p-2 bg-surface2">
              <label v-for="u in usersList" :key="u.id"
                class="flex items-center gap-3 px-2 py-1.5 rounded-lg cursor-pointer hover:bg-surface3 transition-all"
                :class="selectedPenerima.includes(u.id) ? 'bg-accentGlow border border-accent/20' : ''">
                <input type="checkbox" :value="u.id" :checked="selectedPenerima.includes(u.id)"
                  @change="togglePenerima(u.id)" class="accent-accent" />
                <div class="flex-1 min-w-0">
                  <div class="text-[13px] font-semibold text-textMain truncate">{{ u.nama_lengkap }}</div>
                  <div class="text-[11px] text-textMuted">{{ u.jabatan || u.role }}</div>
                </div>
              </label>
            </div>
            <p class="text-xs text-textMuted mt-1">{{ selectedPenerima.length }} penerima dipilih</p>
          </div>

          <!-- Catatan Tambahan -->
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Catatan Tambahan</label>
            <textarea v-model="formDisposisi.catatan_direktur" rows="2"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent"
              placeholder="(Opsional)"></textarea>
          </div>

          <div class="flex justify-end gap-3 pt-4 border-t border-border">
            <button type="button" @click="showModal = false"
              class="px-5 py-2.5 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain">
              Batal
            </button>
            <button type="submit" :disabled="submitting"
              class="px-5 py-2.5 text-sm font-semibold rounded-lg bg-accent text-white hover:bg-accentHover shadow-[0_0_15px_var(--accentGlow)] disabled:opacity-60">
              <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-2"></span>
              {{ submitting ? 'Mengirim...' : 'Kirim Disposisi' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

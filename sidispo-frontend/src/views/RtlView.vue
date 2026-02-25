<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'

const router = useRouter()
const auth = useAuthStore()
const { user } = storeToRefs(auth)

// State
const rtlList = ref([])
// Form & Selection State
const disposisiList = ref([]) // Untuk dropdown form
const usersList = ref([]) // Untuk opsi penerima
const selectedPenerima = ref([]) // Array of user_id

const loading = ref(true)
const showModal = ref(false)
const submitting = ref(false)
const submitMsg = ref({ type: '', text: '' })

// Filter tab
const tabs = ['Semua', 'TO_DO', 'ON_PROGRESS', 'REVIEW', 'DONE']
const activeTab = ref('Semua')

// Role Checks
const isAdmin = computed(() => user.value?.role === 'ADMIN')
const isDirectorOrAdmin = computed(() => ['ADMIN', 'DIREKTUR'].includes(user.value?.role))

const form = ref({
  disposisi_id: '',
  prioritas: 'Biasa',
  deskripsi_rtl: '',
  batas_waktu: ''
})

const filteredList = computed(() => {
  if (activeTab.value === 'Semua') return rtlList.value
  return rtlList.value.filter(r => r.status_progress === activeTab.value)
})

// Tab Counts
const tabCounts = computed(() => {
  const counts = { Semua: rtlList.value.length }
  const statusKeys = ['TO_DO', 'ON_PROGRESS', 'REVIEW', 'DONE']
  for (const key of statusKeys) {
    counts[key] = rtlList.value.filter(r => r.status_progress === key).length
  }
  return counts
})

const getStatusLabel = (status) => {
  const map = {
    'TO_DO': 'To Do',
    'ON_PROGRESS': 'On Progress',
    'REVIEW': 'Review',
    'DONE': 'Done'
  }
  return map[status] || status
}

const getPrioritasColor = (prioritas) => {
  const map = {
    'Biasa': 'bg-surface3 text-textMuted border-border',
    'Penting': 'bg-brandBlueBg text-brandBlue border-brandBlue/30',
    'Segera': 'bg-brandYellowBg text-brandYellow border-brandYellow/50',
    'Rahasia': 'bg-brandRedBg text-brandRed border-brandRed/30'
  }
  return map[prioritas] || 'bg-surface3 text-textMuted border-border'
}

const getStatusColor = (status) => {
  const map = {
    'TO_DO': 'bg-surface3 text-textMuted border-border',
    'ON_PROGRESS': 'bg-brandBlueBg text-brandBlue border-brandBlue/30',
    'REVIEW': 'bg-brandYellowBg text-brandYellow border-brandYellow/50',
    'DONE': 'bg-brandGreenBg text-brandGreen border-brandGreen/40'
  }
  return map[status] || 'bg-surface3 text-textMuted border-border'
}

const fetchData = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/rtl')
    rtlList.value = data.data || []
  } catch (e) {
    console.error(e)
  } finally {
    loading.value = false
  }
}

const fetchDisposisiSelesai = async () => {
  if (!isAdmin.value) return
  try {
    const { data } = await api.get('/rtl/disposisi-selesai')
    disposisiList.value = data.data || []
  } catch (e) {
    console.error('Failed to fetch disposisi selesai', e)
  }
}

onMounted(() => {
  fetchData()
  fetchDisposisiSelesai()
})

const togglePenerima = (userId) => {
  const idx = selectedPenerima.value.indexOf(userId)
  if (idx === -1) selectedPenerima.value.push(userId)
  else selectedPenerima.value.splice(idx, 1)
}

const openModal = async () => {
  form.value = { disposisi_id: '', prioritas: 'Biasa', deskripsi_rtl: '', batas_waktu: '' }
  selectedPenerima.value = []
  submitMsg.value = { type: '', text: '' }
  showModal.value = true

  if (usersList.value.length === 0) {
    try {
      const { data } = await api.get('/users')
      usersList.value = data.data || []
    } catch (e) {
      console.error('Failed to fetch users', e)
    }
  }
}

const handleSubmit = async () => {
  if (!form.value.disposisi_id || !form.value.deskripsi_rtl) {
    submitMsg.value = { type: 'error', text: 'Disposisi dan Deskripsi wajib diisi.' }
    return
  }
  if (selectedPenerima.value.length === 0) {
    submitMsg.value = { type: 'error', text: 'Pilih minimal satu penerima.' }
    return
  }
  
  submitting.value = true
  submitMsg.value = { type: '', text: '' }

  try {
    const payload = { ...form.value, penerima: selectedPenerima.value }
    const { data } = await api.post('/rtl', payload)
    submitMsg.value = { type: 'success', text: 'RTL berhasil dibuat!' }
    rtlList.value.unshift(data.data) // Prepend new item
    setTimeout(() => { showModal.value = false }, 1500)
  } catch (err) {
    submitMsg.value = { type: 'error', text: err.response?.data?.message || 'Gagal menyimpan RTL.' }
  } finally {
    submitting.value = false
  }
}

const goDetail = (id) => {
  router.push({ name: 'rtl-detail', params: { id } })
}

function formatDate(d) {
  if (!d || d === '0000-00-00' || d === '1970-01-01') return '—'
  const dateObj = new Date(d)
  if (isNaN(dateObj)) return '—'
  return dateObj.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6 animate-[fadeIn_0.4s_ease]">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-textMain mb-1">Rencana Tindak Lanjut</h1>
        <p class="text-sm text-textMuted">Pantau progres tindak lanjut dari disposisi yang sudah selesai.</p>
      </div>
      <button v-if="isAdmin" @click="openModal"
        class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold transition-all flex items-center gap-2 shadow-sm">
        <span>📋</span> Buat RTL
      </button>
    </div>

    <!-- Tabs Filter -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-2 scrollbar-thin">
      <button v-for="tab in tabs" :key="tab"
        @click="activeTab = tab"
        class="px-4 py-1.5 rounded-full text-sm font-semibold transition-all whitespace-nowrap border"
        :class="activeTab === tab
          ? 'bg-accent text-white border-accent shadow-sm'
          : 'bg-surface hover:bg-surface2 text-textMuted border-border'">
        {{ getStatusLabel(tab) }}
        <span class="ml-1.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold"
          :class="activeTab === tab ? 'bg-white/20 text-white' : 'bg-surface3 text-textDim'">
          {{ tabCounts[tab] }}
        </span>
      </button>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="space-y-3">
      <div v-for="i in 3" :key="i" class="h-24 bg-surface3 rounded-xl animate-pulse"></div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredList.length === 0" class="bg-surface border border-border rounded-xl p-10 text-center flex flex-col items-center justify-center">
      <div class="text-4xl mb-3 opacity-30">📭</div>
      <h3 class="text-lg font-bold text-textMain mb-1">Tidak ada RTL</h3>
      <p class="text-sm text-textMuted">Belum ada Rencana Tindak Lanjut di kategori ini.</p>
    </div>

    <!-- Data List (Cards instead of table for better progress visualization) -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div v-for="rtl in filteredList" :key="rtl.id" 
           class="bg-surface border border-border rounded-xl p-5 hover:border-accent transition-all duration-200 flex flex-col">
        
        <div class="flex justify-between items-start mb-3 gap-2">
          <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-textDim tracking-wider">{{ rtl.nomor_disposisi }}</span>
            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded uppercase border" :class="getPrioritasColor(rtl.prioritas)">
              {{ rtl.prioritas || 'Biasa' }}
            </span>
          </div>
          <div class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border" :class="getStatusColor(rtl.status_progress)">
            {{ getStatusLabel(rtl.status_progress) }}
          </div>
        </div>
        
        <div class="text-sm font-semibold text-textMain line-clamp-2 mb-3 cursor-help" :title="rtl.deskripsi_rtl">
          {{ rtl.deskripsi_rtl }}
        </div>
        
        <div class="text-xs text-textMuted mb-4 flex-1">
          <span class="opacity-60">Ref:</span> {{ rtl.perihal_surat }}
        </div>

        <div class="flex items-baseline justify-between text-[11px] text-textMuted pt-3 border-t border-border">
          <div class="flex flex-col gap-0.5 flex-1 min-w-0 pr-3">
            <span class="opacity-60">Penerima ({{ rtl.penerima_names ? rtl.penerima_names.split(',').length : 0 }})</span>
            <span class="font-medium text-textMain truncate leading-tight" :title="rtl.penerima_names">{{ rtl.penerima_names || '—' }}</span>
          </div>
          <div class="flex flex-col gap-0.5 text-right shrink-0">
            <span class="opacity-60">Batas Waktu</span>
            <span class="font-medium" :class="rtl.batas_waktu && new Date(rtl.batas_waktu) < new Date() && rtl.status_progress !== 'DONE' ? 'text-brandRed' : 'text-textMain'">
              {{ formatDate(rtl.batas_waktu) }}
            </span>
          </div>
        </div>

        <!-- Tombol Detail -->
        <div class="mt-4 pt-3 border-t border-border/50">
          <button @click="goDetail(rtl.id)"
            class="w-full py-1.5 text-xs font-bold rounded-lg border border-border/50 text-textMain hover:bg-surface2 hover:border-accent/40 hover:text-accent transition-all flex items-center justify-center gap-1.5">
            <span>👁️</span> Lihat Detail
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- Modal Buat RTL -->
  <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModal = false"></div>
    <div class="bg-surface w-full max-w-2xl rounded-2xl shadow-2xl relative flex flex-col max-h-[90vh] animate-[fadeIn_0.2s_ease]">
      
      <div class="p-5 px-6 border-b border-border flex justify-between items-center bg-surface2/50 rounded-t-2xl">
        <h3 class="text-lg font-bold text-textMain flex items-center gap-2">
          <span>📋</span> Buat Rencana Tindak Lanjut
        </h3>
        <button @click="showModal = false" class="text-textMuted hover:text-brandRed text-2xl leading-none">&times;</button>
      </div>

      <div class="p-6 overflow-y-auto flex-1 custom-scrollbar">
        <div v-if="submitMsg.text" class="mb-5 p-3 rounded-lg text-sm font-semibold border"
          :class="submitMsg.type === 'error' ? 'bg-brandRedBg text-brandRed border-brandRed/20' : 'bg-brandGreenBg text-brandGreen border-brandGreen/20'">
          {{ submitMsg.text }}
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-5">
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Pilih Disposisi Selesai *</label>
            <select v-model="form.disposisi_id" required class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm focus:border-accent outline-none text-textMain appearance-none">
              <option value="" disabled>-- Pilih Disposisi --</option>
              <option v-for="disp in disposisiList" :key="disp.id" :value="disp.id">
                [{{ disp.nomor_disposisi }}] {{ disp.perihal }}
              </option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Deskripsi RTL *</label>
            <textarea v-model="form.deskripsi_rtl" required rows="3" placeholder="Tuliskan detail Rencana Tindak Lanjut..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm focus:border-accent outline-none text-textMain"></textarea>
          </div>

          <div class="grid grid-cols-[1fr_200px] gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Prioritas</label>
              <select v-model="form.prioritas" class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm focus:border-accent outline-none text-textMain appearance-none">
                <option value="Biasa">Biasa</option>
                <option value="Penting">Penting</option>
                <option value="Segera">Segera</option>
                <option value="Rahasia">Rahasia</option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Batas Waktu</label>
              <input type="date" v-model="form.batas_waktu"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm focus:border-accent outline-none text-textMain appearance-none" />
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Penerima RTL *</label>
            <div class="bg-surface2 border border-border rounded-xl p-3 max-h-48 overflow-y-auto custom-scrollbar flex flex-col gap-2">
              <label v-for="u in usersList" :key="u.id" 
                class="flex items-center gap-3 p-2 rounded-lg cursor-pointer transition-all border border-transparent"
                :class="selectedPenerima.includes(u.id) ? 'bg-brandGreenBg border-brandGreen/40' : 'hover:bg-surface'">
                <input type="checkbox" :value="u.id" @change="togglePenerima(u.id)"
                  :checked="selectedPenerima.includes(u.id)"
                  class="w-4 h-4 rounded text-brandGreen focus:ring-brandGreen/30 accent-brandGreen bg-surface border-border" />
                <div>
                  <div class="text-sm font-bold text-textMain leading-tight">{{ u.nama_lengkap }}</div>
                  <div class="text-[11px] text-textMuted mt-0.5">{{ u.jabatan || 'Staff' }}</div>
                </div>
              </label>
            </div>
            <div class="text-[11px] text-textMuted mt-2">{{ selectedPenerima.length }} penerima dipilih</div>
          </div>
        </form>
      </div>

      <div class="p-5 px-6 border-t border-border flex justify-end gap-3 bg-surface2/50 rounded-b-2xl shrink-0">
        <button type="button" @click="showModal = false" :disabled="submitting"
          class="px-5 py-2 text-sm font-bold text-textMuted bg-surface hover:bg-surface3 border border-border rounded-lg transition-all">
          Batal
        </button>
        <button type="button" @click="handleSubmit" :disabled="submitting"
          class="px-6 py-2 text-sm font-bold text-white bg-brandGreen hover:bg-brandGreen/90 disabled:opacity-50 rounded-lg shadow-sm transition-all flex items-center gap-2">
          <span v-if="submitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          Simpan RTL
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: var(--color-border);
  border-radius: 10px;
}
</style>

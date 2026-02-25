<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)

const authObj = JSON.parse(localStorage.getItem('sidispo_user') || '{}')
const isDirectorOrAdmin = computed(() => ['ADMIN', 'DIREKTUR'].includes(authObj.role))

const id = route.params.id
const rtl = ref(null)
const loading = ref(true)
const error = ref(null)

// Modal Progress State
const showModal = ref(false)
const submitting = ref(false)
const submitMsg = ref({ type: '', text: '' })
const currentPenerimaId = ref(null)
const formProgress = ref({
  status: '',
  catatan: ''
})

const goBack = () => router.push({ name: 'rtl' })

const fetchDetail = async () => {
  loading.value = true
  error.value = null
  try {
    const { data } = await api.get(`/rtl/${id}`)
    rtl.value = data.data
  } catch (e) {
    error.value = e.response?.data?.message || 'Gagal memuat detail RTL'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchDetail()
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

const getStatusColor = (status) => {
  const map = {
    'TO_DO': 'bg-surface3 text-textMuted border-border',
    'ON_PROGRESS': 'bg-brandBlueBg text-brandBlue border-brandBlue/30',
    'REVIEW': 'bg-brandYellowBg text-brandYellow border-brandYellow/50',
    'DONE': 'bg-brandGreenBg text-brandGreen border-brandGreen/40'
  }
  return map[status] || 'bg-surface3 text-textMuted border-border'
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

function formatDate(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric' })
}

function formatDateFull(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  return d.toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' })
}

function initials(nama) {
  return (nama || '').split(' ').slice(0,2).map(w => w[0]?.toUpperCase()).join('')
}

const openProgressModal = (dp) => {
  currentPenerimaId.value = dp.id
  formProgress.value = {
    status: dp.status || 'TO_DO',
    catatan: ''
  }
  submitMsg.value = { type: '', text: '' }
  showModal.value = true
}

const handleUpdateProgress = async () => {
  if (!formProgress.value.catatan.trim()) {
    submitMsg.value = { type: 'error', text: 'Catatan wajib diisi.' }
    return
  }

  submitting.value = true
  submitMsg.value = { type: '', text: '' }

  try {
    await api.put(`/rtl/progress/${currentPenerimaId.value}`, formProgress.value)
    submitMsg.value = { type: 'success', text: 'Progress berhasil diupdate' }
    
    // Refresh data
    await fetchDetail()
    
    setTimeout(() => { showModal.value = false }, 1000)
  } catch (err) {
    submitMsg.value = { type: 'error', text: err.response?.data?.message || 'Terjadi kesalahan.' }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6 bg-surface2/30 animate-[fadeIn_0.4s_ease]">
    <div class="max-w-6xl mx-auto">

      <!-- Header -->
      <div class="mb-5 flex items-center justify-between">
        <button @click="goBack" class="flex items-center gap-2 text-textMuted hover:text-textMain transition-all text-sm font-semibold">
          <span>&larr;</span> Kembali
        </button>
      </div>

      <!-- Loading State -->
      <div v-if="loading" class="space-y-4">
        <div class="h-32 bg-surface rounded-xl border border-border animate-pulse"></div>
        <div class="flex gap-4">
          <div class="flex-1 h-64 bg-surface rounded-xl border border-border animate-pulse"></div>
          <div class="w-80 h-32 bg-surface rounded-xl border border-border animate-pulse"></div>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="bg-brandRedBg text-brandRed p-6 rounded-xl border border-brandRed/20 text-center font-semibold">
        Oops! {{ error }}
      </div>

      <!-- Content -->
      <div v-else-if="rtl" class="space-y-4">
        
        <!-- Top Card: RTL Info -->
        <div class="bg-surface border border-border rounded-xl p-6 shadow-sm">
          <div class="flex justify-between items-start mb-4">
            <div>
              <div class="flex items-center gap-2 mb-2">
                <span class="text-sm font-bold text-accent tracking-wider">{{ rtl.nomor_disposisi }}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border" :class="getPrioritasColor(rtl.prioritas)">
                  {{ rtl.prioritas || 'Biasa' }}
                </span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border" :class="getStatusColor(rtl.status_progress)">
                  {{ getStatusLabel(rtl.status_progress) }}
                </span>
              </div>
              <h2 class="text-xl font-bold text-textMain leading-snug">{{ rtl.perihal_surat }}</h2>
            </div>
            
            <div class="text-right text-sm">
              <div class="text-textMuted text-xs mb-0.5">Dibuat oleh</div>
              <div class="font-bold text-textMain">{{ rtl.pembuat }}</div>
              <div class="text-textDim text-[11px] mt-1">Batas Waktu: {{ rtl.batas_waktu && rtl.batas_waktu !== '0000-00-00' ? formatDateFull(rtl.batas_waktu).split(',')[0] : '—' }}</div>
            </div>
          </div>

          <div class="mt-6 border-l-2 border-brandBlue/50 pl-4 bg-brandBlueBg/20 py-3 rounded-r-lg">
            <div class="text-[11px] font-bold text-textDim uppercase mb-1.5">Deskripsi RTL</div>
            <div class="text-sm text-textMain whitespace-pre-wrap leading-relaxed">{{ rtl.deskripsi_rtl }}</div>
          </div>
        </div>

        <!-- Layout Body -->
        <div class="flex flex-col lg:flex-row gap-4">
          
          <!-- Left: Timeline Progress -->
          <div class="flex-1 bg-surface border border-border rounded-xl p-6 shadow-sm">
            <h3 class="text-[14px] font-bold text-textMain mb-6 flex items-center gap-2">
              <span>📋</span> Timeline Progress
            </h3>

            <div v-if="!rtl.timeline || rtl.timeline.length === 0" class="text-sm text-textMuted italic p-4 text-center border border-dashed border-border rounded-lg bg-surface2/50">
              Belum ada update progress.
            </div>

            <div v-else class="relative pl-5">
              <div class="absolute left-0 top-2 bottom-2 w-[2px] bg-border"></div>
              <div v-for="(log, idx) in rtl.timeline" :key="log.id" class="relative mb-5 last:mb-0">
                <div class="absolute -left-[calc(1.25rem+1px)] w-3.5 h-3.5 rounded-full border-2 border-bg"
                  :class="log.status_baru === 'DONE' ? 'bg-brandGreen' : log.status_baru === 'OVERDUE' ? 'bg-brandRed' : 'bg-accent'"></div>
                <div class="bg-surface2 border border-border rounded-lg p-3.5">
                  <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white shrink-0">
                        {{ initials(log.nama_penerima || log.pembuat) }}
                      </div>
                      <div>
                        <div class="text-[13px] font-semibold">{{ log.nama_penerima || log.pembuat }}</div>
                        <div class="text-[11px] text-textMuted">{{ log.jabatan || 'Penerima Tugas' }}</div>
                      </div>
                    </div>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                      :class="getStatusColor(log.status_baru)">
                      {{ getStatusLabel(log.status_baru) }}
                    </span>
                  </div>
                  <p v-if="log.catatan" class="text-sm text-textMain leading-relaxed mb-2 whitespace-pre-wrap">{{ log.catatan }}</p>
                  <div class="text-[11px] text-textDim flex justify-between">
                    <span>{{ formatDateFull(log.dibuat_at) }}</span>
                    <span v-if="log.pembuat && log.nama_penerima && log.pembuat !== log.nama_penerima" class="italic">Diupdate oleh: {{ log.pembuat }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Action Area -->
          <div class="flex flex-col gap-4 w-full lg:w-[320px] shrink-0">
            <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-sm">
              <div class="p-4 border-b border-border text-[13px] font-bold">👥 Penerima RTL</div>
              <div class="p-4 flex flex-col gap-3">
                <div v-for="dp in rtl.penerima" :key="dp.id"
                  class="flex items-center justify-between p-3 rounded-lg bg-surface2 border border-border">
                  <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white">
                      {{ initials(dp.nama_lengkap) }}
                    </div>
                    <div>
                      <div class="text-[13px] font-semibold">{{ dp.nama_lengkap }}</div>
                      <div class="text-[11px] text-textMuted">{{ dp.jabatan }}</div>
                    </div>
                  </div>
                  <div class="flex flex-col items-end gap-1.5">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                      :class="getStatusColor(dp.status)">
                      {{ getStatusLabel(dp.status) }}
                    </span>
                    <button v-if="isDirectorOrAdmin || dp.user_id == user?.id" @click="openProgressModal(dp)"
                      class="text-xs text-accent hover:underline font-semibold">Update ↗</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- Modal Update Progress -->
  <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="showModal = false"></div>
    <div class="bg-surface w-full max-w-md rounded-2xl shadow-2xl relative flex flex-col animate-[fadeIn_0.2s_ease]">
      
      <div class="p-4 px-5 border-b border-border flex justify-between items-center bg-surface2/50 rounded-t-2xl">
        <h3 class="text-base font-bold text-textMain flex items-center gap-2">
          <span>📝</span> Update Progress RTL
        </h3>
        <button @click="showModal = false" class="text-textMuted hover:text-brandRed text-xl leading-none">&times;</button>
      </div>

      <div class="p-5">
        <div v-if="submitMsg.text" class="mb-4 p-2.5 rounded-lg text-xs font-semibold border"
          :class="submitMsg.type === 'error' ? 'bg-brandRedBg text-brandRed border-brandRed/20' : 'bg-brandGreenBg text-brandGreen border-brandGreen/20'">
          {{ submitMsg.text }}
        </div>

        <form @submit.prevent="handleUpdateProgress" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Status Baru</label>
            <select v-model="formProgress.status" required class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm focus:border-accent outline-none text-textMain appearance-none">
              <option value="TO_DO">To Do</option>
              <option value="ON_PROGRESS">On Progress</option>
              <option value="REVIEW">Review</option>
              <option value="DONE">Done</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Catatan Progress *</label>
            <textarea v-model="formProgress.catatan" required rows="4" placeholder="Deskripsikan perkembangan tindak lanjut Anda..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2 text-sm focus:border-accent outline-none text-textMain"></textarea>
          </div>
        </form>
      </div>

      <div class="p-4 px-5 border-t border-border flex justify-end gap-2 bg-surface2/50 rounded-b-2xl">
        <button type="button" @click="showModal = false" :disabled="submitting"
          class="px-4 py-2 text-sm font-bold text-textMuted bg-surface hover:bg-surface3 border border-border rounded-lg transition-all">
          Batal
        </button>
        <button type="button" @click="handleUpdateProgress" :disabled="submitting"
          class="px-5 py-2 text-sm font-bold text-white bg-brandGreen hover:bg-brandGreen/90 disabled:opacity-50 rounded-lg shadow-sm transition-all flex items-center gap-2">
          <span v-if="submitting" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
          Simpan Progress
        </button>
      </div>
    </div>
  </div>
</template>

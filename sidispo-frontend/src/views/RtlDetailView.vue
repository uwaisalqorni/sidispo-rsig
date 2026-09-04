<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'
import api from '@/api/axios'

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const { user } = storeToRefs(authStore)
const toast = useToast()

const authObj = JSON.parse(localStorage.getItem('sidispo_user') || '{}')
const isDirectorOrAdmin = computed(() => ['ADMIN', 'DIREKTUR'].includes(authObj.role))
const currentUserId = computed(() => {
  const storeUid = user.value?.id
  if (storeUid) return Number(storeUid)
  return Number(authObj?.id || 0)
})

const id = route.params.id
const rtl = ref(null)
const loading = ref(true)
const error = ref(null)

// Modal Progress State (Tambah / Edit)
const showModal = ref(false)
const isEditing = ref(false)
const editingLogId = ref(null)
const submitting = ref(false)
const submitMsg = ref({ type: '', text: '' })
const currentPenerimaId = ref(null)
const formProgress = ref({
  status: 'ON_PROGRESS',
  catatan: ''
})

// Modal Konfirmasi Hapus
const showDeleteModal = ref(false)
const deletingLog = ref(null)
const deleting = ref(false)

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

// Cek apakah pengguna yang sedang login adalah salah satu penerima RTL ini
const myPenerima = computed(() => {
  if (!rtl.value?.penerima || !currentUserId.value) return null
  return rtl.value.penerima.find(p => Number(p.user_id) === currentUserId.value)
})

// Cek apakah catatan progress dibuat oleh pengguna yang sedang login untuk tugasnya sendiri
const isMyLog = (log) => {
  if (!currentUserId.value || !log) return false
  const uid = currentUserId.value

  // Jika log terikat pada penerima tugas, penerima tugas tersebut HARUS user yang sedang login
  if (log.penerima_user_id && Number(log.penerima_user_id) !== uid) {
    return false
  }

  // Pembuat log harus user yang sedang login
  return Number(log.dibuat_oleh) === uid
}

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
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateFull(ts) {
  if (!ts) return ''
  const d = new Date(ts)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function initials(nama) {
  return (nama || '').split(' ').slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}

// Buka modal untuk tambah progress baru
const openProgressModal = (dp) => {
  // Hanya boleh jika penerima ini adalah user yang sedang login
  if (Number(dp.user_id) !== currentUserId.value) return
  isEditing.value = false
  editingLogId.value = null
  currentPenerimaId.value = dp.id
  formProgress.value = {
    status: dp.status === 'TO_DO' ? 'ON_PROGRESS' : (dp.status || 'ON_PROGRESS'),
    catatan: ''
  }
  submitMsg.value = { type: '', text: '' }
  showModal.value = true
}

// Buka modal untuk edit progress yang sudah ada
const openEditProgressModal = (log) => {
  if (!isMyLog(log)) return
  isEditing.value = true
  editingLogId.value = log.id
  formProgress.value = {
    status: log.status_baru || 'ON_PROGRESS',
    catatan: log.catatan || ''
  }
  submitMsg.value = { type: '', text: '' }
  showModal.value = true
}

// Submit progress (Tambah / Edit)
const handleUpdateProgress = async () => {
  if (!formProgress.value.catatan.trim()) {
    submitMsg.value = { type: 'error', text: 'Catatan wajib diisi.' }
    return
  }

  submitting.value = true
  submitMsg.value = { type: '', text: '' }

  try {
    if (isEditing.value && editingLogId.value) {
      // Edit log progress milik sendiri
      await api.put(`/rtl/progress/log/${editingLogId.value}`, formProgress.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Progress RTL berhasil diperbarui', life: 3000 })
    } else {
      // Tambah progress baru
      await api.put(`/rtl/progress/${currentPenerimaId.value}`, formProgress.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Progress RTL berhasil dicatat', life: 3000 })
    }
    
    showModal.value = false
    await fetchDetail()
  } catch (err) {
    submitMsg.value = { type: 'error', text: err.response?.data?.message || 'Terjadi kesalahan.' }
  } finally {
    submitting.value = false
  }
}

// Buka modal konfirmasi hapus log
const openDeleteModal = (log) => {
  if (!isMyLog(log)) return
  deletingLog.value = log
  showDeleteModal.value = true
}

// Eksekusi hapus log progress
const handleDeleteLog = async () => {
  if (!deletingLog.value) return
  deleting.value = true
  try {
    await api.delete(`/rtl/progress/log/${deletingLog.value.id}`)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Progress RTL berhasil dihapus', life: 3000 })
    showDeleteModal.value = false
    deletingLog.value = null
    await fetchDetail()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal menghapus progress RTL', life: 3000 })
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6 bg-surface2/30 animate-[fadeIn_0.4s_ease]">
    <Toast />

    <div class="max-w-6xl mx-auto">

      <!-- Header -->
      <div class="mb-5 flex items-center justify-between">
        <button @click="goBack" class="flex items-center gap-2 text-textMuted hover:text-textMain transition-all text-sm font-semibold">
          <i class="pi pi-arrow-left text-xs"></i> Kembali
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
        <div class="bg-surface border border-border rounded-xl p-6 shadow-xs">
          <div class="flex justify-between items-start mb-4">
            <div>
              <div class="flex items-center gap-2 mb-2 flex-wrap">
                <span class="text-sm font-bold text-accent tracking-wider font-mono bg-accentGlow/20 px-2 py-0.5 rounded border border-accent/30">{{ rtl.nomor_disposisi }}</span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border" :class="getPrioritasColor(rtl.prioritas)">
                  {{ rtl.prioritas || 'Biasa' }}
                </span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase border" :class="getStatusColor(rtl.status_progress)">
                  {{ getStatusLabel(rtl.status_progress) }}
                </span>
              </div>
              <h2 class="text-xl font-bold text-textMain leading-snug">{{ rtl.perihal_surat }}</h2>
            </div>
            
            <div class="text-right text-sm shrink-0">
              <div class="text-textMuted text-xs mb-0.5">Dibuat oleh</div>
              <div class="font-bold text-textMain">{{ rtl.pembuat }}</div>
              <div class="text-textDim text-[11px] mt-1 font-mono">Batas Waktu: {{ rtl.batas_waktu && rtl.batas_waktu !== '0000-00-00' ? formatDateFull(rtl.batas_waktu).split(',')[0] : '—' }}</div>
            </div>
          </div>

          <div class="mt-4 border-l-4 border-brandBlue pl-4 bg-brandBlueBg/20 py-3 rounded-r-lg">
            <div class="text-[11px] font-bold text-textDim uppercase mb-1">Deskripsi RTL</div>
            <div class="text-sm text-textMain whitespace-pre-wrap leading-relaxed">{{ rtl.deskripsi_rtl }}</div>
          </div>
        </div>

        <!-- Layout Body -->
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5">
          
          <!-- Left: Timeline Progress -->
          <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
            <div class="p-4 border-b border-border flex items-center justify-between bg-surface2/60">
              <div class="text-[13px] font-bold text-textMain flex items-center gap-2">
                <span>📋 Timeline Progress RTL</span>
                <span class="text-xs text-textMuted font-semibold bg-surface3 px-2 py-0.5 rounded-full">{{ rtl.timeline?.length || 0 }}</span>
              </div>

              <!-- Tombol Cepat Tambah Progress (jika user merupakan penerima RTL ini) -->
              <button
                v-if="myPenerima"
                @click="openProgressModal(myPenerima)"
                class="text-xs px-3 py-1.5 bg-accent text-white font-semibold rounded-lg hover:bg-accentHover transition-colors flex items-center gap-1.5 shadow-xs"
              >
                <i class="pi pi-plus text-[10px]"></i>
                Update Progress
              </button>
            </div>

            <div class="p-5 flex-1">
              <div v-if="!rtl.timeline || rtl.timeline.length === 0" class="text-sm text-textMuted py-8 text-center flex flex-col items-center justify-center gap-2">
                <i class="pi pi-inbox text-3xl text-textDim"></i>
                <span>Belum ada catatan progress untuk RTL ini.</span>
                <button
                  v-if="myPenerima"
                  @click="openProgressModal(myPenerima)"
                  class="mt-2 text-xs text-accent font-semibold hover:underline"
                >
                  Mulai catat progress pertama Anda →
                </button>
              </div>

              <div v-else class="relative pl-5">
                <div class="absolute left-0 top-2 bottom-2 w-[2px] bg-border"></div>
                <div v-for="log in rtl.timeline" :key="log.id" class="relative mb-5 last:mb-0">
                  <div class="absolute -left-[calc(1.25rem+1px)] w-3.5 h-3.5 rounded-full border-2 border-bg"
                    :class="log.status_baru === 'DONE' ? 'bg-brandGreen' : log.status_baru === 'REVIEW' ? 'bg-brandYellow' : 'bg-brandBlue'"></div>
                  
                  <div class="bg-surface2 border border-border rounded-lg p-3.5 transition-colors hover:border-accent/40 shadow-xs">
                    <div class="flex items-start justify-between gap-2 mb-2">
                      <div class="flex items-center gap-2 min-w-0">
                        <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white shrink-0">
                          {{ initials(log.nama_penerima || log.pembuat) }}
                        </div>
                        <div class="min-w-0">
                          <div class="text-[13px] font-semibold truncate flex items-center gap-1.5">
                            <span>{{ log.nama_penerima || log.pembuat }}</span>
                            <span v-if="isMyLog(log)" class="text-[10px] bg-accent/15 text-accent font-bold px-1.5 py-0.2 rounded">
                              Anda
                            </span>
                          </div>
                          <div class="text-[11px] text-textMuted truncate">{{ log.jabatan || 'Penerima Tugas' }}</div>
                        </div>
                      </div>

                      <div class="flex items-center gap-2 shrink-0">
                        <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border"
                          :class="getStatusColor(log.status_baru)">
                          {{ getStatusLabel(log.status_baru) }}
                        </span>

                        <!-- Action Buttons: HANYA untuk progress yang dibuat oleh user sendiri -->
                        <div v-if="isMyLog(log)" class="flex items-center gap-1 border-l border-border pl-2 ml-1">
                          <button
                            type="button"
                            @click="openEditProgressModal(log)"
                            class="p-1.5 rounded-md text-textMuted hover:text-brandBlue hover:bg-brandBlueBg transition-colors"
                            title="Ubah progress Anda"
                          >
                            <i class="pi pi-pencil text-xs"></i>
                          </button>
                          <button
                            type="button"
                            @click="openDeleteModal(log)"
                            class="p-1.5 rounded-md text-textMuted hover:text-brandRed hover:bg-brandRedBg transition-colors"
                            title="Hapus progress Anda"
                          >
                            <i class="pi pi-trash text-xs"></i>
                          </button>
                        </div>
                      </div>
                    </div>

                    <p v-if="log.catatan" class="text-sm text-textMain/90 leading-relaxed mb-2 whitespace-pre-wrap bg-white/70 p-2.5 rounded-md border border-border/50">
                      {{ log.catatan }}
                    </p>

                    <div class="flex items-center justify-between text-[11px] text-textDim pt-1 border-t border-border/30">
                      <span>{{ formatDateFull(log.dibuat_at) }}</span>
                      <span v-if="log.pembuat && log.nama_penerima && log.pembuat !== log.nama_penerima" class="italic">
                        Diinput oleh: {{ log.pembuat }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Right: Penerima RTL -->
          <div class="flex flex-col gap-4 w-full shrink-0">
            <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
              <div class="p-4 border-b border-border text-[13px] font-bold flex items-center justify-between bg-surface2/60">
                <span class="text-textMain">👥 Penerima RTL</span>
                <span class="text-xs text-textMuted font-semibold">({{ rtl.penerima?.length || 0 }})</span>
              </div>
              <div class="p-4 flex flex-col gap-3">
                <div
                  v-for="dp in rtl.penerima"
                  :key="dp.id"
                  class="flex items-center justify-between p-3 rounded-lg border transition-all"
                  :class="Number(dp.user_id) === currentUserId ? 'bg-accentGlow/20 border-accent/40 shadow-xs' : 'bg-surface2 border-border'"
                >
                  <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white shrink-0">
                      {{ initials(dp.nama_lengkap) }}
                    </div>
                    <div class="min-w-0">
                      <div class="text-[13px] font-semibold truncate flex items-center gap-1.5">
                        <span>{{ dp.nama_lengkap }}</span>
                        <span v-if="Number(dp.user_id) === currentUserId" class="text-[10px] bg-accent text-white font-bold px-1.5 py-0.2 rounded-full">
                          Anda
                        </span>
                      </div>
                      <div class="text-[11px] text-textMuted truncate">{{ dp.jabatan }}</div>
                    </div>
                  </div>
                  <div class="flex flex-col items-end gap-1.5 shrink-0">
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border"
                      :class="getStatusColor(dp.status)">
                      {{ getStatusLabel(dp.status) }}
                    </span>

                    <!-- Tombol Update Progress hanya muncul jika penerima ini adalah pengguna yang sedang login -->
                    <button
                      v-if="Number(dp.user_id) === currentUserId"
                      @click="openProgressModal(dp)"
                      class="text-xs text-accent font-semibold hover:underline flex items-center gap-1"
                    >
                      <i class="pi pi-plus text-[10px]"></i> Update Progress
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>

      </div>
    </div>

    <!-- Modal Update / Edit Progress RTL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
      <div class="bg-surface w-full max-w-md rounded-xl shadow-2xl relative flex flex-col animate-[fadeIn_0.2s_ease] overflow-hidden border border-border">
        
        <div class="p-4 px-5 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="text-base font-bold text-textMain flex items-center gap-2">
            <span>{{ isEditing ? '✏️ Edit Progress RTL' : '📝 Update Progress RTL' }}</span>
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
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Status Pekerjaan</label>
              <select v-model="formProgress.status" required class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm focus:border-accent outline-none text-textMain font-medium">
                <option value="TO_DO">To Do</option>
                <option value="ON_PROGRESS">On Progress</option>
                <option value="REVIEW">Review</option>
                <option value="DONE">Done</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Catatan Progress *</label>
              <textarea
                v-model="formProgress.catatan"
                required
                rows="4"
                placeholder="Deskripsikan perkembangan tindak lanjut Anda..."
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm focus:border-accent outline-none text-textMain"
              ></textarea>
            </div>

            <div class="pt-3 border-t border-border flex justify-end gap-2.5">
              <button
                type="button"
                @click="showModal = false"
                :disabled="submitting"
                class="px-4 py-2 text-sm font-semibold text-textMuted bg-surface hover:bg-surface3 border border-border rounded-lg transition-all"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="submitting"
                class="px-5 py-2 text-sm font-semibold text-white bg-accent hover:bg-accentHover disabled:opacity-50 rounded-lg shadow-xs transition-all flex items-center gap-1.5"
              >
                <span v-if="submitting" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                {{ submitting ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Simpan Progress') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Modal Konfirmasi Hapus Progress RTL -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-sm shadow-2xl animate-[fadeIn_0.2s_ease] overflow-hidden">
        <div class="p-5 flex flex-col gap-3">
          <div class="w-12 h-12 rounded-full bg-brandRedBg text-brandRed flex items-center justify-center mx-auto text-xl">
            <i class="pi pi-exclamation-triangle"></i>
          </div>
          <div class="text-center">
            <h3 class="text-base font-bold text-textMain">Hapus Catatan Progress RTL?</h3>
            <p class="text-xs text-textMuted mt-1 leading-relaxed">
              Catatan progress ini akan dihapus. Status RTL penerima Anda akan otomatis disesuaikan dengan tahapan progress sebelumnya.
            </p>
          </div>
          <div v-if="deletingLog?.catatan" class="bg-surface2 p-2.5 rounded-lg border border-border/60 text-xs text-textMain italic truncate">
            "{{ deletingLog.catatan }}"
          </div>
          <div class="flex justify-end gap-2 pt-3 border-t border-border mt-2">
            <button
              type="button"
              @click="showDeleteModal = false"
              :disabled="deleting"
              class="px-4 py-2 text-xs font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2"
            >
              Batal
            </button>
            <button
              type="button"
              @click="handleDeleteLog"
              :disabled="deleting"
              class="px-4 py-2 text-xs font-semibold rounded-lg bg-brandRed text-white hover:bg-red-700 disabled:opacity-60 flex items-center gap-1.5 shadow-xs"
            >
              <span v-if="deleting" class="inline-block w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              {{ deleting ? 'Menghapus...' : 'Ya, Hapus' }}
            </button>
          </div>
        </div>
      </div>
    </div>

  </div>
</template>

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

const disposisi = ref(null)
const timeline  = ref([])
const loading   = ref(true)

// Progress Modal State (Add & Edit)
const showProgressModal = ref(false)
const isEditing         = ref(false)
const editingLogId      = ref(null)
const submitting        = ref(false)
const submitError       = ref('')
const progressForm      = ref({ penerima_id: '', status: 'PROSES', catatan: '' })

// Delete Confirmation Modal State
const showDeleteModal   = ref(false)
const deletingLog       = ref(null)
const deleting          = ref(false)

const statusConfig = {
  DITERIMA:{ label: 'Diterima',  cls: 'bg-surface3 text-textMuted' },
  PROSES:  { label: 'Diproses',  cls: 'bg-brandBlueBg text-brandBlue' },
  TUNGGU:  { label: 'Menunggu',  cls: 'bg-brandYellowBg text-brandYellow' },
  SELESAI: { label: 'Selesai',   cls: 'bg-brandGreenBg text-brandGreen' },
  OVERDUE: { label: 'Overdue',   cls: 'bg-brandRedBg text-brandRed' },
}

const loadData = async () => {
  const id = route.params.id
  const [detailRes, timelineRes] = await Promise.all([
    api.get(`/disposisi/${id}`),
    api.get(`/progress/disposisi/${id}`)
  ])
  disposisi.value = detailRes.data.data
  timeline.value  = timelineRes.data.data || []
}

onMounted(async () => {
  try {
    await loadData()
  } catch (e) {
    // silent
  } finally {
    loading.value = false
  }
})

// Cek apakah user yang sedang login adalah salah satu penerima di disposisi ini
const myPenerima = computed(() => {
  if (!disposisi.value?.penerima || !user.value) return null
  return disposisi.value.penerima.find(p => Number(p.user_id) === Number(user.value.id))
})

// Cek apakah log progress ini dibuat oleh user yang sedang login
const isMyLog = (log) => {
  if (!user.value || !log) return false
  return Number(log.user_id) === Number(user.value.id)
}

// Buka modal untuk tambah progress baru
const openAddProgressModal = (dp) => {
  isEditing.value = false
  editingLogId.value = null
  submitError.value = ''
  progressForm.value = {
    penerima_id: dp.id,
    status: dp.status === 'DITERIMA' ? 'PROSES' : dp.status,
    catatan: ''
  }
  showProgressModal.value = true
}

// Buka modal untuk edit progress yang sudah ada
const openEditProgressModal = (log) => {
  if (!isMyLog(log)) return
  isEditing.value = true
  editingLogId.value = log.id
  submitError.value = ''
  progressForm.value = {
    penerima_id: log.disposisi_penerima_id,
    status: log.status_baru || 'PROSES',
    catatan: log.catatan || ''
  }
  showProgressModal.value = true
}

// Submit progress (Tambah / Edit)
const handleProgressSubmit = async () => {
  if (!progressForm.value.catatan.trim()) {
    submitError.value = 'Catatan progress wajib diisi.'
    return
  }
  submitting.value = true
  submitError.value = ''
  try {
    if (isEditing.value && editingLogId.value) {
      // Update existing log
      await api.put(`/progress/log/${editingLogId.value}`, {
        status: progressForm.value.status,
        catatan: progressForm.value.catatan.trim()
      })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Catatan progress berhasil diperbarui', life: 3000 })
    } else {
      // Create new log
      await api.post(`/progress/${progressForm.value.penerima_id}`, {
        status: progressForm.value.status,
        catatan: progressForm.value.catatan.trim()
      })
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Progress berhasil dicatat', life: 3000 })
    }
    showProgressModal.value = false
    await loadData()
  } catch (err) {
    submitError.value = err.response?.data?.message || 'Gagal menyimpan progress.'
  } finally {
    submitting.value = false
  }
}

// Buka modal konfirmasi hapus
const openDeleteModal = (log) => {
  if (!isMyLog(log)) return
  deletingLog.value = log
  showDeleteModal.value = true
}

// Eksekusi hapus progress
const handleDeleteLog = async () => {
  if (!deletingLog.value) return
  deleting.value = true
  try {
    await api.delete(`/progress/log/${deletingLog.value.id}`)
    toast.add({ severity: 'success', summary: 'Sukses', detail: 'Catatan progress berhasil dihapus', life: 3000 })
    showDeleteModal.value = false
    deletingLog.value = null
    await loadData()
  } catch (err) {
    toast.add({ severity: 'error', summary: 'Gagal', detail: err.response?.data?.message || 'Gagal menghapus progress', life: 3000 })
  } finally {
    deleting.value = false
  }
}

function initials(nama) {
  return (nama || '').split(' ').slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}

function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

const prioritasColors = {
  URGENT: 'text-brandRed bg-brandRedBg',
  TINGGI: 'text-brandYellow bg-brandYellowBg',
  NORMAL: 'text-brandYellow bg-brandYellowBg',
  BIASA:  'text-textMuted bg-surface3'
}

function fileUrl(path) {
  const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/SiDispo/api/v1')
    .replace('/api/v1', '')
  return base + '/' + path
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6 animate-[fadeIn_0.4s_ease]">
    <Toast />

    <!-- Back button -->
    <button @click="router.back()" class="flex items-center gap-2 text-sm text-textMuted hover:text-textMain mb-5 transition-colors font-medium">
      <i class="pi pi-arrow-left text-xs"></i> Kembali
    </button>

    <!-- Loading state -->
    <div v-if="loading" class="space-y-4">
      <div class="h-8 bg-surface rounded animate-pulse w-48"></div>
      <div class="h-4 bg-surface rounded animate-pulse w-3/4"></div>
      <div class="h-4 bg-surface rounded animate-pulse w-1/2"></div>
    </div>

    <template v-else-if="disposisi">
      <!-- Header -->
      <div class="bg-surface border border-border rounded-xl p-6 mb-5 shadow-xs">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
          <div>
            <div class="flex items-center gap-2.5 mb-2 flex-wrap">
              <span class="font-mono text-accent text-sm font-bold bg-accentGlow/20 px-2 py-0.5 rounded-md border border-accent/30">{{ disposisi.nomor_disposisi }}</span>
              <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                :class="prioritasColors[disposisi.prioritas] || 'text-textMuted bg-surface3'">
                {{ disposisi.prioritas }}
              </span>
              <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full"
                :class="statusConfig[disposisi.status_global]?.cls ?? 'bg-surface3 text-textMuted'">
                {{ statusConfig[disposisi.status_global]?.label ?? disposisi.status_global }}
              </span>
              <span v-if="Number(disposisi.is_berjenjang) === 1" class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-600 border border-amber-500/20 flex items-center gap-1">
                <i class="pi pi-sitemap text-[10px]"></i> Mode Berjenjang
              </span>
            </div>
            <h2 class="text-xl font-bold text-textMain leading-snug">{{ disposisi.perihal }}</h2>
            <p class="text-sm text-textMuted mt-1 flex items-center gap-2 flex-wrap">
              <span>Dari: <strong>{{ disposisi.asal_surat }}</strong></span>
              <span>•</span>
              <span>No. Surat: <strong>{{ disposisi.nomor_surat }}</strong></span>
            </p>
          </div>
          <div class="flex flex-col items-end gap-1 text-right">
            <span class="text-xs text-textMuted">Dibuat oleh</span>
            <span class="text-sm font-semibold text-textMain">{{ disposisi.pembuat }}</span>
            <span class="text-xs text-textMuted">Batas waktu: <strong class="text-brandYellow font-mono">{{ disposisi.batas_waktu || '—' }}</strong></span>
          </div>
        </div>

        <div v-if="Number(disposisi.is_berjenjang) === 1" class="p-3 mb-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-700 dark:text-amber-300 flex items-center gap-2.5">
          <i class="pi pi-info-circle text-base text-amber-600 shrink-0"></i>
          <div>
            <strong>Alur Validasi Berjenjang Aktif:</strong> Progress divalidasi secara berurutan sesuai level hierarki jabatan. Penerima di level atas baru dapat mencatat progress setelah level di bawahnya selesai.
          </div>
        </div>

        <div class="bg-surface2 rounded-lg p-4 border-l-4 border-accent mb-4">
          <div class="text-[11px] font-bold text-textMuted uppercase tracking-wider mb-1">Isi Disposisi</div>
          <p class="text-sm text-textMain leading-relaxed whitespace-pre-line">{{ disposisi.isi_disposisi }}</p>
        </div>

        <div v-if="disposisi.catatan_direktur" class="bg-surface2 rounded-lg p-4 border-l-4 border-brandYellow">
          <div class="text-[11px] font-bold text-textMuted uppercase tracking-wider mb-1">Catatan Tambahan</div>
          <p class="text-sm text-textMain leading-relaxed whitespace-pre-line">{{ disposisi.catatan_direktur }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_360px] gap-5">
        <!-- Timeline -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs flex flex-col">
          <div class="p-4 border-b border-border flex items-center justify-between bg-surface2/60">
            <div class="text-[13px] font-bold flex items-center gap-2 text-textMain">
              <span>📋 Timeline Progress</span>
              <span class="text-xs text-textMuted font-semibold bg-surface3 px-2 py-0.5 rounded-full">{{ timeline.length }}</span>
            </div>
            <!-- Tombol Tambah Progress (jika user merupakan penerima) -->
            <div v-if="myPenerima">
              <button
                v-if="!myPenerima.is_locked"
                @click="openAddProgressModal(myPenerima)"
                class="text-xs px-3 py-1.5 bg-accent text-white font-semibold rounded-lg hover:bg-accentHover transition-colors flex items-center gap-1.5 shadow-xs"
              >
                <i class="pi pi-plus text-[10px]"></i>
                Update Progress
              </button>
              <div
                v-else
                class="text-xs px-2.5 py-1 bg-surface3 text-textMuted border border-border/80 font-semibold rounded-lg flex items-center gap-1.5 cursor-not-allowed opacity-80"
                title="Level Anda belum dapat diisi. Menunggu validasi level sebelumnya."
              >
                <i class="pi pi-lock text-[10px] text-amber-500"></i>
                <span>Terkunci (Tunggu Level Bawah)</span>
              </div>
            </div>
          </div>
          <div class="p-5 flex-1">
            <div v-if="timeline.length === 0" class="text-sm text-textMuted py-8 text-center flex flex-col items-center justify-center gap-2">
              <i class="pi pi-inbox text-3xl text-textDim"></i>
              <span>Belum ada catatan progress untuk disposisi ini.</span>
              <button
                v-if="myPenerima"
                @click="openAddProgressModal(myPenerima)"
                class="mt-2 text-xs text-accent font-semibold hover:underline"
              >
                Mulai update progress pertama Anda →
              </button>
            </div>
            <div v-else class="relative pl-5">
              <div class="absolute left-0 top-2 bottom-2 w-[2px] bg-border"></div>
              <div v-for="log in timeline" :key="log.id" class="relative mb-5 last:mb-0">
                <div class="absolute -left-[calc(1.25rem+1px)] w-3.5 h-3.5 rounded-full border-2 border-bg"
                  :class="log.status_baru === 'SELESAI' ? 'bg-brandGreen' : log.status_baru === 'OVERDUE' ? 'bg-brandRed' : log.status_baru === 'TUNGGU' ? 'bg-brandYellow' : 'bg-brandBlue'"></div>
                
                <div class="bg-surface2 border border-border rounded-lg p-3.5 transition-colors hover:border-accent/40 shadow-xs">
                  <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2 min-w-0">
                      <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white shrink-0">
                        {{ initials(log.nama_lengkap) }}
                      </div>
                      <div class="min-w-0">
                        <div class="text-[13px] font-semibold truncate flex items-center gap-1.5">
                          <span>{{ log.nama_lengkap }}</span>
                          <span v-if="isMyLog(log)" class="text-[10px] bg-accent/15 text-accent font-bold px-1.5 py-0.2 rounded">
                            Anda
                          </span>
                        </div>
                        <div class="text-[11px] text-textMuted truncate">{{ log.jabatan }}</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <span class="text-[11px] font-semibold px-2.5 py-0.5 rounded-full"
                        :class="statusConfig[log.status_baru]?.cls ?? 'bg-surface3 text-textMuted'">
                        {{ statusConfig[log.status_baru]?.label ?? log.status_baru }}
                      </span>

                      <!-- Action buttons: HANYA untuk progress milik user yang login -->
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

                  <p v-if="log.catatan" class="text-sm text-textMain/90 leading-relaxed mb-2 whitespace-pre-line bg-white/70 p-2.5 rounded-md border border-border/50">
                    {{ log.catatan }}
                  </p>
                  
                  <div class="flex items-center justify-between text-[11px] text-textDim pt-1 border-t border-border/30">
                    <span>{{ formatDate(log.created_at) }}</span>
                    <span v-if="log.status_lama" class="italic">
                      Status: {{ statusConfig[log.status_lama]?.label || log.status_lama }} → {{ statusConfig[log.status_baru]?.label || log.status_baru }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Penerima & Lampiran -->
        <div class="flex flex-col gap-4">
          <!-- Penerima -->
          <div class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="p-4 border-b border-border text-[13px] font-bold flex items-center justify-between bg-surface2/60">
              <span class="text-textMain">👥 Penerima Disposisi</span>
              <span class="text-xs text-textMuted font-semibold">({{ disposisi.penerima?.length || 0 }})</span>
            </div>
            <div class="p-4 flex flex-col gap-3">
              <div
                v-for="dp in disposisi.penerima"
                :key="dp.id"
                class="flex items-center justify-between p-3 rounded-lg border transition-all"
                :class="Number(dp.user_id) === Number(user?.id) ? 'bg-accentGlow/20 border-accent/40 shadow-xs' : 'bg-surface2 border-border'"
              >
                <div class="flex items-center gap-3 min-w-0">
                  <div class="w-9 h-9 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white shrink-0">
                    {{ initials(dp.nama_lengkap) }}
                  </div>
                  <div class="min-w-0">
                    <div class="text-[13px] font-semibold truncate flex items-center gap-1.5">
                      <span>{{ dp.nama_lengkap }}</span>
                      <span v-if="Number(dp.user_id) === Number(user?.id)" class="text-[10px] bg-accent text-white font-bold px-1.5 py-0.2 rounded-full">
                        Anda
                      </span>
                    </div>
                    <div class="text-[11px] text-textMuted truncate flex items-center gap-1.5 mt-0.5">
                      <span>{{ dp.jabatan_master || dp.jabatan }}</span>
                      <span v-if="dp.urutan_level" class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-600 border border-amber-500/20">
                        Lv.{{ dp.urutan_level }}
                      </span>
                    </div>
                  </div>
                </div>

                <div class="flex flex-col items-end gap-1.5 shrink-0">
                  <div class="flex items-center gap-1.5">
                    <span v-if="dp.is_locked" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-surface3 text-amber-600 border border-amber-500/30 flex items-center gap-1" title="Menunggu validasi level sebelumnya">
                      <i class="pi pi-lock text-[9px]"></i> Terkunci
                    </span>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                      :class="statusConfig[dp.status]?.cls ?? 'bg-surface3 text-textMuted'">
                      {{ statusConfig[dp.status]?.label ?? dp.status }}
                    </span>
                  </div>

                  <!-- Tombol Update Progress hanya muncul jika penerima ini adalah pengguna yang sedang login -->
                  <template v-if="Number(dp.user_id) === Number(user?.id)">
                    <button
                      v-if="!dp.is_locked"
                      @click="openAddProgressModal(dp)"
                      class="text-xs text-accent font-semibold hover:underline flex items-center gap-1"
                    >
                      <i class="pi pi-plus text-[10px]"></i> Update Progress
                    </button>
                    <span v-else class="text-[10px] text-textMuted italic flex items-center gap-1">
                      <i class="pi pi-lock text-[9px]"></i> Tunggu level bawah
                    </span>
                  </template>
                </div>
              </div>
            </div>
          </div>

          <!-- Lampiran -->
          <div v-if="disposisi.files?.length" class="bg-surface border border-border rounded-xl overflow-hidden shadow-xs">
            <div class="p-4 border-b border-border text-[13px] font-bold bg-surface2/60 text-textMain">
              📎 Lampiran Dokumen ({{ disposisi.files.length }})
            </div>
            <div class="p-4 flex flex-col gap-2">
              <a v-for="f in disposisi.files" :key="f.id"
                :href="fileUrl(f.path_file)" target="_blank"
                class="flex items-center gap-3 p-2.5 rounded-lg border border-border hover:border-accent hover:bg-surface2 transition-all group">
                <span class="text-xl">📄</span>
                <div class="flex-1 min-w-0">
                  <div class="text-[13px] font-medium truncate text-textMain group-hover:text-accent">{{ f.nama_asli }}</div>
                  <div class="text-[11px] text-textMuted">{{ Math.round(f.ukuran_bytes / 1024) }} KB</div>
                </div>
                <i class="pi pi-external-link text-xs text-textDim group-hover:text-accent"></i>
              </a>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="text-center py-20 text-textMuted">Disposisi tidak ditemukan.</div>

    <!-- Progress Modal (Tambah / Edit) -->
    <div v-if="showProgressModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-md shadow-2xl animate-[fadeIn_0.2s_ease] overflow-hidden">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="text-base font-bold text-textMain flex items-center gap-2">
            <span>{{ isEditing ? '✏️ Edit Progress' : '📝 Update Progress' }}</span>
          </h3>
          <button @click="showProgressModal = false" class="text-textMuted hover:text-textMain text-xl leading-none">&times;</button>
        </div>
        <form @submit.prevent="handleProgressSubmit" class="p-5 flex flex-col gap-4">
          <div v-if="submitError" class="p-3 rounded-lg bg-brandRedBg border border-brandRed/20 text-sm text-brandRed font-medium">
            {{ submitError }}
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Status Pekerjaan</label>
            <select v-model="progressForm.status" class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-black focus:border-accent font-medium">
              <option value="PROSES">Sedang Diproses</option>
              <option value="TUNGGU">Menunggu</option>
              <option value="SELESAI">Selesai</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Catatan Progress *</label>
            <textarea
              v-model="progressForm.catatan"
              rows="4"
              placeholder="Deskripsikan perkembangan tindak lanjut Anda secara detail..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent"
              required
            ></textarea>
          </div>

          <div class="flex justify-end gap-2.5 pt-3 border-t border-border">
            <button
              type="button"
              @click="showProgressModal = false"
              :disabled="submitting"
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain"
            >
              Batal
            </button>
            <button
              type="submit"
              :disabled="submitting"
              class="px-4 py-2 text-sm font-semibold rounded-lg bg-accent text-white hover:bg-accentHover disabled:opacity-60 flex items-center gap-1.5 shadow-xs"
            >
              <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-1"></span>
              {{ submitting ? 'Menyimpan...' : (isEditing ? 'Simpan Perubahan' : 'Simpan Progress') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-sm shadow-2xl animate-[fadeIn_0.2s_ease] overflow-hidden">
        <div class="p-5 flex flex-col gap-3">
          <div class="w-12 h-12 rounded-full bg-brandRedBg text-brandRed flex items-center justify-center mx-auto text-xl">
            <i class="pi pi-exclamation-triangle"></i>
          </div>
          <div class="text-center">
            <h3 class="text-base font-bold text-textMain">Hapus Catatan Progress?</h3>
            <p class="text-xs text-textMuted mt-1 leading-relaxed">
              Catatan progress ini akan dihapus. Status penerima Anda akan otomatis disesuaikan dengan tahapan progress sebelumnya.
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

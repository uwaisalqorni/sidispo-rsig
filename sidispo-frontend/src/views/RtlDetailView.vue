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

// Cek apakah batas waktu sudah lewat
const isOverdue = computed(() => {
  if (!rtl.value?.batas_waktu || rtl.value.batas_waktu === '0000-00-00') return false
  if (rtl.value.status_progress === 'DONE') return false
  const deadline = new Date(rtl.value.batas_waktu)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  return deadline < today
})

// Hitung penerima yang sudah selesai
const completedPenerimaCount = computed(() => {
  if (!rtl.value?.penerima) return 0
  return rtl.value.penerima.filter(p => p.status === 'DONE').length
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
    'TO_DO': 'bg-slate-100 text-slate-700 border-slate-200',
    'ON_PROGRESS': 'bg-blue-50 text-blue-700 border-blue-200',
    'REVIEW': 'bg-amber-50 text-amber-700 border-amber-200',
    'DONE': 'bg-emerald-50 text-emerald-700 border-emerald-200'
  }
  return map[status] || 'bg-slate-100 text-slate-700 border-slate-200'
}

const getPrioritasColor = (prioritas) => {
  const map = {
    'Biasa': 'bg-slate-100 text-slate-700 border-slate-200',
    'Penting': 'bg-blue-50 text-blue-700 border-blue-200',
    'Segera': 'bg-amber-50 text-amber-700 border-amber-200',
    'Rahasia': 'bg-rose-50 text-rose-700 border-rose-200'
  }
  return map[prioritas] || 'bg-slate-100 text-slate-700 border-slate-200'
}

function formatDate(ts) {
  if (!ts || ts === '0000-00-00') return '—'
  const d = new Date(ts)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateFull(ts) {
  if (!ts) return '—'
  const d = new Date(ts)
  return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}

function initials(nama) {
  return (nama || '').split(' ').slice(0, 2).map(w => w[0]?.toUpperCase()).join('')
}

// Buka modal untuk tambah progress baru
const openProgressModal = (dp) => {
  if (Number(dp.user_id) !== currentUserId.value) return
  if (dp.is_locked) {
    toast.add({ severity: 'warn', summary: 'Tugas Terkunci', detail: 'Level Anda belum dapat diisi. Menunggu penyelesaian dari level sebelumnya.', life: 4000 })
    return
  }
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
    submitMsg.value = { type: 'error', text: 'Catatan progress wajib diisi.' }
    return
  }

  submitting.value = true
  submitMsg.value = { type: '', text: '' }

  try {
    if (isEditing.value && editingLogId.value) {
      await api.put(`/rtl/progress/log/${editingLogId.value}`, formProgress.value)
      toast.add({ severity: 'success', summary: 'Sukses', detail: 'Progress RTL berhasil diperbarui', life: 3000 })
    } else {
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
  <div class="flex-1 overflow-y-auto p-4 md:p-6 w-full animate-[fadeIn_0.3s_ease] space-y-6">
    <Toast />

    <!-- Top Navigation & Breadcrumb -->
    <div class="flex items-center justify-between gap-4 flex-wrap pb-2 border-b border-border/40">
      <div class="flex items-center gap-3">
        <button
          @click="goBack"
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface2 text-textMuted hover:text-textMain text-sm font-semibold transition-all shadow-xs"
        >
          <i class="pi pi-arrow-left text-xs"></i>
          <span>Kembali ke RTL</span>
        </button>

        <div class="hidden sm:flex items-center gap-2 text-xs text-textMuted font-medium">
          <span>/</span>
          <span>Rencana Tindak Lanjut</span>
          <span>/</span>
          <span class="text-textMain font-bold">Detail RTL #{{ id }}</span>
        </div>
      </div>

      <div class="flex items-center gap-2.5">
        <button
          v-if="rtl?.disposisi_id"
          @click="router.push(`/disposisi/${rtl.disposisi_id}`)"
          class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-border bg-surface hover:bg-surface2 text-textMain text-xs font-semibold shadow-xs transition-colors"
          title="Buka Disposisi Induk"
        >
          <i class="pi pi-file-arrow-up text-accent"></i>
          <span>Disposisi: {{ rtl.nomor_disposisi }}</span>
        </button>

        <button
          @click="fetchDetail"
          class="p-2 rounded-lg border border-border bg-surface hover:bg-surface2 text-textMuted hover:text-textMain text-xs transition-colors"
          title="Refresh Data"
        >
          <i class="pi pi-refresh" :class="{ 'animate-spin': loading }"></i>
        </button>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading" class="space-y-6">
      <div class="h-44 bg-surface rounded-2xl border border-border animate-pulse p-6"></div>
      <div class="grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-6">
        <div class="h-96 bg-surface rounded-2xl border border-border animate-pulse"></div>
        <div class="space-y-4">
          <div class="h-48 bg-surface rounded-2xl border border-border animate-pulse"></div>
          <div class="h-48 bg-surface rounded-2xl border border-border animate-pulse"></div>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-else-if="error" class="bg-rose-50 text-rose-700 p-6 rounded-2xl border border-rose-200 text-center font-semibold">
      <i class="pi pi-exclamation-circle text-2xl mb-2 block"></i>
      Oops! {{ error }}
      <div class="mt-3">
        <button @click="fetchDetail" class="px-4 py-2 bg-rose-600 text-white rounded-lg text-xs font-bold hover:bg-rose-700">
          Coba Lagi
        </button>
      </div>
    </div>

    <!-- Main Content (Fullscreen Responsive Layout) -->
    <div v-else-if="rtl" class="space-y-6">

      <!-- Full-Width Header Card -->
      <div class="bg-surface border border-border/80 rounded-2xl p-6 shadow-sm relative overflow-hidden">
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-accent via-emerald-500 to-teal-400"></div>

        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-5">
          <div class="flex-1 min-w-0 space-y-3">
            <!-- Badges Row -->
            <div class="flex items-center gap-2.5 flex-wrap">
              <span
                @click="router.push(`/disposisi/${rtl.disposisi_id}`)"
                class="cursor-pointer inline-flex items-center gap-1.5 font-mono text-xs font-bold text-accent bg-accent/10 hover:bg-accent/20 px-2.5 py-1 rounded-lg border border-accent/25 transition-colors"
                title="Klik untuk membuka Disposisi"
              >
                <i class="pi pi-file text-[11px]"></i>
                {{ rtl.nomor_disposisi }}
              </span>

              <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase border shadow-2xs" :class="getPrioritasColor(rtl.prioritas)">
                <i class="pi pi-bookmark text-[10px]"></i>
                {{ rtl.prioritas || 'Biasa' }}
              </span>

              <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase border shadow-2xs" :class="getStatusColor(rtl.status_progress)">
                <span class="w-1.5 h-1.5 rounded-full" :class="rtl.status_progress === 'DONE' ? 'bg-emerald-500' : rtl.status_progress === 'REVIEW' ? 'bg-amber-500' : 'bg-blue-500'"></span>
                {{ getStatusLabel(rtl.status_progress) }}
              </span>

              <span v-if="Number(rtl.is_berjenjang) === 1" class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase border shadow-2xs bg-amber-500/10 text-amber-600 border-amber-500/20">
                <i class="pi pi-sitemap text-[10px]"></i>
                Mode Berjenjang
              </span>

              <span v-if="isOverdue" class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full uppercase border bg-rose-50 text-rose-700 border-rose-200 animate-pulse">
                <i class="pi pi-exclamation-triangle text-[10px]"></i>
                Overdue
              </span>
            </div>

            <!-- Subject / Perihal -->
            <h1 class="text-xl md:text-2xl font-extrabold text-textMain leading-tight">
              {{ rtl.perihal_surat || 'Rencana Tindak Lanjut' }}
            </h1>

            <!-- Alert Mode Berjenjang -->
            <div v-if="Number(rtl.is_berjenjang) === 1" class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs text-amber-700 dark:text-amber-300 flex items-center gap-2.5">
              <i class="pi pi-info-circle text-base text-amber-600 shrink-0"></i>
              <div>
                <strong>Alur Validasi Berjenjang RTL Aktif:</strong> Pengerjaan dan validasi tugas diselesaikan secara berurutan sesuai level jabatan. Level atas baru dapat menyelesaikan setelah level di bawahnya berstatus Selesai (Done).
              </div>
            </div>

            <!-- Reference Chips Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
              <div class="bg-surface2/60 border border-border/60 rounded-xl p-3">
                <div class="text-[11px] font-bold text-textMuted uppercase flex items-center gap-1 mb-1">
                  <i class="pi pi-building text-xs text-accent"></i> Asal Surat
                </div>
                <div class="text-xs font-semibold text-textMain truncate" :title="rtl.asal_surat || '—'">
                  {{ rtl.asal_surat || '—' }}
                </div>
              </div>

              <div class="bg-surface2/60 border border-border/60 rounded-xl p-3">
                <div class="text-[11px] font-bold text-textMuted uppercase flex items-center gap-1 mb-1">
                  <i class="pi pi-file text-xs text-accent"></i> No. Surat
                </div>
                <div class="text-xs font-semibold text-textMain truncate" :title="rtl.nomor_surat || '—'">
                  {{ rtl.nomor_surat || '—' }}
                </div>
              </div>

              <div class="bg-surface2/60 border border-border/60 rounded-xl p-3">
                <div class="text-[11px] font-bold text-textMuted uppercase flex items-center gap-1 mb-1">
                  <i class="pi pi-calendar text-xs text-accent"></i> Tgl Surat / Disposisi
                </div>
                <div class="text-xs font-semibold text-textMain truncate">
                  {{ formatDate(rtl.tanggal_surat || rtl.tanggal_disposisi) }}
                </div>
              </div>

              <div class="bg-surface2/60 border border-border/60 rounded-xl p-3" :class="{ 'bg-rose-50/70 border-rose-200': isOverdue }">
                <div class="text-[11px] font-bold uppercase flex items-center gap-1 mb-1" :class="isOverdue ? 'text-rose-600' : 'text-textMuted'">
                  <i class="pi pi-clock text-xs" :class="isOverdue ? 'text-rose-600' : 'text-accent'"></i> Batas Waktu
                </div>
                <div class="text-xs font-semibold truncate" :class="isOverdue ? 'text-rose-700 font-bold' : 'text-textMain'">
                  {{ formatDate(rtl.batas_waktu) }}
                </div>
              </div>
            </div>
          </div>

          <!-- Creator & Deadline Meta Column -->
          <div class="lg:w-64 shrink-0 bg-surface2/40 border border-border/70 rounded-xl p-4 flex flex-col justify-between gap-3">
            <div>
              <div class="text-[11px] font-bold text-textMuted uppercase tracking-wider mb-1 flex items-center gap-1.5">
                <i class="pi pi-user text-xs text-accent"></i> Dibuat Oleh
              </div>
              <div class="text-sm font-bold text-textMain">{{ rtl.pembuat || 'Administrator' }}</div>
              <div class="text-[11px] text-textMuted mt-0.5">Pada {{ formatDateFull(rtl.created_at) }}</div>
            </div>

            <div class="pt-2 border-t border-border/50 flex items-center justify-between text-xs">
              <span class="text-textMuted">Progres Selesai</span>
              <span class="font-bold font-mono text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                {{ completedPenerimaCount }} / {{ rtl.penerima?.length || 0 }} Penerima
              </span>
            </div>
          </div>
        </div>

        <!-- Deskripsi RTL & Disposisi Callouts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-5 pt-5 border-t border-border/50">
          <!-- RTL Task Description -->
          <div class="bg-emerald-50/40 border-l-4 border-emerald-500 rounded-r-xl p-4 border border-border/50">
            <div class="text-[11px] font-extrabold text-emerald-800 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
              <i class="pi pi-check-square text-emerald-600"></i>
              Instruksi Tindak Lanjut (RTL)
            </div>
            <div class="text-sm text-textMain leading-relaxed whitespace-pre-wrap font-medium">
              {{ rtl.deskripsi_rtl }}
            </div>
          </div>

          <!-- Original Disposisi Reference Note -->
          <div class="bg-blue-50/30 border-l-4 border-blue-400 rounded-r-xl p-4 border border-border/50">
            <div class="text-[11px] font-extrabold text-blue-800 uppercase tracking-wider mb-1.5 flex items-center gap-1.5">
              <i class="pi pi-comments text-blue-600"></i>
              Catatan Disposisi Rujukan ({{ rtl.nomor_disposisi }})
            </div>
            <div class="text-sm text-textMain leading-relaxed whitespace-pre-wrap font-normal">
              {{ rtl.isi_disposisi || 'Tidak ada catatan khusus pada disposisi induk.' }}
            </div>
          </div>
        </div>
      </div>

      <!-- 2-Column Fullscreen Body Grid -->
      <div class="grid grid-cols-1 xl:grid-cols-[1fr_380px] gap-6 items-start w-full">

        <!-- Left Column: Timeline Progress RTL -->
        <div class="bg-surface border border-border/80 rounded-2xl overflow-hidden shadow-sm flex flex-col">
          <!-- Timeline Header -->
          <div class="p-4 md:px-6 border-b border-border/80 flex items-center justify-between bg-surface2/50 flex-wrap gap-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 rounded-lg bg-accent/10 text-accent flex items-center justify-center font-bold">
                <i class="pi pi-history text-sm"></i>
              </div>
              <div>
                <h3 class="text-sm md:text-base font-extrabold text-textMain leading-tight">Timeline & Catatan Progress RTL</h3>
                <p class="text-[11px] text-textMuted">Histori pembaruan tugas dari penerima RTL</p>
              </div>
              <span class="text-xs text-textMuted font-bold bg-surface3 px-2.5 py-0.5 rounded-full ml-1 border border-border">
                {{ rtl.timeline?.length || 0 }} Log
              </span>
            </div>

            <!-- Quick Action: Update Progress button if logged-in user is a recipient -->
            <div v-if="myPenerima">
              <button
                v-if="!myPenerima.is_locked"
                @click="openProgressModal(myPenerima)"
                class="px-3.5 py-1.5 bg-accent text-white font-semibold text-xs rounded-lg hover:bg-accentHover transition-colors flex items-center gap-1.5 shadow-xs"
              >
                <i class="pi pi-plus text-[11px]"></i>
                <span>Update Progress Anda</span>
              </button>
              <div
                v-else
                class="px-3 py-1.5 bg-surface3 text-textMuted font-semibold text-xs rounded-lg border border-border flex items-center gap-1.5 cursor-not-allowed opacity-80"
                title="Level Anda belum dapat diisi. Menunggu penyelesaian dari level sebelumnya."
              >
                <i class="pi pi-lock text-[11px] text-amber-500"></i>
                <span>Terkunci (Tunggu Level Bawah)</span>
              </div>
            </div>
          </div>

          <!-- Timeline Content -->
          <div class="p-4 md:p-6 flex-1">
            <!-- Empty State -->
            <div v-if="!rtl.timeline || rtl.timeline.length === 0" class="text-sm text-textMuted py-16 text-center flex flex-col items-center justify-center gap-3">
              <div class="w-16 h-16 rounded-full bg-surface2 flex items-center justify-center text-textMuted text-2xl border border-border">
                <i class="pi pi-inbox"></i>
              </div>
              <div>
                <div class="font-bold text-textMain text-base">Belum ada catatan progress</div>
                <p class="text-xs text-textMuted mt-1 max-w-sm mx-auto">
                  Catatan progres dari penerima RTL akan muncul secara berurutan di timeline ini.
                </p>
              </div>
              <template v-if="myPenerima">
                <button
                  v-if="!myPenerima.is_locked"
                  @click="openProgressModal(myPenerima)"
                  class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-accent text-white text-xs font-bold rounded-lg hover:bg-accentHover shadow-xs transition-colors"
                >
                  <i class="pi pi-plus text-xs"></i>
                  <span>Catat Progres Pertama Anda</span>
                </button>
                <div
                  v-else
                  class="mt-2 inline-flex items-center gap-2 px-4 py-2 bg-surface3 text-textMuted text-xs font-bold rounded-lg border border-border"
                >
                  <i class="pi pi-lock text-xs text-amber-500"></i>
                  <span>Tugas Anda Terkunci (Menunggu Level Bawah)</span>
                </div>
              </template>
            </div>

            <!-- Timeline List -->
            <div v-else class="relative pl-6 md:pl-8 space-y-6">
              <!-- Vertical Line -->
              <div class="absolute left-2.5 md:left-3.5 top-3 bottom-3 w-0.5 bg-border/80"></div>

              <div v-for="log in rtl.timeline" :key="log.id" class="relative group">
                <!-- Status Node Icon -->
                <div
                  class="absolute -left-[calc(1.5rem-2px)] md:-left-[calc(2rem-6px)] top-3 w-6 h-6 rounded-full border-2 border-surface flex items-center justify-center shadow-xs z-10"
                  :class="log.status_baru === 'DONE' ? 'bg-emerald-500 text-white' : log.status_baru === 'REVIEW' ? 'bg-amber-500 text-white' : 'bg-blue-500 text-white'"
                >
                  <i class="text-[9px]" :class="log.status_baru === 'DONE' ? 'pi pi-check' : log.status_baru === 'REVIEW' ? 'pi pi-search' : 'pi pi-cog'"></i>
                </div>

                <!-- Log Card -->
                <div class="bg-surface2/60 border border-border/80 rounded-xl p-4 md:p-5 transition-all hover:border-accent/40 hover:bg-surface2/90 shadow-2xs">
                  <!-- Header: User & Status -->
                  <div class="flex items-start justify-between gap-3 mb-3 flex-wrap">
                    <div class="flex items-center gap-3 min-w-0">
                      <div class="w-9 h-9 rounded-full bg-gradient-to-br from-accent to-emerald-600 flex items-center justify-center text-xs font-bold text-white shrink-0 shadow-2xs">
                        {{ initials(log.nama_penerima || log.pembuat) }}
                      </div>
                      <div class="min-w-0">
                        <div class="text-sm font-bold text-textMain truncate flex items-center gap-2">
                          <span>{{ log.nama_penerima || log.pembuat }}</span>
                          <span v-if="isMyLog(log)" class="text-[10px] bg-accent/15 text-accent font-extrabold px-2 py-0.5 rounded-full border border-accent/20">
                            Anda
                          </span>
                        </div>
                        <div class="text-xs text-textMuted truncate">{{ log.jabatan || 'Penerima Tugas' }}</div>
                      </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                      <span class="text-xs font-bold px-3 py-1 rounded-full border shadow-2xs" :class="getStatusColor(log.status_baru)">
                        {{ getStatusLabel(log.status_baru) }}
                      </span>

                      <!-- Action Buttons: ONLY for the user's own logs -->
                      <div v-if="isMyLog(log)" class="flex items-center gap-1 border-l border-border/70 pl-2 ml-1">
                        <button
                          type="button"
                          @click="openEditProgressModal(log)"
                          class="p-1.5 rounded-lg text-textMuted hover:text-blue-600 hover:bg-blue-50 transition-colors"
                          title="Ubah catatan progress Anda"
                        >
                          <i class="pi pi-pencil text-xs"></i>
                        </button>
                        <button
                          type="button"
                          @click="openDeleteModal(log)"
                          class="p-1.5 rounded-lg text-textMuted hover:text-rose-600 hover:bg-rose-50 transition-colors"
                          title="Hapus catatan progress Anda"
                        >
                          <i class="pi pi-trash text-xs"></i>
                        </button>
                      </div>
                    </div>
                  </div>

                  <!-- Catatan Progress Content -->
                  <div class="bg-white/80 border border-border/60 rounded-xl p-3.5 text-sm text-textMain/95 leading-relaxed whitespace-pre-wrap shadow-2xs font-normal">
                    {{ log.catatan }}
                  </div>

                  <!-- Footer: Timestamp & Created Info -->
                  <div class="flex items-center justify-between text-[11px] text-textMuted pt-2.5 mt-3 border-t border-border/40">
                    <span class="flex items-center gap-1">
                      <i class="pi pi-clock text-[10px]"></i>
                      {{ formatDateFull(log.dibuat_at) }}
                    </span>
                    <span v-if="log.pembuat && log.nama_penerima && log.pembuat !== log.nama_penerima" class="italic text-textDim">
                      Dicatat oleh: {{ log.pembuat }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Sidebar (Recipients & Details) -->
        <div class="space-y-6">

          <!-- Widget 1: Status & Pelaksanaan Info -->
          <div class="bg-surface border border-border/80 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-border/80 bg-surface2/50 flex items-center justify-between">
              <span class="text-xs font-bold text-textMain uppercase tracking-wider flex items-center gap-2">
                <i class="pi pi-sliders-h text-accent"></i>
                Ringkasan Pelaksanaan
              </span>
              <span class="text-[11px] font-bold px-2 py-0.5 rounded-full border uppercase" :class="getStatusColor(rtl.status_progress)">
                {{ getStatusLabel(rtl.status_progress) }}
              </span>
            </div>

            <div class="p-4 space-y-3.5 text-xs">
              <div class="flex items-center justify-between py-1 border-b border-border/40">
                <span class="text-textMuted">Tingkat Prioritas</span>
                <span class="font-bold px-2 py-0.5 rounded border uppercase text-[10px]" :class="getPrioritasColor(rtl.prioritas)">
                  {{ rtl.prioritas || 'Biasa' }}
                </span>
              </div>

              <div class="flex items-center justify-between py-1 border-b border-border/40">
                <span class="text-textMuted">Batas Waktu</span>
                <span class="font-bold font-mono" :class="isOverdue ? 'text-rose-600 font-extrabold' : 'text-textMain'">
                  {{ formatDate(rtl.batas_waktu) }}
                  <span v-if="isOverdue" class="text-[10px] text-rose-600 block text-right font-sans font-bold">(Terlambat)</span>
                </span>
              </div>

              <div class="flex items-center justify-between py-1 border-b border-border/40">
                <span class="text-textMuted">Tanggal Dibuat</span>
                <span class="font-semibold text-textMain">{{ formatDate(rtl.created_at) }}</span>
              </div>

              <div class="flex items-center justify-between py-1">
                <span class="text-textMuted">Penyelesaian Tim</span>
                <span class="font-bold text-emerald-600">
                  {{ completedPenerimaCount }} dari {{ rtl.penerima?.length || 0 }} Selesai
                </span>
              </div>

              <!-- Progress bar -->
              <div class="w-full bg-surface3 h-2 rounded-full overflow-hidden">
                <div
                  class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                  :style="{ width: (rtl.penerima?.length ? (completedPenerimaCount / rtl.penerima.length * 100) : 0) + '%' }"
                ></div>
              </div>
            </div>
          </div>

          <!-- Widget 2: Penerima Tugas RTL -->
          <div class="bg-surface border border-border/80 rounded-2xl overflow-hidden shadow-sm">
            <div class="p-4 border-b border-border/80 bg-surface2/50 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <i class="pi pi-users text-accent text-sm"></i>
                <span class="text-xs font-bold text-textMain uppercase tracking-wider">Penerima Tugas RTL</span>
              </div>
              <span class="text-xs font-extrabold bg-surface3 px-2 py-0.5 rounded-full border border-border text-textMuted">
                {{ rtl.penerima?.length || 0 }}
              </span>
            </div>

            <div class="p-4 space-y-3">
              <div
                v-for="dp in rtl.penerima"
                :key="dp.id"
                class="flex flex-col gap-2 p-3 rounded-xl border transition-all"
                :class="Number(dp.user_id) === currentUserId ? 'bg-accent/5 border-accent/40 shadow-xs' : 'bg-surface2/50 border-border/70'"
              >
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-accent to-emerald-600 flex items-center justify-center text-xs font-bold text-white shrink-0">
                      {{ initials(dp.nama_lengkap) }}
                    </div>
                    <div class="min-w-0">
                      <div class="text-xs font-bold text-textMain truncate flex items-center gap-1.5">
                        <span>{{ dp.nama_lengkap }}</span>
                        <span v-if="Number(dp.user_id) === currentUserId" class="text-[9px] bg-accent text-white font-bold px-1.5 py-0.2 rounded-full">
                          Anda
                        </span>
                      </div>
                      <div class="text-[11px] text-textMuted truncate flex items-center gap-1.5 mt-0.5">
                        <span>{{ dp.jabatan_master || dp.jabatan || 'Penerima Tugas' }}</span>
                        <span v-if="dp.urutan_level" class="text-[9px] font-bold px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-600 border border-amber-500/20">
                          Lv.{{ dp.urutan_level }}
                        </span>
                      </div>
                    </div>
                  </div>

                  <div class="flex items-center gap-1.5 shrink-0">
                    <span v-if="dp.is_locked" class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-surface3 text-amber-600 border border-amber-500/30 flex items-center gap-1" title="Menunggu validasi level sebelumnya">
                      <i class="pi pi-lock text-[9px]"></i> Terkunci
                    </span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full border shrink-0 uppercase" :class="getStatusColor(dp.status)">
                      {{ getStatusLabel(dp.status) }}
                    </span>
                  </div>
                </div>

                <!-- Action Button inside Recipient Card for the logged-in user -->
                <div v-if="Number(dp.user_id) === currentUserId" class="pt-2 border-t border-accent/20 flex justify-end">
                  <button
                    v-if="!dp.is_locked"
                    type="button"
                    @click="openProgressModal(dp)"
                    class="inline-flex items-center gap-1.5 px-3 py-1 bg-accent text-white text-xs font-bold rounded-lg hover:bg-accentHover transition-colors shadow-xs"
                  >
                    <i class="pi pi-plus text-[10px]"></i>
                    Update Progress Saya
                  </button>
                  <div
                    v-else
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-surface3 text-textMuted text-xs font-bold rounded-lg border border-border cursor-not-allowed opacity-80"
                    title="Tugas ini terkunci karena penerima di level sebelumnya belum menyelesaikan tugasnya."
                  >
                    <i class="pi pi-lock text-[10px] text-amber-500"></i>
                    <span>Terkunci (Menunggu Level Bawah)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Widget 3: Quick Navigation to Disposisi Induk -->
          <div class="bg-gradient-to-br from-surface to-surface2 border border-border/80 rounded-2xl p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3 mb-2">
              <div>
                <div class="text-[11px] font-bold text-textMuted uppercase">Disposisi Induk</div>
                <div class="text-sm font-bold text-textMain font-mono mt-0.5">{{ rtl.nomor_disposisi }}</div>
              </div>
              <button
                @click="router.push(`/disposisi/${rtl.disposisi_id}`)"
                class="p-2 rounded-lg bg-accent text-white hover:bg-accentHover transition-colors text-xs font-bold shadow-xs flex items-center gap-1"
                title="Buka Disposisi"
              >
                <i class="pi pi-arrow-up-right"></i>
              </button>
            </div>
            <p class="text-xs text-textMuted line-clamp-2 mt-1">
              {{ rtl.isi_disposisi || 'Klik tombol untuk melihat dokumen disposisi lengkap dan lampiran surat.' }}
            </p>
          </div>

        </div>

      </div>

    </div>

    <!-- Modal Update / Edit Progress RTL -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
      <div class="bg-surface w-full max-w-lg rounded-2xl shadow-2xl relative flex flex-col animate-[fadeIn_0.2s_ease] overflow-hidden border border-border">
        
        <div class="p-4 px-6 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="text-base font-extrabold text-textMain flex items-center gap-2">
            <span>{{ isEditing ? '✏️ Ubah Catatan Progress RTL' : '📝 Catat Progress RTL Baru' }}</span>
          </h3>
          <button @click="showModal = false" class="text-textMuted hover:text-rose-600 text-xl leading-none">&times;</button>
        </div>

        <div class="p-6">
          <div v-if="submitMsg.text" class="mb-4 p-3 rounded-xl text-xs font-semibold border"
            :class="submitMsg.type === 'error' ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'">
            {{ submitMsg.text }}
          </div>

          <form @submit.prevent="handleUpdateProgress" class="space-y-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Tahapan / Status Pekerjaan *</label>
              <select v-model="formProgress.status" required class="w-full bg-surface2 border border-border rounded-xl px-3.5 py-2.5 text-sm focus:border-accent outline-none text-textMain font-medium">
                <option value="TO_DO">To Do (Belum Dimulai)</option>
                <option value="ON_PROGRESS">On Progress (Sedang Dikerjakan)</option>
                <option value="REVIEW">Review (Tahap Peninjauan)</option>
                <option value="DONE">Done (Selesai)</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Catatan & Keterangan Perkembangan *</label>
              <textarea
                v-model="formProgress.catatan"
                required
                rows="5"
                placeholder="Jelaskan tindakan yang telah atau sedang Anda lakukan terkait rencana tindak lanjut ini..."
                class="w-full bg-surface2 border border-border rounded-xl px-3.5 py-2.5 text-sm focus:border-accent outline-none text-textMain leading-relaxed"
              ></textarea>
            </div>

            <div class="pt-4 border-t border-border flex justify-end gap-3">
              <button
                type="button"
                @click="showModal = false"
                :disabled="submitting"
                class="px-4 py-2.5 text-xs font-bold text-textMuted bg-surface hover:bg-surface2 border border-border rounded-xl transition-all"
              >
                Batal
              </button>
              <button
                type="submit"
                :disabled="submitting"
                class="px-5 py-2.5 text-xs font-bold text-white bg-accent hover:bg-accentHover disabled:opacity-50 rounded-xl shadow-xs transition-all flex items-center gap-2"
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
      <div class="bg-surface border border-border rounded-2xl w-full max-w-sm shadow-2xl animate-[fadeIn_0.2s_ease] overflow-hidden">
        <div class="p-6 flex flex-col gap-3">
          <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center mx-auto text-xl border border-rose-200">
            <i class="pi pi-exclamation-triangle"></i>
          </div>
          <div class="text-center">
            <h3 class="text-base font-extrabold text-textMain">Hapus Catatan Progress RTL?</h3>
            <p class="text-xs text-textMuted mt-1.5 leading-relaxed">
              Catatan progress ini akan dihapus permanen. Status RTL Anda akan otomatis disesuaikan dengan riwayat progress sebelumnya.
            </p>
          </div>
          <div v-if="deletingLog?.catatan" class="bg-surface2 p-3 rounded-xl border border-border/60 text-xs text-textMain italic truncate">
            "{{ deletingLog.catatan }}"
          </div>
          <div class="flex justify-end gap-2.5 pt-4 border-t border-border mt-2">
            <button
              type="button"
              @click="showDeleteModal = false"
              :disabled="deleting"
              class="px-4 py-2 text-xs font-bold rounded-xl border border-border text-textMuted hover:bg-surface2"
            >
              Batal
            </button>
            <button
              type="button"
              @click="handleDeleteLog"
              :disabled="deleting"
              class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-600 text-white hover:bg-rose-700 disabled:opacity-60 flex items-center gap-1.5 shadow-xs"
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


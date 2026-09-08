<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import api from '@/api/axios'

import Button from 'primevue/button'
import InputText from 'primevue/inputtext'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Tag from 'primevue/tag'
import Badge from 'primevue/badge'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'

import BuatEkspedisiModal from '@/components/ekspedisi/BuatEkspedisiModal.vue'
import CetakTandaTerimaModal from '@/components/ekspedisi/CetakTandaTerimaModal.vue'
import TolakEkspedisiModal from '@/components/ekspedisi/TolakEkspedisiModal.vue'
import LembarDisposisiModal from '@/components/disposisi/LembarDisposisiModal.vue'
import BerkasSuratModal from '@/components/ekspedisi/BerkasSuratModal.vue'
import { useToast } from 'primevue/usetoast'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const { user } = storeToRefs(auth)
const toast = useToast()

const isAdmin = computed(() => ['ADMIN', 'DIREKTUR'].includes(user.value?.role))
const isStrictAdmin = computed(() => user.value?.role === 'ADMIN')
const userUnit = computed(() => user.value?.unit || '')

// Active Tab: 'siap-kirim' | 'riwayat' | 'masuk'
const activeTab = ref(isAdmin.value ? 'siap-kirim' : 'masuk')

// Data states
const siapKirimList = ref([])
const loadingSiapKirim = ref(false)
const searchSiapKirim = ref('')

const riwayatList = ref([])
const loadingRiwayat = ref(false)
const searchRiwayat = ref('')
const filterJenisRiwayat = ref('')
const filterStatusRiwayat = ref('')

const masukList = ref([])
const loadingMasuk = ref(false)
const searchMasuk = ref('')
const filterStatusMasuk = ref('')

const jenisOptions = [
  { label: 'Semua Jenis', value: '' },
  { label: 'Digital', value: 'DIGITAL' },
  { label: 'Fisik', value: 'FISIK' }
]

const statusRiwayatOptions = [
  { label: 'Semua Status', value: '' },
  { label: 'Menunggu', value: 'PENDING' },
  { label: 'Sebagian Diterima', value: 'PARTIAL' },
  { label: 'Selesai (Semua Terima)', value: 'RECEIVED' },
  { label: 'Ditolak', value: 'REJECTED' }
]

const statusMasukOptions = [
  { label: 'Semua Status Penerimaan', value: '' },
  { label: 'Perlu Konfirmasi (Pending)', value: 'PENDING' },
  { label: 'Sudah Diterima', value: 'RECEIVED' },
  { label: 'Ditolak / Dikembalikan', value: 'REJECTED' }
]

// Modal States
const showBuatModal = ref(false)
const selectedDisposisiForBuat = ref(null)
const editMode = ref(false)
const selectedEkspedisiForEdit = ref(null)

const showDeleteDialog = ref(false)
const deleteTarget = ref(null)
const deleting = ref(false)

const showCetakModal = ref(false)
const selectedEkspedisiForCetak = ref({})

const showTolakModal = ref(false)
const selectedTujuanForTolak = ref(null)

const showBerkasModal = ref(false)
const selectedEkspedisiForBerkas = ref(null)

// Detail Ekspedisi Modal
const showDetailModal = ref(false)
const detailEkspedisi = ref(null)
const loadingDetail = ref(false)

// Lembar Disposisi View Modal
const showLembarModal = ref(false)
const lembarDisposisiData = ref(null)
const loadingLembar = ref(false)

// Konfirmasi Terima loading state per id
const confirmingId = ref(null)

const fileUrl = (path) => {
  if (!path) return '#'
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/SiDispo/api/v1')
    .replace('/api/v1', '')
  return base + '/' + path
}

// ── FETCH DATA ─────────────────────────────────────────────────────────────

const loadSiapKirim = async () => {
  loadingSiapKirim.value = true
  try {
    const { data } = await api.get('/ekspedisi/siap-kirim', {
      params: { q: searchSiapKirim.value }
    })
    siapKirimList.value = data.data || []
  } catch (e) {
    console.error('Gagal memuat siap kirim', e)
  } finally {
    loadingSiapKirim.value = false
  }
}

const loadRiwayat = async () => {
  loadingRiwayat.value = true
  try {
    const { data } = await api.get('/ekspedisi', {
      params: {
        q: searchRiwayat.value,
        jenis: filterJenisRiwayat.value,
        status: filterStatusRiwayat.value
      }
    })
    riwayatList.value = data.data || []
  } catch (e) {
    console.error('Gagal memuat riwayat ekspedisi', e)
  } finally {
    loadingRiwayat.value = false
  }
}

const loadMasuk = async () => {
  loadingMasuk.value = true
  try {
    const { data } = await api.get('/ekspedisi/masuk', {
      params: {
        q: searchMasuk.value,
        status: filterStatusMasuk.value
      }
    })
    masukList.value = data.data || []
  } catch (e) {
    console.error('Gagal memuat ekspedisi masuk', e)
  } finally {
    loadingMasuk.value = false
  }
}

const refreshCurrentTab = () => {
  if (activeTab.value === 'siap-kirim') loadSiapKirim()
  else if (activeTab.value === 'riwayat') loadRiwayat()
  else if (activeTab.value === 'masuk') loadMasuk()
}

onMounted(() => {
  if (route.query.tab) {
    activeTab.value = route.query.tab
  }
  loadSiapKirim()
  loadRiwayat()
  loadMasuk()
})

watch(activeTab, (tab) => {
  router.replace({ query: { ...route.query, tab } })
  refreshCurrentTab()
})

// ── ACTIONS ────────────────────────────────────────────────────────────────

const openBuatModal = (disposisi = null) => {
  editMode.value = false
  selectedEkspedisiForEdit.value = null
  selectedDisposisiForBuat.value = disposisi
  showBuatModal.value = true
}

const openEditModal = async (item) => {
  try {
    const { data } = await api.get(`/ekspedisi/${item.id}`)
    selectedEkspedisiForEdit.value = data.data
    selectedDisposisiForBuat.value = null
    editMode.value = true
    showBuatModal.value = true
    if (showDetailModal.value) {
      showDetailModal.value = false
    }
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Gagal',
      detail: e.response?.data?.message || 'Gagal memuat data ekspedisi untuk diedit.',
      life: 3000
    })
  }
}

const confirmDelete = (item) => {
  deleteTarget.value = item
  showDeleteDialog.value = true
}

const handleDelete = async () => {
  if (!deleteTarget.value) return
  deleting.value = true
  try {
    await api.delete(`/ekspedisi/${deleteTarget.value.id}`)
    toast.add({
      severity: 'success',
      summary: 'Berhasil',
      detail: `Ekspedisi ${deleteTarget.value.nomor_ekspedisi || ''} berhasil dihapus.`,
      life: 3000
    })
    showDeleteDialog.value = false
    if (showDetailModal.value && detailEkspedisi.value?.id === deleteTarget.value.id) {
      showDetailModal.value = false
    }
    deleteTarget.value = null
    loadRiwayat()
    loadSiapKirim()
  } catch (e) {
    toast.add({
      severity: 'error',
      summary: 'Gagal Menghapus',
      detail: e.response?.data?.message || 'Gagal menghapus data ekspedisi.',
      life: 4000
    })
  } finally {
    deleting.value = false
  }
}

const openCetakModal = (item) => {
  selectedEkspedisiForCetak.value = item
  showCetakModal.value = true
}

const openTolakModal = (item) => {
  selectedTujuanForTolak.value = item
  showTolakModal.value = true
}

const openBerkasModal = (item) => {
  selectedEkspedisiForBerkas.value = item
  showBerkasModal.value = true
}

const handleEkspedisiSaved = () => {
  toast.add({
    severity: 'success',
    summary: 'Berhasil',
    detail: editMode.value ? 'Perubahan ekspedisi berhasil disimpan.' : 'Ekspedisi baru berhasil dibuat.',
    life: 3000
  })
  editMode.value = false
  selectedEkspedisiForEdit.value = null
  loadSiapKirim()
  loadRiwayat()
  activeTab.value = 'riwayat'
}

const handleKonfirmasiTerima = async (item) => {
  const tujuanId = item.ekspedisi_tujuan_id || item.id
  confirmingId.value = tujuanId
  try {
    await api.put(`/ekspedisi/terima/${tujuanId}`)
    await loadMasuk()
    loadRiwayat()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal mengonfirmasi terima dokumen.')
  } finally {
    confirmingId.value = null
  }
}

const openDetail = async (id) => {
  loadingDetail.value = true
  showDetailModal.value = true
  try {
    const { data } = await api.get(`/ekspedisi/${id}`)
    detailEkspedisi.value = data.data
  } catch (e) {
    console.error('Gagal mengambil detail ekspedisi', e)
  } finally {
    loadingDetail.value = false
  }
}

const openLembarDisposisi = async (disposisiId) => {
  loadingLembar.value = true
  try {
    const { data } = await api.get(`/ekspedisi/lembar-disposisi/${disposisiId}`)
    lembarDisposisiData.value = data.data
    showLembarModal.value = true
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal memuat lembar disposisi.')
  } finally {
    loadingLembar.value = false
  }
}

const handleRevisi = async (id) => {
  if (!confirm('Apakah Anda ingin merevisi & mengirim ulang ekspedisi ini? User yang sebelumnya menolak akan dikembalikan ke status Menunggu/Pending.')) return
  try {
    await api.put(`/ekspedisi/${id}/revisi`)
    loadRiwayat()
  } catch (e) {
    alert(e.response?.data?.message || 'Gagal merevisi ekspedisi.')
  }
}

// ── FORMATTERS ─────────────────────────────────────────────────────────────

const formatDate = (val) => {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
  } catch { return val }
}

const formatDateTime = (val) => {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', {
      day: '2-digit', month: 'short', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    })
  } catch { return val }
}

const countPendingMasuk = computed(() => {
  return masukList.value.filter(m => m.status_tujuan === 'PENDING').length
})
</script>

<template>
  <div class="page-container animate-fade-in pb-16">
    
    <!-- HERO BANNER -->
    <div
      class="page-hero flex flex-col sm:flex-row sm:items-center justify-between gap-4"
      style="background: linear-gradient(135deg, #1b4332 0%, #2d6a4f 45%, #40916c 100%);"
    >
      <div>
        <div class="flex items-center gap-2 flex-wrap relative z-10">
          <h2 class="page-hero-title">Ekspedisi Surat</h2>
          <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full border border-white/30 bg-white/15 text-white">
            <span class="w-2 h-2 rounded-full bg-emerald-300"></span>
            DISTRIBUSI RESMI
          </span>
        </div>
        <p class="page-hero-sub">Pencatatan serah terima & distribusi dokumen resmi disposisi selesai RSI Gondanglegi.</p>
      </div>

      <div class="flex items-center gap-2.5 relative z-10">
        <Button
          v-if="isAdmin"
          label="Buat Ekspedisi"
          icon="pi pi-plus"
          class="!bg-white !text-sidebar !border-0 shadow-glow font-bold text-xs !py-2.5 !px-4 !rounded-xl hover:!bg-white/95 transition-all cursor-pointer"
          @click="openBuatModal(null)"
        />
        <Button
          icon="pi pi-refresh"
          class="!bg-white/15 !text-white !border-white/20 hover:!bg-white/25 !rounded-xl !w-10 !h-10"
          @click="refreshCurrentTab"
          v-tooltip.bottom="'Segarkan Data'"
        />
      </div>
    </div>

    <!-- STATS CARDS -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3.5 mb-5">
      <div
        v-if="isAdmin"
        @click="activeTab = 'siap-kirim'"
        class="glass-card p-4 transition-all cursor-pointer hover:shadow-card-hover hover:-translate-y-0.5 border-l-4"
        :class="activeTab === 'siap-kirim' ? 'border-l-blue-500 ring-2 ring-blue-500/20' : 'border-l-blue-400'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-textMuted uppercase tracking-wider">Siap Kirim</span>
          <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold">
            <i class="pi pi-inbox text-lg"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-textMain mt-2 font-mono">{{ siapKirimList.length }}</div>
        <div class="text-[11px] text-textMuted mt-0.5">Disposisi selesai siap kirim</div>
      </div>

      <div
        v-if="isAdmin"
        @click="activeTab = 'riwayat'"
        class="glass-card p-4 transition-all cursor-pointer hover:shadow-card-hover hover:-translate-y-0.5 border-l-4"
        :class="activeTab === 'riwayat' ? 'border-l-purple-500 ring-2 ring-purple-500/20' : 'border-l-purple-400'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-textMuted uppercase tracking-wider">Riwayat Kirim</span>
          <div class="w-10 h-10 rounded-xl bg-purple-500/10 text-purple-600 flex items-center justify-center font-bold">
            <i class="pi pi-history text-lg"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-textMain mt-2 font-mono">{{ riwayatList.length }}</div>
        <div class="text-[11px] text-textMuted mt-0.5">Total ekspedisi keluar</div>
      </div>

      <div
        @click="activeTab = 'masuk'"
        class="glass-card p-4 transition-all cursor-pointer hover:shadow-card-hover hover:-translate-y-0.5 border-l-4"
        :class="activeTab === 'masuk' ? 'border-l-emerald-500 ring-2 ring-emerald-500/20' : 'border-l-emerald-400'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-textMuted uppercase tracking-wider">Ekspedisi Masuk</span>
          <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
            <i class="pi pi-envelope text-lg"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-textMain mt-2 font-mono">{{ masukList.length }}</div>
        <div class="text-[11px] text-textMuted mt-0.5">Ditujukan kepada Anda</div>
      </div>

      <div
        @click="activeTab = 'masuk'; filterStatusMasuk = 'PENDING'"
        class="glass-card p-4 transition-all cursor-pointer hover:shadow-card-hover hover:-translate-y-0.5 border-l-4"
        :class="activeTab === 'masuk' && filterStatusMasuk === 'PENDING' ? 'border-l-amber-500 ring-2 ring-amber-500/20' : 'border-l-amber-400'"
      >
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-textMuted uppercase tracking-wider">Perlu Konfirmasi</span>
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold">
            <i class="pi pi-clock text-lg"></i>
          </div>
        </div>
        <div class="text-2xl font-black text-amber-600 mt-2 font-mono">{{ countPendingMasuk }}</div>
        <div class="text-[11px] text-textMuted mt-0.5">Menunggu konfirmasi terima</div>
      </div>
    </div>

    <!-- TAB NAVIGATION BAR -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 mb-4">
      <button
        v-if="isAdmin"
        type="button"
        @click="activeTab = 'siap-kirim'"
        class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 border whitespace-nowrap cursor-pointer shadow-2xs"
        :class="activeTab === 'siap-kirim' 
          ? 'bg-brandGreen text-white border-brandGreen shadow-xs' 
          : 'bg-surface text-textMuted border-border hover:bg-surface2 hover:text-textMain'"
      >
        <i class="pi pi-send text-xs"></i>
        <span>Siap Kirim</span>
        <span 
          class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
          :class="activeTab === 'siap-kirim' ? 'bg-white/20 text-white' : 'bg-surface2 text-textMuted'"
        >
          {{ siapKirimList.length }}
        </span>
      </button>

      <button
        v-if="isAdmin"
        type="button"
        @click="activeTab = 'riwayat'"
        class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 border whitespace-nowrap cursor-pointer shadow-2xs"
        :class="activeTab === 'riwayat' 
          ? 'bg-brandGreen text-white border-brandGreen shadow-xs' 
          : 'bg-surface text-textMuted border-border hover:bg-surface2 hover:text-textMain'"
      >
        <i class="pi pi-history text-xs"></i>
        <span>Riwayat Pengiriman</span>
        <span 
          class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
          :class="activeTab === 'riwayat' ? 'bg-white/20 text-white' : 'bg-surface2 text-textMuted'"
        >
          {{ riwayatList.length }}
        </span>
      </button>

      <button
        type="button"
        @click="activeTab = 'masuk'"
        class="px-4 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center gap-2 border whitespace-nowrap cursor-pointer shadow-2xs"
        :class="activeTab === 'masuk' 
          ? 'bg-brandGreen text-white border-brandGreen shadow-xs' 
          : 'bg-surface text-textMuted border-border hover:bg-surface2 hover:text-textMain'"
      >
        <i class="pi pi-inbox text-xs"></i>
        <span>{{ isAdmin ? 'Ekspedisi Masuk' : 'Ekspedisi Masuk Anda' }}</span>
        <span 
          v-if="countPendingMasuk > 0"
          class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold animate-pulse"
          :class="activeTab === 'masuk' ? 'bg-amber-400 text-slate-900' : 'bg-amber-100 text-amber-800'"
        >
          {{ countPendingMasuk }} Perlu Konfirmasi
        </span>
        <span 
          v-else
          class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
          :class="activeTab === 'masuk' ? 'bg-white/20 text-white' : 'bg-surface2 text-textMuted'"
        >
          {{ masukList.length }}
        </span>
      </button>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 1: SIAP KIRIM (ADMIN) -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <div v-if="activeTab === 'siap-kirim' && isAdmin" class="flex flex-col gap-4">
      <div class="filter-panel mb-2 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <IconField class="w-full sm:w-80">
          <InputIcon class="pi pi-search text-accent" />
          <InputText
            v-model="searchSiapKirim"
            @input="loadSiapKirim"
            placeholder="Cari no. surat, perihal, asal surat..."
            class="w-full text-xs !rounded-xl !bg-surface2"
          />
        </IconField>
        <div class="text-xs text-textMuted font-medium flex items-center gap-1.5">
          <i class="pi pi-info-circle text-emerald-600"></i>
          <span>Menampilkan disposisi berstatus <strong>SELESAI</strong> yang siap dibuatkan resi ekspedisi.</span>
        </div>
      </div>

      <div class="glass-card overflow-hidden shadow-card mb-4">
        <div v-if="loadingSiapKirim" class="p-10 text-center text-xs text-textMuted">
          <i class="pi pi-spin pi-spinner text-2xl text-emerald-600 mb-2"></i>
          <div>Memuat data disposisi selesai...</div>
        </div>

        <div v-else-if="siapKirimList.length === 0" class="p-12 text-center text-xs text-textMuted flex flex-col items-center">
          <div class="w-14 h-14 rounded-2xl bg-surface2 flex items-center justify-center mb-3 text-textMuted">
            <i class="pi pi-check-circle text-3xl text-emerald-500"></i>
          </div>
          <div class="font-bold text-textMain text-sm">Semua Disposisi Selesai Sudah Diekspedisi</div>
          <div class="text-xs text-textMuted mt-1 max-w-sm">Belum ada dokumen baru yang memerlukan pengiriman ekspedisi saat ini.</div>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-surface2/80 border-b border-border text-[11px] uppercase tracking-wider font-bold text-textMuted">
                <th class="p-3.5 text-center w-12">No</th>
                <th class="p-3.5">Surat Masuk</th>
                <th class="p-3.5">Perihal</th>
                <th class="p-3.5">Asal Surat</th>
                <th class="p-3.5 text-center">No. Disposisi</th>
                <th class="p-3.5 text-center">Berkas</th>
                <th class="p-3.5 text-center">Tgl Disposisi</th>
                <th class="p-3.5 text-center w-36">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/40">
              <tr
                v-for="(item, idx) in siapKirimList"
                :key="item.disposisi_id"
                class="hover:bg-surface2/50 transition-colors text-textMain"
              >
                <td class="p-3.5 text-center text-textMuted font-bold">{{ idx + 1 }}</td>
                <td class="p-3.5">
                  <div class="font-bold text-textMain">{{ item.nomor_surat }}</div>
                  <div class="text-[11px] text-textMuted">Agenda: {{ item.nomor_agenda || '-' }}</div>
                </td>
                <td class="p-3.5 max-w-xs">
                  <div class="font-medium text-textMain line-clamp-2 leading-relaxed">{{ item.perihal }}</div>
                </td>
                <td class="p-3.5 text-textMain font-medium">{{ item.asal_surat }}</td>
                <td class="p-3.5 text-center">
                  <span class="px-2 py-0.5 rounded-md bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 font-mono font-bold text-[11px]">
                    {{ item.nomor_disposisi }}
                  </span>
                </td>
                <td class="p-3.5 text-center">
                  <button
                    type="button"
                    @click="openBerkasModal(item)"
                    class="px-2.5 py-1 rounded-lg bg-blue-500/10 text-blue-700 dark:text-blue-400 font-bold text-[11px] border border-blue-500/20 hover:bg-blue-500/20 cursor-pointer inline-flex items-center gap-1.5 transition-colors"
                  >
                    <i class="pi pi-paperclip text-[10px]"></i>
                    <span>{{ (item.files || []).length }} berkas</span>
                  </button>
                </td>
                <td class="p-3.5 text-center text-textMuted">{{ formatDate(item.tanggal_disposisi) }}</td>
                <td class="p-3.5 text-center">
                  <Button
                    label="Buat Ekspedisi"
                    icon="pi pi-send"
                    severity="success"
                    size="small"
                    class="btn-gradient !rounded-xl !text-xs font-bold whitespace-nowrap shadow-xs !py-1.5 !px-3"
                    @click="openBuatModal(item)"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 2: RIWAYAT PENGIRIMAN (ADMIN) -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <div v-if="activeTab === 'riwayat' && isAdmin" class="flex flex-col gap-4">
      <!-- FILTERS -->
      <div class="filter-panel mb-2 flex flex-wrap items-center justify-between gap-3">
        <IconField class="w-full sm:w-80">
          <InputIcon class="pi pi-search text-accent" />
          <InputText
            v-model="searchRiwayat"
            @input="loadRiwayat"
            placeholder="Cari nomor resi, no. surat, perihal..."
            class="w-full text-xs !rounded-xl !bg-surface2"
          />
        </IconField>

        <div class="flex items-center gap-2.5 flex-wrap text-xs">
          <!-- Filter Jenis -->
          <Select
            v-model="filterJenisRiwayat"
            :options="jenisOptions"
            option-label="label"
            option-value="value"
            placeholder="Pilih Jenis"
            class="w-36 !rounded-xl text-xs !bg-surface2"
            @change="loadRiwayat"
          />

          <!-- Filter Status -->
          <Select
            v-model="filterStatusRiwayat"
            :options="statusRiwayatOptions"
            option-label="label"
            option-value="value"
            placeholder="Pilih Status"
            class="w-52 !rounded-xl text-xs !bg-surface2"
            @change="loadRiwayat"
          />
        </div>
      </div>

      <!-- TABLE RIWAYAT -->
      <div class="glass-card overflow-hidden shadow-card mb-4">
        <div v-if="loadingRiwayat" class="p-10 text-center text-xs text-textMuted">
          <i class="pi pi-spin pi-spinner text-2xl text-emerald-600 mb-2"></i>
          <div>Memuat riwayat ekspedisi...</div>
        </div>

        <div v-else-if="riwayatList.length === 0" class="p-12 text-center text-xs text-textMuted flex flex-col items-center">
          <div class="w-14 h-14 rounded-2xl bg-surface2 flex items-center justify-center mb-3 text-textMuted">
            <i class="pi pi-inbox text-3xl"></i>
          </div>
          <div class="font-bold text-textMain text-sm">Belum Ada Riwayat Ekspedisi</div>
          <div class="text-xs text-textMuted mt-1">Buat ekspedisi baru dari Tab Siap Kirim.</div>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-surface2/80 border-b border-border text-[11px] uppercase tracking-wider font-bold text-textMuted">
                <th class="p-3.5 text-center w-12">No</th>
                <th class="p-3.5">No. Resi & Tgl Kirim</th>
                <th class="p-3.5">Surat & Perihal</th>
                <th class="p-3.5 text-center">Jenis</th>
                <th class="p-3.5">User / Penerima Tujuan</th>
                <th class="p-3.5 text-center">Berkas</th>
                <th class="p-3.5 text-center">Status Global</th>
                <th class="p-3.5 text-center w-36">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/40">
              <tr
                v-for="(item, idx) in riwayatList"
                :key="item.id"
                class="hover:bg-surface2/50 transition-colors text-textMain"
              >
                <td class="p-3.5 text-center text-textMuted font-bold">{{ idx + 1 }}</td>
                <td class="p-3.5">
                  <div class="font-mono font-bold text-textMain text-xs">{{ item.nomor_ekspedisi }}</div>
                  <div class="text-[10px] text-textMuted font-semibold mt-0.5">
                    Tgl Kirim: {{ formatDate(item.tanggal_kirim) }}
                  </div>
                </td>
                <td class="p-3.5 max-w-xs">
                  <div class="font-bold text-textMain truncate">{{ item.nomor_surat }}</div>
                  <div class="text-[11px] text-textMuted line-clamp-1 mt-0.5">{{ item.perihal }}</div>
                  <div class="text-[10px] text-textMuted/80">Asal: {{ item.asal_surat }}</div>
                </td>
                <td class="p-3.5 text-center">
                  <span
                    v-if="item.jenis_pengiriman === 'FISIK'"
                    class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 font-bold text-[10px] inline-flex items-center gap-1"
                  >
                    <i class="pi pi-box text-[9px]"></i> Fisik
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 font-bold text-[10px] inline-flex items-center gap-1"
                  >
                    <i class="pi pi-desktop text-[9px]"></i> Digital
                  </span>
                </td>
                <td class="p-3.5">
                  <div class="flex flex-wrap gap-1.5 max-w-sm">
                    <span
                      v-for="t in (item.tujuan || [])"
                      :key="t.id"
                      class="px-2 py-0.5 rounded-md text-[10px] font-bold flex items-center gap-1 border"
                      :class="{
                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20': t.status === 'RECEIVED',
                        'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20': t.status === 'PENDING',
                        'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20': t.status === 'REJECTED'
                      }"
                      :title="t.status === 'RECEIVED' ? 'Diterima oleh ' + (t.nama_penerima || t.nama_user_tujuan) : (t.status === 'REJECTED' ? 'Ditolak: ' + t.alasan_tolak : 'Menunggu konfirmasi')"
                    >
                      <span>{{ t.nama_user_tujuan || t.unit_tujuan }}</span>
                      <i v-if="t.status === 'RECEIVED'" class="pi pi-check text-[9px]"></i>
                      <i v-else-if="t.status === 'REJECTED'" class="pi pi-times text-[9px]"></i>
                      <i v-else class="pi pi-clock text-[9px]"></i>
                    </span>
                  </div>
                </td>
                <td class="p-3.5 text-center">
                  <button
                    type="button"
                    @click="openBerkasModal(item)"
                    class="px-2.5 py-1 rounded-lg bg-blue-500/10 text-blue-700 dark:text-blue-400 font-bold text-[11px] border border-blue-500/20 hover:bg-blue-500/20 cursor-pointer inline-flex items-center gap-1 transition-colors"
                    title="Buka Berkas & Lampiran Surat"
                  >
                    <i class="pi pi-paperclip text-[10px]"></i>
                    <span>{{ (item.files || []).length }} File</span>
                  </button>
                </td>
                <td class="p-3.5 text-center">
                  <Tag
                    v-if="item.status_global === 'RECEIVED'"
                    severity="success"
                    value="SELESAI"
                    class="text-[10px]"
                  />
                  <Tag
                    v-else-if="item.status_global === 'PARTIAL'"
                    severity="info"
                    value="SEBAGIAN"
                    class="text-[10px]"
                  />
                  <Tag
                    v-else-if="item.status_global === 'REJECTED'"
                    severity="danger"
                    value="DITOLAK"
                    class="text-[10px]"
                  />
                  <Tag
                    v-else
                    severity="warn"
                    value="MENUNGGU"
                    class="text-[10px]"
                  />
                </td>
                <td class="p-3.5 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <!-- Tombol Cetak Tanda Terima (Khusus Fisik) -->
                    <Button
                      v-if="item.jenis_pengiriman === 'FISIK'"
                      icon="pi pi-print"
                      severity="secondary"
                      size="small"
                      outlined
                      @click="openCetakModal(item)"
                      v-tooltip.top="'Cetak Tanda Terima Fisik / QR'"
                      class="!w-8 !h-8 !p-0 !rounded-lg"
                    />

                    <!-- Tombol Detail / Lacak -->
                    <Button
                      icon="pi pi-eye"
                      severity="info"
                      size="small"
                      outlined
                      @click="openDetail(item.id)"
                      v-tooltip.top="'Lihat Detail Ekspedisi'"
                      class="!w-8 !h-8 !p-0 !rounded-lg"
                    />

                    <!-- Tombol Edit (Hanya Admin) -->
                    <Button
                      v-if="isStrictAdmin"
                      icon="pi pi-pencil"
                      severity="warn"
                      size="small"
                      outlined
                      @click="openEditModal(item)"
                      v-tooltip.top="'Edit Ekspedisi (Admin)'"
                      class="!w-8 !h-8 !p-0 !rounded-lg text-amber-600 hover:!bg-amber-500/10 border-amber-500/30"
                    />

                    <!-- Tombol Hapus (Hanya Admin) -->
                    <Button
                      v-if="isStrictAdmin"
                      icon="pi pi-trash"
                      severity="danger"
                      size="small"
                      outlined
                      @click="confirmDelete(item)"
                      v-tooltip.top="'Hapus Ekspedisi (Admin)'"
                      class="!w-8 !h-8 !p-0 !rounded-lg text-red-600 hover:!bg-red-500/10 border-red-500/30"
                    />

                    <!-- Tombol Revisi & Kirim Ulang jika ditolak -->
                    <Button
                      v-if="item.status_global === 'REJECTED' || (item.tujuan || []).some(t => t.status === 'REJECTED')"
                      icon="pi pi-refresh"
                      severity="warning"
                      size="small"
                      outlined
                      @click="handleRevisi(item.id)"
                      v-tooltip.top="'Revisi & Kirim Ulang'"
                      class="!w-8 !h-8 !p-0 !rounded-lg"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- TAB 3: EKSPEDISI MASUK (SISI USER PENERIMA) -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <div v-if="activeTab === 'masuk' || !isAdmin" class="flex flex-col gap-4">
      <!-- FILTERS -->
      <div class="filter-panel mb-2 flex flex-wrap items-center justify-between gap-3">
        <IconField class="w-full sm:w-80">
          <InputIcon class="pi pi-search text-accent" />
          <InputText
            v-model="searchMasuk"
            @input="loadMasuk"
            placeholder="Cari no. resi, nomor surat, perihal..."
            class="w-full text-xs !rounded-xl !bg-surface2"
          />
        </IconField>

        <div class="flex items-center gap-2">
          <Select
            v-model="filterStatusMasuk"
            :options="statusMasukOptions"
            option-label="label"
            option-value="value"
            placeholder="Pilih Status Penerimaan"
            class="w-64 !rounded-xl text-xs !bg-surface2"
            @change="loadMasuk"
          />
        </div>
      </div>

      <!-- TABLE EKSPEDISI MASUK -->
      <div class="glass-card overflow-hidden shadow-card mb-4">
        <div v-if="loadingMasuk" class="p-10 text-center text-xs text-textMuted">
          <i class="pi pi-spin pi-spinner text-2xl text-emerald-600 mb-2"></i>
          <div>Memuat daftar ekspedisi masuk...</div>
        </div>

        <div v-else-if="masukList.length === 0" class="p-12 text-center text-xs text-textMuted flex flex-col items-center">
          <div class="w-14 h-14 rounded-2xl bg-surface2 flex items-center justify-center mb-3 text-textMuted">
            <i class="pi pi-check-circle text-3xl text-emerald-500"></i>
          </div>
          <div class="font-bold text-textMain text-sm">Tidak Ada Dokumen Ekspedisi Masuk</div>
          <div class="text-xs text-textMuted mt-1">Belum ada surat yang diekspedisikan kepada akun Anda saat ini.</div>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-surface2/80 border-b border-border text-[11px] uppercase tracking-wider font-bold text-textMuted">
                <th class="p-3.5 text-center w-12">No</th>
                <th class="p-3.5">No. Resi & Tgl Kirim</th>
                <th class="p-3.5">Surat & Asal</th>
                <th class="p-3.5">Perihal</th>
                <th class="p-3.5">Penerima</th>
                <th class="p-3.5 text-center">Jenis</th>
                <th class="p-3.5 text-center">Berkas Surat</th>
                <th class="p-3.5 text-center min-w-[220px]">Status & Konfirmasi Penerimaan</th>
                <th class="p-3.5 text-center w-28">Aksi</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-border/40">
              <tr
                v-for="(item, idx) in masukList"
                :key="item.ekspedisi_tujuan_id"
                class="hover:bg-surface2/50 transition-colors text-textMain"
                :class="{ 'bg-amber-500/5 dark:bg-amber-500/10': item.status_tujuan === 'PENDING' }"
              >
                <td class="p-3.5 text-center text-textMuted font-bold">{{ idx + 1 }}</td>
                <td class="p-3.5">
                  <div class="font-mono font-bold text-textMain text-xs">{{ item.nomor_ekspedisi }}</div>
                  <div class="text-[10px] text-textMuted font-semibold mt-0.5">Tgl Kirim: {{ formatDate(item.tanggal_kirim) }}</div>
                  <div class="text-[9.5px] text-textMuted mt-0.5">Oleh: {{ item.nama_pengirim || 'Sekretariat' }}</div>
                </td>
                <td class="p-3.5 max-w-[200px]">
                  <div class="font-bold text-textMain truncate">{{ item.nomor_surat }}</div>
                  <div class="text-[11px] text-textMuted font-medium truncate">{{ item.asal_surat }}</div>
                  <div class="text-[10px] text-textMuted/80">Tgl Surat: {{ formatDate(item.tanggal_surat) }}</div>
                </td>
                <td class="p-3.5 max-w-xs">
                  <div class="font-medium text-textMain line-clamp-2 leading-relaxed">{{ item.perihal }}</div>
                </td>
                <td class="p-3.5">
                  <div class="font-bold text-textMain">{{ item.nama_user_tujuan || item.unit_tujuan }}</div>
                  <div class="text-[10px] text-textMuted">{{ item.jabatan_user_tujuan || item.unit_tujuan }}</div>
                </td>
                <td class="p-3.5 text-center">
                  <span
                    v-if="item.jenis_pengiriman === 'FISIK'"
                    class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 font-bold text-[10px] inline-flex items-center gap-1"
                  >
                    <i class="pi pi-box text-[9px]"></i> Fisik
                  </span>
                  <span
                    v-else
                    class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 font-bold text-[10px] inline-flex items-center gap-1"
                  >
                    <i class="pi pi-desktop text-[9px]"></i> Digital
                  </span>
                </td>

                <!-- KOLOM BERKAS SURAT (BISA DILIHAT OLEH USER PENERIMA EKSPEDISI) -->
                <td class="p-3.5 text-center">
                  <button
                    type="button"
                    @click="openBerkasModal(item)"
                    class="px-2.5 py-1.5 rounded-xl bg-blue-500/10 text-blue-700 dark:text-blue-400 hover:bg-blue-500/20 border border-blue-500/20 font-bold text-xs inline-flex items-center gap-1.5 cursor-pointer shadow-2xs transition-all"
                  >
                    <i class="pi pi-paperclip text-xs"></i>
                    <span>{{ (item.files || []).length }} Berkas</span>
                  </button>
                </td>

                <!-- KOLOM STATUS & KONFIRMASI -->
                <td class="p-3.5 text-center">
                  <!-- KONDISI 1: PENDING -> TOMBOL HIJAU MENCOLOK CHECKLIST & TOMBOL TOLAK -->
                  <div v-if="item.status_tujuan === 'PENDING'" class="flex items-center justify-center gap-2">
                    <button
                      type="button"
                      @click="handleKonfirmasiTerima(item)"
                      :disabled="confirmingId === item.ekspedisi_tujuan_id"
                      class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white font-black text-xs rounded-xl shadow-xs flex items-center gap-1.5 transition-all cursor-pointer border-0"
                    >
                      <i v-if="confirmingId === item.ekspedisi_tujuan_id" class="pi pi-spin pi-spinner text-xs"></i>
                      <i v-else class="pi pi-check text-xs font-bold"></i>
                      <span>Konfirmasi Terima</span>
                    </button>

                    <button
                      type="button"
                      @click="openTolakModal(item)"
                      class="px-2.5 py-1.5 bg-red-500/10 hover:bg-red-500/20 text-red-600 dark:text-red-400 border border-red-500/20 font-semibold text-xs rounded-xl transition-all cursor-pointer"
                      title="Tolak / Kembalikan Dokumen"
                    >
                      <i class="pi pi-times text-xs"></i>
                      <span class="ml-1">Tolak</span>
                    </button>
                  </div>

                  <!-- KONDISI 2: RECEIVED -> BADGE HIJAU INFORMATIF DITERIMA PADA & OLEH NAMA STAF -->
                  <div v-else-if="item.status_tujuan === 'RECEIVED'" class="inline-block text-left p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 shadow-2xs max-w-[260px]">
                    <div class="flex items-center gap-1.5 text-emerald-800 dark:text-emerald-300 font-bold text-[11px] leading-tight">
                      <i class="pi pi-check-circle text-emerald-600 dark:text-emerald-400 text-sm shrink-0"></i>
                      <span>Diterima: {{ formatDateTime(item.received_at) }}</span>
                    </div>
                    <div class="text-[10px] text-textMain font-bold mt-0.5 truncate">
                      oleh {{ item.nama_penerima || 'Staf Penerima' }}
                      <span v-if="item.nip_penerima" class="text-textMuted font-normal">(NIP. {{ item.nip_penerima }})</span>
                    </div>
                  </div>

                  <!-- KONDISI 3: REJECTED -> BADGE MERAH INFORMATIF -->
                  <div v-else-if="item.status_tujuan === 'REJECTED'" class="inline-block text-left p-2 rounded-xl bg-red-500/10 border border-red-500/20 text-red-800 dark:text-red-300 max-w-[260px]">
                    <div class="flex items-center gap-1.5 font-bold text-[10.5px]">
                      <i class="pi pi-times-circle text-red-600 text-sm shrink-0"></i>
                      <span>Ditolak: {{ formatDateTime(item.rejected_at) }}</span>
                    </div>
                    <div class="text-[10px] text-red-700 dark:text-red-400 font-medium mt-0.5 italic truncate">
                      "{{ item.alasan_tolak || 'Tidak sesuai' }}"
                    </div>
                    <div class="text-[9.5px] text-textMuted mt-0.5">oleh {{ item.nama_penolak || 'Staf' }}</div>
                  </div>
                </td>

                <!-- AKSI LIHAT LEMBAR DISPOSISI / CETAK -->
                <td class="p-3.5 text-center">
                  <div class="flex items-center justify-center gap-1.5">
                    <Button
                      icon="pi pi-file-check"
                      severity="success"
                      size="small"
                      outlined
                      @click="openLembarDisposisi(item.disposisi_id)"
                      v-tooltip.top="'Lihat Lembar Disposisi Selesai'"
                      class="!w-8 !h-8 !p-0 !rounded-lg"
                    />
                    <Button
                      v-if="item.jenis_pengiriman === 'FISIK'"
                      icon="pi pi-print"
                      severity="secondary"
                      size="small"
                      outlined
                      @click="openCetakModal(item)"
                      v-tooltip.top="'Lihat Tanda Terima Fisik'"
                      class="!w-8 !h-8 !p-0 !rounded-lg"
                    />
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <!-- MODAL DETAIL EKSPEDISI (PELACAKAN SERAH TERIMA) -->
    <!-- ═══════════════════════════════════════════════════════════════════════════ -->
    <Dialog
      v-model:visible="showDetailModal"
      modal
      :style="{ width: '720px', maxWidth: '95vw' }"
      class="detail-ekspedisi-dialog"
    >
      <template #header>
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
            <i class="pi pi-box text-lg"></i>
          </div>
          <div>
            <h3 class="text-base font-bold text-textMain leading-tight">Detail & Pelacakan Ekspedisi</h3>
            <p class="text-xs text-textMuted font-mono">{{ detailEkspedisi?.nomor_ekspedisi || 'Memuat...' }}</p>
          </div>
        </div>
      </template>

      <div v-if="loadingDetail" class="p-10 text-center text-xs text-textMuted">
        <i class="pi pi-spin pi-spinner text-2xl text-emerald-600 mb-2"></i>
        <div>Memuat data detail pelacakan...</div>
      </div>

      <div v-else-if="detailEkspedisi" class="flex flex-col gap-4 py-1 text-xs">
        <!-- Metadata Ringkas Card -->
        <div class="p-4 rounded-2xl bg-surface2/60 border border-border grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Nomor Resi & Jenis</div>
            <div class="font-mono font-black text-emerald-700 dark:text-emerald-400 text-sm mt-0.5">{{ detailEkspedisi.nomor_ekspedisi }}</div>
            <div class="mt-1">
              <span
                v-if="detailEkspedisi.jenis_pengiriman === 'FISIK'"
                class="px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 font-bold text-[10px] inline-flex items-center gap-1"
              >
                <i class="pi pi-box text-[9px]"></i> Dokumen Fisik
              </span>
              <span
                v-else
                class="px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 font-bold text-[10px] inline-flex items-center gap-1"
              >
                <i class="pi pi-desktop text-[9px]"></i> Digital Only
              </span>
            </div>
          </div>
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Tanggal & Pengirim</div>
            <div class="font-bold text-textMain mt-0.5">{{ formatDate(detailEkspedisi.tanggal_kirim) }}</div>
            <div class="text-textMuted text-[11px] mt-0.5">Oleh: <strong class="text-textMain">{{ detailEkspedisi.nama_pengirim || 'Admin' }}</strong></div>
          </div>
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Surat Masuk</div>
            <div class="font-bold text-textMain mt-0.5">{{ detailEkspedisi.nomor_surat }}</div>
            <div class="text-textMuted text-[11px] truncate mt-0.5 leading-relaxed">{{ detailEkspedisi.perihal }}</div>
          </div>
          <div>
            <div class="text-textMuted text-[10px] uppercase font-bold tracking-wider">Asal Surat & Disposisi</div>
            <div class="font-bold text-textMain mt-0.5">{{ detailEkspedisi.asal_surat }}</div>
            <div class="text-[11px] font-mono text-emerald-600 mt-0.5">{{ detailEkspedisi.nomor_disposisi }}</div>
          </div>
        </div>

        <!-- Tabel Pelacakan Tujuan -->
        <div>
          <div class="font-bold text-textMain uppercase tracking-wider text-[11px] mb-2 flex items-center justify-between">
            <span>Status Serah Terima Tiap Penerima:</span>
            <span class="text-textMuted font-normal text-[11px]">{{ (detailEkspedisi.tujuan || []).length }} tujuan</span>
          </div>
          <div class="border border-border rounded-2xl overflow-hidden bg-surface">
            <table class="w-full text-left text-xs">
              <thead class="bg-surface2/80 border-b border-border text-[10px] font-bold text-textMuted uppercase tracking-wider">
                <tr>
                  <th class="p-3">User / Penerima Tujuan</th>
                  <th class="p-3 text-center">Status</th>
                  <th class="p-3">Waktu & Penerima</th>
                  <th class="p-3">Catatan / Keterangan</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-border/40">
                <tr v-for="t in (detailEkspedisi.tujuan || [])" :key="t.id" class="hover:bg-surface2/30 transition-colors">
                  <td class="p-3">
                    <div class="font-bold text-textMain">{{ t.nama_user_tujuan || t.unit_tujuan }}</div>
                    <div class="text-[10.5px] text-textMuted">{{ t.jabatan_user_tujuan || t.unit_tujuan }}</div>
                  </td>
                  <td class="p-3 text-center">
                    <span
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border inline-flex items-center gap-1"
                      :class="{
                        'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20': t.status === 'RECEIVED',
                        'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20': t.status === 'PENDING',
                        'bg-red-500/10 text-red-700 dark:text-red-400 border-red-500/20': t.status === 'REJECTED'
                      }"
                    >
                      <i v-if="t.status === 'RECEIVED'" class="pi pi-check text-[9px]"></i>
                      <i v-else-if="t.status === 'REJECTED'" class="pi pi-times text-[9px]"></i>
                      <i v-else class="pi pi-clock text-[9px]"></i>
                      <span>{{ t.status }}</span>
                    </span>
                  </td>
                  <td class="p-3">
                    <div v-if="t.status === 'RECEIVED'">
                      <div class="font-bold text-textMain">{{ t.nama_penerima }}</div>
                      <div class="text-textMuted text-[10.5px]">{{ formatDateTime(t.received_at) }}</div>
                    </div>
                    <div v-else-if="t.status === 'REJECTED'">
                      <div class="font-bold text-red-600">{{ t.nama_penolak }}</div>
                      <div class="text-textMuted text-[10.5px]">{{ formatDateTime(t.rejected_at) }}</div>
                    </div>
                    <span v-else class="text-textMuted italic text-[11px]">Belum dikonfirmasi</span>
                  </td>
                  <td class="p-3">
                    <div v-if="t.alasan_tolak" class="text-red-600 italic font-medium leading-tight">"{{ t.alasan_tolak }}"</div>
                    <div v-else-if="t.catatan" class="text-textMain">{{ t.catatan }}</div>
                    <span v-else class="text-textMuted">-</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Lampiran Dokumen Surat -->
        <div v-if="detailEkspedisi.files && detailEkspedisi.files.length">
          <div class="font-bold text-textMain uppercase tracking-wider text-[11px] mb-2">Berkas Lampiran Surat:</div>
          <div class="flex flex-col gap-2">
            <a
              v-for="f in detailEkspedisi.files"
              :key="f.id"
              :href="fileUrl(f.path_file)"
              target="_blank"
              class="flex items-center gap-2.5 p-2.5 rounded-xl bg-surface2/50 border border-border hover:border-blue-500/40 hover:bg-surface2 transition-all text-textMain no-underline group"
            >
              <i class="pi pi-file-pdf text-red-500 text-base"></i>
              <span class="font-medium truncate flex-1 text-xs group-hover:text-blue-600 transition-colors">{{ f.nama_asli || f.nama_file || f.path_file }}</span>
              <i class="pi pi-external-link text-xs text-textMuted group-hover:text-blue-500"></i>
            </a>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-between items-center w-full pt-3 border-t border-border">
          <div class="flex items-center gap-2">
            <Button
              v-if="detailEkspedisi?.jenis_pengiriman === 'FISIK'"
              label="Cetak Tanda Terima"
              icon="pi pi-print"
              severity="secondary"
              size="small"
              class="!rounded-xl text-xs"
              @click="openCetakModal(detailEkspedisi)"
            />
            <Button
              v-if="isStrictAdmin && detailEkspedisi"
              label="Edit"
              icon="pi pi-pencil"
              severity="warn"
              size="small"
              outlined
              class="!rounded-xl text-xs border-amber-500/40 text-amber-600 hover:bg-amber-500/10"
              @click="openEditModal(detailEkspedisi)"
            />
            <Button
              v-if="isStrictAdmin && detailEkspedisi"
              label="Hapus"
              icon="pi pi-trash"
              severity="danger"
              size="small"
              outlined
              class="!rounded-xl text-xs border-red-500/40 text-red-600 hover:bg-red-500/10"
              @click="confirmDelete(detailEkspedisi)"
            />
          </div>
          <Button label="Tutup" severity="secondary" text class="!rounded-xl text-xs" @click="showDetailModal = false" />
        </div>
      </template>
    </Dialog>

    <!-- SUB-MODALS -->
    <BuatEkspedisiModal
      v-model:visible="showBuatModal"
      :selectedDisposisi="selectedDisposisiForBuat"
      :editMode="editMode"
      :editData="selectedEkspedisiForEdit"
      @saved="handleEkspedisiSaved"
    />

    <CetakTandaTerimaModal
      v-model:visible="showCetakModal"
      :ekspedisi="selectedEkspedisiForCetak"
    />

    <TolakEkspedisiModal
      v-model:visible="showTolakModal"
      :targetTujuan="selectedTujuanForTolak"
      @rejected="loadMasuk(); loadRiwayat()"
    />

    <BerkasSuratModal
      v-model:visible="showBerkasModal"
      :ekspedisi="selectedEkspedisiForBerkas"
      @openLembarDisposisi="openLembarDisposisi"
    />

    <LembarDisposisiModal
      v-if="lembarDisposisiData"
      v-model:visible="showLembarModal"
      :disposisi="lembarDisposisiData"
    />

    <!-- DIALOG KONFIRMASI HAPUS EKSPEDISI (ADMIN ONLY) -->
    <Dialog
      v-model:visible="showDeleteDialog"
      modal
      header="Konfirmasi Hapus Ekspedisi"
      :style="{ width: '460px', maxWidth: '92vw' }"
      :closable="!deleting"
    >
      <div class="space-y-4 pt-1">
        <div class="flex items-start gap-3 p-3.5 bg-red-500/10 border border-red-500/20 rounded-xl text-red-700 dark:text-red-400">
          <i class="pi pi-exclamation-triangle text-xl mt-0.5 shrink-0"></i>
          <div class="text-xs">
            <div class="font-bold text-sm mb-1">Apakah Anda yakin ingin menghapus data ekspedisi ini?</div>
            <p class="leading-relaxed text-red-600/90 dark:text-red-300">
              Tindakan ini akan menghapus riwayat pengiriman, tanda terima, dan seluruh status pelacakan penerima secara permanen.
            </p>
          </div>
        </div>

        <div v-if="deleteTarget" class="p-3.5 bg-surface2/60 border border-border rounded-xl text-xs space-y-1.5">
          <div class="flex justify-between">
            <span class="text-textMuted">No. Ekspedisi:</span>
            <span class="font-mono font-bold text-textMain">{{ deleteTarget.nomor_ekspedisi }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-textMuted">No. Surat:</span>
            <span class="font-bold text-textMain truncate max-w-[240px]">{{ deleteTarget.nomor_surat }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-textMuted">Perihal:</span>
            <span class="text-textMain truncate max-w-[240px]">{{ deleteTarget.perihal }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-textMuted">Jumlah Penerima:</span>
            <span class="font-bold text-textMain">{{ (deleteTarget.tujuan || []).length }} User</span>
          </div>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2 pt-3 border-t border-border">
          <Button
            label="Batal"
            severity="secondary"
            text
            size="small"
            class="!rounded-xl text-xs"
            :disabled="deleting"
            @click="showDeleteDialog = false"
          />
          <Button
            label="Hapus Ekspedisi"
            icon="pi pi-trash"
            severity="danger"
            size="small"
            class="!rounded-xl text-xs font-semibold"
            :loading="deleting"
            @click="handleDelete"
          />
        </div>
      </template>
    </Dialog>

  </div>
</template>

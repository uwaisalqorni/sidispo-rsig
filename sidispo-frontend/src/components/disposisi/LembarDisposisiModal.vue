<script setup>
import { ref, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import logoRsi from '@/assets/logorsi.png'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false
  },
  disposisi: {
    type: Object,
    default: () => ({})
  },
  timeline: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['update:visible'])

// Tutup modal
const closeDialog = () => {
  emit('update:visible', false)
}

// Format tanggal Indonesia: 05 September 2026 atau 05/09/2026
const formatDateIndo = (dateStr) => {
  if (!dateStr) return '..............................'
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return dateStr
    return d.toLocaleDateString('id-ID', {
      day: '2-digit',
      month: 'long',
      year: 'numeric'
    })
  } catch {
    return dateStr
  }
}

const formatDateShort = (dateStr) => {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return dateStr
    const day = String(d.getDate()).padStart(2, '0')
    const month = String(d.getMonth() + 1).padStart(2, '0')
    const year = d.getFullYear()
    return `${day}/${month}/${year}`
  } catch {
    return dateStr
  }
}

const formatTimeShort = (dateStr) => {
  if (!dateStr) return ''
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return ''
    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) + ' WIB'
  } catch {
    return ''
  }
}

// Mengurutkan penerima berjenjang dari level tertinggi (Direktur) ke level terendah (Staf)
// Sesuai urutan formulir fisik Lembar Disposisi RS
const hierarchicalLevels = computed(() => {
  if (!props.disposisi?.penerima || props.disposisi.penerima.length === 0) return []

  // Salin dan urutkan: Level angka tertinggi di atas (misal Lv 5 Direktur -> Lv 4 Wadir -> Lv 3 Kabid -> Lv 2 Ka. Instalasi -> Lv 1 Staf)
  const list = [...props.disposisi.penerima].map(p => {
    // Cari catatan selesai dari log timeline jika p.catatan_akhir kosong
    let selesaiNote = p.catatan_akhir || ''
    let selesaiDate = p.tanggal_selesai || p.updated_at || ''

    if (props.timeline && props.timeline.length > 0) {
      const finishLog = [...props.timeline]
        .reverse()
        .find(log => (Number(log.disposisi_penerima_id) === Number(p.id) || Number(log.user_id) === Number(p.user_id)) && log.status_baru === 'SELESAI')

      if (finishLog) {
        if (!selesaiNote && finishLog.catatan) selesaiNote = finishLog.catatan
        if (!selesaiDate && finishLog.created_at) selesaiDate = finishLog.created_at
      }
    }

    const jabLevel = parseInt(p.jabatan_level) || parseInt(p.urutan_level) || 1
    const jabTitle = p.jabatan_master || p.jabatan || 'Pejabat / Staf'

    return {
      ...p,
      jabatan_level: jabLevel,
      display_jabatan: jabTitle,
      selesai_note: selesaiNote,
      selesai_date: selesaiDate,
      is_done: p.status === 'SELESAI'
    }
  })

  // Sort descending by level (Level 5 Direktur -> Level 4 Wadir -> Level 3 Kabid -> Level 2 Ka Instalasi -> Level 1 Staf)
  list.sort((a, b) => b.jabatan_level - a.jabatan_level)

  return list
})

// Tanggal penyelesaian akhir disposisi untuk footer "Kembali ke TU Tanggal"
const tanggalSelesaiAkhir = computed(() => {
  if (!props.disposisi) return ''
  // Cari tanggal selesai paling akhir
  let latest = props.disposisi.updated_at || props.disposisi.tanggal_disposisi
  if (props.disposisi.penerima) {
    for (const p of props.disposisi.penerima) {
      if (p.tanggal_selesai && p.tanggal_selesai > latest) {
        latest = p.tanggal_selesai
      }
    }
  }
  return latest
})

// Fungsi print cetak
const handlePrint = () => {
  window.print()
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    modal
    :style="{ width: '880px', maxWidth: '95vw' }"
    :closable="true"
    class="lembar-disposisi-dialog"
  >
    <template #header>
      <div class="flex items-center justify-between w-full pr-4">
        <div class="flex items-center gap-2.5">
          <i class="pi pi-file-check text-brandGreen text-xl"></i>
          <div>
            <h3 class="text-base font-bold text-textMain m-0">Lembar Disposisi Selesai</h3>
            <p class="text-xs text-textMuted m-0">Format resmi Berkas Disposisi Rumah Sakit Islam Gondanglegi</p>
          </div>
        </div>
        <div class="flex items-center gap-2 no-print">
          <Button
            label="Cetak Lembar Disposisi"
            icon="pi pi-print"
            class="btn-gradient !text-xs !py-1.5 !px-3"
            @click="handlePrint"
          />
        </div>
      </div>
    </template>

    <!-- AREA DOKUMEN CETAK LEMBAR DISPOSISI -->
    <div id="lembar-disposisi-print-area" class="lembar-disposisi-paper mx-auto my-1">
      <!-- KOP SURAT RESMI RSI GONDANGLEGI -->
      <div class="kop-container relative flex items-center justify-between px-6 py-3 border-b-2 border-slate-800">
        <!-- Logo RSI -->
        <div class="logo-wrapper w-20 h-20 flex items-center justify-center shrink-0">
          <img
            :src="logoRsi"
            alt="Logo RSI Gondanglegi"
            class="w-18 h-18 object-contain"
          />
        </div>

        <!-- Teks Kop Surat -->
        <div class="kop-text flex-1 text-center px-2">
          <h2 class="text-[15px] font-extrabold tracking-wide uppercase text-slate-900 leading-tight m-0">
            YAYASAN RUMAH SAKIT ISLAM GONDANGLEGI
          </h2>
          <h3 class="text-[13px] font-bold tracking-wider uppercase text-slate-900 leading-tight mt-0.5 m-0">
            BIDANG KESEKRETARIATAN
          </h3>
          <h4 class="text-[14px] font-extrabold tracking-wider uppercase text-slate-900 leading-tight mt-0.5 m-0">
            SEKSI TATA USAHA & HUKUM
          </h4>
          <p class="text-[10px] text-slate-700 leading-snug mt-1 m-0">
            Jl. Hayam Wuruk No. 122 Telp. (0341) 875129
          </p>
          <div class="text-[9.5px] text-slate-700 flex items-center justify-center gap-2 mt-0.5 flex-wrap">
            <span>Email : <strong>rsigondanglegi@gmail.com</strong></span>
            <span>•</span>
            <span>Website : <strong>www.rsigondanglegi.com</strong></span>
            <span>•</span>
            <span>WA : <strong>0887 7065 5458</strong></span>
          </div>
        </div>

        <!-- Space holder penyeimbang logo di kanan -->
        <div class="w-16 shrink-0 hidden sm:block"></div>
      </div>

      <!-- Garis Ganda Pembatas Kop -->
      <div class="border-b-[3px] border-double border-slate-900 mb-3"></div>

      <!-- JUDUL LEMBAR DISPOSISI -->
      <div class="text-center px-4 my-3">
        <h1 class="text-[16px] sm:text-[17px] font-black tracking-wide text-slate-900 uppercase leading-snug m-0">
          LEMBAR DISPOSISI {{ (disposisi.perihal || 'SURAT MASUK').toUpperCase() }}
        </h1>
      </div>

      <!-- INFO TANGGAL & AGENDA NO -->
      <div class="flex items-center justify-between px-4 py-1.5 text-xs font-semibold text-slate-900 border-b border-t border-slate-700 my-2">
        <div class="flex items-center gap-1.5">
          <span>Tanggal :</span>
          <span class="font-normal">{{ formatDateIndo(disposisi.tanggal_surat || disposisi.tanggal_disposisi) }}</span>
        </div>
        <div class="flex items-center gap-1.5">
          <span>Agenda No. :</span>
          <span class="font-bold">{{ disposisi.nomor_agenda || '—' }}</span>
        </div>
      </div>

      <!-- BADAN TABEL DISPOSISI BERJENJANG -->
      <div class="grid grid-cols-12 border border-slate-800 my-3">
        <!-- AREA KIRI: HIERARKI JABATAN & CATATAN PROGRESS SELESAI (9 Kolom) -->
        <div class="col-span-8 sm:col-span-9 divide-y divide-slate-800 border-r border-slate-800">
          <!-- Loop Jenjang Jabatan -->
          <div
            v-for="(item, idx) in hierarchicalLevels"
            :key="item.id"
            class="p-2.5 min-h-[105px] flex flex-col justify-between"
          >
            <div>
              <!-- Judul Jabatan & Nama Pejabat -->
              <div class="flex items-center justify-between">
                <span class="font-bold text-[13px] text-slate-900">
                  {{ idx + 1 }}. {{ item.display_jabatan }}
                </span>
                <span class="text-[11px] font-medium text-slate-700 italic">
                  ({{ item.nama_lengkap }})
                </span>
              </div>

              <!-- Isi Catatan Progress Selesai -->
              <div class="mt-1.5 pl-3 pr-1 text-[12px] text-slate-800">
                <!-- Jika ini Direktur dan ada catatan awal -->
                <div v-if="item.jabatan_level >= 5 && disposisi.catatan_direktur && disposisi.catatan_direktur !== item.selesai_note" class="mb-1 text-[11px] text-slate-600 italic">
                  <strong>Instruksi Awal:</strong> {{ disposisi.catatan_direktur }}
                </div>

                <!-- Catatan progress yang statusnya SELESAI -->
                <div v-if="item.selesai_note" class="font-medium leading-relaxed bg-white/50 p-1 rounded border border-slate-200">
                  <span class="text-slate-900">{{ item.selesai_note }}</span>
                </div>
                <div v-else-if="item.is_done" class="text-slate-600 italic text-[11px]">
                  ✓ Tugas telah diselesaikan dan divalidasi.
                </div>
                <div v-else class="text-slate-400 italic text-[11px]">
                  — Menunggu giliran alur validasi berjenjang —
                </div>
              </div>
            </div>

            <!-- Ruled Lines Khas Lembar Disposisi Kertas Fisik -->
            <div class="mt-2 space-y-1.5 opacity-40">
              <div class="border-b border-dashed border-slate-400"></div>
              <div class="border-b border-dashed border-slate-400"></div>
            </div>
          </div>

          <!-- Tambahan baris kosong bergaris jika hierarki kurang dari 5 baris (agar sesuai format 5 tingkat) -->
          <template v-if="hierarchicalLevels.length < 5">
            <div
              v-for="emptyIdx in (5 - hierarchicalLevels.length)"
              :key="'empty-' + emptyIdx"
              class="p-2.5 min-h-[90px] flex flex-col justify-between"
            >
              <div class="font-bold text-[13px] text-slate-900">
                {{ hierarchicalLevels.length + emptyIdx }}.
              </div>
              <div class="mt-auto space-y-2 opacity-35">
                <div class="border-b border-dashed border-slate-400"></div>
                <div class="border-b border-dashed border-slate-400"></div>
              </div>
            </div>
          </template>
        </div>

        <!-- AREA KANAN: TABEL TGL & PARAF (3 atau 4 Kolom) -->
        <div class="col-span-4 sm:col-span-3 flex flex-col">
          <!-- Header Tabel Kolom Kanan -->
          <div class="grid grid-cols-2 text-center text-xs font-bold uppercase text-slate-900 border-b border-slate-800 bg-slate-100/50 py-1">
            <div class="border-r border-slate-800 italic">Tgl</div>
            <div class="italic">Paraf</div>
          </div>

          <!-- Baris Tanggal & Paraf per Jabatan -->
          <div class="flex-1 flex flex-col divide-y divide-slate-800">
            <div
              v-for="item in hierarchicalLevels"
              :key="'paraf-' + item.id"
              class="grid grid-cols-2 min-h-[105px] text-center"
            >
              <!-- Kolom Tanggal -->
              <div class="border-r border-slate-800 p-1.5 flex flex-col items-center justify-center text-[10.5px] font-semibold text-slate-800">
                <span v-if="item.selesai_date">{{ formatDateShort(item.selesai_date) }}</span>
                <span v-if="item.selesai_date" class="text-[9px] text-slate-500">{{ formatTimeShort(item.selesai_date) }}</span>
                <span v-else class="text-slate-300">...</span>
              </div>

              <!-- Kolom Paraf Digital -->
              <div class="p-1 flex flex-col items-center justify-center">
                <template v-if="item.is_done">
                  <div class="paraf-stamp border border-emerald-600 rounded p-1 text-center bg-white/70 shadow-2xs">
                    <div class="text-[8.5px] font-extrabold text-emerald-800 tracking-tighter uppercase leading-none">
                      VALIDATED
                    </div>
                    <div class="text-[9px] font-bold text-slate-900 truncate max-w-[85px] mt-0.5 leading-tight">
                      {{ item.nama_lengkap }}
                    </div>
                    <div class="text-[7.5px] text-emerald-700 font-mono leading-none mt-0.5">
                      DISP-RSI
                    </div>
                  </div>
                </template>
                <template v-else>
                  <div class="text-[10px] text-slate-300 italic">
                    Paraf
                  </div>
                </template>
              </div>
            </div>

            <!-- Baris Kosong Kolom Kanan untuk pelengkap 5 slot -->
            <template v-if="hierarchicalLevels.length < 5">
              <div
                v-for="emptyP in (5 - hierarchicalLevels.length)"
                :key="'empty-paraf-' + emptyP"
                class="grid grid-cols-2 min-h-[90px] text-center"
              >
                <div class="border-r border-slate-800 p-1 flex items-center justify-center text-[10px] text-slate-300">...</div>
                <div class="p-1 flex items-center justify-center text-[10px] text-slate-300">...</div>
              </div>
            </template>
          </div>
        </div>
      </div>

      <!-- FOOTER DOKUMEN -->
      <div class="border-t-[2px] border-slate-800 pt-2 px-2 mt-2">
        <div class="flex items-center justify-between text-xs text-slate-900 font-semibold">
          <div>
            Kembali ke TU Tanggal : <span class="font-normal">{{ formatDateIndo(tanggalSelesaiAkhir) }}</span>
          </div>
          <div class="text-[10px] font-mono text-slate-500">
            No. Disposisi: {{ disposisi.nomor_disposisi }}
          </div>
        </div>
        <p class="text-[10px] text-slate-700 italic mt-1.5 m-0">
          * Berkas Asli terkait {{ disposisi.perihal || 'permohonan' }} disimpan di Seksi Tata Usaha & Arsip Digital SiDispo
        </p>
      </div>
    </div>

    <!-- FOOTER DIALOG (NO PRINT) -->
    <template #footer>
      <div class="flex justify-between items-center w-full pt-2 border-t border-border no-print">
        <div class="text-xs text-textMuted flex items-center gap-2">
          <i class="pi pi-check-circle text-brandGreen"></i>
          <span>Status Disposisi: <strong class="text-brandGreen">{{ disposisi.status_global || 'SELESAI' }}</strong></span>
        </div>
        <div class="flex gap-2">
          <Button label="Tutup" severity="secondary" text @click="closeDialog" />
          <Button label="Cetak Dokumen" icon="pi pi-print" class="btn-gradient" @click="handlePrint" />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
/* ── Styling Kertas Lembar Disposisi Sesuai Gambar Asli ── */
.lembar-disposisi-paper {
  background-color: #dbeafe; /* Biru muda lembut khas lembar disposisi rumah sakit */
  color: #0f172a;
  font-family: 'Plus Jakarta Sans', 'Segoe UI', Arial, sans-serif;
  padding: 1.5rem 1.75rem;
  border: 1px solid #1e293b;
  border-radius: 6px;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.08);
}

/* ── Aturan Cetak (Print Media) ── */
@media print {
  /* Sembunyikan semua elemen aplikasi di layar */
  body * {
    visibility: hidden;
  }

  /* Tampilkan hanya area lembar disposisi */
  #lembar-disposisi-print-area,
  #lembar-disposisi-print-area * {
    visibility: visible;
  }

  #lembar-disposisi-print-area {
    position: fixed;
    left: 0;
    top: 0;
    width: 100vw;
    height: 100vh;
    margin: 0;
    padding: 12mm 15mm;
    background-color: #dbeafe !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    box-shadow: none !important;
    border: none !important;
    z-index: 999999;
  }

  .no-print {
    display: none !important;
  }
}
</style>

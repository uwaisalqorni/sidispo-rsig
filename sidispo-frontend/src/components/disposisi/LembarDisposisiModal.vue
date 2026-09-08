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

// Mode Layar Penuh (Fullscreen) & Zooming Dokumen
const isFullscreen = ref(false)
const zoomLevel = ref(100)

const zoomIn = () => {
  if (zoomLevel.value < 150) zoomLevel.value += 10
}

const zoomOut = () => {
  if (zoomLevel.value > 60) zoomLevel.value -= 10
}

const resetZoom = () => {
  zoomLevel.value = 100
}

const toggleFullscreen = () => {
  isFullscreen.value = !isFullscreen.value
}

const dialogStyle = computed(() => {
  if (isFullscreen.value) {
    return {
      width: '100vw',
      height: '100vh',
      maxWidth: '100vw',
      maxHeight: '100vh',
      margin: '0',
      borderRadius: '0'
    }
  }
  return {
    width: '920px',
    maxWidth: '96vw',
    maxHeight: '94vh'
  }
})

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

// Fungsi cetak dokumen yang rapi, pas 1 halaman A4, dan anti-terpotong
const handlePrint = () => {
  const printContent = document.getElementById('lembar-disposisi-print-area')
  if (!printContent) {
    window.print()
    return
  }

  const clone = printContent.cloneNode(true)
  clone.style.transform = 'none'

  // Buat iframe tersembunyi untuk mencetak secara terisolasi tanpa terpengaruh modal/CSS app
  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  document.body.appendChild(iframe)

  const doc = iframe.contentWindow.document
  doc.open()
  doc.write(`
    <!DOCTYPE html>
    <html lang="id">
      <head>
        <meta charset="UTF-8">
        <title>Lembar Disposisi - ${props.disposisi?.nomor_disposisi || 'RSI Gondanglegi'}</title>
        <style>
          @page {
            size: A4 portrait;
            margin: 6mm 8mm 6mm 8mm;
          }
          * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
          }
          body {
            font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif;
            background-color: #ffffff;
            color: #0f172a;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }
          .lembar-disposisi-paper {
            background-color: #dbeafe !important;
            width: 100%;
            max-width: 194mm;
            margin: 0 auto;
            padding: 10px 14px;
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }
          .kop-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 6px;
            border-bottom: 2px solid #0f172a;
          }
          .logo-wrapper {
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
          }
          .logo-wrapper img {
            width: 54px;
            height: 54px;
            object-fit: contain;
          }
          .kop-text {
            flex: 1;
            text-align: center;
            padding: 0 8px;
          }
          .kop-text h2 {
            font-size: 13px;
            font-weight: 900;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.2;
          }
          .kop-text h3 {
            font-size: 11.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.2;
            margin-top: 1px;
          }
          .kop-text h4 {
            font-size: 12px;
            font-weight: 900;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.2;
            margin-top: 1px;
          }
          .kop-text p {
            font-size: 9px;
            color: #334155;
            margin-top: 2px;
            line-height: 1.2;
          }
          .kop-meta {
            font-size: 8.5px;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            margin-top: 2px;
          }
          .double-line {
            border-bottom: 3px double #0f172a;
            margin-bottom: 6px;
          }
          .title-section {
            text-align: center;
            margin: 6px 0;
          }
          .title-section h1 {
            font-size: 13.5px;
            font-weight: 900;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.3;
          }
          .info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10.5px;
            font-weight: 700;
            border-top: 1px solid #0f172a;
            border-bottom: 1px solid #0f172a;
            padding: 3px 6px;
            margin: 4px 0 6px 0;
          }
          .table-disposisi {
            display: grid;
            grid-template-columns: 74% 26%;
            border: 1.5px solid #0f172a;
            margin-bottom: 6px;
          }
          .col-left {
            border-right: 1.5px solid #0f172a;
          }
          .row-item {
            padding: 4px 8px;
            min-height: 72px;
            border-bottom: 1px solid #0f172a;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
          }
          .row-item:last-child {
            border-bottom: none;
          }
          .row-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11.5px;
            font-weight: 800;
          }
          .row-pejabat {
            font-size: 10px;
            font-weight: 600;
            font-style: italic;
            color: #334155;
          }
          .row-content {
            margin-top: 3px;
            padding-left: 6px;
            font-size: 10.5px;
            color: #0f172a;
          }
          .note-box {
            background-color: rgba(255, 255, 255, 0.65);
            padding: 2px 5px;
            border-radius: 3px;
            border: 1px solid #cbd5e1;
            line-height: 1.25;
          }
          .ruled-lines {
            margin-top: 3px;
          }
          .ruled-line {
            border-bottom: 1px dashed #94a3b8;
            margin-top: 3px;
            height: 1px;
            opacity: 0.6;
          }
          .col-right {
            display: flex;
            flex-direction: column;
          }
          .header-paraf {
            display: grid;
            grid-template-columns: 42% 58%;
            border-bottom: 1.5px solid #0f172a;
            text-align: center;
            font-size: 10px;
            font-weight: 800;
            font-style: italic;
            background-color: rgba(241, 245, 249, 0.6);
            padding: 2px 0;
          }
          .header-paraf > div:first-child {
            border-right: 1px solid #0f172a;
          }
          .body-paraf-row {
            display: grid;
            grid-template-columns: 42% 58%;
            min-height: 72px;
            border-bottom: 1px solid #0f172a;
            text-align: center;
          }
          .body-paraf-row:last-child {
            border-bottom: none;
          }
          .cell-tgl {
            border-right: 1px solid #0f172a;
            padding: 4px 2px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 9.5px;
            font-weight: 700;
          }
          .cell-paraf {
            padding: 3px 2px;
            display: flex;
            align-items: center;
            justify-content: center;
          }
          .paraf-badge {
            border: 1px solid #059669;
            border-radius: 4px;
            padding: 2px 4px;
            background-color: rgba(255, 255, 255, 0.85);
            text-align: center;
          }
          .paraf-badge .badge-title {
            font-size: 7.5px;
            font-weight: 900;
            color: #065f46;
            text-transform: uppercase;
            line-height: 1;
          }
          .paraf-badge .badge-name {
            font-size: 8px;
            font-weight: 800;
            color: #0f172a;
            margin-top: 1px;
            line-height: 1.1;
            max-width: 85px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }
          .paraf-badge .badge-disp {
            font-size: 6.5px;
            font-family: monospace;
            color: #047857;
            line-height: 1;
            margin-top: 1px;
            max-width: 85px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
          }
          .footer-section {
            border-top: 1.5px solid #0f172a;
            padding-top: 4px;
            font-size: 10px;
            font-weight: 700;
          }
          .footer-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
          }
          .footer-note {
            font-size: 9px;
            font-style: italic;
            color: #334155;
            margin-top: 3px;
          }
        </style>
      </head>
      <body>
        ${clone.outerHTML}
      </body>
    </html>
  `)
  doc.close()

  // Tunggu gambar & layout siap, lalu buka dialog cetak
  setTimeout(() => {
    iframe.contentWindow.focus()
    iframe.contentWindow.print()
    setTimeout(() => {
      document.body.removeChild(iframe)
    }, 1200)
  }, 350)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    modal
    :maximizable="true"
    :breakpoints="{ '1200px': '94vw', '960px': '96vw', '640px': '98vw' }"
    :style="dialogStyle"
    :closable="true"
    class="lembar-disposisi-dialog"
    :class="{ 'is-fullscreen-modal': isFullscreen }"
  >
    <template #header>
      <div class="flex items-center justify-between w-full pr-2 flex-wrap gap-2">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold shadow-2xs">
            <i class="pi pi-file-check text-lg"></i>
          </div>
          <div>
            <div class="flex items-center gap-2">
              <h3 class="text-base font-bold text-slate-800 dark:text-slate-100 m-0">Lembar Disposisi Selesai</h3>
              <span class="text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded border border-emerald-300">RESMI</span>
            </div>
            <p class="text-xs text-slate-500 m-0">Format Formulir Dinas RSI Gondanglegi (Siap Cetak A4)</p>
          </div>
        </div>

        <!-- Action Toolbar -->
        <div class="flex items-center gap-1.5 sm:gap-2 no-print">
          <!-- Zoom Controls -->
          <div class="flex items-center bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg p-0.5 text-xs">
            <button 
              type="button" 
              class="w-7 h-7 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded transition-colors disabled:opacity-40" 
              :disabled="zoomLevel <= 60"
              @click="zoomOut"
              title="Perkecil (-)"
            >
              <i class="pi pi-minus text-[10px]"></i>
            </button>
            <button 
              type="button" 
              class="px-2 h-7 flex items-center justify-center font-mono font-bold text-[11px] text-slate-700 dark:text-slate-200 hover:bg-white dark:hover:bg-slate-700 rounded transition-colors"
              @click="resetZoom"
              title="Reset 100%"
            >
              {{ zoomLevel }}%
            </button>
            <button 
              type="button" 
              class="w-7 h-7 flex items-center justify-center text-slate-600 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700 rounded transition-colors disabled:opacity-40" 
              :disabled="zoomLevel >= 150"
              @click="zoomIn"
              title="Perbesar (+)"
            >
              <i class="pi pi-plus text-[10px]"></i>
            </button>
          </div>

          <!-- Fullscreen Toggle -->
          <button
            type="button"
            class="h-8 px-2.5 flex items-center gap-1.5 text-xs font-semibold rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 transition-colors shadow-2xs"
            @click="toggleFullscreen"
            :title="isFullscreen ? 'Kecilkan Tampilan' : 'Tampilan Layar Penuh'"
          >
            <i :class="isFullscreen ? 'pi pi-window-minimize' : 'pi pi-window-maximize'" class="text-xs"></i>
            <span class="hidden md:inline">{{ isFullscreen ? 'Kecilkan' : 'Layar Penuh' }}</span>
          </button>

          <!-- Cetak Button -->
          <Button
            label="Cetak"
            icon="pi pi-print"
            class="btn-gradient !text-xs !py-1.5 !px-3 shadow-sm"
            @click="handlePrint"
          />
        </div>
      </div>
    </template>

    <!-- AREA CANVAS PRATINJAU DOKUMEN -->
    <div class="lembar-disposisi-viewer-canvas">
      <!-- AREA DOKUMEN CETAK LEMBAR DISPOSISI (A4 PROPORTIONAL) -->
      <div 
        id="lembar-disposisi-print-area" 
        class="lembar-disposisi-paper"
        :style="{
          transform: `scale(${zoomLevel / 100})`,
          transformOrigin: 'top center'
        }"
      >
        <!-- KOP SURAT RESMI RSI GONDANGLEGI -->
        <div class="kop-container">
          <!-- Logo RSI -->
          <div class="logo-wrapper">
            <img
              :src="logoRsi"
              alt="Logo RSI Gondanglegi"
            />
          </div>

          <!-- Teks Kop Surat -->
          <div class="kop-text">
            <h2>YAYASAN RUMAH SAKIT ISLAM GONDANGLEGI</h2>
            <h3>BIDANG KESEKRETARIATAN</h3>
            <h4>SEKSI TATA USAHA & HUKUM</h4>
            <p>Jl. Hayam Wuruk No. 122 Telp. (0341) 875129</p>
            <div class="kop-meta">
              <span>Email : <strong>rsigondanglegi@gmail.com</strong></span>
              <span>•</span>
              <span>Website : <strong>www.rsigondanglegi.com</strong></span>
              <span>•</span>
              <span>WA : <strong>0887 7065 5458</strong></span>
            </div>
          </div>

          <!-- Spacer penyeimbang di kanan -->
          <div class="w-14 shrink-0 hidden sm:block"></div>
        </div>

        <!-- Garis Ganda Pembatas Kop -->
        <div class="double-line"></div>

        <!-- JUDUL LEMBAR DISPOSISI -->
        <div class="title-section">
          <h1>
            LEMBAR DISPOSISI {{ (disposisi.perihal || 'SURAT MASUK').toUpperCase() }}
          </h1>
        </div>

        <!-- INFO TANGGAL & AGENDA NO -->
        <div class="info-bar">
          <div>
            <span>Tanggal : </span>
            <span class="font-normal">{{ formatDateIndo(disposisi.tanggal_surat || disposisi.tanggal_disposisi) }}</span>
          </div>
          <div>
            <span>Agenda No. : </span>
            <span class="font-bold font-mono">{{ disposisi.nomor_agenda || '.........' }}</span>
          </div>
        </div>

        <!-- BADAN TABEL DISPOSISI BERJENJANG -->
        <div class="table-disposisi">
          <!-- AREA KIRI: HIERARKI JABATAN & CATATAN PROGRESS SELESAI (74%) -->
          <div class="col-left">
            <!-- Loop Jenjang Jabatan -->
            <div
              v-for="(item, idx) in hierarchicalLevels"
              :key="item.id"
              class="row-item"
            >
              <div>
                <!-- Judul Jabatan & Nama Pejabat -->
                <div class="row-header">
                  <span>{{ idx + 1 }}. {{ item.display_jabatan }}</span>
                  <span class="row-pejabat">({{ item.nama_lengkap }})</span>
                </div>

                <!-- Isi Catatan Progress Selesai -->
                <div class="row-content">
                  <!-- Jika ini Direktur dan ada catatan awal -->
                  <div v-if="item.jabatan_level >= 5 && disposisi.catatan_direktur && disposisi.catatan_direktur !== item.selesai_note" class="mb-1 text-[9.5px] text-slate-700 italic">
                    <strong>Instruksi Awal:</strong> {{ disposisi.catatan_direktur }}
                  </div>

                  <!-- Catatan progress yang statusnya SELESAI -->
                  <div v-if="item.selesai_note" class="note-box">
                    <span>{{ item.selesai_note }}</span>
                  </div>
                  <div v-else-if="item.is_done" class="text-slate-600 italic text-[9.5px]">
                    ✓ Tugas telah diselesaikan dan divalidasi.
                  </div>
                  <div v-else class="text-slate-400 italic text-[9.5px]">
                    — Menunggu giliran alur validasi berjenjang —
                  </div>
                </div>
              </div>

              <!-- Ruled Lines Khas Lembar Disposisi Kertas Fisik -->
              <div class="ruled-lines">
                <div class="ruled-line"></div>
                <div class="ruled-line"></div>
              </div>
            </div>

            <!-- Tambahan baris kosong bergaris jika hierarki kurang dari 5 baris (agar presisi format 5 tingkat) -->
            <template v-if="hierarchicalLevels.length < 5">
              <div
                v-for="emptyIdx in (5 - hierarchicalLevels.length)"
                :key="'empty-' + emptyIdx"
                class="row-item"
              >
                <div class="row-header">
                  <span>{{ hierarchicalLevels.length + emptyIdx }}.</span>
                </div>
                <div class="ruled-lines mt-auto">
                  <div class="ruled-line"></div>
                  <div class="ruled-line"></div>
                </div>
              </div>
            </template>
          </div>

          <!-- AREA KANAN: TABEL TGL & PARAF (26%) -->
          <div class="col-right">
            <!-- Header Tabel Kolom Kanan -->
            <div class="header-paraf">
              <div>Tgl</div>
              <div>Paraf</div>
            </div>

            <!-- Baris Tanggal & Paraf per Jabatan -->
            <div class="flex-1 flex flex-col">
              <div
                v-for="item in hierarchicalLevels"
                :key="'paraf-' + item.id"
                class="body-paraf-row flex-1"
              >
                <!-- Kolom Tanggal Selesai -->
                <div class="cell-tgl">
                  <span v-if="item.selesai_date">{{ formatDateShort(item.selesai_date) }}</span>
                  <span v-if="item.selesai_date" class="text-[8px] text-slate-600 mt-0.5">{{ formatTimeShort(item.selesai_date) }}</span>
                  <span v-else class="text-slate-400">...</span>
                </div>

                <!-- Kolom Paraf Digital -->
                <div class="cell-paraf">
                  <template v-if="item.is_done">
                    <div class="paraf-badge">
                      <div class="badge-title">VALIDATED</div>
                      <div class="badge-name">{{ item.nama_lengkap }}</div>
                      <div class="badge-disp">NIP {{ item.nip || '-' }}</div>
                    </div>
                  </template>
                  <template v-else>
                    <span class="text-[9px] text-slate-400 italic">Paraf</span>
                  </template>
                </div>
              </div>

              <!-- Baris Kosong Kolom Kanan untuk pelengkap 5 slot -->
              <template v-if="hierarchicalLevels.length < 5">
                <div
                  v-for="emptyP in (5 - hierarchicalLevels.length)"
                  :key="'empty-paraf-' + emptyP"
                  class="body-paraf-row flex-1"
                >
                  <div class="cell-tgl text-slate-300">...</div>
                  <div class="cell-paraf text-slate-300 text-[9px]">...</div>
                </div>
              </template>
            </div>
          </div>
        </div>

        <!-- FOOTER DOKUMEN -->
        <div class="footer-section">
          <div class="footer-meta">
            <div>
              Kembali ke TU Tanggal : <span class="font-normal">{{ formatDateIndo(tanggalSelesaiAkhir) }}</span>
            </div>
            <div class="text-[9.5px] font-mono text-slate-600">
              No. Disposisi: <strong>{{ disposisi.nomor_disposisi }}</strong>
            </div>
          </div>
          <p class="footer-note">
            * Berkas Asli terkait {{ disposisi.perihal || 'permohonan' }} disimpan di Seksi Tata Usaha & Arsip Digital SiDispo
          </p>
        </div>
      </div>
    </div>

    <!-- FOOTER DIALOG (NO PRINT) -->
    <template #footer>
      <div class="flex justify-between items-center w-full px-2 py-1 border-t border-slate-200 dark:border-slate-700 no-print">
        <div class="text-xs text-slate-500 flex items-center gap-2">
          <i class="pi pi-check-circle text-emerald-600 text-sm"></i>
          <span>Status: <strong class="text-emerald-700 uppercase">{{ disposisi.status_global || 'SELESAI' }}</strong></span>
          <span class="text-slate-300 hidden sm:inline">•</span>
          <span class="text-slate-500 text-[11px] hidden sm:inline">No. Agenda: <strong>{{ disposisi.nomor_agenda || '-' }}</strong></span>
        </div>
        <div class="flex items-center gap-2">
          <Button label="Tutup" severity="secondary" text class="!text-xs" @click="closeDialog" />
          <Button label="Cetak Dokumen" icon="pi pi-print" class="btn-gradient !text-xs !py-1.5 !px-3.5" @click="handlePrint" />
        </div>
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
/* ── Container Canvas Pratinjau Dokumen ── */
.lembar-disposisi-viewer-canvas {
  background-color: #f1f5f9;
  background-image: radial-gradient(#cbd5e1 1.2px, transparent 1.2px);
  background-size: 18px 18px;
  min-height: calc(84vh - 120px);
  padding: 1.5rem 1rem 3rem 1rem;
  display: flex;
  justify-content: center;
  align-items: flex-start;
  overflow-y: auto;
  overflow-x: auto;
  transition: all 0.2s ease;
}

.is-fullscreen-modal .lembar-disposisi-viewer-canvas {
  min-height: calc(100vh - 125px);
  padding: 2.25rem 1rem 5rem 1rem;
}

:deep(.lembar-disposisi-dialog .p-dialog-content) {
  padding: 0 !important;
  background-color: #f1f5f9 !important;
  overflow-y: auto !important;
}

:deep(.lembar-disposisi-dialog.p-dialog-maximized .p-dialog-content) {
  height: calc(100vh - 125px) !important;
}

:deep(.lembar-disposisi-dialog .p-dialog-header) {
  padding: 0.75rem 1.25rem !important;
  border-bottom: 1px solid #e2e8f0 !important;
  background-color: #ffffff !important;
}

:deep(.lembar-disposisi-dialog .p-dialog-footer) {
  padding: 0.65rem 1.25rem !important;
  border-top: 1px solid #e2e8f0 !important;
  background-color: #ffffff !important;
}

/* ── Styling Kertas Lembar Disposisi Sesuai Gambar Asli (A4 Proportional) ── */
.lembar-disposisi-paper {
  background-color: #dbeafe; /* Biru muda lembut khas lembar disposisi rumah sakit */
  color: #0f172a;
  font-family: 'Plus Jakarta Sans', Arial, Helvetica, sans-serif;
  width: 100%;
  max-width: 820px;
  margin: 0 auto;
  padding: 1.25rem 1.5rem;
  border: 1.5px solid #0f172a;
  border-radius: 4px;
  box-shadow: 0 12px 28px -5px rgba(15, 23, 42, 0.16), 0 8px 10px -6px rgba(15, 23, 42, 0.08);
  transition: transform 0.12s ease-out;
}

.kop-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 6px;
  border-bottom: 2px solid #0f172a;
}

.logo-wrapper {
  width: 58px;
  height: 58px;
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.logo-wrapper img {
  width: 52px;
  height: 52px;
  object-fit: contain;
}

.kop-text {
  flex: 1;
  text-align: center;
  padding: 0 8px;
}

.kop-text h2 {
  font-size: 13px;
  font-weight: 900;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  line-height: 1.2;
  margin: 0;
}

.kop-text h3 {
  font-size: 11.5px;
  font-weight: 800;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  line-height: 1.2;
  margin: 1px 0 0 0;
}

.kop-text h4 {
  font-size: 12px;
  font-weight: 900;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  line-height: 1.2;
  margin: 1px 0 0 0;
}

.kop-text p {
  font-size: 9px;
  color: #334155;
  margin: 2px 0 0 0;
  line-height: 1.2;
}

.kop-meta {
  font-size: 8.5px;
  color: #334155;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 2px;
}

.double-line {
  border-bottom: 3px double #0f172a;
  margin-bottom: 6px;
}

.title-section {
  text-align: center;
  margin: 6px 0;
}

.title-section h1 {
  font-size: 13.5px;
  font-weight: 900;
  letter-spacing: 0.5px;
  text-transform: uppercase;
  line-height: 1.3;
  margin: 0;
}

.info-bar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 10.5px;
  font-weight: 700;
  border-top: 1px solid #0f172a;
  border-bottom: 1px solid #0f172a;
  padding: 3px 6px;
  margin: 4px 0 6px 0;
}

.table-disposisi {
  display: grid;
  grid-template-columns: 74% 26%;
  border: 1.5px solid #0f172a;
  margin-bottom: 6px;
}

.col-left {
  border-right: 1.5px solid #0f172a;
}

.row-item {
  padding: 5px 8px;
  min-height: 74px;
  border-bottom: 1px solid #0f172a;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
}

.row-item:last-child {
  border-bottom: none;
}

.row-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 11.5px;
  font-weight: 800;
}

.row-pejabat {
  font-size: 10px;
  font-weight: 600;
  font-style: italic;
  color: #334155;
}

.row-content {
  margin-top: 3px;
  padding-left: 6px;
  font-size: 10.5px;
  color: #0f172a;
}

.note-box {
  background-color: rgba(255, 255, 255, 0.65);
  padding: 2px 5px;
  border-radius: 3px;
  border: 1px solid #cbd5e1;
  line-height: 1.25;
}

.ruled-lines {
  margin-top: 4px;
}

.ruled-line {
  border-bottom: 1px dashed #94a3b8;
  margin-top: 3px;
  height: 1px;
  opacity: 0.6;
}

.col-right {
  display: flex;
  flex-direction: column;
}

.header-paraf {
  display: grid;
  grid-template-columns: 42% 58%;
  border-bottom: 1.5px solid #0f172a;
  text-align: center;
  font-size: 10px;
  font-weight: 800;
  font-style: italic;
  background-color: rgba(241, 245, 249, 0.6);
  padding: 2px 0;
}

.header-paraf > div:first-child {
  border-right: 1px solid #0f172a;
}

.body-paraf-row {
  display: grid;
  grid-template-columns: 42% 58%;
  min-height: 74px;
  border-bottom: 1px solid #0f172a;
  text-align: center;
}

.body-paraf-row:last-child {
  border-bottom: none;
}

.cell-tgl {
  border-right: 1px solid #0f172a;
  padding: 4px 2px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-size: 9.5px;
  font-weight: 700;
}

.cell-paraf {
  padding: 3px 2px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.paraf-badge {
  border: 1px solid #059669;
  border-radius: 4px;
  padding: 2px 4px;
  background-color: rgba(255, 255, 255, 0.85);
  text-align: center;
}

.paraf-badge .badge-title {
  font-size: 7.5px;
  font-weight: 900;
  color: #065f46;
  text-transform: uppercase;
  line-height: 1;
}

.paraf-badge .badge-name {
  font-size: 8px;
  font-weight: 800;
  color: #0f172a;
  margin-top: 1px;
  line-height: 1.1;
  max-width: 85px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.paraf-badge .badge-disp {
  font-size: 6.5px;
  font-family: monospace;
  color: #047857;
  line-height: 1;
  margin-top: 1px;
  max-width: 85px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.footer-section {
  border-top: 1.5px solid #0f172a;
  padding-top: 4px;
  font-size: 10px;
  font-weight: 700;
}

.footer-meta {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.footer-note {
  font-size: 9px;
  font-style: italic;
  color: #334155;
  margin-top: 3px;
}

/* ── Aturan Cetak (Fallback jika menggunakan Ctrl+P langsung) ── */
@media print {
  @page {
    size: A4 portrait;
    margin: 6mm 8mm 6mm 8mm;
  }

  html, body, #app, .p-dialog-mask, .p-dialog, .p-dialog-content {
    overflow: visible !important;
    height: auto !important;
    max-height: none !important;
    position: static !important;
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
  }

  body * {
    visibility: hidden;
  }

  #lembar-disposisi-print-area,
  #lembar-disposisi-print-area * {
    visibility: visible;
  }

  #lembar-disposisi-print-area {
    position: absolute !important;
    left: 0 !important;
    top: 0 !important;
    width: 100% !important;
    max-width: 194mm !important;
    margin: 0 auto !important;
    padding: 10px 14px !important;
    background-color: #dbeafe !important;
    -webkit-print-color-adjust: exact !important;
    print-color-adjust: exact !important;
    box-shadow: none !important;
    border: 1.5px solid #0f172a !important;
  }

  .no-print,
  .p-dialog-header,
  .p-dialog-footer {
    display: none !important;
  }
}
</style>

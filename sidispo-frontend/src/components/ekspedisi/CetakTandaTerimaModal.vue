<script setup>
import { ref, watch, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import QRCode from 'qrcode'
import logoRsi from '@/assets/logorsi.png'

const props = defineProps({
  visible: { type: Boolean, default: false },
  ekspedisi: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['update:visible'])

const qrDataUrl = ref('')
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

const generateQr = async () => {
  if (props.ekspedisi?.nomor_ekspedisi) {
    try {
      qrDataUrl.value = await QRCode.toDataURL(props.ekspedisi.nomor_ekspedisi, {
        width: 140,
        margin: 1,
        color: {
          dark: '#0f172a',
          light: '#ffffff'
        }
      })
    } catch (e) {
      console.error('Error generating QR', e)
    }
  }
}

watch(() => props.visible, (val) => {
  if (val) {
    generateQr()
  }
})

watch(() => props.ekspedisi, () => {
  if (props.visible) {
    generateQr()
  }
}, { deep: true })

const formatDateIndo = (val) => {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'long',
      year: 'numeric'
    })
  } catch {
    return val
  }
}

const formatDateTime = (val) => {
  if (!val) return '-'
  try {
    const d = new Date(val)
    return d.toLocaleDateString('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    })
  } catch {
    return val
  }
}

const printDateNow = computed(() => {
  const d = new Date()
  return d.toLocaleDateString('id-ID', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }) + ' WIB'
})

const handlePrint = () => {
  const printContent = document.getElementById('lembar-tanda-terima-print-area')
  if (!printContent) return

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
    <html>
      <head>
        <title>Tanda Terima Ekspedisi - ${props.ekspedisi.nomor_ekspedisi || 'SiDispo'}</title>
        <meta charset="utf-8" />
        <style>
          @page {
            size: A4 portrait;
            margin: 10mm 12mm 10mm 12mm;
          }
          * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
          }
          body {
            font-family: 'Arial', 'Helvetica Neue', sans-serif;
            font-size: 10.5px;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background: #fff;
          }
          .tanda-terima-paper {
            width: 100%;
            max-width: 100%;
            margin: 0 auto;
          }
          .kop-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 5px;
          }
          .logo-wrapper {
            width: 60px;
            flex-shrink: 0;
            text-align: center;
          }
          .logo-wrapper img {
            width: 52px;
            height: 52px;
            object-fit: contain;
          }
          .kop-text {
            flex: 1;
            text-align: center;
            padding: 0 10px;
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
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.2;
            margin: 1px 0 0 0;
          }
          .kop-text h4 {
            font-size: 11.5px;
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
          }
          .kop-meta {
            font-size: 8.5px;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 2px;
          }
          .double-line {
            border-bottom: 3px double #0f172a;
            margin-bottom: 8px;
          }
          .doc-title {
            text-align: center;
            margin: 6px 0 10px 0;
          }
          .doc-title h1 {
            font-size: 14px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
            color: #0f172a;
          }
          .doc-title p {
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 1px;
            color: #475569;
            text-transform: uppercase;
            margin: 2px 0 0 0;
          }
          .meta-box {
            border: 1.5px solid #0f172a;
            border-radius: 4px;
            padding: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            background-color: #f8fafc;
          }
          .meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5px;
          }
          .meta-table td {
            padding: 2.5px 4px;
            vertical-align: top;
          }
          .meta-table td.label-col {
            font-weight: 700;
            width: 110px;
            color: #334155;
          }
          .meta-table td.sep-col {
            width: 10px;
            font-weight: 700;
          }
          .qr-box {
            text-align: center;
            flex-shrink: 0;
            padding: 4px 8px;
            border-left: 1px dashed #94a3b8;
          }
          .qr-box img {
            width: 90px;
            height: 90px;
            display: block;
            margin: 0 auto;
          }
          .qr-box .resi-text {
            font-family: monospace;
            font-weight: 900;
            font-size: 9px;
            margin-top: 2px;
            color: #0f172a;
          }
          .tabel-tujuan {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #0f172a;
            margin-bottom: 12px;
            font-size: 10px;
          }
          .tabel-tujuan th {
            background-color: #f1f5f9;
            border: 1px solid #0f172a;
            padding: 6px 4px;
            font-weight: 800;
            text-align: center;
            text-transform: uppercase;
            font-size: 9.5px;
          }
          .tabel-tujuan td {
            border: 1px solid #0f172a;
            padding: 8px 6px;
            vertical-align: middle;
          }
          .catatan-box {
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 9.5px;
            background-color: #fff;
            margin-bottom: 14px;
          }
          .catatan-box .title {
            font-weight: 700;
            color: #334155;
            margin-bottom: 2px;
          }
          .ttd-section {
            display: flex;
            justify-content: space-between;
            margin-top: 18px;
            padding: 0 16px;
          }
          .ttd-box {
            text-align: center;
            width: 200px;
          }
          .ttd-box .ttd-role {
            font-size: 10px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 50px;
          }
          .ttd-box .ttd-name {
            font-size: 10.5px;
            font-weight: 900;
            text-decoration: underline;
            color: #0f172a;
          }
          .ttd-box .ttd-nip {
            font-size: 9px;
            color: #475569;
          }
          .footer-note {
            margin-top: 20px;
            padding-top: 6px;
            border-top: 1px dashed #cbd5e1;
            font-size: 8.5px;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
          }
          .badge-received {
            font-size: 8px;
            font-weight: 800;
            color: #065f46;
            background-color: #d1fae5;
            padding: 2px 4px;
            border-radius: 3px;
            display: inline-block;
          }
        </style>
      </head>
      <body>
        ${printContent.outerHTML}
      </body>
    </html>
  `)
  doc.close()

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
    :style="{ width: '880px', maxWidth: '96vw' }"
    :closable="true"
    class="tanda-terima-dialog"
  >
    <template #header>
      <div class="flex items-center justify-between w-full pr-4 flex-wrap gap-2">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-amber-500/10 text-amber-600 flex items-center justify-center font-bold">
            <i class="pi pi-print text-base"></i>
          </div>
          <div>
            <h3 class="text-base font-bold text-textMain leading-tight">Lembar Tanda Terima Ekspedisi Fisik</h3>
            <p class="text-xs text-textMuted font-mono">{{ ekspedisi?.nomor_ekspedisi || 'Pratinjau Cetak' }}</p>
          </div>
        </div>

        <div class="flex items-center gap-2.5">
          <!-- Zoom Controls -->
          <div class="flex items-center gap-1 bg-surface2 px-2 py-1 rounded-xl border border-border">
            <Button
              icon="pi pi-minus"
              text
              severity="secondary"
              size="small"
              @click="zoomOut"
              :disabled="zoomLevel <= 60"
              class="!w-7 !h-7 !p-0 !rounded-lg"
              v-tooltip.top="'Perkecil'"
            />
            <span class="text-xs font-mono font-bold text-textMain min-w-[42px] text-center">{{ zoomLevel }}%</span>
            <Button
              icon="pi pi-plus"
              text
              severity="secondary"
              size="small"
              @click="zoomIn"
              :disabled="zoomLevel >= 150"
              class="!w-7 !h-7 !p-0 !rounded-lg"
              v-tooltip.top="'Perbesar'"
            />
            <Button
              icon="pi pi-refresh"
              text
              severity="secondary"
              size="small"
              @click="resetZoom"
              class="!w-7 !h-7 !p-0 !rounded-lg"
              v-tooltip.top="'Reset Zoom (100%)'"
            />
          </div>

          <Button
            label="Cetak Sekarang"
            icon="pi pi-print"
            severity="success"
            size="small"
            class="btn-gradient !rounded-xl text-xs font-bold shadow-xs !py-1.5 !px-3"
            @click="handlePrint"
          />
        </div>
      </div>
    </template>

    <!-- AREA CANVAS PRATINJAU -->
    <div class="overflow-y-auto max-h-[75vh] p-3 sm:p-5 bg-slate-100 dark:bg-slate-900/50 rounded-2xl flex justify-center">
      <!-- AREA DOKUMEN CETAK TANDA TERIMA -->
      <div
        id="lembar-tanda-terima-print-area"
        class="tanda-terima-paper bg-white text-slate-900 p-6 sm:p-7 rounded-xl border border-slate-300 shadow-md origin-top transition-transform duration-150"
        :style="{ transform: `scale(${zoomLevel / 100})`, width: '100%', maxWidth: '760px' }"
      >
      <!-- KOP RESMI RSI GONDANGLEGI -->
      <div class="kop-container flex items-center justify-between pb-1">
        <div class="logo-wrapper w-14 shrink-0 flex items-center justify-center">
          <img :src="logoRsi" alt="Logo RSI" class="w-12 h-12 object-contain" />
        </div>
        <div class="kop-text flex-1 text-center px-2">
          <h2 class="text-xs font-black uppercase tracking-wide leading-tight">YAYASAN RUMAH SAKIT ISLAM GONDANGLEGI</h2>
          <h3 class="text-[11px] font-extrabold uppercase tracking-wide leading-tight mt-0.5">BIDANG KESEKRETARIATAN</h3>
          <h4 class="text-[11.5px] font-black uppercase tracking-wide leading-tight mt-0.5">SEKSI TATA USAHA & HUKUM</h4>
          <p class="text-[9px] text-slate-600 mt-0.5">Jl. Hayam Wuruk No. 122 Telp. (0341) 875129 Malang</p>
          <div class="kop-meta text-[8.5px] text-slate-600 flex items-center justify-center gap-2 mt-0.5">
            <span>Email : <strong>rsigondanglegi@gmail.com</strong></span>
            <span>•</span>
            <span>Website : <strong>www.rsigondanglegi.com</strong></span>
          </div>
        </div>
        <div class="w-14 shrink-0 hidden sm:block"></div>
      </div>

      <div class="double-line border-b-[3px] border-double border-slate-900 mb-2"></div>

      <!-- JUDUL DOKUMEN -->
      <div class="doc-title text-center my-2">
        <h1 class="text-sm font-black uppercase tracking-wider text-slate-900">
          LEMBAR TANDA TERIMA EKSPEDISI SURAT
        </h1>
        <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">
          BUKTI SERAH TERIMA DOKUMEN FISIK
        </p>
      </div>

      <!-- BOX METADATA & QR CODE -->
      <div class="meta-box border-[1.5px] border-slate-900 rounded p-2.5 flex items-center justify-between gap-4 mb-3 bg-slate-50">
        <div class="flex-1">
          <table class="meta-table w-full text-xs">
            <tbody>
              <tr>
                <td class="label-col font-bold text-slate-600 w-28 py-0.5">No. Resi Ekspedisi</td>
                <td class="sep-col w-3 font-bold py-0.5">:</td>
                <td class="font-mono font-black text-emerald-800 text-xs py-0.5">{{ ekspedisi.nomor_ekspedisi }}</td>
              </tr>
              <tr>
                <td class="label-col font-bold text-slate-600 py-0.5">Tanggal Ekspedisi</td>
                <td class="sep-col font-bold py-0.5">:</td>
                <td class="py-0.5">{{ formatDateIndo(ekspedisi.tanggal_kirim) }}</td>
              </tr>
              <tr>
                <td class="label-col font-bold text-slate-600 py-0.5">Nomor Surat</td>
                <td class="sep-col font-bold py-0.5">:</td>
                <td class="font-bold py-0.5">{{ ekspedisi.nomor_surat }}</td>
              </tr>
              <tr>
                <td class="label-col font-bold text-slate-600 py-0.5">Asal Surat</td>
                <td class="sep-col font-bold py-0.5">:</td>
                <td class="py-0.5">{{ ekspedisi.asal_surat }}</td>
              </tr>
              <tr>
                <td class="label-col font-bold text-slate-600 py-0.5">Perihal</td>
                <td class="sep-col font-bold py-0.5">:</td>
                <td class="font-semibold py-0.5">{{ ekspedisi.perihal }}</td>
              </tr>
              <tr>
                <td class="label-col font-bold text-slate-600 py-0.5">No. Disposisi / Agenda</td>
                <td class="sep-col font-bold py-0.5">:</td>
                <td class="py-0.5">{{ ekspedisi.nomor_disposisi }} (Agenda: {{ ekspedisi.nomor_agenda || '-' }})</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- QR Code -->
        <div class="qr-box text-center pl-4 border-l border-dashed border-slate-300 shrink-0">
          <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR Code Resi" class="w-20 h-20 mx-auto" />
          <div class="resi-text font-mono font-bold text-[9px] text-slate-800 mt-1">{{ ekspedisi.nomor_ekspedisi }}</div>
          <span class="text-[8px] font-extrabold text-amber-800 bg-amber-100 px-1.5 py-0.5 rounded mt-0.5 inline-block">
            PENGIRIMAN FISIK
          </span>
        </div>
      </div>

      <!-- TABEL DAFTAR SERAH TERIMA UNIT TUJUAN -->
      <table class="tabel-tujuan w-full border-collapse border-[1.5px] border-slate-900 text-xs mb-3">
        <thead>
          <tr class="bg-slate-100 text-[10px] uppercase font-bold text-slate-800">
            <th class="border border-slate-900 p-1.5 text-center w-8">No</th>
            <th class="border border-slate-900 p-1.5 text-left">Penerima / User Tujuan</th>
            <th class="border border-slate-900 p-1.5 text-center w-28">Status</th>
            <th class="border border-slate-900 p-1.5 text-center w-36">Tanggal / Waktu Diterima</th>
            <th class="border border-slate-900 p-1.5 text-left w-44">Nama Penerima Dokumen</th>
            <th class="border border-slate-900 p-1.5 text-center w-28">Paraf / TTD Fisik</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(t, idx) in (ekspedisi.tujuan || [])"
            :key="t.id"
            class="text-xs"
          >
            <td class="border border-slate-900 p-2 text-center font-bold">{{ idx + 1 }}</td>
            <td class="border border-slate-900 p-2">
              <div class="font-bold text-slate-800">{{ t.nama_user_tujuan || t.unit_tujuan }}</div>
              <div class="text-[9px] text-slate-500">
                {{ t.jabatan_user_tujuan || t.unit_tujuan }}
                <span v-if="t.nip_user_tujuan">• NIP. {{ t.nip_user_tujuan }}</span>
              </div>
            </td>
            <td class="border border-slate-900 p-2 text-center">
              <span
                v-if="t.status === 'RECEIVED'"
                class="badge-received text-[9px] font-bold text-emerald-800 bg-emerald-100 px-1.5 py-0.5 rounded"
              >
                DITERIMA
              </span>
              <span
                v-else-if="t.status === 'REJECTED'"
                class="text-[9px] font-bold text-red-800 bg-red-100 px-1.5 py-0.5 rounded"
              >
                DITOLAK
              </span>
              <span
                v-else
                class="text-[9px] font-semibold text-slate-500 italic"
              >
                Menunggu
              </span>
            </td>
            <td class="border border-slate-900 p-2 text-center text-[10.5px]">
              {{ t.received_at ? formatDateTime(t.received_at) : '..................................' }}
            </td>
            <td class="border border-slate-900 p-2 text-[10.5px]">
              <div v-if="t.nama_penerima" class="font-bold text-slate-800">{{ t.nama_penerima }}</div>
              <div v-if="t.nip_penerima" class="text-[9px] text-slate-500">NIP. {{ t.nip_penerima }}</div>
              <span v-if="!t.nama_penerima" class="text-slate-400">................................................</span>
            </td>
            <td class="border border-slate-900 p-2 text-center h-12 align-middle">
              <span v-if="t.status === 'RECEIVED'" class="text-[9px] font-bold text-emerald-700 italic">[ Terverifikasi ]</span>
              <span v-else class="text-slate-300 text-[10px]">Paraf</span>
            </td>
          </tr>
        </tbody>
      </table>

      <!-- CATATAN PENGIRIM JIKA ADA -->
      <div v-if="ekspedisi.catatan" class="catatan-box border border-slate-200 rounded p-2 text-xs bg-slate-50 mb-3">
        <div class="title font-bold text-slate-700 mb-0.5">Catatan Pengirim:</div>
        <div class="text-slate-600 text-[11px]">{{ ekspedisi.catatan }}</div>
      </div>

      <!-- TANDA TANGAN SECTION -->
      <div class="ttd-section flex justify-between items-start mt-4 px-4 text-xs">
        <div class="ttd-box text-center w-52">
          <div class="ttd-role font-bold text-slate-600 mb-12">Petugas Pengirim Ekspedisi,</div>
          <div class="ttd-name font-bold underline text-slate-900">{{ ekspedisi.nama_pengirim || 'Sekretariat' }}</div>
          <div class="ttd-nip text-[9.5px] text-slate-500">NIP. {{ ekspedisi.nip_pengirim || '-' }}</div>
        </div>

        <div class="ttd-box text-center w-52">
          <div class="ttd-role font-bold text-slate-600 mb-1">Mengetahui,</div>
          <div class="text-[10px] text-slate-500 mb-10">Kasubag / Pimpinan Terkait</div>
          <div class="ttd-name font-bold underline text-slate-900">( ............................................ )</div>
          <div class="ttd-nip text-[9.5px] text-slate-500">NIP. ........................................</div>
        </div>
      </div>

      <!-- FOOTER NOTE -->
      <div class="footer-note flex justify-between items-center text-[8.5px] text-slate-400 border-t border-dashed border-slate-300 pt-2 mt-4">
        <div>Dicetak secara otomatis oleh Sistem SiDispo RSI Gondanglegi pada: {{ printDateNow }}</div>
        <div>Dokumen Tanda Terima Ekspedisi Resmi • Halaman 1 dari 1</div>
      </div>
    </div>
  </div>

    <template #footer>
      <div class="flex items-center justify-between w-full pt-3 border-t border-border">
        <div class="text-xs text-textMuted flex items-center gap-1.5">
          <i class="pi pi-info-circle text-amber-500"></i>
          <span>Gunakan ukuran kertas <strong>A4 Portrait</strong> saat mencetak.</span>
        </div>
        <div class="flex items-center gap-2">
          <Button
            label="Tutup"
            icon="pi pi-times"
            text
            severity="secondary"
            class="!rounded-xl text-xs"
            @click="emit('update:visible', false)"
          />
          <Button
            label="Cetak Lembar Tanda Terima"
            icon="pi pi-print"
            severity="success"
            class="btn-gradient !rounded-xl text-xs font-bold shadow-xs !py-2 !px-4"
            @click="handlePrint"
          />
        </div>
      </div>
    </template>
  </Dialog>
</template>

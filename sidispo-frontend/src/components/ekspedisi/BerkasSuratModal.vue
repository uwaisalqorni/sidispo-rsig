<script setup>
import { ref, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'

const props = defineProps({
  visible: { type: Boolean, default: false },
  ekspedisi: { type: Object, default: () => ({}) }
})

const emit = defineEmits(['update:visible', 'openLembarDisposisi'])

const fileUrl = (path) => {
  if (!path) return '#'
  if (path.startsWith('http://') || path.startsWith('https://')) return path
  const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/SiDispo/api/v1')
    .replace('/api/v1', '')
  return base + '/' + path
}

const formatSize = (bytes) => {
  if (!bytes) return ''
  const kb = bytes / 1024
  if (kb < 1024) return kb.toFixed(1) + ' KB'
  return (kb / 1024).toFixed(1) + ' MB'
}

const isPdf = (mime, path = '') => {
  return (mime && mime.includes('pdf')) || path.toLowerCase().endsWith('.pdf')
}

const isImage = (mime, path = '') => {
  return (mime && mime.startsWith('image/')) || /\.(jpg|jpeg|png|webp|gif)$/i.test(path)
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    modal
    :style="{ width: '640px', maxWidth: '96vw' }"
    class="berkas-surat-dialog"
  >
    <template #header>
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center font-bold">
          <i class="pi pi-paperclip text-lg"></i>
        </div>
        <div>
          <h3 class="text-base font-bold text-textMain leading-tight">Berkas & Lampiran Surat</h3>
          <p class="text-xs text-textMuted">Akses dokumen resmi surat masuk dan lembar disposisi</p>
        </div>
      </div>
    </template>

    <div v-if="ekspedisi" class="flex flex-col gap-4 py-1 text-xs">
      <!-- Informasi Surat -->
      <div class="p-4 rounded-2xl bg-surface2/60 border border-border flex flex-col gap-1.5">
        <div class="flex items-center justify-between font-bold text-textMain">
          <span class="truncate">{{ ekspedisi.nomor_surat }}</span>
          <span class="text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded text-[10px] font-mono">
            {{ ekspedisi.nomor_disposisi }}
          </span>
        </div>
        <div class="text-textMain font-medium leading-relaxed">{{ ekspedisi.perihal }}</div>
        <div class="text-textMuted text-[11px] flex items-center gap-2 flex-wrap mt-0.5">
          <span>Asal: <strong class="text-textMain">{{ ekspedisi.asal_surat }}</strong></span>
          <span>•</span>
          <span>No. Agenda: <strong class="text-textMain">{{ ekspedisi.nomor_agenda || '-' }}</strong></span>
          <span>•</span>
          <span>Resi: <strong class="font-mono text-emerald-700 dark:text-emerald-400">{{ ekspedisi.nomor_ekspedisi }}</strong></span>
        </div>
      </div>

      <!-- Akses Lembar Disposisi Selesai -->
      <div class="flex items-center justify-between p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-950 dark:text-emerald-200">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-xl bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 font-bold">
            <i class="pi pi-file-check text-lg"></i>
          </div>
          <div>
            <div class="font-bold text-xs">Lembar Disposisi Resmi</div>
            <div class="text-[11px] text-emerald-800 dark:text-emerald-300">Lihat tanda tangan / paraf digital berjenjang dan catatan disposisi</div>
          </div>
        </div>
        <Button
          label="Buka Lembar Disposisi"
          icon="pi pi-external-link"
          severity="success"
          size="small"
          class="text-xs font-bold whitespace-nowrap shadow-xs !rounded-xl"
          @click="emit('openLembarDisposisi', ekspedisi.disposisi_id)"
        />
      </div>

      <!-- Daftar Berkas Lampiran Dokumen Surat -->
      <div>
        <div class="font-bold text-textMain uppercase tracking-wider text-[11px] mb-2 flex items-center justify-between">
          <span>Berkas Lampiran Surat:</span>
          <span class="text-textMuted font-normal">({{ (ekspedisi.files || []).length }} berkas)</span>
        </div>

        <div v-if="!ekspedisi.files || ekspedisi.files.length === 0" class="p-8 text-center text-xs text-textMuted border border-dashed border-border rounded-2xl bg-surface2/30">
          <i class="pi pi-file text-2xl text-textMuted/60 mb-2"></i>
          <div>Tidak ada berkas file fisik yang diunggah untuk surat ini.</div>
        </div>

        <div v-else class="flex flex-col gap-2">
          <div
            v-for="file in ekspedisi.files"
            :key="file.id"
            class="flex items-center justify-between p-3 rounded-2xl border border-border bg-surface hover:border-blue-500/40 hover:bg-surface2/40 transition-all shadow-2xs group"
          >
            <div class="flex items-center gap-3 min-w-0">
              <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0"
                :class="isPdf(file.mime_type, file.path_file) ? 'bg-red-500/10 text-red-600' : (isImage(file.mime_type, file.path_file) ? 'bg-blue-500/10 text-blue-600' : 'bg-surface2 text-textMuted')">
                <i :class="isPdf(file.mime_type, file.path_file) ? 'pi pi-file-pdf text-lg' : (isImage(file.mime_type, file.path_file) ? 'pi pi-image text-lg' : 'pi pi-file text-lg')"></i>
              </div>
              <div class="min-w-0">
                <div class="font-bold text-textMain text-xs truncate group-hover:text-blue-600 transition-colors">
                  {{ file.nama_asli || file.nama_file || file.path_file }}
                </div>
                <div class="text-[10.5px] text-textMuted flex items-center gap-2 mt-0.5">
                  <span v-if="file.ukuran_bytes">{{ formatSize(file.ukuran_bytes) }}</span>
                  <span v-if="file.ukuran_bytes">•</span>
                  <span>Diunggah: {{ file.created_at ? new Date(file.created_at).toLocaleDateString('id-ID') : '-' }}</span>
                </div>
              </div>
            </div>

            <a
              :href="fileUrl(file.path_file)"
              target="_blank"
              class="px-3 py-1.5 rounded-xl bg-blue-500/10 hover:bg-blue-500/20 text-blue-700 dark:text-blue-400 font-bold text-xs flex items-center gap-1.5 shrink-0 transition-colors no-underline border border-blue-500/20"
            >
              <span>Buka File</span>
              <i class="pi pi-external-link text-[10px]"></i>
            </a>
          </div>
        </div>
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end pt-3 border-t border-border">
        <Button label="Tutup" severity="secondary" text class="!rounded-xl text-xs" @click="emit('update:visible', false)" />
      </div>
    </template>
  </Dialog>
</template>

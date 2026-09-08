<script setup>
import { ref, watch } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import api from '@/api/axios'

const props = defineProps({
  visible: { type: Boolean, default: false },
  targetTujuan: { type: Object, default: null }
})

const emit = defineEmits(['update:visible', 'rejected'])

const alasan = ref('')
const submitting = ref(false)
const errorMessage = ref('')

watch(() => props.visible, (val) => {
  if (val) {
    alasan.value = ''
    errorMessage.value = ''
  }
})

const handleReject = async () => {
  if (!alasan.value.trim()) {
    errorMessage.value = 'Silakan isi alasan penolakan dokumen.'
    return
  }

  submitting.value = true
  errorMessage.value = ''
  try {
    const tujuanId = props.targetTujuan?.ekspedisi_tujuan_id || props.targetTujuan?.id
    await api.put(`/ekspedisi/tolak/${tujuanId}`, {
      alasan_tolak: alasan.value.trim()
    })
    emit('rejected')
    emit('update:visible', false)
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Gagal menolak dokumen ekspedisi.'
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <Dialog
    :visible="visible"
    @update:visible="emit('update:visible', $event)"
    modal
    :style="{ width: '480px', maxWidth: '94vw' }"
    :closable="!submitting"
  >
    <template #header>
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-red-500/10 text-red-600 flex items-center justify-center font-bold">
          <i class="pi pi-times-circle text-lg"></i>
        </div>
        <div>
          <h3 class="text-base font-bold text-textMain leading-tight">Tolak / Kembalikan Ekspedisi</h3>
          <p class="text-xs text-textMuted">Berikan keterangan alasan penolakan dokumen ini</p>
        </div>
      </div>
    </template>

    <div class="flex flex-col gap-3.5 py-1 text-xs">
      <div v-if="targetTujuan" class="p-3.5 rounded-2xl bg-surface2/60 border border-border">
        <div class="font-bold text-textMain truncate">{{ targetTujuan.nomor_surat }}</div>
        <div class="text-textMuted truncate mt-0.5">{{ targetTujuan.perihal }}</div>
        <div class="text-textMuted/80 text-[10.5px] mt-0.5 font-mono">No. Resi: {{ targetTujuan.nomor_ekspedisi }}</div>
      </div>

      <div v-if="errorMessage" class="p-3 bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 rounded-xl">
        {{ errorMessage }}
      </div>

      <div class="flex flex-col gap-1.5">
        <label class="font-bold text-textMain uppercase tracking-wider text-[11px]">
          Alasan Penolakan <span class="text-red-500">*</span>
        </label>
        <Textarea
          v-model="alasan"
          rows="3"
          autoResize
          placeholder="Contoh: Berkas fisik tidak lengkap / salah unit tujuan / lampiran tidak terbaca..."
          class="w-full text-xs !rounded-xl !bg-surface2 !border-border !text-textMain"
          autofocus
        />
        <span class="text-[10.5px] text-textMuted italic">
          Keterangan ini akan dikirimkan kembali ke Admin/Pengirim untuk dilakukan revisi & pengiriman ulang.
        </span>
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
        <Button
          label="Batal"
          text
          severity="secondary"
          class="!rounded-xl text-xs"
          @click="emit('update:visible', false)"
          :disabled="submitting"
        />
        <Button
          label="Tolak & Kembalikan"
          icon="pi pi-times"
          severity="danger"
          class="!rounded-xl text-xs font-bold shadow-xs !py-2 !px-4"
          :loading="submitting"
          @click="handleReject"
        />
      </div>
    </template>
  </Dialog>
</template>

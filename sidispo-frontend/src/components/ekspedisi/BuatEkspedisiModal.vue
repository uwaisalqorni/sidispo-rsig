<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import RadioButton from 'primevue/radiobutton'
import InputText from 'primevue/inputtext'
import api from '@/api/axios'

const props = defineProps({
  visible: { type: Boolean, default: false },
  selectedDisposisi: { type: Object, default: null }
})

const emit = defineEmits(['update:visible', 'saved'])

const loadingUsers = ref(false)
const userOptions = ref([])
const siapKirimList = ref([])
const loadingSiapKirim = ref(false)
const submitting = ref(false)
const errorMessage = ref('')

const todayDateString = () => {
  const d = new Date()
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const form = ref({
  disposisi_id: null,
  jenis_pengiriman: 'DIGITAL',
  user_tujuan: [],
  tanggal_kirim: todayDateString(),
  catatan: ''
})

const chosenDisposisi = computed(() => {
  if (props.selectedDisposisi) return props.selectedDisposisi
  if (!form.value.disposisi_id) return null
  return siapKirimList.value.find(d => d.disposisi_id == form.value.disposisi_id)
})

const loadUsers = async () => {
  loadingUsers.value = true
  try {
    const { data } = await api.get('/ekspedisi/user-options')
    userOptions.value = (data.data || []).map(u => ({
      id: u.id,
      nama_lengkap: u.nama_lengkap,
      nip: u.nip,
      jabatan: u.jabatan_master || u.jabatan,
      unit: u.unit,
      level: u.jabatan_level,
      role: u.role,
      labelSearch: `${u.nama_lengkap} ${u.nip || ''} ${u.jabatan || ''} ${u.unit || ''}`
    }))
  } catch (e) {
    console.error('Gagal memuat daftar user', e)
  } finally {
    loadingUsers.value = false
  }
}

const loadSiapKirim = async () => {
  loadingSiapKirim.value = true
  try {
    const { data } = await api.get('/ekspedisi/siap-kirim')
    siapKirimList.value = data.data || []
  } catch (e) {
    console.error('Gagal memuat siap kirim', e)
  } finally {
    loadingSiapKirim.value = false
  }
}

watch(() => props.visible, (val) => {
  if (val) {
    errorMessage.value = ''
    loadUsers()
    if (props.selectedDisposisi) {
      form.value.disposisi_id = props.selectedDisposisi.disposisi_id || props.selectedDisposisi.id
    } else {
      loadSiapKirim()
      form.value.disposisi_id = null
    }
    form.value.jenis_pengiriman = 'DIGITAL'
    form.value.user_tujuan = []
    form.value.tanggal_kirim = todayDateString()
    form.value.catatan = ''
  }
})

const handleSubmit = async () => {
  errorMessage.value = ''
  if (!form.value.disposisi_id) {
    errorMessage.value = 'Silakan pilih surat / disposisi yang akan diekspedisikan.'
    return
  }
  if (!form.value.user_tujuan || form.value.user_tujuan.length === 0) {
    errorMessage.value = 'Silakan pilih minimal 1 user tujuan ekspedisi.'
    return
  }
  if (!form.value.tanggal_kirim) {
    errorMessage.value = 'Silakan tentukan tanggal kirim ekspedisi.'
    return
  }

  submitting.value = true
  try {
    const payload = {
      disposisi_id: form.value.disposisi_id,
      jenis_pengiriman: form.value.jenis_pengiriman,
      user_tujuan: form.value.user_tujuan,
      tanggal_kirim: form.value.tanggal_kirim,
      catatan: form.value.catatan
    }

    const { data } = await api.post('/ekspedisi', payload)
    emit('saved', data.data)
    emit('update:visible', false)
  } catch (e) {
    errorMessage.value = e.response?.data?.message || 'Gagal menyimpan ekspedisi.'
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
    :style="{ width: '680px', maxWidth: '96vw' }"
    :closable="!submitting"
    class="buat-ekspedisi-dialog"
  >
    <template #header>
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-xl bg-emerald-500/10 text-emerald-600 flex items-center justify-center font-bold">
          <i class="pi pi-send text-lg"></i>
        </div>
        <div>
          <h3 class="text-base font-bold text-textMain leading-tight">Buat Ekspedisi Surat</h3>
          <p class="text-xs text-textMuted">Kirim surat & disposisi selesai ke user / pegawai tujuan</p>
        </div>
      </div>
    </template>

    <div class="flex flex-col gap-4 py-1">
      <!-- Error Message -->
      <div v-if="errorMessage" class="p-3 bg-red-500/10 border border-red-500/20 text-red-600 dark:text-red-400 text-xs rounded-xl flex items-start gap-2">
        <i class="pi pi-exclamation-circle text-base mt-0.5 shrink-0"></i>
        <span>{{ errorMessage }}</span>
      </div>

      <!-- Pilih Disposisi / Surat -->
      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-bold text-textMain uppercase tracking-wider">
          Surat / Disposisi Selesai <span class="text-red-500">*</span>
        </label>
        
        <div v-if="selectedDisposisi" class="p-3.5 rounded-2xl bg-surface2/60 border border-border text-xs flex flex-col gap-1">
          <div class="flex items-center justify-between font-bold text-textMain">
            <span class="truncate">{{ selectedDisposisi.nomor_surat }}</span>
            <span class="text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded text-[10px] font-mono">
              {{ selectedDisposisi.nomor_disposisi }}
            </span>
          </div>
          <div class="text-textMuted font-medium truncate">{{ selectedDisposisi.perihal }}</div>
          <div class="text-textMuted/80 text-[11px] flex items-center gap-2">
            <span>Asal: {{ selectedDisposisi.asal_surat }}</span>
            <span>•</span>
            <span>Agenda: {{ selectedDisposisi.nomor_agenda || '-' }}</span>
          </div>
        </div>

        <Select
          v-else
          v-model="form.disposisi_id"
          :options="siapKirimList"
          optionLabel="perihal"
          optionValue="disposisi_id"
          placeholder="Pilih Surat / Disposisi Selesai..."
          class="w-full text-xs !rounded-xl !bg-surface2"
          :loading="loadingSiapKirim"
          filter
          filterPlaceholder="Cari nomor surat / perihal..."
        >
          <template #value="slotProps">
            <div v-if="chosenDisposisi" class="text-xs truncate">
              <span class="font-bold text-textMain">[{{ chosenDisposisi.nomor_disposisi }}]</span>
              {{ chosenDisposisi.nomor_surat }} - {{ chosenDisposisi.perihal }}
            </div>
            <span v-else class="text-textMuted">{{ slotProps.placeholder }}</span>
          </template>
          <template #option="slotProps">
            <div class="flex flex-col gap-0.5 py-0.5 text-xs">
              <div class="font-bold text-textMain flex items-center justify-between">
                <span>{{ slotProps.option.nomor_surat }}</span>
                <span class="text-[10px] text-emerald-600 bg-emerald-500/10 px-1.5 py-0.5 rounded font-mono">{{ slotProps.option.nomor_disposisi }}</span>
              </div>
              <div class="text-textMuted text-[11px] truncate">{{ slotProps.option.perihal }}</div>
              <div class="text-textMuted/80 text-[10px]">Asal: {{ slotProps.option.asal_surat }}</div>
            </div>
          </template>
        </Select>
      </div>

      <!-- Field Tanggal Kirim & Jenis Pengiriman -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <!-- Tanggal Kirim -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMain uppercase tracking-wider">
            Tanggal Kirim <span class="text-red-500">*</span>
          </label>
          <input
            v-model="form.tanggal_kirim"
            type="date"
            class="w-full px-3 py-2 bg-surface2 border border-border rounded-xl text-xs font-semibold text-textMain focus:outline-none focus:border-emerald-500 transition-colors"
            required
          />
          <span class="text-[10.5px] text-textMuted">Tanggal pengiriman resmi dokumen ekspedisi</span>
        </div>

        <!-- Pilihan Jenis Pengiriman: Digital vs Fisik -->
        <div class="flex flex-col gap-1.5">
          <label class="text-xs font-bold text-textMain uppercase tracking-wider">
            Jenis Pengiriman <span class="text-red-500">*</span>
          </label>
          <div class="grid grid-cols-2 gap-2">
            <label
              class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all"
              :class="form.jenis_pengiriman === 'DIGITAL' 
                ? 'border-emerald-500 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-bold shadow-xs' 
                : 'border-border hover:bg-surface2 text-textMuted'"
            >
              <RadioButton v-model="form.jenis_pengiriman" inputId="jenis-digital" value="DIGITAL" />
              <div class="text-xs font-bold flex items-center gap-1.5">
                <i class="pi pi-desktop text-emerald-600 text-xs"></i>
                <span>Digital</span>
              </div>
            </label>

            <label
              class="flex items-center gap-2 p-2.5 rounded-xl border cursor-pointer transition-all"
              :class="form.jenis_pengiriman === 'FISIK' 
                ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-amber-400 font-bold shadow-xs' 
                : 'border-border hover:bg-surface2 text-textMuted'"
            >
              <RadioButton v-model="form.jenis_pengiriman" inputId="jenis-fisik" value="FISIK" />
              <div class="text-xs font-bold flex items-center gap-1.5">
                <i class="pi pi-box text-amber-600 text-xs"></i>
                <span>Fisik</span>
              </div>
            </label>
          </div>
        </div>
      </div>

      <!-- Pilih User Tujuan (Multi-select seperti Disposisi) -->
      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-bold text-textMain uppercase tracking-wider">
          User Tujuan Ekspedisi <span class="text-red-500">* (Bisa pilih multi-user)</span>
        </label>
        <MultiSelect
          v-model="form.user_tujuan"
          :options="userOptions"
          optionLabel="nama_lengkap"
          optionValue="id"
          placeholder="Pilih Pengguna / Pegawai Penerima..."
          display="chip"
          filter
          filterPlaceholder="Cari nama, NIP, jabatan, unit..."
          :filterFields="['nama_lengkap', 'nip', 'jabatan', 'unit']"
          class="w-full text-xs !rounded-xl !bg-surface2"
          :loading="loadingUsers"
        >
          <template #option="slotProps">
            <div class="flex items-center justify-between gap-2 py-1 w-full text-xs">
              <div class="min-w-0">
                <div class="font-bold text-textMain flex items-center gap-1.5">
                  <span>{{ slotProps.option.nama_lengkap }}</span>
                  <span v-if="slotProps.option.nip" class="text-[10px] text-textMuted font-normal">({{ slotProps.option.nip }})</span>
                </div>
                <div class="text-textMuted text-[11px] truncate flex items-center gap-1.5 mt-0.5">
                  <span v-if="slotProps.option.level" class="text-[9.5px] font-bold px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                    Lv.{{ slotProps.option.level }}
                  </span>
                  <span>{{ slotProps.option.jabatan || '-' }}</span>
                  <span v-if="slotProps.option.unit" class="text-textMuted/80">• {{ slotProps.option.unit }}</span>
                </div>
              </div>
              <span class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase shrink-0"
                :class="slotProps.option.role === 'DIREKTUR' 
                  ? 'bg-purple-500/10 text-purple-700 dark:text-purple-400 border border-purple-500/20' 
                  : (slotProps.option.role === 'PEJABAT' 
                    ? 'bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-500/20' 
                    : 'bg-surface2 text-textMuted border border-border')">
                {{ slotProps.option.role }}
              </span>
            </div>
          </template>
        </MultiSelect>
        <span class="text-[11px] text-textMuted italic">
          Surat akan langsung diekspedisikan ke akun pengguna terpilih, dan mereka dapat melihat file surat serta melakukan konfirmasi terima.
        </span>
      </div>

      <!-- Catatan Pengiriman -->
      <div class="flex flex-col gap-1.5">
        <label class="text-xs font-bold text-textMain uppercase tracking-wider">
          Catatan / Instruksi Pengiriman (Opsional)
        </label>
        <Textarea
          v-model="form.catatan"
          rows="3"
          autoResize
          placeholder="Contoh: Berkas fisik diletakkan di loker / Harap diperiksa berkas lampirannya..."
          class="w-full text-xs !rounded-xl !bg-surface2 !border-border !text-textMain"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex items-center justify-end gap-2 pt-3 border-t border-border">
        <Button
          label="Batal"
          icon="pi pi-times"
          text
          severity="secondary"
          class="!rounded-xl text-xs"
          @click="emit('update:visible', false)"
          :disabled="submitting"
        />
        <Button
          label="Simpan & Kirim Ekspedisi"
          icon="pi pi-send"
          severity="success"
          class="btn-gradient !rounded-xl text-xs font-bold shadow-xs !py-2 !px-4"
          :loading="submitting"
          @click="handleSubmit"
        />
      </div>
    </template>
  </Dialog>
</template>

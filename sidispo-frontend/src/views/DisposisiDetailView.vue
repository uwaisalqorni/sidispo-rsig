<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import api from '@/api/axios'

const route = useRoute()
const router = useRouter()
const disposisi = ref(null)
const timeline  = ref([])
const loading   = ref(true)
const showProgressModal = ref(false)
const submitting = ref(false)
const submitError = ref('')
const progressForm = ref({ penerima_id: '', status: 'PROSES', catatan: '' })

const statusConfig = {
  DITERIMA:{ label: 'Diterima',  cls: 'bg-surface3 text-textMuted' },
  PROSES:  { label: 'Diproses',  cls: 'bg-brandBlueBg text-brandBlue' },
  TUNGGU:  { label: 'Menunggu',  cls: 'bg-brandYellowBg text-brandYellow' },
  SELESAI: { label: 'Selesai',   cls: 'bg-brandGreenBg text-brandGreen' },
  OVERDUE: { label: 'Overdue',   cls: 'bg-brandRedBg text-brandRed' },
}

onMounted(async () => {
  try {
    const id = route.params.id
    const [detailRes, timelineRes] = await Promise.all([
      api.get(`/disposisi/${id}`),
      api.get(`/progress/disposisi/${id}`)
    ])
    disposisi.value = detailRes.data.data
    timeline.value  = timelineRes.data.data || []
  } catch (e) {
    // silent
  } finally { loading.value = false }
})

const openProgressModal = (dp) => {
  progressForm.value = { penerima_id: dp.id, status: dp.status, catatan: '' }
  showProgressModal.value = true
  submitError.value = ''
}

const handleProgressSubmit = async () => {
  if (!progressForm.value.catatan) {
    submitError.value = 'Catatan progress wajib diisi.'
    return
  }
  submitting.value = true
  submitError.value = ''
  try {
    await api.post(`/progress/${progressForm.value.penerima_id}`, {
      status: progressForm.value.status,
      catatan: progressForm.value.catatan
    })
    showProgressModal.value = false
    // Refresh
    const id = route.params.id
    const [detailRes, timelineRes] = await Promise.all([
      api.get(`/disposisi/${id}`),
      api.get(`/progress/disposisi/${id}`)
    ])
    disposisi.value = detailRes.data.data
    timeline.value  = timelineRes.data.data || []
  } catch (err) {
    submitError.value = err.response?.data?.message || 'Gagal menyimpan progress.'
  } finally { submitting.value = false }
}

function initials(nama) {
  return (nama || '').split(' ').slice(0,2).map(w => w[0]?.toUpperCase()).join('')
}
function formatDate(dt) {
  if (!dt) return '—'
  return new Date(dt).toLocaleDateString('id-ID', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' })
}
const prioritasColors = { URGENT:'text-brandRed bg-brandRedBg', TINGGI:'text-brandYellow bg-brandYellowBg', NORMAL:'text-brandYellow bg-brandYellowBg', BIASA:'text-textMuted bg-surface3' }

function fileUrl(path) {
  const base = (import.meta.env.VITE_API_BASE_URL || 'http://localhost/SiDispo/api/v1')
    .replace('/api/v1', '')
  return base + '/' + path
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6 animate-[fadeIn_0.4s_ease]">
    <!-- Back button -->
    <button @click="router.back()" class="flex items-center gap-2 text-sm text-textMuted hover:text-textMain mb-5 transition-colors">
      ← Kembali
    </button>

    <!-- Loading state -->
    <div v-if="loading" class="space-y-4">
      <div class="h-8 bg-surface rounded animate-pulse w-48"></div>
      <div class="h-4 bg-surface rounded animate-pulse w-3/4"></div>
      <div class="h-4 bg-surface rounded animate-pulse w-1/2"></div>
    </div>

    <template v-else-if="disposisi">
      <!-- Header -->
      <div class="bg-surface border border-border rounded-xl p-6 mb-5">
        <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <span class="font-mono text-accent text-sm font-bold">{{ disposisi.nomor_disposisi }}</span>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                :class="prioritasColors[disposisi.prioritas] || 'text-textMuted bg-surface3'">
                {{ disposisi.prioritas }}
              </span>
              <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                :class="statusConfig[disposisi.status_global]?.cls ?? 'bg-surface3 text-textMuted'">
                {{ statusConfig[disposisi.status_global]?.label ?? disposisi.status_global }}
              </span>
            </div>
            <h2 class="text-xl font-bold text-textMain">{{ disposisi.perihal }}</h2>
            <p class="text-sm text-textMuted mt-1">Dari: {{ disposisi.asal_surat }} • Surat: {{ disposisi.nomor_surat }}</p>
          </div>
          <div class="flex flex-col items-end gap-1 text-right">
            <span class="text-xs text-textMuted">Dibuat oleh</span>
            <span class="text-sm font-semibold">{{ disposisi.pembuat }}</span>
            <span class="text-xs text-textMuted">Batas waktu: <span class="text-brandYellow">{{ disposisi.batas_waktu || '—' }}</span></span>
          </div>
        </div>

        <div class="bg-surface2 rounded-lg p-4 border-l-2 border-accent mb-4">
          <div class="text-xs font-bold text-textMuted uppercase mb-1.5">Isi Disposisi</div>
          <p class="text-sm text-textMain leading-relaxed">{{ disposisi.isi_disposisi }}</p>
        </div>

        <div v-if="disposisi.catatan_direktur" class="bg-surface2 rounded-lg p-4 border-l-2 border-brandYellow">
          <div class="text-xs font-bold text-textMuted uppercase mb-1.5">Catatan Tambahan</div>
          <p class="text-sm text-textMain leading-relaxed">{{ disposisi.catatan_direktur }}</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-[1fr_340px] gap-5">
        <!-- Timeline -->
        <div class="bg-surface border border-border rounded-xl overflow-hidden">
          <div class="p-4 border-b border-border text-[13px] font-bold">📋 Timeline Progress</div>
          <div class="p-5">
            <div v-if="timeline.length === 0" class="text-sm text-textMuted py-4 text-center">Belum ada progress.</div>
            <div v-else class="relative pl-5">
              <div class="absolute left-0 top-2 bottom-2 w-[2px] bg-border"></div>
              <div v-for="(log, idx) in timeline" :key="log.id" class="relative mb-5 last:mb-0">
                <div class="absolute -left-[calc(1.25rem+1px)] w-3.5 h-3.5 rounded-full border-2 border-bg"
                  :class="log.status_baru === 'SELESAI' ? 'bg-brandGreen' : log.status_baru === 'OVERDUE' ? 'bg-brandRed' : 'bg-accent'"></div>
                <div class="bg-surface2 border border-border rounded-lg p-3.5">
                  <div class="flex items-start justify-between gap-2 mb-2">
                    <div class="flex items-center gap-2">
                      <div class="w-7 h-7 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white shrink-0">
                        {{ initials(log.nama_lengkap) }}
                      </div>
                      <div>
                        <div class="text-[13px] font-semibold">{{ log.nama_lengkap }}</div>
                        <div class="text-[11px] text-textMuted">{{ log.jabatan }}</div>
                      </div>
                    </div>
                    <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full shrink-0"
                      :class="statusConfig[log.status_baru]?.cls ?? 'bg-surface3 text-textMuted'">
                      {{ statusConfig[log.status_baru]?.label ?? log.status_baru }}
                    </span>
                  </div>
                  <p v-if="log.catatan" class="text-sm text-textMuted leading-relaxed mb-2">{{ log.catatan }}</p>
                  <div class="text-[11px] text-textDim">{{ formatDate(log.created_at) }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Penerima & Lampiran -->
        <div class="flex flex-col gap-4">
          <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="p-4 border-b border-border text-[13px] font-bold">👥 Penerima</div>
            <div class="p-4 flex flex-col gap-3">
              <div v-for="dp in disposisi.penerima" :key="dp.id"
                class="flex items-center justify-between p-3 rounded-lg bg-surface2 border border-border">
                <div class="flex items-center gap-3">
                  <div class="w-9 h-9 rounded-full bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center text-xs font-bold text-white">
                    {{ initials(dp.nama_lengkap) }}
                  </div>
                  <div>
                    <div class="text-[13px] font-semibold">{{ dp.nama_lengkap }}</div>
                    <div class="text-[11px] text-textMuted">{{ dp.jabatan }}</div>
                  </div>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                  <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full"
                    :class="statusConfig[dp.status]?.cls ?? 'bg-surface3 text-textMuted'">
                    {{ statusConfig[dp.status]?.label ?? dp.status }}
                  </span>
                  <button @click="openProgressModal(dp)"
                    class="text-xs text-accent hover:underline">Update Progress ↗</button>
                </div>
              </div>
            </div>
          </div>

          <!-- Lampiran -->
          <div v-if="disposisi.files?.length" class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="p-4 border-b border-border text-[13px] font-bold">📎 Lampiran ({{ disposisi.files.length }})</div>
            <div class="p-4 flex flex-col gap-2">
              <a v-for="f in disposisi.files" :key="f.id"
                :href="fileUrl(f.path_file)" target="_blank"
                class="flex items-center gap-3 p-2.5 rounded-lg border border-border hover:border-accent hover:bg-surface2 transition-all">
                <span class="text-xl">📄</span>
                <div class="flex-1 min-w-0">
                  <div class="text-[13px] font-medium truncate">{{ f.nama_asli }}</div>
                  <div class="text-[11px] text-textMuted">{{ Math.round(f.ukuran_bytes / 1024) }} KB</div>
                </div>
              </a>
            </div>
          </div>
        </div>
      </div>
    </template>

    <div v-else class="text-center py-20 text-textMuted">Disposisi tidak ditemukan.</div>

    <!-- Progress Modal -->
    <div v-if="showProgressModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-md shadow-2xl animate-[fadeIn_0.2s_ease]">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="text-base font-bold">📝 Update Progress</h3>
          <button @click="showProgressModal = false" class="text-textMuted hover:text-textMain text-xl leading-none">&times;</button>
        </div>
        <form @submit.prevent="handleProgressSubmit" class="p-5 flex flex-col gap-4">
          <div v-if="submitError" class="p-3 rounded-lg bg-brandRedBg border border-brandRed/20 text-sm text-brandRed">{{ submitError }}</div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Status Baru</label>
            <select v-model="progressForm.status" class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-black focus:border-accent">
              <option value="PROSES">Sedang Diproses</option>
              <option value="TUNGGU">Menunggu</option>
              <option value="SELESAI">Selesai</option>
            </select>
          </div>

          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Catatan Progress *</label>
            <textarea v-model="progressForm.catatan" rows="4"
              placeholder="Deskripsikan perkembangan tindak lanjut Anda..."
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent" required></textarea>
          </div>

          <div class="flex justify-end gap-3 pt-2 border-t border-border">
            <button type="button" @click="showProgressModal = false"
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain">Batal</button>
            <button type="submit" :disabled="submitting"
              class="px-4 py-2 text-sm font-semibold rounded-lg bg-accent text-white hover:bg-accentHover disabled:opacity-60">
              <span v-if="submitting" class="inline-block w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin mr-1"></span>
              {{ submitting ? 'Menyimpan...' : 'Simpan Progress' }}
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

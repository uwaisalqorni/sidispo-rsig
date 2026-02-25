<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const settings   = ref({})
const stats      = ref({})
const loading    = ref(true)
const saving     = ref(false)
const saveMsg    = ref('')
const activeTab  = ref('profil')

const TABS = [
  { id: 'profil',   label: '🏥 Profil RS' },
  { id: 'sistem',   label: '⚙️ Sistem' },
  { id: 'notif',    label: '🔔 Notifikasi' },
  { id: 'stats',    label: '📊 Statistik' },
]

const load = async () => {
  loading.value = true
  try {
    const [settRes, statsRes] = await Promise.all([
      api.get('/admin/settings'),
      api.get('/admin/stats')
    ])
    settings.value = settRes.data.data || {}
    stats.value    = statsRes.data.data || {}
  } catch { /* silent */ } finally { loading.value = false }
}

onMounted(load)

const save = async () => {
  saving.value = true; saveMsg.value = ''
  try {
    await api.put('/admin/settings', settings.value)
    saveMsg.value = 'success:Konfigurasi berhasil disimpan!'
    setTimeout(() => saveMsg.value = '', 3000)
  } catch (e) {
    saveMsg.value = 'error:' + (e.response?.data?.message || 'Gagal menyimpan.')
  } finally { saving.value = false }
}

const ROLES_LABEL = {
  ADMIN: 'Admin', DIREKTUR: 'Direktur', WAKIL_DIREKTUR: 'Wakil Direktur',
  KABID: 'Kepala Bidang', KABAG: 'Kepala Bagian', STAF: 'Staf'
}

function roleColor(role) {
  const map = {
    ADMIN: 'bg-brandPurpleBg text-brandPurple',
    DIREKTUR: 'bg-brandBlueBg text-brandBlue',
    WAKIL_DIREKTUR: 'bg-brandCyanBg text-brandCyan',
    KABID: 'bg-brandGreenBg text-brandGreen',
    KABAG: 'bg-brandGreenBg text-brandGreen',
    STAF: 'bg-surface3 text-textMuted',
  }
  return map[role] || 'bg-surface3 text-textMuted'
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-textMain mb-1">⚙️ Konfigurasi Sistem</h1>
        <p class="text-sm text-textMuted">Pengaturan profil rumah sakit, sistem, dan notifikasi.</p>
      </div>
      <button v-if="activeTab !== 'stats'" @click="save" :disabled="saving"
        class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold flex items-center gap-2 shadow-sm transition-all disabled:opacity-60">
        <span v-if="saving" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
        {{ saving ? 'Menyimpan...' : '💾 Simpan Konfigurasi' }}
      </button>
    </div>

    <!-- Save message -->
    <div v-if="saveMsg" class="mb-4 p-3.5 rounded-xl text-sm font-semibold flex items-center gap-2"
      :class="saveMsg.startsWith('success') ? 'bg-brandGreenBg text-brandGreen border border-brandGreen/20' : 'bg-brandRedBg text-brandRed border border-brandRed/20'">
      {{ saveMsg.split(':')[1] }}
    </div>

    <!-- Tabs -->
    <div class="flex gap-1 mb-6 bg-surface2 p-1 rounded-xl w-fit border border-border">
      <button v-for="t in TABS" :key="t.id" @click="activeTab = t.id"
        class="px-4 py-1.5 rounded-lg text-[13px] font-semibold transition-all"
        :class="activeTab === t.id ? 'bg-surface text-accent border border-accent/20 shadow-sm' : 'text-textMuted hover:text-textMain'">
        {{ t.label }}
      </button>
    </div>

    <div v-if="loading" class="text-center py-10 text-textMuted text-sm">Memuat konfigurasi...</div>

    <template v-else>
      <!-- TAB: Profil RS -->
      <div v-if="activeTab === 'profil'" class="bg-surface border border-border rounded-xl p-6">
        <h2 class="text-sm font-bold text-textMain uppercase tracking-wide mb-4">🏥 Informasi Rumah Sakit</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Nama Rumah Sakit</label>
            <input v-model="settings.nama_rs" type="text"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Alamat</label>
            <textarea v-model="settings.alamat_rs" rows="2"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none"></textarea>
          </div>
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">No. Telepon</label>
            <input v-model="settings.telp_rs" type="text" placeholder="0411-123456"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Email Resmi RS</label>
            <input v-model="settings.email_rs" type="email" placeholder="info@rsud.go.id"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
          </div>
        </div>
      </div>

      <!-- TAB: Sistem -->
      <div v-if="activeTab === 'sistem'" class="bg-surface border border-border rounded-xl p-6">
        <h2 class="text-sm font-bold text-textMain uppercase tracking-wide mb-4">⚙️ Konfigurasi Sistem</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Batas Waktu Default Disposisi (hari)</label>
            <input v-model="settings.batas_waktu_default" type="number" min="1" max="365"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
            <p class="text-xs text-textMuted mt-1">Jumlah hari batas waktu default saat membuat disposisi baru.</p>
          </div>
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">JWT Token Berlaku (jam)</label>
            <input v-model="settings.jwt_expired_hours" type="number" min="1" max="168"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
            <p class="text-xs text-textMuted mt-1">Sesi login akan kedaluwarsa setelah jumlah jam ini.</p>
          </div>
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Maks Upload File (MB)</label>
            <input v-model="settings.max_upload_mb" type="number" min="1" max="100"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
          </div>
          <div>
            <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Versi Aplikasi</label>
            <input v-model="settings.versi_aplikasi" type="text" placeholder="1.0.0"
              class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-bold text-textMuted uppercase mb-2">Mode Maintenance</label>
            <div class="flex items-center gap-3">
              <button type="button" @click="settings.maintenance_mode = settings.maintenance_mode == '1' ? '0' : '1'"
                class="relative w-12 h-6 rounded-full transition-all duration-300 focus:outline-none"
                :class="settings.maintenance_mode == '1' ? 'bg-brandRed' : 'bg-brandGreen'">
                <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-300"
                  :class="settings.maintenance_mode == '1' ? 'left-6' : 'left-0.5'"></span>
              </button>
              <span class="text-sm font-semibold" :class="settings.maintenance_mode == '1' ? 'text-brandRed' : 'text-brandGreen'">
                {{ settings.maintenance_mode == '1' ? 'Maintenance — Aplikasi tidak dapat diakses pengguna' : 'Normal — Aplikasi berjalan' }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB: Notifikasi -->
      <div v-if="activeTab === 'notif'" class="bg-surface border border-border rounded-xl p-6">
        <h2 class="text-sm font-bold text-textMain uppercase tracking-wide mb-4">🔔 Pengaturan Notifikasi</h2>
        <div class="flex flex-col gap-5">
          <div class="flex items-start justify-between p-4 rounded-lg border border-border bg-surface2">
            <div>
              <div class="font-semibold text-textMain text-sm">Notifikasi Sistem (In-App)</div>
              <div class="text-xs text-textMuted mt-0.5">Notifikasi muncul di ikon 🔔 pada topbar aplikasi.</div>
            </div>
            <button type="button" @click="settings.notif_system = settings.notif_system == '1' ? '0' : '1'"
              class="relative w-12 h-6 shrink-0 rounded-full transition-all duration-300 ml-4"
              :class="settings.notif_system == '1' ? 'bg-accent' : 'bg-border'">
              <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-300"
                :class="settings.notif_system == '1' ? 'left-6' : 'left-0.5'"></span>
            </button>
          </div>
          <div class="flex items-start justify-between p-4 rounded-lg border border-border bg-surface2">
            <div>
              <div class="font-semibold text-textMain text-sm">Notifikasi Email</div>
              <div class="text-xs text-textMuted mt-0.5">Kirim email saat ada disposisi baru atau update progress. (Butuh konfigurasi SMTP)</div>
            </div>
            <button type="button" @click="settings.notif_email = settings.notif_email == '1' ? '0' : '1'"
              class="relative w-12 h-6 shrink-0 rounded-full transition-all duration-300 ml-4"
              :class="settings.notif_email == '1' ? 'bg-accent' : 'bg-border'">
              <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-300"
                :class="settings.notif_email == '1' ? 'left-6' : 'left-0.5'"></span>
            </button>
          </div>
        </div>
      </div>

      <!-- TAB: Statistik Sistem -->
      <div v-if="activeTab === 'stats'">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
          <div v-for="(v, k) in { 'Total User': stats.total_users, 'User Aktif': stats.active_users, 'Total Disposisi': stats.total_disposisi, 'Total Surat': stats.total_surat }"
            :key="k" class="bg-surface border border-border rounded-xl p-5 text-center">
            <div class="text-3xl font-extrabold text-textMain">{{ v ?? '—' }}</div>
            <div class="text-xs text-textMuted mt-1">{{ k }}</div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Per Role -->
          <div class="bg-surface border border-border rounded-xl overflow-hidden col-span-2">
            <div class="p-4 border-b border-border text-sm font-bold text-textMain bg-surface2">👥 Pengguna per Role</div>
            <div class="p-4">
              <div v-for="row in (stats.per_role || [])" :key="row.role"
                class="flex items-center justify-between py-2 border-b border-border last:border-b-0">
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold" :class="roleColor(row.role)">
                  {{ ROLES_LABEL[row.role] || row.role }}
                </span>
                <span class="font-bold text-textMain text-lg">{{ row.total }}</span>
              </div>
            </div>
          </div>

          <!-- Status disposisi -->
          <div class="bg-surface border border-border rounded-xl overflow-hidden">
            <div class="p-4 border-b border-border text-sm font-bold text-textMain bg-surface2">📊 Status Disposisi</div>
            <div class="p-4 flex flex-col gap-3">
              <div class="flex justify-between items-center">
                <span class="text-sm text-textMuted">Selesai</span>
                <span class="text-lg font-extrabold text-brandGreen">{{ stats.selesai ?? '—' }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-textMuted">Overdue</span>
                <span class="text-lg font-extrabold text-brandRed">{{ stats.overdue ?? '—' }}</span>
              </div>
              <div class="flex justify-between items-center">
                <span class="text-sm text-textMuted">Total Folder</span>
                <span class="text-lg font-extrabold text-brandBlue">{{ stats.total_folders ?? '—' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/api/axios'

const settings   = ref({})
const stats      = ref({})
const loading    = ref(true)
const saving     = ref(false)
const saveMsg    = ref('')
const activeTab  = ref('profil')
const showSmtpPass    = ref(false)
const testingEmail    = ref(false)
const testEmailTarget = ref('')
const testEmailMsg    = ref('')
const testEmailError  = ref('')

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
    if (!testEmailTarget.value && settings.value.smtp_user) {
      testEmailTarget.value = settings.value.smtp_user
    }
  } catch { /* silent */ } finally { loading.value = false }
}

onMounted(load)

const testEmail = async () => {
  if (!testEmailTarget.value) {
    testEmailTarget.value = settings.value.smtp_user || ''
  }
  if (!testEmailTarget.value) {
    testEmailError.value = 'Masukkan email tujuan pengujian.'
    return
  }

  testingEmail.value = true
  testEmailMsg.value = ''
  testEmailError.value = ''

  try {
    const res = await api.post('/admin/settings/test-email', {
      target_email: testEmailTarget.value,
      smtp_host: settings.value.smtp_host,
      smtp_port: settings.value.smtp_port,
      smtp_user: settings.value.smtp_user,
      smtp_pass: settings.value.smtp_pass,
      smtp_crypto: settings.value.smtp_crypto
    })
    testEmailMsg.value = res.data.message || 'Email uji coba berhasil dikirim!'
  } catch (err) {
    testEmailError.value = err.response?.data?.message || 'Gagal mengirim email uji coba.'
  } finally {
    testingEmail.value = false
  }
}

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
      <div v-if="activeTab === 'notif'" class="space-y-6">
        <div class="bg-surface border border-border rounded-xl p-6">
          <h2 class="text-sm font-bold text-textMain uppercase tracking-wide mb-4">🔔 Pengaturan Notifikasi</h2>
          <div class="flex flex-col gap-4">
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
                <div class="text-xs text-textMuted mt-0.5">Kirim email otomatis ke penerima saat ada disposisi baru atau update tindak lanjut.</div>
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

        <!-- Konfigurasi Server SMTP -->
        <div class="bg-surface border border-border rounded-xl p-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-5">
            <div>
              <h2 class="text-sm font-bold text-textMain uppercase tracking-wide">📧 Konfigurasi Server SMTP</h2>
              <p class="text-xs text-textMuted mt-0.5">Pengaturan kredensial pengiriman email keluar menggunakan protokol SMTP.</p>
            </div>
            <div class="flex items-center">
              <span v-if="settings.notif_email == '1'" class="text-xs px-3 py-1 bg-brandGreenBg text-brandGreen font-semibold rounded-full border border-brandGreen/30 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-brandGreen animate-pulse"></span> Notifikasi Email Aktif
              </span>
              <span v-else class="text-xs px-3 py-1 bg-surface3 text-textMuted font-semibold rounded-full border border-border">
                Notifikasi Email Nonaktif
              </span>
            </div>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">SMTP Host</label>
              <input v-model="settings.smtp_host" type="text" placeholder="smtp.gmail.com"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
              <p class="text-[11px] text-textMuted mt-1">Host server email (contoh: smtp.gmail.com)</p>
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">SMTP Port</label>
              <input v-model="settings.smtp_port" type="number" placeholder="587"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
              <p class="text-[11px] text-textMuted mt-1">Port SMTP (587 untuk TLS, 465 untuk SSL)</p>
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">SMTP User / Email Pengirim</label>
              <input v-model="settings.smtp_user" type="email" placeholder="oktaimtiziliffa@gmail.com"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
              <p class="text-[11px] text-textMuted mt-1">Alamat email akun pengirim notifikasi</p>
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">SMTP Password / Sandi Aplikasi</label>
              <div class="relative">
                <input v-model="settings.smtp_pass" :type="showSmtpPass ? 'text' : 'password'" placeholder="Sandi aplikasi 16 karakter"
                  class="w-full bg-surface2 border border-border rounded-lg pl-3 pr-10 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
                <button type="button" @click="showSmtpPass = !showSmtpPass"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-textMuted hover:text-textMain p-1 text-sm">
                  <i :class="showSmtpPass ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
                </button>
              </div>
              <p class="text-[11px] text-textMuted mt-1">Untuk Gmail, gunakan 16 karakter Sandi Aplikasi Google.</p>
            </div>

            <div class="col-span-1 md:col-span-2">
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Protokol Enkripsi (Crypto)</label>
              <select v-model="settings.smtp_crypto"
                class="w-full md:w-1/2 bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none">
                <option value="tls">TLS (Disarankan untuk Port 587)</option>
                <option value="ssl">SSL (Disarankan untuk Port 465)</option>
                <option value="">None (Tanpa Enkripsi)</option>
              </select>
            </div>
          </div>

          <!-- Uji Coba Pengiriman Email -->
          <div class="mt-6 pt-5 border-t border-border">
            <h3 class="text-xs font-bold text-textMain uppercase tracking-wide mb-1.5 flex items-center gap-2">
              <span>🧪 Uji Coba Kirim Email (Test SMTP)</span>
            </h3>
            <p class="text-xs text-textMuted mb-3">Kirim email percobaan untuk memastikan pengaturan SMTP dapat terhubung dan mengirim pesan dengan lancar.</p>

            <div class="flex flex-col sm:flex-row gap-3">
              <input v-model="testEmailTarget" type="email" placeholder="Email penerima uji coba..."
                class="flex-1 bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
              <button type="button" @click="testEmail" :disabled="testingEmail"
                class="bg-surface3 hover:bg-surface border border-border px-5 py-2.5 rounded-lg font-semibold text-sm text-textMain flex items-center justify-center gap-2 transition-all disabled:opacity-60 shrink-0">
                <span v-if="testingEmail" class="w-4 h-4 border-2 border-accent border-t-transparent rounded-full animate-spin"></span>
                <i v-else class="pi pi-send text-accent"></i>
                {{ testingEmail ? 'Mengirim...' : 'Kirim Email Uji Coba' }}
              </button>
            </div>

            <!-- Alert Result -->
            <div v-if="testEmailMsg" class="mt-3 p-3.5 rounded-xl text-xs font-semibold bg-brandGreenBg text-brandGreen border border-brandGreen/30 flex items-center gap-2">
              <i class="pi pi-check-circle text-base"></i>
              <span>{{ testEmailMsg }}</span>
            </div>
            <div v-if="testEmailError" class="mt-3 p-3.5 rounded-xl text-xs font-semibold bg-brandRedBg text-brandRed border border-brandRed/30 flex items-start gap-2">
              <i class="pi pi-exclamation-triangle text-base shrink-0 mt-0.5"></i>
              <span class="leading-relaxed whitespace-pre-wrap">{{ testEmailError }}</span>
            </div>
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

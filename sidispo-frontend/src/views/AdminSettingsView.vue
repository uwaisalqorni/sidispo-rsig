<script setup>
import { ref, onMounted, computed } from 'vue'
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

// WhatsApp Gateway States
const showWaPass        = ref(false)
const testingWaDb       = ref(false)
const testWaDbResult    = ref(null)
const testWaDbError     = ref('')
const testingWaSend     = ref(false)
const testWaPhone       = ref('')
const testWaCustomMsg   = ref('')
const testWaWithSampleFile = ref(false)
const testWaSendResult  = ref('')
const testWaSendError   = ref('')

const TABS = [
  { id: 'profil',   label: '🏥 Profil RS' },
  { id: 'sistem',   label: '⚙️ Sistem' },
  { id: 'notif',    label: '🔔 Notifikasi Email' },
  { id: 'whatsapp', label: '📱 WhatsApp Gateway' },
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

// ── WHATSAPP GATEWAY METHODS ───────────────────────────────────────────────

const testWaDb = async () => {
  testingWaDb.value = true
  testWaDbResult.value = null
  testWaDbError.value = ''
  try {
    const res = await api.post('/admin/settings/test-wa-db', {
      wa_db_host: settings.value.wa_db_host,
      wa_db_port: settings.value.wa_db_port,
      wa_db_user: settings.value.wa_db_user,
      wa_db_pass: settings.value.wa_db_pass,
      wa_db_name: settings.value.wa_db_name,
      wa_outbox_table: settings.value.wa_outbox_table
    })
    testWaDbResult.value = res.data
  } catch (err) {
    testWaDbError.value = err.response?.data?.message || 'Gagal terhubung ke database WhatsApp Gateway.'
  } finally {
    testingWaDb.value = false
  }
}

const testSendWa = async () => {
  if (!testWaPhone.value) {
    testWaSendError.value = 'Nomor WhatsApp tujuan wajib diisi.'
    return
  }
  testingWaSend.value = true
  testWaSendResult.value = ''
  testWaSendError.value = ''
  try {
    const res = await api.post('/admin/settings/test-send-wa', {
      target_phone: testWaPhone.value,
      message: testWaCustomMsg.value,
      send_sample_file: testWaWithSampleFile.value,
      wa_media_path: settings.value.wa_media_path,
      wa_db_host: settings.value.wa_db_host,
      wa_db_port: settings.value.wa_db_port,
      wa_db_user: settings.value.wa_db_user,
      wa_db_pass: settings.value.wa_db_pass,
      wa_db_name: settings.value.wa_db_name,
      wa_outbox_table: settings.value.wa_outbox_table,
      wa_sender: settings.value.wa_sender,
      wa_source: settings.value.wa_source
    })
    testWaSendResult.value = res.data.message || 'Pesan berhasil dimasukkan ke antrean!'
  } catch (err) {
    testWaSendError.value = err.response?.data?.message || 'Gagal mengirim pesan uji coba.'
  } finally {
    testingWaSend.value = false
  }
}

const PLACEHOLDERS = [
  { tag: '{nama_penerima}', label: 'Nama Penerima' },
  { tag: '{nip}', label: 'NIP' },
  { tag: '{jabatan}', label: 'Jabatan' },
  { tag: '{unit}', label: 'Unit Kerja' },
  { tag: '{nomor_ekspedisi}', label: 'No. Ekspedisi' },
  { tag: '{nomor_surat}', label: 'No. Surat' },
  { tag: '{asal_surat}', label: 'Asal Surat' },
  { tag: '{perihal}', label: 'Perihal Surat' },
  { tag: '{tanggal_kirim}', label: 'Tgl Kirim' },
  { tag: '{jenis_pengiriman}', label: 'Jenis (Digital/Fisik)' },
  { tag: '{catatan_pengiriman}', label: 'Catatan Pengiriman' },
  { tag: '{keterangan_tambahan}', label: 'Keterangan Tambahan' },
  { tag: '{nama_rs}', label: 'Nama RS' },
]

const insertPlaceholder = (tag) => {
  const textarea = document.getElementById('templateTextarea')
  if (!textarea) {
    settings.value.wa_template_ekspedisi = (settings.value.wa_template_ekspedisi || '') + tag
    return
  }
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const text = settings.value.wa_template_ekspedisi || ''
  settings.value.wa_template_ekspedisi = text.substring(0, start) + tag + text.substring(end)
  setTimeout(() => {
    textarea.focus()
    textarea.setSelectionRange(start + tag.length, start + tag.length)
  }, 0)
}

const resetDefaultTemplate = () => {
  if (confirm('Apakah Anda yakin ingin mengembalikan template ke format bawaan sistem?')) {
    settings.value.wa_template_ekspedisi = `*NOTIFIKASI EKSPEDISI SURAT MASUK*
{nama_rs}

Yth. *{nama_penerima}*
({jabatan} - {unit})

Dokumen resmi disposisi telah diekspedisikan kepada Anda dengan rincian:
----------------------------------------
- *No. Ekspedisi:* {nomor_ekspedisi}
- *No. Surat:* {nomor_surat}
- *Asal Surat:* {asal_surat}
- *Perihal:* {perihal}
- *Tanggal Kirim:* {tanggal_kirim}
- *Jenis Pengiriman:* {jenis_pengiriman}
{catatan_pengiriman}
----------------------------------------
{keterangan_tambahan}

Silakan akses sistem *SiDispo* pada menu *Ekspedisi Masuk* untuk memeriksa berkas digital dan melakukan konfirmasi serah terima dokumen.

Terima kasih.
_Sistem Informasi Disposisi RSI Gondanglegi_`
  }
}

const previewRenderedWa = computed(() => {
  let tpl = settings.value.wa_template_ekspedisi || ''
  const dummy = {
    '{nama_rs}': settings.value.nama_rs || 'RSI GONDANGLEGI',
    '{nama_penerima}': 'dr. H. Navis Yuliansyah, Sp.S',
    '{nip}': '197508122005011004',
    '{jabatan}': 'Direktur',
    '{unit}': 'Direksi',
    '{nomor_ekspedisi}': 'EXP-20260908-0001',
    '{nomor_surat}': '445/012/RSIG/2026',
    '{asal_surat}': 'Dinas Kesehatan Kab. Malang',
    '{perihal}': 'Undangan Sosialisasi Program Akreditasi RS 2026',
    '{tanggal_kirim}': '08-09-2026',
    '{jenis_pengiriman}': 'DIGITAL',
    '{catatan_pengiriman}': '- *Catatan:* Mohon segera diverifikasi sebelum batas waktu rapat.',
    '{keterangan_tambahan}': '*Dokumen Digital:*\nLembar disposisi selesai dan file berkas surat dapat langsung diunduh melalui aplikasi SiDispo.'
  }
  for (const [k, v] of Object.entries(dummy)) {
    tpl = tpl.replaceAll(k, v)
  }
  return tpl
})

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

      <!-- TAB: WhatsApp Gateway -->
      <div v-if="activeTab === 'whatsapp'" class="space-y-6">
        <!-- Status Switch -->
        <div class="bg-surface border border-border rounded-xl p-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-sm font-bold text-textMain uppercase tracking-wide">📱 Integrasi WhatsApp Gateway</h2>
                <span v-if="settings.wa_enabled == '1'" class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-600 border border-emerald-500/20 flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> AKTIF
                </span>
                <span v-else class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-surface3 text-textMuted border border-border">
                  NONAKTIF
                </span>
              </div>
              <p class="text-xs text-textMuted mt-1">
                Kirim notifikasi pesan otomatis ke WhatsApp penerima setiap kali admin membuat atau memperbarui Ekspedisi Surat.
              </p>
            </div>
            <button type="button" @click="settings.wa_enabled = settings.wa_enabled == '1' ? '0' : '1'"
              class="relative w-12 h-6 shrink-0 rounded-full transition-all duration-300"
              :class="settings.wa_enabled == '1' ? 'bg-emerald-600' : 'bg-border'">
              <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-300"
                :class="settings.wa_enabled == '1' ? 'left-6' : 'left-0.5'"></span>
            </button>
          </div>
        </div>

        <!-- Konfigurasi Koneksi Database WA Gateway -->
        <div class="bg-surface border border-border rounded-xl p-6">
          <div class="flex items-center gap-2 mb-1">
            <i class="pi pi-database text-emerald-600 font-bold"></i>
            <h2 class="text-sm font-bold text-textMain uppercase tracking-wide">Koneksi Database WA Gateway (MySQL)</h2>
          </div>
          <p class="text-xs text-textMuted mb-5">
            Pengaturan server database yang memuat tabel antrean pengiriman pesan WhatsApp (default: <code class="px-1.5 py-0.5 bg-surface2 rounded text-emerald-600 font-mono">wa_delphi3</code>, tabel <code class="px-1.5 py-0.5 bg-surface2 rounded text-emerald-600 font-mono">wa_outbox</code>).
          </p>

          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Database Host / IP</label>
              <input v-model="settings.wa_db_host" type="text" placeholder="192.168.0.194"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Database Port</label>
              <input v-model="settings.wa_db_port" type="number" placeholder="3306"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Nama Database WA</label>
              <input v-model="settings.wa_db_name" type="text" placeholder="wa_delphi3"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Nama Tabel Outbox</label>
              <input v-model="settings.wa_outbox_table" type="text" placeholder="wa_outbox"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Database Username</label>
              <input v-model="settings.wa_db_user" type="text" placeholder="root"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Database Password</label>
              <div class="relative">
                <input v-model="settings.wa_db_pass" :type="showWaPass ? 'text' : 'password'" placeholder="Password MySQL WA"
                  class="w-full bg-surface2 border border-border rounded-lg pl-3 pr-10 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
                <button type="button" @click="showWaPass = !showWaPass"
                  class="absolute right-2.5 top-1/2 -translate-y-1/2 text-textMuted hover:text-textMain p-1 text-sm">
                  <i :class="showWaPass ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
                </button>
              </div>
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Sender Code</label>
              <input v-model="settings.wa_sender" type="text" placeholder="NODEJS"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
              <p class="text-[11px] text-textMuted mt-1">Kode pengenal daemon worker (contoh: NODEJS, DELPHI)</p>
            </div>

            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Source Identitas</label>
              <input v-model="settings.wa_source" type="text" placeholder="SIDISPO"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
              <p class="text-[11px] text-textMuted mt-1">Label identitas sumber modul pengirim (contoh: SIDISPO)</p>
            </div>

            <!-- Folder Media Gateway (Opsi A) -->
            <div class="col-span-1 md:col-span-2">
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5 flex items-center gap-1.5">
                <i class="pi pi-folder text-emerald-600"></i>
                Folder Media WA Gateway (Penyimpanan File)
              </label>
              <input v-model="settings.wa_media_path" type="text" placeholder="c:/xampp/htdocs/nodejs-gateway/media"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
              <p class="text-[11px] text-textMuted mt-1">
                Lokasi folder fisik media server WA Gateway (default: <code class="px-1.5 py-0.5 bg-surface2 rounded text-emerald-600 font-mono">c:/xampp/htdocs/nodejs-gateway/media</code>). Berkas surat akan otomatis disalin ke folder ini saat pengiriman ekspedisi.
              </p>
            </div>

            <!-- Saklar Kirim Lampiran Dokumen Surat -->
            <div class="col-span-1 md:col-span-2 flex items-start justify-between p-4 rounded-xl border border-border bg-surface2 mt-1">
              <div>
                <div class="font-semibold text-textMain text-sm flex items-center gap-2">
                  <i class="pi pi-paperclip text-emerald-600"></i>
                  Kirim Lampiran Berkas Dokumen Surat Masuk
                </div>
                <div class="text-xs text-textMuted mt-0.5 leading-relaxed">
                  Jika aktif, notifikasi WhatsApp akan dikirimkan sebagai tipe <code class="font-bold text-emerald-600">FILE</code> lengkap dengan berkas fisik asli surat (PDF/gambar) dan pesan teks pengantar (caption).
                </div>
              </div>
              <button type="button" @click="settings.wa_send_file = settings.wa_send_file == '0' ? '1' : '0'"
                class="relative w-12 h-6 shrink-0 rounded-full transition-all duration-300 ml-4 cursor-pointer"
                :class="settings.wa_send_file != '0' ? 'bg-emerald-600' : 'bg-border'">
                <span class="absolute top-0.5 w-5 h-5 bg-white rounded-full shadow transition-all duration-300"
                  :class="settings.wa_send_file != '0' ? 'left-6' : 'left-0.5'"></span>
              </button>
            </div>
          </div>

          <!-- Tombol Uji Koneksi Database WA -->
          <div class="mt-6 pt-5 border-t border-border flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
              <div class="text-xs font-bold text-textMain uppercase tracking-wide">Uji Koneksi Database</div>
              <div class="text-xs text-textMuted mt-0.5">Memvalidasi konektivitas server MySQL dan memeriksa tabel antrean outbox.</div>
            </div>
            <button type="button" @click="testWaDb" :disabled="testingWaDb"
              class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs flex items-center gap-2 shadow-sm transition-all disabled:opacity-60 cursor-pointer">
              <span v-if="testingWaDb" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <i v-else class="pi pi-check-circle"></i>
              {{ testingWaDb ? 'Menguji Koneksi...' : 'Uji Koneksi Database' }}
            </button>
          </div>

          <!-- Alert Hasil Uji Koneksi -->
          <div v-if="testWaDbResult" class="mt-4 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-xs">
            <div class="flex items-center gap-2 font-bold text-sm mb-1.5">
              <i class="pi pi-check-circle text-base"></i>
              <span>{{ testWaDbResult.message }}</span>
            </div>
            <div v-if="testWaDbResult.stats" class="flex flex-wrap gap-4 mt-2 pt-2 border-t border-emerald-500/20 text-xs">
              <div>Total Data Outbox: <b class="font-mono text-emerald-800 dark:text-emerald-300">{{ testWaDbResult.stats.total_record }}</b></div>
              <div>Sedang Antre: <b class="font-mono text-amber-600 dark:text-amber-400">{{ testWaDbResult.stats.antrian }}</b></div>
              <div>Terkirim/Selesai: <b class="font-mono text-emerald-800 dark:text-emerald-300">{{ testWaDbResult.stats.terkirim }}</b></div>
            </div>
          </div>

          <div v-if="testWaDbError" class="mt-4 p-4 rounded-xl bg-red-500/10 border border-red-500/20 text-red-700 dark:text-red-400 text-xs flex items-start gap-2.5">
            <i class="pi pi-exclamation-triangle text-base mt-0.5 shrink-0"></i>
            <div>
              <div class="font-bold text-sm mb-0.5">Koneksi Gagal</div>
              <div class="leading-relaxed">{{ testWaDbError }}</div>
            </div>
          </div>

          <!-- Kirim Uji Coba WA -->
          <div class="mt-6 pt-5 border-t border-border">
            <div class="text-xs font-bold text-textMain uppercase tracking-wide mb-1 flex items-center gap-2">
              <i class="pi pi-send text-emerald-600"></i>
              <span>Kirim Pesan Uji Coba WhatsApp</span>
            </div>
            <p class="text-xs text-textMuted mb-3">Masukkan nomor WhatsApp aktif untuk memastikan pesan dapat dimasukkan ke tabel antrean outbox.</p>

            <div class="flex flex-col sm:flex-row gap-3">
              <input v-model="testWaPhone" type="text" placeholder="Contoh: 085330609002..."
                class="flex-1 bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none font-mono" />
              <button type="button" @click="testSendWa" :disabled="testingWaSend"
                class="bg-surface3 hover:bg-surface border border-border px-5 py-2.5 rounded-lg font-semibold text-xs text-textMain flex items-center justify-center gap-2 transition-all disabled:opacity-60 shrink-0 cursor-pointer">
                <span v-if="testingWaSend" class="w-4 h-4 border-2 border-accent border-t-transparent rounded-full animate-spin"></span>
                <i v-else class="pi pi-send text-emerald-600"></i>
                {{ testingWaSend ? 'Memproses...' : 'Kirim Pesan Uji Coba' }}
              </button>
            </div>

            <div class="flex items-center gap-2 mt-2.5">
              <input type="checkbox" id="testWaSampleFile" v-model="testWaWithSampleFile" class="rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer w-4 h-4" />
              <label for="testWaSampleFile" class="text-xs text-textMain cursor-pointer select-none font-medium flex items-center gap-1.5">
                <i class="pi pi-file-pdf text-red-500 text-xs"></i>
                Sertakan contoh file PDF dokumen (uji kirim berkas ke folder media & outbox tipe FILE)
              </label>
            </div>

            <div v-if="testWaSendResult" class="mt-3 p-3.5 rounded-xl text-xs font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 flex items-center gap-2">
              <i class="pi pi-check-circle text-base"></i>
              <span>{{ testWaSendResult }}</span>
            </div>
            <div v-if="testWaSendError" class="mt-3 p-3.5 rounded-xl text-xs font-semibold bg-red-500/10 text-red-700 dark:text-red-400 border border-red-500/20 flex items-start gap-2">
              <i class="pi pi-exclamation-triangle text-base shrink-0 mt-0.5"></i>
              <span>{{ testWaSendError }}</span>
            </div>
          </div>
        </div>

        <!-- Editor Template Pesan Ekspedisi -->
        <div class="bg-surface border border-border rounded-xl p-6">
          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
            <div class="flex items-center gap-2">
              <i class="pi pi-file-edit text-emerald-600 font-bold"></i>
              <h2 class="text-sm font-bold text-textMain uppercase tracking-wide">Template Pesan WhatsApp Ekspedisi</h2>
            </div>
            <button type="button" @click="resetDefaultTemplate"
              class="text-xs text-amber-600 hover:text-amber-700 hover:underline flex items-center gap-1.5 self-start cursor-pointer font-semibold">
              <i class="pi pi-replay text-[11px]"></i>
              Reset ke Format Bawaan
            </button>
          </div>
          <p class="text-xs text-textMuted mb-4">
            Klik tag placeholder di bawah untuk menyisipkan variabel otomatis ke dalam teks pesan.
          </p>

          <!-- Placeholder Chips -->
          <div class="mb-4 p-3 bg-surface2/70 border border-border rounded-xl">
            <div class="text-[11px] font-bold text-textMuted uppercase mb-2">Klik untuk Sisipkan Placeholder:</div>
            <div class="flex flex-wrap gap-1.5">
              <button
                v-for="p in PLACEHOLDERS"
                :key="p.tag"
                type="button"
                @click="insertPlaceholder(p.tag)"
                class="px-2.5 py-1 rounded-lg text-xs font-mono bg-surface hover:bg-emerald-500/10 hover:border-emerald-500/40 border border-border text-textMain hover:text-emerald-600 transition-all cursor-pointer flex items-center gap-1"
                :title="p.label"
              >
                <span class="text-emerald-600 font-bold">+</span>
                <span>{{ p.tag }}</span>
              </button>
            </div>
          </div>

          <!-- Grid: Textarea & WhatsApp Live Preview -->
          <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            <!-- Textarea Editor -->
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Isi Teks Template Pesan</label>
              <textarea
                id="templateTextarea"
                v-model="settings.wa_template_ekspedisi"
                rows="16"
                class="w-full bg-surface2 border border-border rounded-xl p-3.5 text-xs text-textMain font-mono focus:border-emerald-500 focus:outline-none leading-relaxed resize-y"
                placeholder="Tulis template pesan WhatsApp di sini..."
              ></textarea>
              <p class="text-[11px] text-textMuted mt-1.5">
                Format WhatsApp: <code class="font-bold">*tebal*</code>, <code class="italic">_miring_</code>, <code class="line-through">~coret~</code>, <code>`monospace`</code>.
              </p>
            </div>

            <!-- WhatsApp Bubble Live Preview -->
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">
                👁️ Live Preview Tampilan WhatsApp
              </label>
              <div class="border border-border rounded-2xl overflow-hidden shadow-sm bg-[#efeae2] dark:bg-[#0c1317]">
                <!-- WA Header -->
                <div class="bg-[#008069] dark:bg-[#202c33] text-white p-3 flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center font-bold text-xs">
                    <i class="pi pi-whatsapp text-lg"></i>
                  </div>
                  <div>
                    <div class="font-bold text-xs leading-tight">SiDispo RSI Gondanglegi</div>
                    <div class="text-[10px] text-white/80">WhatsApp Notifikasi Resmi</div>
                  </div>
                </div>

                <!-- WA Chat Area -->
                <div class="p-4 min-h-[350px] flex flex-col justify-start">
                  <div class="bg-white dark:bg-[#202c33] text-slate-800 dark:text-slate-100 rounded-2xl rounded-tl-none p-3.5 shadow-sm max-w-full text-xs font-sans whitespace-pre-wrap leading-relaxed border border-black/5 dark:border-white/5 relative">
                    {{ previewRenderedWa }}
                    <div class="flex items-center justify-end gap-1 text-[10px] text-slate-400 mt-2">
                      <span>{{ new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</span>
                      <i class="pi pi-check text-[10px] text-blue-500"></i>
                    </div>
                  </div>
                </div>
              </div>
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

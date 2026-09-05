<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '@/api/axios'

// ── State ─────────────────────────────────────────────────────────────────
const users       = ref([])
const loading     = ref(true)
const showModal   = ref(false)
const showReset   = ref(false)
const editMode    = ref(false) // false=create, true=edit
const submitting  = ref(false)
const submitError = ref('')
const resetMsg    = ref('')
const search      = ref('')
const filterRole  = ref('')
const selectedUser = ref(null)

const ROLES = ['ADMIN', 'DIREKTUR', 'PEJABAT', 'STAF']
const masterJabatan = ref([])

const form = ref({
  nip: '', nama_lengkap: '', email: '', no_hp: '', jabatan: '', jabatan_id: '',
  unit: '', role: 'STAF', password: '', is_active: 1
})
const resetForm = ref({ password: 'Sidispo@2026' })

// ── Stats ringkas ──────────────────────────────────────────────────────────
const stats = computed(() => {
  const total  = users.value.length
  const active = users.value.filter(u => u.is_active == 1).length
  const admins = users.value.filter(u => u.role === 'ADMIN').length
  return { total, active, inactive: total - active, admins }
})

// ── Filter ─────────────────────────────────────────────────────────────────
const filtered = computed(() => {
  let list = users.value
  if (filterRole.value) list = list.filter(u => u.role === filterRole.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(u =>
      u.nama_lengkap.toLowerCase().includes(q) ||
      u.email.toLowerCase().includes(q) ||
      (u.no_hp || '').toLowerCase().includes(q) ||
      (u.nip || '').includes(q) ||
      (u.jabatan || '').toLowerCase().includes(q)
    )
  }
  return list
})

// ── Load data ─────────────────────────────────────────────────────────────
const load = async () => {
  loading.value = true
  try {
    const { data } = await api.get('/admin/users')
    users.value = data.data || []
  } catch { /* silent */ } finally { loading.value = false }
}

const loadJabatan = async () => {
  try {
    const { data } = await api.get('/jabatan')
    masterJabatan.value = data.data || []
  } catch { /* silent */ }
}

onMounted(() => {
  load()
  loadJabatan()
})

const getJabatanInfo = (jid) => {
  if (!jid) return null
  return masterJabatan.value.find(j => j.id == jid) || null
}

const onJabatanMasterChange = () => {
  if (form.value.jabatan_id && !form.value.jabatan) {
    const found = masterJabatan.value.find(j => j.id == form.value.jabatan_id)
    if (found) form.value.jabatan = found.nama
  }
}

// ── Open modal ─────────────────────────────────────────────────────────────
const openCreate = () => {
  editMode.value = false
  submitError.value = ''
  Object.assign(form.value, { nip:'', nama_lengkap:'', email:'', no_hp:'', jabatan:'', jabatan_id:'', unit:'', role:'STAF', password:'', is_active:1 })
  showModal.value = true
}

const openEdit = (user) => {
  editMode.value = true
  submitError.value = ''
  Object.assign(form.value, {
    nip: user.nip, nama_lengkap: user.nama_lengkap, email: user.email,
    no_hp: user.no_hp || '',
    jabatan: user.jabatan,
    jabatan_id: user.jabatan_id || '',
    unit: user.unit,
    role: user.role, password: '', is_active: user.is_active
  })
  selectedUser.value = user
  showModal.value = true
}

const openReset = (user) => {
  selectedUser.value = user
  resetMsg.value = ''
  resetForm.value.password = 'Sidispo@2026'
  showReset.value = true
}

// ── Submit ─────────────────────────────────────────────────────────────────
const handleSubmit = async () => {
  if (!form.value.nama_lengkap || !form.value.email || !form.value.role) {
    submitError.value = 'Nama, email, dan role wajib diisi.'
    return
  }
  if (!editMode.value && !form.value.password) {
    submitError.value = 'Password wajib diisi untuk pengguna baru.'
    return
  }
  submitting.value = true; submitError.value = ''
  try {
    const payload = { ...form.value }
    if (!payload.password) delete payload.password
    if (editMode.value) {
      await api.put(`/admin/users/${selectedUser.value.id}`, payload)
    } else {
      await api.post('/admin/users', payload)
    }
    showModal.value = false
    await load()
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Gagal menyimpan data.'
  } finally { submitting.value = false }
}

const handleReset = async () => {
  if (!resetForm.value.password || resetForm.value.password.length < 8) {
    resetMsg.value = 'error:Password minimal 8 karakter.'
    return
  }
  submitting.value = true; resetMsg.value = ''
  try {
    await api.post(`/admin/users/${selectedUser.value.id}/reset-password`, resetForm.value)
    resetMsg.value = 'success:Password berhasil direset!'
    setTimeout(() => { showReset.value = false }, 1500)
  } catch (e) {
    resetMsg.value = 'error:' + (e.response?.data?.message || 'Gagal reset password.')
  } finally { submitting.value = false }
}

const toggleActive = async (user) => {
  const newVal = user.is_active == 1 ? 0 : 1
  try {
    await api.put(`/admin/users/${user.id}`, { is_active: newVal })
    user.is_active = newVal
  } catch { /* silent */ }
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

function initials(nama) {
  return (nama || '').split(' ').slice(0,2).map(w => w[0]?.toUpperCase()).join('')
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-textMain mb-1">👥 Manajemen Pengguna</h1>
        <p class="text-sm text-textMuted">Kelola akun, peran, dan akses pengguna sistem.</p>
      </div>
      <button @click="openCreate"
        class="bg-accent hover:bg-accentHover text-white px-4 py-2 rounded-lg font-semibold flex items-center gap-2 shadow-sm transition-all">
        ➕ Tambah Pengguna
      </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
      <div v-for="(val, key) in {Total: stats.total, Aktif: stats.active, 'Tidak Aktif': stats.inactive, Admin: stats.admins}"
        :key="key"
        class="bg-surface border border-border rounded-xl p-4 text-center">
        <div class="text-2xl font-extrabold text-textMain">{{ val }}</div>
        <div class="text-xs text-textMuted mt-0.5">{{ key }}</div>
      </div>
    </div>

    <!-- Filter + Search -->
    <div class="flex flex-wrap gap-2 mb-4">
      <input v-model="search" type="text" placeholder="🔍 Cari nama, email, No. HP, NIP..."
        class="flex-1 min-w-[200px] bg-surface border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none" />
      <select v-model="filterRole"
        class="bg-surface border border-border rounded-lg px-3 py-2 text-sm text-textMain focus:border-accent focus:outline-none">
        <option value="">Semua Role</option>
        <option v-for="r in ROLES" :key="r" :value="r">{{ r }}</option>
      </select>
    </div>

    <!-- Table -->
    <div class="bg-surface border border-border rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm text-left whitespace-nowrap">
          <thead class="text-xs text-textMuted uppercase bg-surface2 border-b border-border">
            <tr>
              <th class="px-5 py-3">Pengguna</th>
              <th class="px-5 py-3">NIP</th>
              <th class="px-5 py-3">No. HP</th>
              <th class="px-5 py-3">Jabatan / Unit</th>
              <th class="px-5 py-3">Role</th>
              <th class="px-5 py-3">Status</th>
              <th class="px-5 py-3 text-right">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="loading" v-for="i in 5" :key="i" class="border-b border-border">
              <td colspan="7" class="px-5 py-3">
                <div class="h-3 bg-surface3 rounded animate-pulse w-full"></div>
              </td>
            </tr>
            <tr v-else v-for="u in filtered" :key="u.id"
              class="border-b border-border last:border-b-0 hover:bg-surface2 transition-all">
              <td class="px-5 py-3">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                    :class="u.is_active == 1 ? 'bg-gradient-to-br from-accent to-brandGreen' : 'bg-surface3'">
                    {{ initials(u.nama_lengkap) }}
                  </div>
                  <div>
                    <div class="font-semibold text-textMain text-[13px]">{{ u.nama_lengkap }}</div>
                    <div class="text-[11px] text-textMuted">{{ u.email }}</div>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3 font-mono text-xs text-textMuted">{{ u.nip || '—' }}</td>
              <td class="px-5 py-3">
                <div v-if="u.no_hp" class="text-xs font-mono text-textMain flex items-center gap-1.5">
                  <span class="text-emerald-500">📱</span>
                  <a :href="`tel:${u.no_hp}`" class="hover:text-accent hover:underline">{{ u.no_hp }}</a>
                </div>
                <span v-else class="text-textDim text-xs">—</span>
              </td>
              <td class="px-5 py-3">
                <div class="flex items-center gap-1.5">
                  <div class="text-[13px] font-medium text-textMain">{{ u.jabatan || '—' }}</div>
                  <span v-if="getJabatanInfo(u.jabatan_id)" class="text-[10px] px-1.5 py-0.5 rounded bg-brandBlueBg text-brandBlue font-semibold border border-brandBlue/20">
                    Lv.{{ getJabatanInfo(u.jabatan_id).level }}
                  </span>
                </div>
                <div class="text-[11px] text-textMuted">{{ u.unit || '' }}</div>
              </td>
              <td class="px-5 py-3">
                <span class="px-2 py-0.5 rounded-full text-xs font-semibold" :class="roleColor(u.role)">
                  {{ u.role }}
                </span>
              </td>
              <td class="px-5 py-3">
                <button @click="toggleActive(u)"
                  class="px-2.5 py-0.5 rounded-full text-xs font-semibold transition-all"
                  :class="u.is_active == 1 ? 'bg-brandGreenBg text-brandGreen hover:bg-brandRedBg hover:text-brandRed' : 'bg-brandRedBg text-brandRed hover:bg-brandGreenBg hover:text-brandGreen'">
                  {{ u.is_active == 1 ? 'Aktif' : 'Nonaktif' }}
                </button>
              </td>
              <td class="px-5 py-3 text-right">
                <div class="flex items-center justify-end gap-1.5">
                  <button @click="openEdit(u)"
                    class="text-xs px-2.5 py-1.5 rounded-md bg-accentGlow text-accent hover:bg-accent hover:text-white transition-all font-semibold">
                    ✏️ Edit
                  </button>
                  <button @click="openReset(u)"
                    class="text-xs px-2.5 py-1.5 rounded-md bg-brandYellowBg text-brandYellow hover:bg-brandYellow hover:text-white transition-all font-semibold">
                    🔑 Reset
                  </button>
                </div>
              </td>
            </tr>
            <tr v-if="!loading && filtered.length === 0">
              <td colspan="7" class="px-5 py-10 text-center text-textMuted">Tidak ada pengguna ditemukan.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Modal Tambah / Edit Pengguna -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-xl shadow-2xl flex flex-col max-h-[90vh]">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2 shrink-0">
          <h3 class="text-base font-bold text-textMain">{{ editMode ? '✏️ Edit Pengguna' : '➕ Tambah Pengguna' }}</h3>
          <button @click="showModal = false" class="text-textMuted hover:text-textMain text-xl">×</button>
        </div>
        <form @submit.prevent="handleSubmit" class="p-5 overflow-y-auto flex flex-col gap-4">
          <div v-if="submitError" class="p-3 rounded-lg bg-brandRedBg border border-brandRed/20 text-sm text-brandRed">{{ submitError }}</div>
          
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Nama Lengkap *</label>
              <input v-model="form.nama_lengkap" type="text" placeholder="Nama sesuai identitas"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">NIP</label>
              <input v-model="form.nip" type="text" placeholder="NIP pegawai"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Email *</label>
              <input v-model="form.email" type="email" placeholder="email@rsud.go.id"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" required />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">No. HP / WhatsApp</label>
              <input v-model="form.no_hp" type="tel" placeholder="Contoh: 081234567890"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Master Jabatan (Hierarki)</label>
              <select v-model="form.jabatan_id" @change="onJabatanMasterChange"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none">
                <option value="">-- Pilih Master Jabatan --</option>
                <option v-for="j in masterJabatan" :key="j.id" :value="j.id">
                  [Lv. {{ j.level }}] {{ j.nama }}
                </option>
              </select>
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Jabatan Spesifik</label>
              <input v-model="form.jabatan" type="text" placeholder="Jabatan / posisi detail"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Unit Kerja</label>
              <input v-model="form.unit" type="text" placeholder="Bidang / Bagian"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none" />
            </div>
            <div>
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Role *</label>
              <select v-model="form.role"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none">
                <option v-for="r in ROLES" :key="r" :value="r">{{ r }}</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Status Akun</label>
              <select v-model="form.is_active"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none">
                <option :value="1">Aktif</option>
                <option :value="0">Nonaktif</option>
              </select>
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">
                Password {{ editMode ? '(kosongkan jika tidak ganti)' : '*' }}
              </label>
              <input v-model="form.password" type="password" placeholder="Min. 8 karakter"
                class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none"
                :required="!editMode" />
            </div>
          </div>

          <div class="flex justify-end gap-3 pt-3 border-t border-border">
            <button type="button" @click="showModal = false"
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain">Batal</button>
            <button type="submit" :disabled="submitting"
              class="px-5 py-2 text-sm font-semibold rounded-lg bg-accent text-white hover:bg-accentHover disabled:opacity-60 flex items-center gap-2">
              <span v-if="submitting" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              {{ submitting ? 'Menyimpan...' : (editMode ? 'Simpan Perubahan' : 'Buat Pengguna') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Modal Reset Password -->
    <div v-if="showReset" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div class="bg-surface border border-border rounded-xl w-full max-w-sm shadow-2xl">
        <div class="p-5 border-b border-border flex justify-between items-center bg-surface2">
          <h3 class="text-base font-bold text-textMain">🔑 Reset Password</h3>
          <button @click="showReset = false" class="text-textMuted hover:text-textMain text-xl">×</button>
        </div>
        <div class="p-5">
          <p class="text-sm text-textMuted mb-4">Reset password untuk: <strong class="text-textMain">{{ selectedUser?.nama_lengkap }}</strong></p>
          <div v-if="resetMsg" class="p-3 mb-3 rounded-lg text-sm"
            :class="resetMsg.startsWith('success') ? 'bg-brandGreenBg text-brandGreen' : 'bg-brandRedBg text-brandRed'">
            {{ resetMsg.split(':')[1] }}
          </div>
          <label class="block text-xs font-bold text-textMuted uppercase mb-1.5">Password Baru *</label>
          <input v-model="resetForm.password" type="text"
            class="w-full bg-surface2 border border-border rounded-lg px-3 py-2.5 text-sm text-textMain focus:border-accent focus:outline-none mb-4" />
          <div class="flex justify-end gap-3">
            <button @click="showReset = false"
              class="px-4 py-2 text-sm font-semibold rounded-lg border border-border text-textMuted hover:bg-surface2 hover:text-textMain">Batal</button>
            <button @click="handleReset" :disabled="submitting"
              class="px-4 py-2 text-sm font-semibold rounded-lg bg-brandYellow text-white hover:opacity-90 disabled:opacity-60">
              {{ submitting ? 'Memproses...' : 'Reset Password' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

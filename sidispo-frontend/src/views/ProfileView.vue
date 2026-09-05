<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { useToast } from 'primevue/usetoast'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Divider from 'primevue/divider'
import Avatar from 'primevue/avatar'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'

const auth = useAuthStore()
const { user, profileLoading, fotoProfilUrl } = storeToRefs(auth)
const toast = useToast()

// Profile form
const namaLengkap = ref('')
const email = ref('')
const noHp = ref('')
const fotoFile = ref(null)
const fotoPreview = ref(null)
const profileMsg = ref('')
const profileErr = ref('')

// Password form
const oldPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const pwMsg = ref('')
const pwErr = ref('')
const pwLoading = ref(false)

const userInitials = computed(() => {
  const parts = (user.value?.nama_lengkap || '').split(' ')
  return parts.slice(0, 2).map(p => p[0]?.toUpperCase()).join('')
})

const roleBadge = computed(() => {
  const map = {
    ADMIN: { label: 'Administrator', color: 'bg-amber-100 text-amber-700 border-amber-200' },
    DIREKTUR: { label: 'Direktur', color: 'bg-purple-100 text-purple-700 border-purple-200' },
    PEJABAT: { label: 'Pejabat', color: 'bg-blue-100 text-blue-700 border-blue-200' },
    STAF: { label: 'Staf', color: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
  }
  return map[user.value?.role] || map.STAF
})

onMounted(async () => {
  await auth.refreshUser()
  namaLengkap.value = user.value?.nama_lengkap || ''
  email.value = user.value?.email || ''
  noHp.value = user.value?.no_hp || ''
})

function onFotoSelect(event) {
  const file = event.target.files[0]
  if (!file) return

  if (file.size > 2 * 1024 * 1024) {
    profileErr.value = 'Ukuran file maksimal 2MB.'
    return
  }

  if (!['image/jpeg', 'image/png', 'image/gif', 'image/webp'].includes(file.type)) {
    profileErr.value = 'Format file harus JPG, PNG, GIF, atau WebP.'
    return
  }

  fotoFile.value = file
  fotoPreview.value = URL.createObjectURL(file)
  profileErr.value = ''
}

function removeFotoPreview() {
  fotoFile.value = null
  fotoPreview.value = null
  // Reset file input
  const input = document.getElementById('foto-input')
  if (input) input.value = ''
}

async function handleUpdateProfile() {
  profileMsg.value = ''
  profileErr.value = ''

  const formData = new FormData()

  if (namaLengkap.value.trim()) {
    formData.append('nama_lengkap', namaLengkap.value.trim())
  }
  if (email.value.trim()) {
    formData.append('email', email.value.trim())
  }
  if (noHp.value.trim()) {
    formData.append('no_hp', noHp.value.trim())
  }
  if (fotoFile.value) {
    formData.append('foto_profil', fotoFile.value)
  }

  try {
    await auth.updateProfile(formData)
    profileMsg.value = 'Profil berhasil diperbarui!'
    fotoFile.value = null
    fotoPreview.value = null
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Profil diperbarui.', life: 3000 })
  } catch {
    profileErr.value = auth.profileError || 'Gagal memperbarui profil.'
  }
}

async function handleChangePassword() {
  pwMsg.value = ''
  pwErr.value = ''
  pwLoading.value = true

  if (newPassword.value !== confirmPassword.value) {
    pwErr.value = 'Password baru dan konfirmasi tidak cocok.'
    pwLoading.value = false
    return
  }

  if (newPassword.value.length < 8) {
    pwErr.value = 'Password baru minimal 8 karakter.'
    pwLoading.value = false
    return
  }

  try {
    await auth.changePassword({
      old_password: oldPassword.value,
      new_password: newPassword.value,
      confirm_password: confirmPassword.value
    })
    pwMsg.value = 'Password berhasil diubah!'
    oldPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Password diubah.', life: 3000 })
  } catch {
    const errMsg = auth.profileError || 'Gagal mengubah password.'
    pwErr.value = errMsg
    toast.add({ severity: 'error', summary: 'Gagal', detail: errMsg, life: 5000 })
  } finally {
    pwLoading.value = false
  }
}
</script>

<template>
  <div class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
    <div class="max-w-3xl mx-auto space-y-6">

      <!-- Profile Header Card -->
      <div class="glass-card overflow-hidden">
        <div class="h-28 bg-gradient-to-r from-sidebar via-accent to-brandCyan relative">
          <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 20px 20px;"></div>
        </div>
        <div class="px-6 pb-6 -mt-14 relative z-10">
          <div class="flex flex-col sm:flex-row items-center sm:items-end gap-4">
            <!-- Avatar -->
            <div class="relative group">
              <div class="w-28 h-28 rounded-2xl border-4 border-white shadow-card-hover overflow-hidden bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center">
                <img
                  v-if="fotoProfilUrl"
                  :src="fotoProfilUrl"
                  alt="Foto Profil"
                  class="w-full h-full object-cover"
                />
                <span v-else class="text-white font-extrabold text-3xl">{{ userInitials }}</span>
              </div>
            </div>
            <!-- Info -->
            <div class="flex-1 text-center sm:text-left sm:pb-1">
              <h2 class="text-xl font-extrabold text-textMain mb-0.5">{{ user?.nama_lengkap }}</h2>
              <p class="text-sm text-textMuted mb-2">{{ user?.jabatan }} — {{ user?.unit || 'Unit belum diset' }}</p>
              <div class="flex flex-wrap gap-2 justify-center sm:justify-start">
                <span :class="['text-xs font-bold px-3 py-1 rounded-full border', roleBadge.color]">
                  {{ roleBadge.label }}
                </span>
                <span class="text-xs font-medium px-3 py-1 rounded-full bg-surface2 text-textMuted border border-border/50">
                  NIP: {{ user?.nip }}
                </span>
                <span v-if="user?.no_hp" class="text-xs font-medium px-3 py-1 rounded-full bg-surface2 text-textMuted border border-border/50 flex items-center gap-1">
                  <span>📱</span> {{ user.no_hp }}
                </span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit Profile Section -->
      <div class="glass-card p-6 md:p-7">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center shadow-sm">
            <i class="pi pi-user-edit text-white"></i>
          </div>
          <div>
            <h3 class="text-lg font-extrabold text-textMain m-0">Edit Profil</h3>
            <p class="text-xs text-textMuted m-0">Perbarui informasi dan foto profil Anda</p>
          </div>
        </div>

        <Message v-if="profileMsg" severity="success" :closable="true" @close="profileMsg = ''" class="mb-4">
          {{ profileMsg }}
        </Message>
        <Message v-if="profileErr" severity="error" :closable="true" @close="profileErr = ''" class="mb-4">
          {{ profileErr }}
        </Message>

        <form @submit.prevent="handleUpdateProfile" class="flex flex-col gap-5">
          <!-- Foto Profil Upload -->
          <div class="flex flex-col gap-2">
            <label class="text-xs font-bold text-textMuted uppercase tracking-wide">Foto Profil</label>
            <div class="flex items-center gap-4">
              <div class="w-20 h-20 rounded-xl overflow-hidden bg-surface2 border-2 border-dashed border-border flex items-center justify-center shrink-0">
                <img
                  v-if="fotoPreview"
                  :src="fotoPreview"
                  alt="Preview"
                  class="w-full h-full object-cover"
                />
                <img
                  v-else-if="fotoProfilUrl"
                  :src="fotoProfilUrl"
                  alt="Foto Profil"
                  class="w-full h-full object-cover"
                />
                <i v-else class="pi pi-image text-2xl text-textDim"></i>
              </div>
              <div class="flex flex-col gap-2">
                <label
                  for="foto-input"
                  class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-surface2 border border-border hover:bg-surface3 cursor-pointer transition-all text-sm font-semibold text-textMain"
                >
                  <i class="pi pi-upload text-accent"></i>
                  Pilih Foto
                </label>
                <input
                  id="foto-input"
                  type="file"
                  accept="image/jpeg,image/png,image/gif,image/webp"
                  class="hidden"
                  @change="onFotoSelect"
                />
                <p class="text-[11px] text-textDim">JPG, PNG, GIF, WebP. Maks 2MB.</p>
                <button
                  v-if="fotoPreview"
                  type="button"
                  class="text-xs text-red-500 hover:text-red-700 font-semibold text-left"
                  @click="removeFotoPreview"
                >
                  <i class="pi pi-times text-xs mr-1"></i>Hapus pilihan
                </button>
              </div>
            </div>
          </div>

          <Divider class="!my-1" />

          <!-- Nama Lengkap -->
          <div class="flex flex-col gap-2">
            <label for="nama_lengkap" class="text-xs font-bold text-textMuted uppercase tracking-wide">Nama Lengkap</label>
            <IconField>
              <InputIcon class="pi pi-user text-accent" />
              <InputText id="nama_lengkap" v-model="namaLengkap" placeholder="Nama lengkap" class="w-full !bg-surface2" :disabled="profileLoading" />
            </IconField>
          </div>

          <!-- Email -->
          <div class="flex flex-col gap-2">
            <label for="email" class="text-xs font-bold text-textMuted uppercase tracking-wide">Email</label>
            <IconField>
              <InputIcon class="pi pi-envelope text-accent" />
              <InputText id="email" v-model="email" type="email" placeholder="email@contoh.com" class="w-full !bg-surface2" :disabled="profileLoading" />
            </IconField>
          </div>

          <!-- No. HP / WhatsApp -->
          <div class="flex flex-col gap-2">
            <label for="no_hp" class="text-xs font-bold text-textMuted uppercase tracking-wide">No. HP / WhatsApp</label>
            <IconField>
              <InputIcon class="pi pi-phone text-accent" />
              <InputText id="no_hp" v-model="noHp" type="tel" placeholder="Contoh: 081234567890" class="w-full !bg-surface2" :disabled="profileLoading" />
            </IconField>
          </div>

          <Button
            type="submit"
            label="Simpan Perubahan"
            icon="pi pi-check"
            class="w-full sm:w-auto sm:self-end btn-gradient"
            :loading="profileLoading"
          />
        </form>
      </div>

      <!-- Change Password Section -->
      <div class="glass-card p-6 md:p-7">
        <div class="flex items-center gap-3 mb-6">
          <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-red-500 flex items-center justify-center shadow-sm">
            <i class="pi pi-lock text-white"></i>
          </div>
          <div>
            <h3 class="text-lg font-extrabold text-textMain m-0">Ganti Password</h3>
            <p class="text-xs text-textMuted m-0">Masukkan password lama untuk verifikasi, lalu set password baru</p>
          </div>
        </div>

        <Message v-if="pwMsg" severity="success" :closable="true" @close="pwMsg = ''" class="mb-4">
          {{ pwMsg }}
        </Message>
        <Message v-if="pwErr" severity="error" :closable="true" @close="pwErr = ''" class="mb-4">
          {{ pwErr }}
        </Message>

        <form @submit.prevent="handleChangePassword" class="flex flex-col gap-5">
          <!-- Password Lama -->
          <div class="flex flex-col gap-2">
            <label for="old_password" class="text-xs font-bold text-textMuted uppercase tracking-wide">Password Lama</label>
            <Password
              id="old_password"
              v-model="oldPassword"
              placeholder="Masukkan password lama"
              :feedback="false"
              toggle-mask
              class="w-full"
              input-class="w-full !bg-surface2"
              :disabled="pwLoading"
              required
            />
          </div>

          <!-- Password Baru -->
          <div class="flex flex-col gap-2">
            <label for="new_password" class="text-xs font-bold text-textMuted uppercase tracking-wide">Password Baru</label>
            <Password
              id="new_password"
              v-model="newPassword"
              placeholder="Minimal 8 karakter"
              :feedback="true"
              toggle-mask
              class="w-full"
              input-class="w-full !bg-surface2"
              :disabled="pwLoading"
              required
            />
          </div>

          <!-- Konfirmasi Password -->
          <div class="flex flex-col gap-2">
            <label for="confirm_password" class="text-xs font-bold text-textMuted uppercase tracking-wide">Konfirmasi Password Baru</label>
            <Password
              id="confirm_password"
              v-model="confirmPassword"
              placeholder="Ulangi password baru"
              :feedback="false"
              toggle-mask
              class="w-full"
              input-class="w-full !bg-surface2"
              :disabled="pwLoading"
              required
            />
            <p v-if="confirmPassword && newPassword !== confirmPassword" class="text-xs text-red-500 font-medium">
              <i class="pi pi-times-circle text-xs mr-1"></i>Password tidak cocok
            </p>
            <p v-else-if="confirmPassword && newPassword === confirmPassword" class="text-xs text-green-600 font-medium">
              <i class="pi pi-check-circle text-xs mr-1"></i>Password cocok
            </p>
          </div>

          <Button
            type="submit"
            label="Ubah Password"
            icon="pi pi-shield"
            severity="warning"
            class="w-full sm:w-auto sm:self-end"
            :loading="pwLoading"
            :disabled="!oldPassword || !newPassword || !confirmPassword || newPassword !== confirmPassword"
          />
        </form>
      </div>

    </div>
  </div>
</template>

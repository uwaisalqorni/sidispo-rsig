<script setup>
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { ref } from 'vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Message from 'primevue/message'
import Divider from 'primevue/divider'
import IconField from 'primevue/iconfield'
import InputIcon from 'primevue/inputicon'
import Dialog from 'primevue/dialog'

const auth = useAuthStore()
const { loading, error } = storeToRefs(auth)

const username = ref('')
const password = ref('')

const handleLogin = () => auth.login(username.value, password.value)

const forgotVisible = ref(false)

const features = [
  { icon: 'pi pi-bolt', color: 'from-yellow-400 to-orange-500', title: 'Cepat & Responsif', desc: 'Disposisi langsung terkirim ke unit terkait tanpa kertas.' },
  { icon: 'pi pi-chart-line', color: 'from-cyan-400 to-blue-500', title: 'Monitoring Akurat', desc: 'Pantau progress tindak lanjut dokumen setiap saat.' },
  { icon: 'pi pi-shield', color: 'from-emerald-400 to-green-600', title: 'Aman & Terlacak', desc: 'Setiap perubahan status tercatat secara digital.' },
]
</script>

<template>
  <div class="min-h-screen w-full flex font-sans">
    <!-- Left branding -->
    <div class="hidden lg:flex flex-1 relative overflow-hidden flex-col justify-center items-center p-12 bg-gradient-rsi">
      <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
      <div class="absolute -top-24 -right-24 w-72 h-72 rounded-full bg-emerald-300/10 animate-float"></div>
      <div class="absolute -bottom-20 -left-20 w-96 h-96 rounded-full bg-cyan-300/10"></div>
      <div class="absolute top-1/3 right-1/4 w-48 h-48 rounded-full bg-white/5 blur-2xl"></div>

      <div class="absolute top-8 left-8 z-10 flex items-center gap-3">
        <div class="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur border border-white/30 flex items-center justify-center shadow-glow">
          <i class="pi pi-building text-white text-2xl"></i>
        </div>
        <div>
          <div class="text-2xl font-extrabold text-white">SiDispo</div>
          <div class="text-xs text-emerald-200 font-medium">RSIG — Sistem Disposisi Digital</div>
        </div>
      </div>

      <div class="z-10 max-w-md w-full">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/15 border border-white/25 text-emerald-100 text-xs font-semibold mb-5">
          <span class="w-2 h-2 rounded-full bg-emerald-300 animate-pulse"></span>
          Sistem Aktif — RSI Gondanglegi
        </div>
        <h1 class="text-4xl font-extrabold text-white mb-4 leading-tight drop-shadow">
          Sistem Disposisi<br><span class="text-emerald-200">Digital</span> Terpadu
        </h1>
        <p class="text-white/75 text-lg mb-10 leading-relaxed">
          Kelola arus surat, perizinan, dan disposisi direktur secara terpadu, ringkas, dan terpantau real-time.
        </p>

        <div class="flex flex-col gap-3">
          <div
            v-for="f in features"
            :key="f.title"
            class="flex items-start gap-4 p-4 rounded-2xl bg-white/10 border border-white/20 backdrop-blur-sm hover:bg-white/15 transition-all"
          >
            <div :class="['w-11 h-11 rounded-xl bg-gradient-to-br flex items-center justify-center shrink-0 shadow-sm', f.color]">
              <i :class="[f.icon, 'text-white text-lg']"></i>
            </div>
            <div>
              <div class="font-bold text-white mb-0.5">{{ f.title }}</div>
              <div class="text-sm text-white/65">{{ f.desc }}</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right form -->
    <div class="flex-1 flex flex-col justify-center items-center p-6 md:p-10 relative bg-mesh-green">
      <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-sidebar via-accent to-brandCyan"></div>

      <div class="w-full max-w-md animate-fade-in">
        <!-- Mobile logo -->
        <div class="lg:hidden flex items-center gap-3 mb-8 justify-center">
          <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-sidebar to-accent flex items-center justify-center shadow-glow">
            <i class="pi pi-building text-white text-2xl"></i>
          </div>
          <div>
            <div class="text-2xl font-extrabold text-textMain">SiDispo</div>
            <div class="text-xs text-textMuted">Sistem Disposisi Digital</div>
          </div>
        </div>

        <!-- Form card -->
        <div class="glass-card p-7 md:p-8 shadow-card-hover">
          <div class="mb-7">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-accent to-brandGreen flex items-center justify-center mb-4 shadow-glow">
              <i class="pi pi-sign-in text-white text-xl"></i>
            </div>
            <h2 class="text-2xl font-extrabold text-textMain mb-1">Selamat Datang</h2>
            <p class="text-textMuted text-sm">Masuk menggunakan NIP atau Email Anda.</p>
          </div>

          <Message v-if="error" severity="error" :closable="false" class="mb-5 w-full" />

          <form @submit.prevent="handleLogin" class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
              <label for="username" class="text-xs font-bold text-textMuted uppercase tracking-wide">NIP / Email</label>
              <IconField>
                <InputIcon class="pi pi-user text-accent" />
                <InputText id="username" v-model="username" placeholder="Masukkan NIP atau Email" class="w-full !bg-surface2" :disabled="loading" required />
              </IconField>
            </div>

            <div class="flex flex-col gap-2">
              <div class="flex justify-between items-center">
                <label for="password" class="text-xs font-bold text-textMuted uppercase tracking-wide">Password</label>
                <a href="#" class="text-xs font-semibold text-accent hover:underline" @click.prevent="forgotVisible = true">Lupa Password?</a>
              </div>
              <Password
                id="password"
                v-model="password"
                placeholder="Masukkan password"
                :feedback="false"
                toggle-mask
                class="w-full"
                input-class="w-full !bg-surface2"
                :disabled="loading"
                required
              />
            </div>

            <Button
              type="submit"
              label="Masuk ke Sistem"
              icon="pi pi-arrow-right"
              icon-pos="right"
              class="w-full mt-1 btn-gradient"
              size="large"
              :loading="loading"
            />
          </form>

          <Divider class="my-6" />

          <p class="text-xs text-textDim text-center leading-relaxed">
            Versi 1.0.0 &copy; 2026 RSIG<br>
            <span class="text-accent font-semibold">Dikembangkan oleh IT RSIG</span>
          </p>
        </div>
      </div>
    </div>

    <!-- Forgot Password Dialog -->
    <Dialog
      v-model:visible="forgotVisible"
      header="Lupa Password?"
      :modal="true"
      :closable="true"
      :style="{ width: '400px' }"
      class="!rounded-2xl"
    >
      <div class="flex flex-col items-center text-center gap-4 py-2">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-glow">
          <i class="pi pi-info-circle text-white text-3xl"></i>
        </div>
        <div>
          <p class="text-sm text-textMain font-semibold mb-2">Silakan hubungi Admin IT untuk mereset password Anda.</p>
          <p class="text-xs text-textMuted">Admin akan mereset password Anda ke default, kemudian Anda bisa mengubahnya melalui menu <strong>Profil</strong> setelah login.</p>
        </div>
      </div>
      <template #footer>
        <Button label="Mengerti" icon="pi pi-check" class="btn-gradient w-full" @click="forgotVisible = false" />
      </template>
    </Dialog>
  </div>
</template>

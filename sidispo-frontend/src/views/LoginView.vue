<script setup>
import { useAuthStore } from '@/stores/auth'
import { storeToRefs } from 'pinia'
import { ref } from 'vue'

const auth = useAuthStore()
const { loading, error } = storeToRefs(auth)

const username = ref('')
const password = ref('')
const showPass = ref(false)

const handleLogin = () => auth.login(username.value, password.value)
</script>

<template>
  <div class="min-h-screen w-full flex bg-bg font-sans">
    
    <!-- Left Branding -->
    <div class="hidden lg:flex flex-1 relative overflow-hidden flex-col justify-center items-center p-12"
      style="background: linear-gradient(135deg, #2e7d32 0%, #4a9e4a 40%, #7cb87c 100%);">
      
      <!-- Background dot pattern -->
      <div class="absolute inset-0 opacity-10"
        style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>

      <!-- Decorative circles -->
      <div class="absolute -top-20 -right-20 w-64 h-64 rounded-full bg-white opacity-5"></div>
      <div class="absolute -bottom-16 -left-16 w-96 h-96 rounded-full bg-white opacity-5"></div>
      
      <!-- Top logo -->
      <div class="absolute top-8 left-8 z-10 flex items-center gap-3">
        <img src="/src/assets/logorsi.png" alt="RSI Logo"
          class="w-12 h-12 object-contain drop-shadow-lg" style="filter: brightness(0) invert(1);" />
        <div>
          <div class="text-xl font-extrabold tracking-tight text-white">SiDispo</div>
          <div class="text-xs text-white/70">RSIG — Sistem Disposisi Digital</div>
        </div>
      </div>
      
      <div class="z-10 max-w-md w-full">
        <h1 class="text-4xl font-extrabold text-white mb-4 leading-tight drop-shadow">
          Sistem Disposisi<br><span class="text-white/80">Digital</span> RSI Gondanglegi
        </h1>
        <p class="text-white/80 text-lg mb-10 leading-relaxed">
          Kelola arus surat, perizinan, dan disposisi direktur secara terpadu, ringkas, dan terpantau secara real-time.
        </p>
        
        <div class="flex flex-col gap-4">
          <div class="flex items-start gap-4 p-4 rounded-xl bg-white/10 border border-white/20 backdrop-blur-sm">
            <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center text-xl shrink-0">⚡</div>
            <div>
              <div class="font-bold text-white mb-1">Cepat & Responsif</div>
              <div class="text-sm text-white/70">Disposisi langsung terkirim ke unit terkait tanpa kertas.</div>
            </div>
          </div>
          <div class="flex items-start gap-4 p-4 rounded-xl bg-white/10 border border-white/20 backdrop-blur-sm">
            <div class="w-10 h-10 rounded-lg bg-white/20 flex items-center justify-center text-xl shrink-0">📊</div>
            <div>
              <div class="font-bold text-white mb-1">Monitoring Akurat</div>
              <div class="text-sm text-white/70">Pantau progress tindak lanjut dokumen setiap saat.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Right Form Panel -->
    <div class="flex-1 flex flex-col justify-center items-center p-8 bg-white relative">
      
      <!-- Subtle green accent top border -->
      <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-brandGreen via-accent to-brandCyan"></div>
      
      <div class="w-full max-w-[400px]">
        
        <!-- Mobile Logo -->
        <div class="lg:hidden flex items-center gap-3 mb-10 justify-center">
          <img src="/src/assets/logorsi.png" alt="RSI Logo" class="w-14 h-14 object-contain" />
          <div>
            <div class="text-2xl font-extrabold tracking-tight text-textMain">SiDispo</div>
            <div class="text-xs text-textMuted">Sistem Disposisi Digital</div>
          </div>
        </div>

        <div class="mb-8">
          <h2 class="text-2xl font-bold text-textMain mb-1.5">Selamat Datang 👋</h2>
          <p class="text-textMuted text-sm">Silakan masuk menggunakan NIP atau Email Anda.</p>
        </div>

        <!-- Error Alert -->
        <div v-if="error"
          class="mb-5 p-3.5 rounded-xl bg-brandRedBg border border-brandRed/20 flex items-start gap-3 animate-[fadeIn_0.3s_ease]">
          <span class="text-brandRed text-lg leading-none shrink-0">⚠️</span>
          <div class="text-sm text-brandRed font-medium">{{ error }}</div>
        </div>

        <form @submit.prevent="handleLogin" class="flex flex-col gap-4">
          <!-- NIP/Email -->
          <div class="flex flex-col gap-1.5">
            <label class="text-xs font-bold tracking-wide text-textMuted uppercase">NIP / Email</label>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-textDim">👤</span>
              <input 
                v-model="username" type="text"
                placeholder="Masukkan NIP atau Email"
                class="w-full bg-surface2 border border-border rounded-xl py-3 pl-10 pr-4 text-sm text-textMain placeholder:text-textDim focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-all"
                :disabled="loading" required />
            </div>
          </div>
          
          <!-- Password -->
          <div class="flex flex-col gap-1.5">
            <div class="flex justify-between items-center">
              <label class="text-xs font-bold tracking-wide text-textMuted uppercase">Password</label>
              <a href="#" class="text-xs font-semibold text-accent hover:text-accentHover transition-colors">Lupa Password?</a>
            </div>
            <div class="relative">
              <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-textDim">🔒</span>
              <input 
                v-model="password"
                :type="showPass ? 'text' : 'password'"
                placeholder="••••••••"
                class="w-full bg-surface2 border border-border rounded-xl py-3 pl-10 pr-10 text-sm text-textMain placeholder:text-textDim focus:outline-none focus:border-accent focus:ring-2 focus:ring-accent/20 transition-all"
                :disabled="loading" required />
              <button type="button" @click="showPass = !showPass"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-textDim hover:text-textMain text-sm">
                {{ showPass ? '🙈' : '👁️' }}
              </button>
            </div>
          </div>

          <!-- Submit Button -->
          <button 
            type="submit"
            class="mt-2 w-full text-white font-bold py-3.5 px-4 rounded-xl transition-all flex items-center justify-center gap-2 group disabled:opacity-70 disabled:cursor-not-allowed shadow-lg"
            style="background: linear-gradient(135deg, #2e7d32, #4a9e4a); box-shadow: 0 4px 20px rgba(74,158,74,0.35);"
            :disabled="loading">
            <span v-if="loading" class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
            <span v-else>Masuk ke Sistem</span>
            <span v-if="!loading" class="group-hover:translate-x-1 transition-transform">→</span>
          </button>
        </form>

        <div class="mt-8 text-center border-t border-border pt-6">
          <div class="flex justify-center mb-3">
            <img src="/src/assets/logorsi.png" alt="RSI" class="w-8 h-8 opacity-40 object-contain" />
          </div>
          <p class="text-xs text-textDim">
            Versi 1.0.0 &copy; 2026 RSUD.<br>Dikembangkan oleh IT Internal.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

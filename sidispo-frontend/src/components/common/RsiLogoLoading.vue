<script setup>
import logoRsi from '@/assets/logorsi.png'

defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: 'Memverifikasi Kredensial...',
  },
  subtitle: {
    type: String,
    default: 'Menghubungkan ke Sistem Disposisi RSI Gondanglegi',
  },
})
</script>

<template>
  <Transition
    enter-active-class="transition-opacity duration-300 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-200 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="visible"
      class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/80 backdrop-blur-md select-none p-4"
    >
      <!-- Glass Card Center Container -->
      <div class="relative flex flex-col items-center max-w-sm w-full p-8 rounded-3xl bg-white/[0.04] border border-white/10 shadow-2xl backdrop-blur-xl text-center">
        
        <!-- Ambient radial glow behind spinner -->
        <div class="absolute -top-12 left-1/2 -translate-x-1/2 w-48 h-48 rounded-full bg-emerald-500/20 blur-3xl pointer-events-none"></div>

        <!-- Logo Spinner Container -->
        <div class="relative w-36 h-36 flex items-center justify-center mb-6">
          
          <!-- Outer Pulsing Radar Waves -->
          <div class="absolute inset-0 rounded-full border border-emerald-400/30 animate-ping opacity-25"></div>
          <div class="absolute inset-2 rounded-full border border-teal-300/20 animate-pulse"></div>

          <!-- Dual Orbiting SVG Rings -->
          <svg class="absolute inset-0 w-full h-full animate-spin-slow pointer-events-none" viewBox="0 0 100 100">
            <defs>
              <linearGradient id="ringGrad1" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#34d399" stop-opacity="1" />
                <stop offset="50%" stop-color="#2dd4bf" stop-opacity="0.6" />
                <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
              </linearGradient>
            </defs>
            <circle
              cx="50"
              cy="50"
              r="46"
              fill="none"
              stroke="url(#ringGrad1)"
              stroke-width="3"
              stroke-linecap="round"
              stroke-dasharray="80 180"
            />
          </svg>

          <svg class="absolute inset-1 w-[calc(100%-8px)] h-[calc(100%-8px)] animate-spin-reverse pointer-events-none" viewBox="0 0 100 100">
            <defs>
              <linearGradient id="ringGrad2" x1="100%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="#a7f3d0" stop-opacity="0.9" />
                <stop offset="60%" stop-color="#059669" stop-opacity="0.3" />
                <stop offset="100%" stop-color="#047857" stop-opacity="0" />
              </linearGradient>
            </defs>
            <circle
              cx="50"
              cy="50"
              r="46"
              fill="none"
              stroke="url(#ringGrad2)"
              stroke-width="2.5"
              stroke-linecap="round"
              stroke-dasharray="50 120"
            />
          </svg>

          <!-- Central Emblem with RSI Logo -->
          <div class="relative z-10 w-20 h-20 rounded-2xl bg-gradient-to-br from-white/15 to-white/5 border border-white/20 p-2 shadow-glow flex items-center justify-center backdrop-blur-sm animate-breathe">
            <img
              :src="logoRsi"
              alt="Logo RSI Gondanglegi"
              class="w-full h-full object-contain filter drop-shadow-md"
            />
          </div>
        </div>

        <!-- Typography & Dynamic Indicator -->
        <h3 class="text-lg font-bold text-white tracking-wide mb-1.5 flex items-center justify-center gap-1">
          <span>{{ title }}</span>
        </h3>
        
        <p class="text-xs text-emerald-200/70 max-w-xs leading-relaxed mb-5">
          {{ subtitle }}
        </p>

        <!-- Indeterminate Glowing Progress Bar -->
        <div class="w-44 h-1.5 bg-white/10 rounded-full overflow-hidden relative shadow-inner">
          <div class="absolute inset-y-0 bg-gradient-to-r from-emerald-400 via-teal-300 to-emerald-500 rounded-full animate-progress-glow"></div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* GPU Accelerated CSS Keyframes */
@keyframes spinSlow {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

@keyframes spinReverse {
  from { transform: rotate(360deg); }
  to { transform: rotate(0deg); }
}

@keyframes breathe {
  0%, 100% {
    transform: scale(1);
    box-shadow: 0 0 20px rgba(52, 211, 153, 0.25);
  }
  50% {
    transform: scale(1.05);
    box-shadow: 0 0 35px rgba(52, 211, 153, 0.45);
  }
}

@keyframes progressGlow {
  0% {
    left: -40%;
    width: 40%;
  }
  50% {
    left: 20%;
    width: 60%;
  }
  100% {
    left: 100%;
    width: 40%;
  }
}

.animate-spin-slow {
  animation: spinSlow 3s linear infinite;
  transform-origin: center;
}

.animate-spin-reverse {
  animation: spinReverse 2.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  transform-origin: center;
}

.animate-breathe {
  animation: breathe 2.4s ease-in-out infinite;
}

.animate-progress-glow {
  animation: progressGlow 1.5s ease-in-out infinite;
}
</style>

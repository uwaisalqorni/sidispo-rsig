import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import SuratMasukView from '../views/SuratMasukView.vue'
import LoginView from '../views/LoginView.vue'
import DisposisiView from '../views/DisposisiView.vue'
import DisposisiDetailView from '../views/DisposisiDetailView.vue'
import AdminUsersView from '../views/AdminUsersView.vue'
import AdminFoldersView from '../views/AdminFoldersView.vue'
import AdminSettingsView from '../views/AdminSettingsView.vue'
import AdminPerihalView from '../views/AdminPerihalView.vue'
import AdminAsalSuratView from '../views/AdminAsalSuratView.vue'
import AdminJabatanView from '../views/AdminJabatanView.vue'
import RtlView from '../views/RtlView.vue'
import RtlDetailView from '../views/RtlDetailView.vue'
import DashboardRtlView from '../views/DashboardRtlView.vue'
import ProfileView from '../views/ProfileView.vue'
import EkspedisiView from '../views/EkspedisiView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
    },
    {
      path: '/',
      name: 'dashboard',
      component: DashboardView,
      meta: { requiresAuth: true }
    },
    {
      path: '/surat-masuk',
      name: 'surat-masuk',
      component: SuratMasukView,
      meta: { requiresAuth: true, requiresSekretariat: true }
    },
    {
      path: '/disposisi',
      name: 'disposisi',
      component: DisposisiView,
      meta: { requiresAuth: true }
    },
    {
      path: '/disposisi/:id',
      name: 'disposisi-detail',
      component: DisposisiDetailView,
      meta: { requiresAuth: true }
    },
    {
      path: '/ekspedisi',
      name: 'ekspedisi',
      component: EkspedisiView,
      meta: { requiresAuth: true }
    },
    {
      path: '/selesai',
      redirect: { name: 'disposisi', query: { tab: 'Selesai' } }
    },
    {
      path: '/overdue',
      redirect: { name: 'disposisi', query: { tab: 'Overdue' } }
    },
    {
      path: '/rtl',
      name: 'rtl',
      component: RtlView,
      meta: { requiresAuth: true }
    },
    {
      path: '/rtl/:id',
      name: 'rtl-detail',
      component: RtlDetailView,
      meta: { requiresAuth: true }
    },
    {
      path: '/dashboard-rtl',
      name: 'dashboard-rtl',
      component: DashboardRtlView,
      meta: { requiresAuth: true }
    },
    {
      path: '/profile',
      name: 'profile',
      component: ProfileView,
      meta: { requiresAuth: true }
    },

    // ── ADMIN routes ──────────────────────────────────────────
    {
      path: '/admin/users',
      name: 'admin-users',
      component: AdminUsersView,
      meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
      path: '/admin/folders',
      name: 'admin-folders',
      component: AdminFoldersView,
      meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
      path: '/admin/settings',
      name: 'admin-settings',
      component: AdminSettingsView,
      meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
      path: '/admin/perihal',
      name: 'admin-perihal',
      component: AdminPerihalView,
      meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
      path: '/admin/asal-surat',
      name: 'admin-asal-surat',
      component: AdminAsalSuratView,
      meta: { requiresAuth: true, requiresAdmin: true }
    },
    {
      path: '/admin/jabatan',
      name: 'admin-jabatan',
      component: AdminJabatanView,
      meta: { requiresAuth: true, requiresAdmin: true }
    },
  ],
})

// Navigation Guard — Vue Router 4 style (return instead of next())
router.beforeEach((to) => {
  const token = localStorage.getItem('sidispo_token')
  const isAuthenticated = !!token

  // Belum login → redirect ke login
  if (to.meta.requiresAuth && !isAuthenticated) {
    return { name: 'login' }
  }

  // Sudah login tapi buka /login → redirect ke dashboard
  if (to.name === 'login' && isAuthenticated) {
    return { name: 'dashboard' }
  }

  // Guard halaman admin — cek role dari localStorage
  if (to.meta.requiresAdmin && isAuthenticated) {
    try {
      const user = JSON.parse(localStorage.getItem('sidispo_user') || '{}')
      if (user.role !== 'ADMIN') {
        return { name: 'dashboard' }
      }
    } catch {
      return { name: 'dashboard' }
    }
  }

  // Guard halaman sekretariat (Surat Masuk)
  if (to.meta.requiresSekretariat && isAuthenticated) {
    try {
      const user = JSON.parse(localStorage.getItem('sidispo_user') || '{}')
      if (!['ADMIN', 'DIREKTUR'].includes(user.role)) {
        return { name: 'dashboard' }
      }
    } catch {
      return { name: 'dashboard' }
    }
  }

  // Lanjutkan navigasi
  return true
})

export default router

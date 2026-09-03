import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/axios'
import router from '@/router'

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('sidispo_token') || null)
    const user = ref(JSON.parse(localStorage.getItem('sidispo_user') || 'null'))
    const loading = ref(false)
    const error = ref('')
    const profileLoading = ref(false)
    const profileError = ref('')
    const profileSuccess = ref('')

    const isAuthenticated = computed(() => !!token.value)
    const role = computed(() => user.value?.role || null)

    const apiBaseUrl = computed(() => {
        const base = import.meta.env.VITE_API_BASE_URL || 'http://192.168.0.194/SiDispo/api/v1'
        // Remove /api/v1 to get the base SiDispo URL
        return base.replace(/\/api\/v1\/?$/, '')
    })

    const fotoProfilUrl = computed(() => {
        if (!user.value?.foto_profil) return null
        return `${apiBaseUrl.value}/${user.value.foto_profil}`
    })

    async function login(username, password) {
        loading.value = true
        error.value = ''
        try {
            const { data } = await api.post('/auth/login', { username, password })
            token.value = data.token
            user.value = data.user
            localStorage.setItem('sidispo_token', data.token)
            localStorage.setItem('sidispo_user', JSON.stringify(data.user))
            router.push({ name: 'dashboard' })
        } catch (err) {
            error.value = err.response?.data?.message || 'Login gagal. Periksa NIP/Email dan password.'
        } finally {
            loading.value = false
        }
    }

    function logout() {
        token.value = null
        user.value = null
        localStorage.removeItem('sidispo_token')
        localStorage.removeItem('sidispo_user')
        router.push({ name: 'login' })
    }

    async function refreshUser() {
        try {
            const { data } = await api.get('/auth/me')
            if (data.status === 'success') {
                user.value = data.user
                localStorage.setItem('sidispo_user', JSON.stringify(data.user))
            }
        } catch { /* silent */ }
    }

    async function updateProfile(formData) {
        profileLoading.value = true
        profileError.value = ''
        profileSuccess.value = ''
        try {
            const { data } = await api.post('/auth/profile', formData, {
                headers: { 'Content-Type': 'multipart/form-data' }
            })
            if (data.status === 'success') {
                user.value = data.user
                localStorage.setItem('sidispo_user', JSON.stringify(data.user))
                profileSuccess.value = data.message
            }
            return data
        } catch (err) {
            profileError.value = err.response?.data?.message || 'Gagal memperbarui profil.'
            throw err
        } finally {
            profileLoading.value = false
        }
    }

    async function changePassword(payload) {
        profileLoading.value = true
        profileError.value = ''
        profileSuccess.value = ''
        try {
            const { data } = await api.put('/auth/change-password', payload)
            if (data.status === 'success') {
                profileSuccess.value = data.message
            }
            return data
        } catch (err) {
            profileError.value = err.response?.data?.message || 'Gagal mengubah password.'
            throw err
        } finally {
            profileLoading.value = false
        }
    }

    return {
        token, user, loading, error,
        profileLoading, profileError, profileSuccess,
        isAuthenticated, role, apiBaseUrl, fotoProfilUrl,
        login, logout, refreshUser, updateProfile, changePassword
    }
})

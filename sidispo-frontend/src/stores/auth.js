import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/axios'
import router from '@/router'

export const useAuthStore = defineStore('auth', () => {
    const token = ref(localStorage.getItem('sidispo_token') || null)
    const user = ref(JSON.parse(localStorage.getItem('sidispo_user') || 'null'))
    const loading = ref(false)
    const error = ref('')

    const isAuthenticated = computed(() => !!token.value)
    const role = computed(() => user.value?.role || null)

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

    return { token, user, loading, error, isAuthenticated, role, login, logout }
})

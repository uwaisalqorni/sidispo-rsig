import axios from 'axios'
import router from '@/router'

const api = axios.create({
    baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost/SiDispo/api/v1',
    timeout: 15000,
    headers: { 'Content-Type': 'application/json' }
})

// Request interceptor — otomatis sisipkan Bearer token
api.interceptors.request.use(config => {
    const token = localStorage.getItem('sidispo_token')
    if (token) {
        config.headers.Authorization = `Bearer ${token}`
    }
    return config
})

// Response interceptor — tangani 401 (token expired / invalid)
api.interceptors.response.use(
    res => res,
    err => {
        if (err.response?.status === 401) {
            localStorage.removeItem('sidispo_token')
            localStorage.removeItem('sidispo_user')
            router.push({ name: 'login' })
        }
        return Promise.reject(err)
    }
)

export default api

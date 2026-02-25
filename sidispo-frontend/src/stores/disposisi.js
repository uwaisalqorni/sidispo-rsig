import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export const useDisposisiStore = defineStore('disposisi', () => {
    const list = ref([])
    const detail = ref(null)
    const loading = ref(false)
    const error = ref('')

    async function fetchList(params = {}) {
        loading.value = true
        error.value = ''
        try {
            const { data } = await api.get('/disposisi', { params })
            list.value = data.data || []
        } catch (err) {
            error.value = err.response?.data?.message || 'Gagal memuat disposisi.'
        } finally {
            loading.value = false
        }
    }

    async function fetchDetail(id) {
        loading.value = true
        error.value = ''
        try {
            const { data } = await api.get(`/disposisi/${id}`)
            detail.value = data.data
        } catch (err) {
            error.value = err.response?.data?.message || 'Disposisi tidak ditemukan.'
        } finally {
            loading.value = false
        }
    }

    async function create(payload) {
        const { data } = await api.post('/disposisi', payload)
        await fetchList()
        return data
    }

    return { list, detail, loading, error, fetchList, fetchDetail, create }
})

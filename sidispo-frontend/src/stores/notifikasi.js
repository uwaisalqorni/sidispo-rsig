import { defineStore } from 'pinia'
import { ref } from 'vue'
import api from '@/api/axios'

export const useNotifikasiStore = defineStore('notifikasi', () => {
    const list = ref([])
    const unread = ref(0)
    const loading = ref(false)

    async function fetchList() {
        loading.value = true
        try {
            const { data } = await api.get('/notifikasi')
            list.value = data.data || []
            unread.value = list.value.filter(n => !n.dibaca_at).length
        } catch (e) {
            // silent
        } finally {
            loading.value = false
        }
    }

    async function markRead(id) {
        await api.put(`/notifikasi/${id}/baca`)
        const notif = list.value.find(n => n.id === id)
        if (notif) { notif.dibaca_at = new Date().toISOString() }
        unread.value = list.value.filter(n => !n.dibaca_at).length
    }

    async function markAllRead() {
        await api.put('/notifikasi/baca-semua')
        list.value.forEach(n => { n.dibaca_at = new Date().toISOString() })
        unread.value = 0
    }

    async function testSendEmail(targetEmail = '') {
        const { data } = await api.post('/admin/settings/test-email', { target_email: targetEmail })
        return data
    }

    return { list, unread, loading, fetchList, markRead, markAllRead, testSendEmail }
})

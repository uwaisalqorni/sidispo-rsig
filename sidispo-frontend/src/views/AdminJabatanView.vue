<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/api/axios'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import InputNumber from 'primevue/inputnumber'
import Textarea from 'primevue/textarea'
import Dropdown from 'primevue/dropdown'
import Tag from 'primevue/tag'
import { useToast } from 'primevue/usetoast'
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import { useConfirm } from 'primevue/useconfirm'
import ToggleButton from 'primevue/togglebutton'

const toast = useToast()
const confirm = useConfirm()

const jabatanList = ref([])
const hierarki = ref([])
const stats = ref([])
const loading = ref(false)
const saving = ref(false)

// Detail modal pegawai per jabatan
const showUserListDialog = ref(false)
const selectedJabatanForUsers = ref(null)

// Level presets guide
const LEVEL_PRESETS = [
  { level: 1, label: 'Level 1: Staf / Pelaksana', desc: 'Pelaksana tugas unit/instalasi di lapangan' },
  { level: 2, label: 'Level 2: Kepala Instalasi / Ruangan', desc: 'Koordinator/Kepala unit kerja lapangan' },
  { level: 3, label: 'Level 3: Kepala Bidang / Bagian', desc: 'Manajemen tingkat madya (Kabid/Kabag)' },
  { level: 4, label: 'Level 4: Wakil Direktur', desc: 'Pimpinan setingkat Wakil Direktur' },
  { level: 5, label: 'Level 5: Direktur', desc: 'Pimpinan tertinggi Rumah Sakit' },
  { level: 99, label: 'Level 99: Administrator', desc: 'Pengelola sistem IT' }
]

// Form state
const showDialog = ref(false)
const isEdit = ref(false)
const formData = ref({
  id: null,
  nama: '',
  kode: '',
  level: 1,
  deskripsi: '',
  parent_id: null,
  is_active: true
})

// Opsi Atasan Langsung (selalu bertipe Number id untuk mencocokkan dengan benar)
const parentOptions = computed(() => {
  const options = [{ label: '— Tidak ada atasan (Pucuk Pimpinan / Mandiri) —', value: null }]
  jabatanList.value
    .filter(j => parseInt(j.id) !== parseInt(formData.value.id) && (j.is_active == 1 || j.is_active === true))
    .sort((a, b) => parseInt(b.level) - parseInt(a.level)) // Urutkan dari level tinggi ke rendah
    .forEach(j => {
      options.push({
        label: `${j.nama} (Level ${j.level}${j.kode ? ' - ' + j.kode : ''})`,
        value: parseInt(j.id)
      })
    })
  return options
})

const loadData = async () => {
  loading.value = true
  try {
    const [jabRes, hierRes] = await Promise.all([
      api.get('/jabatan'),
      api.get('/jabatan/hierarki')
    ])
    jabatanList.value = jabRes.data.data || []
    hierarki.value = hierRes.data.data || []
    stats.value = hierRes.data.stats || []
  } catch (e) {
    toast.add({ severity: 'error', summary: 'Error', detail: 'Gagal memuat data jabatan', life: 3000 })
  } finally {
    loading.value = false
  }
}

const openCreate = () => {
  isEdit.value = false
  formData.value = {
    id: null,
    nama: '',
    kode: '',
    level: 1,
    deskripsi: '',
    parent_id: null,
    is_active: true
  }
  showDialog.value = true
}

const openEdit = (item) => {
  isEdit.value = true
  // Temukan parent dari hierarki dengan type-casting aman
  const hItem = hierarki.value.find(h => parseInt(h.id) === parseInt(item.id))
  formData.value = {
    id: parseInt(item.id),
    nama: item.nama || '',
    kode: item.kode || '',
    level: parseInt(item.level) || 1,
    deskripsi: item.deskripsi || '',
    parent_id: (hItem && hItem.parent_id) ? parseInt(hItem.parent_id) : null,
    is_active: item.is_active == 1 || item.is_active === true
  }
  showDialog.value = true
}

const selectLevelPreset = (level) => {
  formData.value.level = level
}

const saveJabatan = async () => {
  if (!formData.value.nama.trim()) {
    toast.add({ severity: 'warn', summary: 'Validasi', detail: 'Nama jabatan wajib diisi', life: 3000 })
    return
  }

  if (!formData.value.level || formData.value.level < 1) {
    toast.add({ severity: 'warn', summary: 'Validasi', detail: 'Level jabatan harus berupa angka minimal 1', life: 3000 })
    return
  }

  saving.value = true
  const payload = {
    nama: formData.value.nama.trim(),
    kode: formData.value.kode ? formData.value.kode.trim().toUpperCase() : null,
    level: parseInt(formData.value.level) || 1,
    deskripsi: formData.value.deskripsi ? formData.value.deskripsi.trim() : '',
    parent_id: formData.value.parent_id ? parseInt(formData.value.parent_id) : null,
    is_active: formData.value.is_active ? 1 : 0
  }

  try {
    if (isEdit.value) {
      await api.put(`/admin/jabatan/${formData.value.id}`, payload)
      toast.add({ severity: 'success', summary: 'Berhasil', detail: `Jabatan "${payload.nama}" berhasil diperbarui`, life: 3000 })
    } else {
      await api.post('/admin/jabatan', payload)
      toast.add({ severity: 'success', summary: 'Berhasil', detail: `Jabatan "${payload.nama}" berhasil ditambahkan`, life: 3000 })
    }
    showDialog.value = false
    await loadData()
  } catch (e) {
    const msg = e.response?.data?.message || 'Gagal menyimpan jabatan'
    toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 })
  } finally {
    saving.value = false
  }
}

const deleteJabatan = (item) => {
  const userCount = getUserCount(item.id)
  if (userCount > 0) {
    toast.add({
      severity: 'warn',
      summary: 'Tidak Dapat Dihapus',
      detail: `Jabatan "${item.nama}" masih digunakan oleh ${userCount} pegawai aktif. Pindahkan pegawai terlebih dahulu atau nonaktifkan status jabatan.`,
      life: 5000
    })
    return
  }

  confirm.require({
    message: `Hapus jabatan "${item.nama}"? Aksi ini akan menghapus jabatan dari master dan hierarki alur.`,
    header: 'Konfirmasi Hapus Jabatan',
    icon: 'pi pi-exclamation-triangle',
    rejectLabel: 'Batal',
    acceptLabel: 'Hapus',
    acceptClass: 'p-button-danger',
    accept: async () => {
      try {
        await api.delete(`/admin/jabatan/${item.id}`)
        toast.add({ severity: 'success', summary: 'Berhasil', detail: 'Jabatan berhasil dihapus', life: 3000 })
        await loadData()
      } catch (e) {
        const msg = e.response?.data?.message || 'Gagal menghapus jabatan'
        toast.add({ severity: 'error', summary: 'Error', detail: msg, life: 4000 })
      }
    }
  })
}

const getLevelSeverity = (level) => {
  const lv = parseInt(level)
  if (lv >= 99) return 'secondary'
  if (lv >= 5) return 'danger'
  if (lv >= 4) return 'warn'
  if (lv >= 3) return 'info'
  if (lv >= 2) return 'success'
  return 'secondary'
}

const getLevelLabel = (level) => {
  const lv = parseInt(level)
  if (lv >= 99) return 'Lv.99 (Admin)'
  return `Level ${lv}`
}

const getUserCount = (jabatanId) => {
  const s = stats.value.find(st => parseInt(st.id) === parseInt(jabatanId))
  return s ? parseInt(s.total_users) : 0
}

const getUserNames = (jabatanId) => {
  const s = stats.value.find(st => parseInt(st.id) === parseInt(jabatanId))
  return s?.user_names || ''
}

const openUserList = (jabatan) => {
  selectedJabatanForUsers.value = {
    ...jabatan,
    count: getUserCount(jabatan.id),
    names: getUserNames(jabatan.id) ? getUserNames(jabatan.id).split(', ') : []
  }
  showUserListDialog.value = true
}

const getParentNama = (jabatanId) => {
  const h = hierarki.value.find(item => parseInt(item.id) === parseInt(jabatanId))
  return h?.parent_nama || '—'
}

// Hierarchy tree for visualization
const treeData = computed(() => {
  const items = [...hierarki.value].sort((a, b) => parseInt(a.level) - parseInt(b.level))
  return items.filter(i => i.is_active == 1 || i.is_active === true)
})

onMounted(loadData)
</script>

<template>
  <Toast />
  <ConfirmDialog />

  <div class="p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold text-textMain flex items-center gap-3">
          <i class="pi pi-sitemap text-brandGreen"></i>
          Master Jabatan & Hierarki
        </h1>
        <p class="text-textMuted text-sm mt-1">
          Kelola struktur jabatan, hierarki atasan-bawahan, dan urutan alur validasi berjenjang
        </p>
      </div>
      <Button label="Tambah Jabatan" icon="pi pi-plus" class="btn-gradient" @click="openCreate" />
    </div>

    <!-- Hierarchy Visualization -->
    <div class="card mb-6 p-5 bg-gradient-to-r from-brandGreenBg to-surface2 rounded-2xl border border-brandGreen/20">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-sm font-bold text-brandGreen flex items-center gap-2 m-0">
          <i class="pi pi-share-alt"></i> Pohon Rantai Hierarki Validasi Disposisi
        </h3>
        <span class="text-xs text-textMuted bg-surface px-2.5 py-1 rounded-full border border-border">
          Alur dari kiri (Level Terendah) ke kanan (Level Tertinggi)
        </span>
      </div>

      <div class="flex items-center gap-2 flex-wrap justify-center py-2">
        <template v-for="(item, idx) in treeData" :key="item.id">
          <div 
            class="flex flex-col items-center px-4 py-2.5 rounded-xl bg-surface shadow-xs border border-border min-w-[130px] transition-all hover:shadow-md cursor-pointer"
            v-tooltip.top="getUserNames(item.id) ? `Pegawai: ${getUserNames(item.id)}` : 'Belum ada pegawai aktif'"
            @click="openUserList(item)"
          >
            <Tag :value="getLevelLabel(item.level)" :severity="getLevelSeverity(item.level)" class="mb-1 !text-[10px]" />
            <span class="jabatan-name text-sm text-center">{{ item.nama }}</span>
            <span class="text-[11px] text-textMuted mt-1 flex items-center gap-1 font-medium">
              <i class="pi pi-users text-[10px] text-brandBlue"></i>
              {{ getUserCount(item.id) }} pegawai
            </span>
          </div>
          <div v-if="idx < treeData.length - 1" class="text-textDim text-xl px-1">
            <i class="pi pi-arrow-right text-brandGreen font-bold"></i>
          </div>
        </template>
      </div>
      <p class="text-[11px] text-textMuted mt-3 text-center italic">
        💡 Pada disposisi berjenjang, penerima di level bawah harus menyelesaikan validasi terlebih dahulu sebelum level di atasnya dapat mengisi progress.
      </p>
    </div>

    <!-- Table -->
    <div class="card rounded-2xl overflow-hidden border border-border bg-surface shadow-xs">
      <DataTable
        :value="jabatanList"
        :loading="loading"
        stripedRows
        responsiveLayout="scroll"
        class="p-datatable-sm"
        :rowHover="true"
        sortField="level"
        :sortOrder="1"
      >
        <Column field="id" header="ID" sortable style="width: 60px">
          <template #body="{ data }">
            <span class="font-mono text-xs text-textMuted">{{ data.id }}</span>
          </template>
        </Column>

        <Column field="nama" header="Nama Jabatan" sortable>
          <template #body="{ data }">
            <div class="jabatan-name text-sm">{{ data.nama }}</div>
            <div v-if="data.deskripsi" class="text-xs text-textMuted mt-0.5">{{ data.deskripsi }}</div>
          </template>
        </Column>

        <Column field="kode" header="Kode" sortable style="width: 130px">
          <template #body="{ data }">
            <code v-if="data.kode" class="text-xs px-2 py-0.5 rounded bg-brandGreenBg text-brandGreen font-mono font-bold border border-brandGreen/20">{{ data.kode }}</code>
            <span v-else class="text-textDim">—</span>
          </template>
        </Column>

        <Column field="level" header="Level Hierarki" sortable style="width: 120px">
          <template #body="{ data }">
            <Tag :value="getLevelLabel(data.level)" :severity="getLevelSeverity(data.level)" />
          </template>
        </Column>

        <Column header="Atasan Langsung" style="width: 180px">
          <template #body="{ data }">
            <div v-if="getParentNama(data.id) !== '—'" class="flex items-center gap-1.5">
              <i class="pi pi-arrow-up-right text-brandGreen text-xs"></i>
              <span class="jabatan-name text-xs">{{ getParentNama(data.id) }}</span>
            </div>
            <span v-else class="text-xs text-textDim italic">Pucuk / Mandiri</span>
          </template>
        </Column>

        <!-- Kolom Pegawai Terdaftar dengan keterangan jelas -->
        <Column header="Pegawai Terdaftar" style="width: 160px">
          <template #body="{ data }">
            <div 
              class="inline-flex items-center gap-1.5 cursor-pointer group"
              v-tooltip.top="getUserNames(data.id) ? `Pegawai: ${getUserNames(data.id)} (Klik untuk melihat)` : 'Belum ada pegawai dengan jabatan ini'"
              @click="openUserList(data)"
            >
              <span 
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-all group-hover:scale-105"
                :class="getUserCount(data.id) > 0 ? 'bg-brandBlueBg text-brandBlue border border-brandBlue/30 hover:bg-brandBlue/20' : 'bg-surface3 text-textDim border border-border'"
              >
                <i class="pi pi-users text-xs"></i>
                <span>{{ getUserCount(data.id) }} Pegawai</span>
              </span>
            </div>
          </template>
        </Column>

        <Column field="is_active" header="Status" style="width: 100px">
          <template #body="{ data }">
            <Tag :value="data.is_active ? 'Aktif' : 'Nonaktif'" :severity="data.is_active ? 'success' : 'danger'" />
          </template>
        </Column>

        <Column header="Aksi" style="width: 110px">
          <template #body="{ data }">
            <div class="flex gap-1">
              <Button icon="pi pi-pencil" text rounded severity="info" size="small" @click="openEdit(data)" v-tooltip.top="'Edit Jabatan'" />
              <Button icon="pi pi-trash" text rounded severity="danger" size="small" @click="deleteJabatan(data)" v-tooltip.top="'Hapus Jabatan'" />
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
  </div>

  <!-- Dialog Form Tambah / Edit Jabatan -->
  <Dialog
    v-model:visible="showDialog"
    :header="isEdit ? '✏️ Edit Jabatan' : '➕ Tambah Jabatan Baru'"
    :style="{ width: '540px' }"
    modal
    :closable="true"
    class="p-dialog-custom"
  >
    <div class="flex flex-col gap-4 pt-2">
      <!-- Nama Jabatan -->
      <div>
        <label class="block text-sm font-semibold text-textMain mb-1">
          Nama Jabatan <span class="text-red-500">*</span>
        </label>
        <InputText 
          v-model="formData.nama" 
          class="w-full" 
          placeholder="Contoh: Kepala Bidang Keuangan" 
          autofocus
        />
      </div>

      <!-- Kode & Level -->
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-semibold text-textMain mb-1">
            Kode Singkat (Opsional)
          </label>
          <InputText 
            v-model="formData.kode" 
            class="w-full font-mono uppercase" 
            placeholder="KABID_KEU" 
            @input="formData.kode = formData.kode.toUpperCase()"
          />
          <small class="text-[11px] text-textMuted">Contoh: STAFF, KABID, DIREKTUR</small>
        </div>
        <div>
          <label class="block text-sm font-semibold text-textMain mb-1">
            Level Hierarki <span class="text-red-500">*</span>
          </label>
          <InputNumber 
            v-model="formData.level" 
            class="w-full" 
            :min="1" 
            :max="99" 
            showButtons 
          />
          <small class="text-[11px] text-textMuted">Angka lebih tinggi = Posisi lebih atas</small>
        </div>
      </div>

      <!-- Quick Preset Level -->
      <div class="p-3 bg-surface2 rounded-xl border border-border">
        <label class="block text-xs font-bold text-textMuted mb-2">Pilih Cepat Level Standar RSI:</label>
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="p in LEVEL_PRESETS"
            :key="p.level"
            type="button"
            @click="selectLevelPreset(p.level)"
            class="px-2.5 py-1 rounded-lg text-xs font-semibold transition-all border text-left"
            :class="formData.level === p.level 
              ? 'bg-brandGreen text-white border-brandGreen shadow-xs' 
              : 'bg-surface text-textMain border-border hover:border-brandGreen hover:text-brandGreen'"
          >
            {{ p.label }}
          </button>
        </div>
      </div>

      <!-- Atasan Langsung -->
      <div>
        <label class="block text-sm font-semibold text-textMain mb-1">
          Atasan Langsung (Hierarki Validasi)
        </label>
        <Dropdown
          v-model="formData.parent_id"
          :options="parentOptions"
          optionLabel="label"
          optionValue="value"
          class="w-full"
          placeholder="Pilih atasan langsung..."
          filter
          filterPlaceholder="Cari jabatan atasan..."
          showClear
        />
        <small class="text-[11px] text-textMuted mt-1 block">
          Penerima jabatan ini akan melapor dan divalidasi oleh jabatan atasan yang dipilih.
        </small>
      </div>

      <!-- Deskripsi -->
      <div>
        <label class="block text-sm font-semibold text-textMain mb-1">Deskripsi / Catatan Tugas</label>
        <Textarea 
          v-model="formData.deskripsi" 
          class="w-full" 
          rows="2" 
          placeholder="Deskripsi singkat fungsi atau wewenang jabatan..." 
        />
      </div>

      <!-- Status Aktif -->
      <div class="flex items-center justify-between p-3 rounded-xl bg-surface2 border border-border">
        <div>
          <div class="text-sm font-semibold text-textMain">Status Jabatan</div>
          <div class="text-xs text-textMuted">Jabatan aktif dapat dipilih pada akun pegawai & hierarki disposisi</div>
        </div>
        <ToggleButton 
          v-model="formData.is_active" 
          onLabel="Aktif" 
          offLabel="Nonaktif" 
          onIcon="pi pi-check" 
          offIcon="pi pi-times" 
          class="!text-xs"
        />
      </div>
    </div>

    <template #footer>
      <div class="flex justify-end gap-2 pt-3 border-t border-border">
        <Button label="Batal" severity="secondary" text @click="showDialog = false" :disabled="saving" />
        <Button 
          :label="isEdit ? 'Simpan Perubahan' : 'Tambah Jabatan'" 
          icon="pi pi-check" 
          class="btn-gradient" 
          :loading="saving" 
          @click="saveJabatan" 
        />
      </div>
    </template>
  </Dialog>

  <!-- Dialog Daftar Pegawai yang Memegang Jabatan -->
  <Dialog
    v-model:visible="showUserListDialog"
    :header="`👥 Pegawai dengan Jabatan: ${selectedJabatanForUsers?.nama || ''}`"
    :style="{ width: '480px' }"
    modal
    :closable="true"
    class="p-dialog-custom"
  >
    <div class="pt-2">
      <div class="mb-4 p-3 bg-brandGreenBg rounded-xl border border-brandGreen/20 flex items-center justify-between">
        <div>
          <span class="text-xs text-textMuted">Tingkat Hierarki:</span>
          <div class="text-sm font-bold text-brandGreen">
            {{ getLevelLabel(selectedJabatanForUsers?.level) }}
          </div>
        </div>
        <div>
          <Tag :value="`${selectedJabatanForUsers?.count || 0} Pegawai Aktif`" severity="info" rounded />
        </div>
      </div>

      <div v-if="selectedJabatanForUsers?.names?.length > 0" class="divide-y divide-border border border-border rounded-xl overflow-hidden bg-surface">
        <div 
          v-for="(name, i) in selectedJabatanForUsers.names" 
          :key="i"
          class="p-3 flex items-center gap-3 hover:bg-surface2 transition-all"
        >
          <div class="w-8 h-8 rounded-full bg-brandBlueBg text-brandBlue flex items-center justify-center font-bold text-xs shrink-0">
            {{ name.charAt(0).toUpperCase() }}
          </div>
          <div class="flex-1">
            <div class="text-sm font-semibold text-textMain">{{ name }}</div>
            <div class="text-[11px] text-textMuted">Pegawai Aktif RSI Gondanglegi</div>
          </div>
          <i class="pi pi-check-circle text-brandGreen text-sm"></i>
        </div>
      </div>
      <div v-else class="text-center py-6 text-textMuted">
        <i class="pi pi-info-circle text-2xl mb-2 text-textDim"></i>
        <p class="text-sm">Belum ada akun pegawai yang dihubungkan dengan jabatan ini.</p>
        <p class="text-xs text-textDim mt-1">Anda dapat menghubungkannya melalui menu <strong>Admin &gt; Pengguna</strong>.</p>
      </div>
    </div>
    <template #footer>
      <div class="flex justify-end pt-2">
        <Button label="Tutup" severity="secondary" @click="showUserListDialog = false" />
      </div>
    </template>
  </Dialog>
</template>

<style scoped>
.jabatan-name {
  color: #2e7d32 !important;
  font-weight: 700;
}
</style>

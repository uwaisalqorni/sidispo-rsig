<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Admin Controller — Manajemen Pengguna, Folder, dan Konfigurasi
 * Hanya dapat diakses oleh user dengan role ADMIN
 */
class Admin extends MY_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->model('Admin_model');
        $this->load->model('Folder_model');
    }

    /** Middleware: pastikan sudah auth DAN role ADMIN */
    private function require_admin() {
        $this->require_auth();
        if ($this->current_user->role !== 'ADMIN') {
            $this->response(['status' => 'error', 'message' => 'Akses ditolak. Hanya Admin yang dapat mengakses endpoint ini.'], 403);
            exit();
        }
    }

    // ════════════════════════════════════════════════════════
    // USERS
    // ════════════════════════════════════════════════════════

    /** GET /api/v1/admin/users — Daftar semua pengguna */
    public function users_index() {
        $this->require_admin();
        $users = $this->Admin_model->get_all_users();
        $this->response(['status' => 'success', 'data' => $users]);
    }

    /** GET /api/v1/users — Daftar user aktif (untuk pilih penerima disposisi — semua role) */
    public function users_active() {
        $this->require_auth();
        $users = $this->Admin_model->get_active_users();
        $this->response(['status' => 'success', 'data' => $users]);
    }

    /** POST /api/v1/admin/users — Buat pengguna baru */
    public function users_create() {
        $this->require_admin();

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $this->input->post();

        $required = ['nip', 'nama_lengkap', 'email', 'password', 'role'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->response(['status' => 'error', 'message' => "Field '$field' wajib diisi."], 422);
                return;
            }
        }

        // Cek duplikat email / NIP
        if ($this->Admin_model->email_exists($data['email'])) {
            $this->response(['status' => 'error', 'message' => 'Email sudah digunakan.'], 409);
            return;
        }
        if ($this->Admin_model->nip_exists($data['nip'])) {
            $this->response(['status' => 'error', 'message' => 'NIP sudah digunakan.'], 409);
            return;
        }

        $allowed_roles = ['ADMIN', 'DIREKTUR', 'PEJABAT', 'STAF'];
        if (!in_array($data['role'], $allowed_roles)) {
            $this->response(['status' => 'error', 'message' => 'Role tidak valid. Gunakan: ADMIN, DIREKTUR, PEJABAT, atau STAF.'], 422);
            return;
        }

        $insert = [
            'nip'          => $data['nip'],
            'nama_lengkap' => $data['nama_lengkap'],
            'email'        => $data['email'],
            'password_hash'=> password_hash($data['password'], PASSWORD_BCRYPT),
            'jabatan'      => $data['jabatan'] ?? '',
            'unit'         => $data['unit'] ?? $data['unit_kerja'] ?? '',
            'role'         => $data['role'],
            'is_active'    => isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ];

        $id = $this->Admin_model->create_user($insert);
        if ($id) {
            $user = $this->Admin_model->get_user_by_id($id);
            $this->response(['status' => 'success', 'message' => 'Pengguna berhasil dibuat.', 'data' => $user], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal membuat pengguna.'], 500);
        }
    }

    /** GET /api/v1/admin/users/:id — Detail pengguna */
    public function users_show($id) {
        $this->require_admin();
        $user = $this->Admin_model->get_user_by_id($id);
        if (!$user) {
            $this->response(['status' => 'error', 'message' => 'Pengguna tidak ditemukan.'], 404);
            return;
        }
        $this->response(['status' => 'success', 'data' => $user]);
    }

    /** PUT /api/v1/admin/users/:id — Update pengguna */
    public function users_update($id) {
        $this->require_admin();

        $user = $this->Admin_model->get_user_by_id($id);
        if (!$user) {
            $this->response(['status' => 'error', 'message' => 'Pengguna tidak ditemukan.'], 404);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $this->input->post();

        $update = [];
        $fields = ['nip', 'nama_lengkap', 'email', 'jabatan', 'unit', 'role', 'is_active'];
        foreach ($fields as $f) {
            if (isset($data[$f])) $update[$f] = $data[$f];
        }
        // Handle unit_kerja alias
        if (isset($data['unit_kerja'])) $update['unit'] = $data['unit_kerja'];

        // Jika ganti email, cek duplikat
        if (!empty($update['email']) && $update['email'] !== $user['email']) {
            if ($this->Admin_model->email_exists($update['email'], $id)) {
                $this->response(['status' => 'error', 'message' => 'Email sudah digunakan.'], 409);
                return;
            }
        }

        // Ganti password jika dikirim
        if (!empty($data['password'])) {
            $update['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (!empty($update)) {
            $this->Admin_model->update_user($id, $update);
        }

        $user = $this->Admin_model->get_user_by_id($id);
        $this->response(['status' => 'success', 'message' => 'Pengguna berhasil diperbarui.', 'data' => $user]);
    }

    /** DELETE /api/v1/admin/users/:id — Nonaktifkan pengguna */
    public function users_delete($id) {
        $this->require_admin();

        // Jangan hapus diri sendiri
        if ((int)$id === (int)$this->current_user->id) {
            $this->response(['status' => 'error', 'message' => 'Tidak dapat menonaktifkan akun sendiri.'], 400);
            return;
        }

        $user = $this->Admin_model->get_user_by_id($id);
        if (!$user) {
            $this->response(['status' => 'error', 'message' => 'Pengguna tidak ditemukan.'], 404);
            return;
        }

        // Soft delete — nonaktifkan saja
        $this->Admin_model->update_user($id, ['is_active' => 0]);
        $this->response(['status' => 'success', 'message' => 'Pengguna berhasil dinonaktifkan.']);
    }

    /** POST /api/v1/admin/users/:id/reset-password — Reset password */
    public function users_reset_password($id) {
        $this->require_admin();

        $data = json_decode(file_get_contents('php://input'), true);
        $password = $data['password'] ?? 'Sidispo@2026';

        if (strlen($password) < 8) {
            $this->response(['status' => 'error', 'message' => 'Password minimal 8 karakter.'], 422);
            return;
        }

        $this->Admin_model->update_user($id, [
            'password_hash' => password_hash($password, PASSWORD_BCRYPT)
        ]);
        $this->response(['status' => 'success', 'message' => 'Password berhasil direset.']);
    }

    // ════════════════════════════════════════════════════════
    // FOLDERS
    // ════════════════════════════════════════════════════════

    /** GET /api/v1/admin/folders — List semua folder */
    public function folders_index() {
        $this->require_admin();
        $folders = $this->Folder_model->get_all_flat();
        $this->response(['status' => 'success', 'data' => $folders ?? []]);
    }

    /** POST /api/v1/admin/folders — Buat folder baru */
    public function folders_create() {
        $this->require_admin();

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $this->input->post();

        if (empty($data['nama'])) {
            $this->response(['status' => 'error', 'message' => 'Nama folder wajib diisi.'], 422);
            return;
        }

        $insert = [
            'nama'       => $data['nama'],
            'deskripsi'  => $data['deskripsi'] ?? '',
            'parent_id'  => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            'warna'      => $data['warna'] ?? '#4a9e4a',
            'urutan'     => (int)($data['urutan'] ?? 99),
            'created_by' => $this->current_user->id,
        ];

        $id = $this->Folder_model->create($insert);
        if ($id) {
            $folder = $this->Folder_model->get_by_id($id);
            $this->response(['status' => 'success', 'message' => 'Folder berhasil dibuat.', 'data' => $folder], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal membuat folder.'], 500);
        }
    }

    /** PUT /api/v1/admin/folders/:id — Update folder */
    public function folders_update($id) {
        $this->require_admin();

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) $data = $this->input->post();

        $update = [];
        $fields = ['nama', 'deskripsi', 'parent_id', 'warna', 'urutan'];
        foreach ($fields as $f) {
            if (isset($data[$f])) $update[$f] = $data[$f];
        }

        if (!empty($update)) {
            $this->Folder_model->update($id, $update);
        }

        $folder = $this->Folder_model->get_by_id($id);
        $this->response(['status' => 'success', 'message' => 'Folder berhasil diperbarui.', 'data' => $folder]);
    }

    /** DELETE /api/v1/admin/folders/:id — Hapus folder */
    public function folders_delete($id) {
        $this->require_admin();
        $this->Folder_model->delete($id);
        $this->response(['status' => 'success', 'message' => 'Folder berhasil dihapus.']);
    }

    // ════════════════════════════════════════════════════════
    // KONFIGURASI SISTEM
    // ════════════════════════════════════════════════════════

    /** GET /api/v1/admin/settings — Baca semua konfigurasi */
    public function settings_index() {
        $this->require_admin();
        $settings = $this->Admin_model->get_all_settings();
        $this->response(['status' => 'success', 'data' => $settings]);
    }

    /** PUT /api/v1/admin/settings — Simpan konfigurasi (bulk) */
    public function settings_update() {
        $this->require_admin();

        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !is_array($data)) {
            $this->response(['status' => 'error', 'message' => 'Data tidak valid.'], 422);
            return;
        }

        foreach ($data as $key => $value) {
            $this->Admin_model->upsert_setting($key, $value);
        }

        $settings = $this->Admin_model->get_all_settings();
        $this->response(['status' => 'success', 'message' => 'Konfigurasi berhasil disimpan.', 'data' => $settings]);
    }

    /** GET /api/v1/admin/stats — Statistik sistem untuk admin */
    public function system_stats() {
        $this->require_admin();
        $stats = $this->Admin_model->get_system_stats();
        $this->response(['status' => 'success', 'data' => $stats]);
    }
}

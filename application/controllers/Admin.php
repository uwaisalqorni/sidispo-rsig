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

    /** POST /api/v1/admin/settings/test-email — Kirim email uji coba SMTP */
    public function settings_test_email() {
        $this->require_admin();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $settings = $this->Admin_model->get_all_settings();

        $smtp_host   = $data['smtp_host'] ?? $settings['smtp_host'] ?? 'smtp.gmail.com';
        $smtp_port   = (int)($data['smtp_port'] ?? $settings['smtp_port'] ?? 587);
        $smtp_user   = $data['smtp_user'] ?? $settings['smtp_user'] ?? 'oktaimtiziliffa@gmail.com';
        $smtp_pass   = $data['smtp_pass'] ?? $settings['smtp_pass'] ?? 'zzua ooyl opsq kdqi';
        $smtp_crypto = $data['smtp_crypto'] ?? $settings['smtp_crypto'] ?? 'tls';
        $target_email= !empty($data['target_email']) ? trim($data['target_email']) : $smtp_user;

        if (empty($smtp_user) || empty($smtp_pass)) {
            $this->response(['status' => 'error', 'message' => 'Username dan Password SMTP wajib diisi.'], 422);
            return;
        }

        if (empty($target_email)) {
            $this->response(['status' => 'error', 'message' => 'Email tujuan wajib diisi.'], 422);
            return;
        }

        $config = [
            'protocol'    => 'smtp',
            'smtp_host'   => $smtp_host,
            'smtp_port'   => $smtp_port,
            'smtp_user'   => $smtp_user,
            'smtp_pass'   => $smtp_pass,
            'smtp_crypto' => $smtp_crypto,
            'mailtype'    => 'html',
            'charset'     => 'utf-8',
            'newline'     => "\r\n",
            'crlf'        => "\r\n"
        ];

        $this->load->library('email');
        $this->email->initialize($config);
        $this->email->from($smtp_user, $settings['nama_rs'] ?? 'SiDispo RSI Gondanglegi');
        $this->email->to($target_email);
        $this->email->subject('[SiDispo] Uji Coba Konfigurasi Notifikasi Email SMTP');
        $app_url = base_url('rsig');
        $sample_deadline = date('d F Y', strtotime('+7 days'));

        $this->email->message('
            <div style="font-family: Arial, Helvetica, sans-serif; max-width: 620px; margin: 0 auto; padding: 28px; border: 1px solid #cbd5e1; border-radius: 14px; background: #ffffff; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
                <div style="border-bottom: 2px solid #52b788; padding-bottom: 14px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <h2 style="color: #2d6a4f; margin: 0; font-size: 20px; font-weight: 800;">Uji Coba Notifikasi Email SMTP</h2>
                        <span style="font-size: 12px; color: #52b788; font-weight: 600;">SiDispo &bull; RSI Gondanglegi</span>
                    </div>
                </div>

                <p style="color: #334155; font-size: 14px; margin: 0 0 12px 0;">Halo,</p>
                <div style="background: #f0fdf4; border-left: 4px solid #52b788; padding: 14px 18px; margin: 16px 0; border-radius: 0 8px 8px 0;">
                    <p style="color: #166534; font-size: 14px; margin: 0; line-height: 1.6; font-weight: 500;">
                        Koneksi SMTP berhasil terhubung! Berikut ini adalah contoh tampilan notifikasi email disposisi yang akan diterima oleh pengguna.
                    </p>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin: 20px 0 24px 0;">
                    <div style="background: #f8fafc; padding: 10px 16px; border-bottom: 1px solid #e2e8f0; font-size: 12px; font-weight: bold; color: #475569; text-transform: uppercase; letter-spacing: 0.5px;">
                        📋 Contoh Rincian Dokumen Disposisi
                    </div>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; color: #64748b; width: 30%; vertical-align: top; font-weight: 600;">Asal Surat</td>
                            <td style="padding: 10px 16px; color: #0f172a; font-weight: 700;">Dinas Kesehatan Provinsi Jawa Timur</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Perihal</td>
                            <td style="padding: 10px 16px; color: #0f172a; font-weight: 600; line-height: 1.5;">Pemberitahuan Akreditasi &amp; Layanan Digital Terpadu RS 2026</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Nomor Surat</td>
                            <td style="padding: 10px 16px; color: #334155;">440/1284/102.1/2026</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Nomor Disposisi</td>
                            <td style="padding: 10px 16px; color: #334155; font-weight: 600;">DSP/2026/09/001</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Prioritas</td>
                            <td style="padding: 10px 16px;">
                                <span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#ffebee; color:#c62828; border:1px solid #ffcdd2;">🔴 URGENT</span>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9; background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Status</td>
                            <td style="padding: 10px 16px;">
                                <span style="display:inline-block; padding:4px 12px; border-radius:20px; font-size:11px; font-weight:bold; background:#e0f2f1; color:#00695c; border:1px solid #b2dfdb;">📩 DITERIMA</span>
                            </td>
                        </tr>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Batas Waktu</td>
                            <td style="padding: 10px 16px; color: #0f172a; font-weight: 600;">' . $sample_deadline . ' <span style="color:#2e7d32; font-weight:bold; font-size:11px;">(7 hari lagi)</span></td>
                        </tr>
                        <tr style="background: #fbfcfd;">
                            <td style="padding: 10px 16px; color: #64748b; vertical-align: top; font-weight: 600;">Instruksi Disposisi</td>
                            <td style="padding: 10px 16px; color: #1e293b; font-style: italic; line-height: 1.5;">Mohon dipelajari dan dipersiapkan berkas pendukung segera.</td>
                        </tr>
                    </table>
                </div>

                <div style="text-align: center; margin: 28px 0 20px 0;">
                    <a href="' . $app_url . '" style="background: linear-gradient(135deg, #2d6a4f 0%, #52b788 100%); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 13px; display: inline-block; box-shadow: 0 4px 12px rgba(45, 106, 79, 0.25);">
                        Buka Aplikasi SiDispo &rarr;
                    </a>
                </div>

                <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0 16px 0;">
                <p style="font-size: 11px; color: #94a3b8; margin: 0; line-height: 1.5; text-align: center;">
                    Host: ' . htmlspecialchars($smtp_host) . ':' . $smtp_port . ' (' . htmlspecialchars($smtp_crypto) . ') &bull; Waktu Uji Coba: ' . date('d-m-Y H:i:s') . ' WIB
                </p>
            </div>
        ');

        if ($this->email->send()) {
            $this->response([
                'status' => 'success',
                'message' => 'Email uji coba berhasil dikirim ke ' . $target_email
            ]);
        } else {
            $debug = $this->email->print_debugger(['headers', 'subject', 'body']);
            $this->response([
                'status' => 'error',
                'message' => 'Gagal mengirim email: ' . strip_tags($debug)
            ], 500);
        }
    }

    /** GET /api/v1/admin/stats — Statistik sistem untuk admin */
    public function system_stats() {
        $this->require_admin();
        $stats = $this->Admin_model->get_system_stats();
        $this->response(['status' => 'success', 'data' => $stats]);
    }
}

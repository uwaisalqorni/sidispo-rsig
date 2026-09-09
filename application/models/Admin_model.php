<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Admin_model — Operasi database untuk panel admin
 */
class Admin_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    // ── USERS ─────────────────────────────────────────────────────────────

    public function get_all_users() {
        return $this->db
            ->select('id, nip, nama_lengkap, email, no_hp, jabatan, jabatan_id, unit, role, is_active, created_at')
            ->order_by('role', 'ASC')
            ->order_by('nama_lengkap', 'ASC')
            ->get('users')
            ->result_array();
    }

    /** Only active users – for recipient selection (accessible by all roles) */
    public function get_active_users() {
        return $this->db
            ->select('id, nip, nama_lengkap, email, no_hp, jabatan, jabatan_id, unit, role')
            ->where('is_active', 1)
            ->order_by('role', 'ASC')
            ->order_by('nama_lengkap', 'ASC')
            ->get('users')
            ->result_array();
    }

    public function get_user_by_id($id) {
        return $this->db
            ->select('id, nip, nama_lengkap, email, no_hp, jabatan, jabatan_id, unit, role, is_active, created_at')
            ->where('id', $id)
            ->get('users')
            ->row_array();
    }

    public function email_exists($email, $exclude_id = null) {
        $this->db->where('email', $email);
        if ($exclude_id) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('users') > 0;
    }

    public function nip_exists($nip, $exclude_id = null) {
        $this->db->where('nip', $nip);
        if ($exclude_id) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('users') > 0;
    }

    public function create_user($data) {
        $this->db->insert('users', $data);
        return $this->db->insert_id();
    }

    public function update_user($id, $data) {
        $this->db->where('id', $id)->update('users', $data);
        return $this->db->affected_rows();
    }

    // ── SETTINGS ──────────────────────────────────────────────────────────

    /**
     * Baca semua konfigurasi sebagai key=>value map
     * Tabel: app_settings (key VARCHAR, value TEXT, deskripsi TEXT)
     */
    public function get_all_settings() {
        // Fallback — jika tabel belum ada, return defaults
        if (!$this->db->table_exists('app_settings')) {
            return $this->default_settings();
        }
        $rows = $this->db->get('app_settings')->result_array();
        $result = $this->default_settings();
        foreach ($rows as $row) {
            $result[$row['setting_key']] = $row['setting_value'];
        }
        return $result;
    }

    public function upsert_setting($key, $value) {
        if (!$this->db->table_exists('app_settings')) {
            $this->create_settings_table();
        }
        $exists = $this->db->where('setting_key', $key)->count_all_results('app_settings') > 0;
        if ($exists) {
            $this->db->where('setting_key', $key)->update('app_settings', ['setting_value' => $value]);
        } else {
            $this->db->insert('app_settings', ['setting_key' => $key, 'setting_value' => $value]);
        }
    }

    private function create_settings_table() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `app_settings` (
                `id` INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `setting_key` VARCHAR(100) NOT NULL UNIQUE,
                `setting_value` TEXT,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private function default_settings() {
        return [
            'nama_rs'             => 'RSUD',
            'alamat_rs'           => '',
            'telp_rs'             => '',
            'email_rs'            => '',
            'logo_rs'             => '',
            'batas_waktu_default' => '7',    // hari
            'notif_email'         => '1',    // 0=off,1=on
            'notif_system'        => '1',
            'smtp_host'           => 'smtp.gmail.com',
            'smtp_port'           => '587',
            'smtp_user'           => 'oktaimtiziliffa@gmail.com',
            'smtp_pass'           => 'zzua ooyl opsq kdqi',
            'smtp_crypto'         => 'tls',
            'jwt_expired_hours'   => '24',
            'max_upload_mb'       => '10',
            'versi_aplikasi'      => '1.0.0',
            'maintenance_mode'    => '0',
            'wa_enabled'            => '1',
            'wa_db_host'            => '192.168.0.194',
            'wa_db_port'            => '3306',
            'wa_db_user'            => 'root',
            'wa_db_pass'            => 'bismillah',
            'wa_db_name'            => 'wa_delphi3',
            'wa_outbox_table'       => 'wa_outbox',
            'wa_sender'             => 'NODEJS',
            'wa_source'             => 'SIDISPO',
            'wa_media_path'         => 'c:/xampp/htdocs/nodejs-gateway/media',
            'wa_send_file'          => '1',
            'wa_template_ekspedisi' => "*NOTIFIKASI EKSPEDISI SURAT MASUK*\n{nama_rs}\n\nYth. *{nama_penerima}*\n({jabatan} - {unit})\n\nDokumen resmi disposisi telah diekspedisikan kepada Anda dengan rincian:\n━━━━━━━━━━━━━━━━━━━━━━━\n📌 *No. Ekspedisi:* {nomor_ekspedisi}\n📨 *No. Surat:* {nomor_surat}\n🏢 *Asal Surat:* {asal_surat}\n📝 *Perihal:* {perihal}\n📅 *Tanggal Kirim:* {tanggal_kirim}\n📦 *Jenis Pengiriman:* {jenis_pengiriman}\n{catatan_pengiriman}\n━━━━━━━━━━━━━━━━━━━━━━━\n{keterangan_tambahan}\n\nSilakan akses sistem *SiDispo* pada menu *Ekspedisi Masuk* untuk memeriksa berkas digital dan melakukan konfirmasi serah terima dokumen.\n\nTerima kasih.\n_Sistem Informasi Disposisi RSI Gondanglegi_",
        ];
    }

    // ── SYSTEM STATS ──────────────────────────────────────────────────────

    public function get_system_stats() {
        $total_users    = $this->db->count_all('users');
        $active_users   = $this->db->where('is_active', 1)->count_all_results('users');
        $total_disposisi= $this->db->count_all('disposisi');
        $total_surat    = $this->db->count_all('surat_masuk');
        $total_folders  = $this->db->count_all('folders');
        $overdue = $this->db->where('status_global', 'ARSIP')->count_all_results('disposisi');
        $selesai = $this->db->where('status_global', 'SELESAI')->count_all_results('disposisi');

        // User per role
        $per_role = $this->db
            ->select('role, COUNT(*) as total')
            ->group_by('role')
            ->get('users')
            ->result_array();

        return [
            'total_users'     => $total_users,
            'active_users'    => $active_users,
            'total_disposisi' => $total_disposisi,
            'total_surat'     => $total_surat,
            'total_folders'   => $total_folders,
            'overdue'         => $overdue,
            'selesai'         => $selesai,
            'per_role'        => $per_role,
        ];
    }
}

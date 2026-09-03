<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Disposisi_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('Notifikasi_model', 'notifikasi');
    }

    /**
     * Get all active disposisi (ADMIN/DIREKTUR view)
     * Returns computed status_display based on penerima statuses and deadline
     */
    public function get_aktif($limit = 100, $offset = 0, $filters = [])
    {
        $where_clauses = ["d.status_global != 'ARSIP'"];
        $params = [];

        if (!empty($filters['q'])) {
            $q = '%' . trim($filters['q']) . '%';
            $where_clauses[] = "(d.nomor_disposisi LIKE ? OR sm.perihal LIKE ? OR sm.asal_surat LIKE ? OR sm.nomor_surat LIKE ? OR d.isi_disposisi LIKE ? OR u.nama_lengkap LIKE ?)";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        if (!empty($filters['prioritas'])) {
            $where_clauses[] = "d.prioritas = ?";
            $params[] = $filters['prioritas'];
        }

        if (!empty($filters['folder_id'])) {
            $where_clauses[] = "d.folder_id = ?";
            $params[] = (int)$filters['folder_id'];
        }

        if (!empty($filters['tanggal_dari'])) {
            $where_clauses[] = "DATE(d.tanggal_disposisi) >= ?";
            $params[] = $filters['tanggal_dari'];
        }

        if (!empty($filters['tanggal_sampai'])) {
            $where_clauses[] = "DATE(d.tanggal_disposisi) <= ?";
            $params[] = $filters['tanggal_sampai'];
        }

        $where_sql = implode(' AND ', $where_clauses);
        $params[] = $limit;
        $params[] = $offset;

        $sql = "
            SELECT
                d.id,
                d.nomor_disposisi,
                d.isi_disposisi,
                d.catatan_direktur,
                sm.perihal,
                sm.asal_surat,
                sm.nomor_surat,
                u.nama_lengkap AS pembuat,
                f.nama AS nama_folder,
                d.prioritas,
                d.batas_waktu,
                d.status_global,
                d.tanggal_disposisi,
                d.surat_masuk_id,
                d.folder_id,
                -- Hitung status granular dari penerima
                CASE
                    WHEN d.status_global = 'SELESAI' THEN 'SELESAI'
                    WHEN d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE() AND d.status_global = 'AKTIF' THEN 'OVERDUE'
                    WHEN EXISTS (SELECT 1 FROM disposisi_penerima dp2 WHERE dp2.disposisi_id = d.id AND dp2.status='PROSES') THEN 'PROSES'
                    WHEN EXISTS (SELECT 1 FROM disposisi_penerima dp2 WHERE dp2.disposisi_id = d.id AND dp2.status='TUNGGU') THEN 'TUNGGU'
                    WHEN EXISTS (SELECT 1 FROM disposisi_penerima dp2 WHERE dp2.disposisi_id = d.id AND dp2.status='OVERDUE') THEN 'OVERDUE'
                    ELSE 'AKTIF'
                END AS status_display,
                (SELECT GROUP_CONCAT(u2.nama_lengkap SEPARATOR ', ') FROM disposisi_penerima dp3 JOIN users u2 ON u2.id = dp3.user_id WHERE dp3.disposisi_id = d.id) AS nama_penerima_list
            FROM disposisi d
            JOIN surat_masuk sm ON d.surat_masuk_id = sm.id
            JOIN users u ON d.dibuat_oleh = u.id
            LEFT JOIN folders f ON d.folder_id = f.id
            WHERE {$where_sql}
            GROUP BY d.id
            ORDER BY d.id DESC
            LIMIT ? OFFSET ?
        ";
        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * Get disposisi assigned to a specific user (STAF/PEJABAT view)
     */
    public function get_by_user($user_id, $limit = 100, $offset = 0, $filters = [])
    {
        $where_clauses = ["dp.user_id = ?", "d.status_global != 'ARSIP'"];
        $params = [$user_id];

        if (!empty($filters['q'])) {
            $q = '%' . trim($filters['q']) . '%';
            $where_clauses[] = "(d.nomor_disposisi LIKE ? OR sm.perihal LIKE ? OR sm.asal_surat LIKE ? OR sm.nomor_surat LIKE ? OR d.isi_disposisi LIKE ? OR u.nama_lengkap LIKE ?)";
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        if (!empty($filters['prioritas'])) {
            $where_clauses[] = "d.prioritas = ?";
            $params[] = $filters['prioritas'];
        }

        if (!empty($filters['folder_id'])) {
            $where_clauses[] = "d.folder_id = ?";
            $params[] = (int)$filters['folder_id'];
        }

        if (!empty($filters['tanggal_dari'])) {
            $where_clauses[] = "DATE(d.tanggal_disposisi) >= ?";
            $params[] = $filters['tanggal_dari'];
        }

        if (!empty($filters['tanggal_sampai'])) {
            $where_clauses[] = "DATE(d.tanggal_disposisi) <= ?";
            $params[] = $filters['tanggal_sampai'];
        }

        $where_sql = implode(' AND ', $where_clauses);
        $params[] = $limit;
        $params[] = $offset;

        $sql = "
            SELECT
                d.id,
                d.nomor_disposisi,
                d.isi_disposisi,
                d.catatan_direktur,
                sm.perihal,
                sm.asal_surat,
                sm.nomor_surat,
                u.nama_lengkap AS pembuat,
                f.nama AS nama_folder,
                d.prioritas,
                d.batas_waktu,
                d.status_global,
                d.tanggal_disposisi,
                d.surat_masuk_id,
                d.folder_id,
                dp.status AS status_penerima,
                dp.id AS disposisi_penerima_id,
                -- Status display untuk filter tab
                CASE
                    WHEN dp.status = 'SELESAI' THEN 'SELESAI'
                    WHEN dp.status = 'OVERDUE' THEN 'OVERDUE'
                    WHEN d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE() THEN 'OVERDUE'
                    WHEN dp.status = 'PROSES' THEN 'PROSES'
                    WHEN dp.status = 'TUNGGU' THEN 'TUNGGU'
                    ELSE 'AKTIF'
                END AS status_display
            FROM disposisi_penerima dp
            JOIN disposisi d ON d.id = dp.disposisi_id
            JOIN surat_masuk sm ON sm.id = d.surat_masuk_id
            JOIN users u ON u.id = d.dibuat_oleh
            LEFT JOIN folders f ON f.id = d.folder_id
            WHERE {$where_sql}
            ORDER BY d.id DESC
            LIMIT ? OFFSET ?
        ";
        return $this->db->query($sql, $params)->result_array();
    }

    /**
     * Get single disposisi details
     */
    public function get_by_id($id)
    {
        $this->db->select('d.*, sm.perihal, sm.asal_surat, sm.nomor_surat, f.nama as nama_folder, u.nama_lengkap as pembuat');
        $this->db->from('disposisi d');
        $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id');
        $this->db->join('folders f', 'f.id = d.folder_id', 'left');
        $this->db->join('users u', 'u.id = d.dibuat_oleh');
        $this->db->where('d.id', $id);
        $disposisi = $this->db->get()->row_array();
        
        if ($disposisi) {
            // Get penerima list
            $this->db->select('dp.*, u.nama_lengkap, u.jabatan, u.unit');
            $this->db->from('disposisi_penerima dp');
            $this->db->join('users u', 'u.id = dp.user_id');
            $this->db->where('dp.disposisi_id', $id);
            $disposisi['penerima'] = $this->db->get()->result_array();
            
            // Get original file from surat_masuk
            $this->db->where('surat_masuk_id', $disposisi['surat_masuk_id']);
            $disposisi['files'] = $this->db->get('dokumen_file')->result_array();
        }
        
        return $disposisi;
    }

    /**
     * Insert new disposisi
     */
    public function insert($data, $penerima_ids)
    {
        $this->db->trans_start();

        // Generate nomor_disposisi D-YYXXXX
        $year = date('y');
        $this->db->select_max('id');
        $this->db->like('nomor_disposisi', "D-$year", 'after');
        $query = $this->db->get('disposisi')->row();
        $seq = $query->id ? ($query->id + 1) : 1;
        $data['nomor_disposisi'] = 'D-' . $year . str_pad($seq, 4, '0', STR_PAD_LEFT);

        $this->db->insert('disposisi', $data);
        $disposisi_id = $this->db->insert_id();

        // Insert assignees and Notifications
        if (!empty($penerima_ids)) {
            $penerima_batch = [];
            $notif_batch = [];
            
            // Generate pesan notif
            $this->db->select('nomor_surat, perihal');
            $sm = $this->db->get_where('surat_masuk', ['id' => $data['surat_masuk_id']])->row();
            $pesan = "Disposisi Baru: " . ($sm ? $sm->perihal : "Surat Masuk");

            foreach ($penerima_ids as $uid) {
                $penerima_batch[] = [
                    'disposisi_id' => $disposisi_id,
                    'user_id' => $uid,
                    'status' => 'DITERIMA'
                ];
                $notif_batch[] = [
                    'user_id' => $uid,
                    'jenis' => 'DISPOSISI_BARU',
                    'judul' => 'Disposisi Masuk',
                    'pesan' => $pesan,
                    'disposisi_id' => $disposisi_id
                ];
            }
            $this->db->insert_batch('disposisi_penerima', $penerima_batch);
            
            // Insert Notifikasi Batch & send emails
            if (!empty($notif_batch)) {
                $this->notifikasi->insert_batch($notif_batch);
            }
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === FALSE) {
            return false;
        }

        return $disposisi_id;
    }

    /**
     * Update progress/status from a penerima
     */
    public function update_progress($dp_id, $user_id, $status_baru, $catatan)
    {
        // Get dp info
        $dp = $this->db->get_where('disposisi_penerima', ['id' => $dp_id, 'user_id' => $user_id])->row_array();
        if (!$dp) return false;

        $this->db->trans_start();
        
        // 1. Log progress (Append-only)
        $this->db->insert('progress_log', [
            'disposisi_penerima_id' => $dp_id,
            'user_id' => $user_id,
            'status_lama' => $dp['status'],
            'status_baru' => $status_baru,
            'catatan' => $catatan
        ]);

        // 2. Update status in disposisi_penerima
        $update_data = ['status' => $status_baru, 'catatan_akhir' => $catatan];
        if ($status_baru == 'SELESAI') {
            $update_data['tanggal_selesai'] = date('Y-m-d H:i:s');
        }
        $this->db->where('id', $dp_id);
        $this->db->update('disposisi_penerima', $update_data);
        
        $this->db->update('disposisi_penerima', $update_data);
        
        // 3. Insert Notification to Disposisi Creator (Direktur/Admin)
        $this->db->select('d.dibuat_oleh, d.nomor_disposisi, u.nama_lengkap as nama_penerima');
        $this->db->from('disposisi d');
        $this->db->join('users u', "u.id = {$user_id}", 'left');
        $this->db->where('d.id', $dp['disposisi_id']);
        $creator_info = $this->db->get()->row();

        if ($creator_info && $creator_info->dibuat_oleh != $user_id) {
            $this->notifikasi->insert([
                'user_id' => $creator_info->dibuat_oleh,
                'jenis' => $status_baru == 'SELESAI' ? 'SELESAI' : 'UPDATE_PROGRESS',
                'judul' => 'Update Progress: ' . $creator_info->nomor_disposisi,
                'pesan' => "{$creator_info->nama_penerima} memperbarui status menjadi {$status_baru}.",
                'disposisi_id' => $dp['disposisi_id']
            ]);
        }
        
        // Trigger trg_check_selesai will auto-update disposisi global status if all finished

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    /**
     * Update disposisi details and assignees
     */
    public function update($id, $data, $penerima_ids = null)
    {
        $this->db->trans_start();

        $this->db->where('id', $id);
        $this->db->update('disposisi', $data);

        // Jika penerima_ids di-update
        if ($penerima_ids !== null && is_array($penerima_ids)) {
            // Ambil penerima yang sudah ada
            $existing_penerima = $this->db->get_where('disposisi_penerima', ['disposisi_id' => $id])->result_array();
            $existing_uids = array_map('intval', array_column($existing_penerima, 'user_id'));
            $new_uids_input = array_map('intval', $penerima_ids);

            // Penerima baru yang belum ada di daftar
            $new_uids = array_diff($new_uids_input, $existing_uids);
            
            // Penerima yang dihapus (hanya hapus yang statusnya masih DITERIMA / belum ada progress)
            $removed_uids = array_diff($existing_uids, $new_uids_input);
            if (!empty($removed_uids)) {
                $this->db->where('disposisi_id', $id);
                $this->db->where_in('user_id', $removed_uids);
                $this->db->where('status', 'DITERIMA');
                $this->db->delete('disposisi_penerima');
            }

            // Tambahkan penerima baru
            if (!empty($new_uids)) {
                $penerima_batch = [];
                $notif_batch = [];

                $disp = $this->db->get_where('disposisi', ['id' => $id])->row_array();
                $sm = $disp ? $this->db->get_where('surat_masuk', ['id' => $disp['surat_masuk_id']])->row() : null;
                $pesan = "Disposisi Masuk: " . ($sm ? $sm->perihal : "Surat Masuk");

                foreach ($new_uids as $uid) {
                    $penerima_batch[] = [
                        'disposisi_id' => $id,
                        'user_id' => $uid,
                        'status' => 'DITERIMA'
                    ];
                    $notif_batch[] = [
                        'user_id' => $uid,
                        'jenis' => 'DISPOSISI_BARU',
                        'judul' => 'Disposisi Masuk',
                        'pesan' => $pesan,
                        'disposisi_id' => $id
                    ];
                }
                $this->db->insert_batch('disposisi_penerima', $penerima_batch);

                if (!empty($notif_batch)) {
                    $this->notifikasi->insert_batch($notif_batch);
                }
            }
        }

        $this->db->trans_complete();

        return $this->db->trans_status();
    }

    /**
     * Delete disposisi
     */
    public function delete($id)
    {
        $this->db->where('id', $id);
        return $this->db->delete('disposisi');
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Dashboard extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->database();
    }

    /**
     * GET /api/v1/dashboard
     * Statistik ringkasan untuk dashboard (role-aware)
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $role    = $this->current_user->role;
        $user_id = (int)$this->current_user->id;
        $bulan   = date('Y-m');

        // --- Stats umum untuk DIREKTUR & ADMIN (global view) ---
        if ($role === 'DIREKTUR' || $role === 'ADMIN') {

            $sql = "
                SELECT
                    COUNT(*) as total_disposisi,
                    SUM(CASE WHEN d.status_global != 'SELESAI' THEN 1 ELSE 0 END) as total_aktif,
                    SUM(CASE WHEN d.status_global = 'SELESAI' THEN 1 ELSE 0 END) as total_selesai,
                    SUM(CASE WHEN d.status_global = 'SELESAI' AND (d.updated_at LIKE ? OR d.tanggal_disposisi LIKE ?) THEN 1 ELSE 0 END) as selesai_bulan_ini,
                    SUM(CASE WHEN d.status_global = 'AKTIF' AND (
                        (d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE())
                        OR EXISTS (SELECT 1 FROM disposisi_penerima dp WHERE dp.disposisi_id = d.id AND dp.status = 'OVERDUE')
                    ) THEN 1 ELSE 0 END) as total_overdue,
                    SUM(CASE WHEN d.status_global = 'AKTIF' AND NOT (
                        (d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE())
                        OR EXISTS (SELECT 1 FROM disposisi_penerima dp WHERE dp.disposisi_id = d.id AND dp.status = 'OVERDUE')
                    ) AND EXISTS (SELECT 1 FROM disposisi_penerima dp WHERE dp.disposisi_id = d.id AND dp.status = 'PROSES')
                    THEN 1 ELSE 0 END) as sedang_proses,
                    SUM(CASE WHEN d.status_global = 'AKTIF' AND NOT (
                        (d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE())
                        OR EXISTS (SELECT 1 FROM disposisi_penerima dp WHERE dp.disposisi_id = d.id AND dp.status = 'OVERDUE')
                    ) AND NOT EXISTS (SELECT 1 FROM disposisi_penerima dp WHERE dp.disposisi_id = d.id AND dp.status = 'PROSES')
                    THEN 1 ELSE 0 END) as menunggu
                FROM disposisi d
                WHERE d.status_global != 'ARSIP'
            ";
            $counts = $this->db->query($sql, [$bulan . '%', $bulan . '%'])->row_array();

            $total_disposisi   = (int)($counts['total_disposisi'] ?? 0);
            $total_aktif       = (int)($counts['total_aktif'] ?? 0);
            $total_selesai     = (int)($counts['total_selesai'] ?? 0);
            $selesai_bulan_ini = (int)($counts['selesai_bulan_ini'] ?? 0);
            $total_overdue     = (int)($counts['total_overdue'] ?? 0);
            $sedang_proses     = (int)($counts['sedang_proses'] ?? 0);
            $menunggu          = (int)($counts['menunggu'] ?? 0);

            // Progress per unit (% selesai dari yang ditugaskan)
            $this->db->select('u.unit, COUNT(dp.id) as total, SUM(IF(dp.status = "SELESAI", 1, 0)) as selesai');
            $this->db->from('disposisi_penerima dp');
            $this->db->join('users u', 'u.id = dp.user_id');
            $this->db->join('disposisi d', 'd.id = dp.disposisi_id');
            $this->db->where('d.status_global !=', 'ARSIP');
            $this->db->where('u.unit IS NOT NULL');
            $this->db->where('u.unit !=', '');
            $this->db->group_by('u.unit');
            $unit_progress = $this->db->get()->result_array();

            // Aktifitas terbaru (5 log terakhir)
            $this->db->select('pl.created_at, pl.status_baru, pl.catatan, u.nama_lengkap, d.id as disposisi_id, sm.perihal');
            $this->db->from('progress_log pl');
            $this->db->join('users u', 'u.id = pl.user_id', 'left');
            $this->db->join('disposisi_penerima dp', 'dp.id = pl.disposisi_penerima_id');
            $this->db->join('disposisi d', 'd.id = dp.disposisi_id');
            $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id', 'left');
            $this->db->where('d.status_global !=', 'ARSIP');
            $this->db->order_by('pl.created_at', 'DESC');
            $this->db->limit(5);
            $aktivitas_terbaru = $this->db->get()->result_array();

        } else {
            // --- Stats untuk STAF / PEJABAT (personal view) ---

            $sql = "
                SELECT
                    COUNT(*) as total_disposisi,
                    SUM(CASE WHEN dp.status != 'SELESAI' THEN 1 ELSE 0 END) as total_aktif,
                    SUM(CASE WHEN dp.status = 'SELESAI' THEN 1 ELSE 0 END) as total_selesai,
                    SUM(CASE WHEN dp.status = 'SELESAI' AND (dp.tanggal_selesai LIKE ? OR dp.updated_at LIKE ?) THEN 1 ELSE 0 END) as selesai_bulan_ini,
                    SUM(CASE WHEN dp.status != 'SELESAI' AND (
                        dp.status = 'OVERDUE'
                        OR (d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE())
                    ) THEN 1 ELSE 0 END) as total_overdue,
                    SUM(CASE WHEN dp.status = 'PROSES' AND NOT (
                        dp.status = 'OVERDUE'
                        OR (d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE())
                    ) THEN 1 ELSE 0 END) as sedang_proses,
                    SUM(CASE WHEN dp.status IN ('TUNGGU', 'DITERIMA') AND NOT (
                        dp.status = 'OVERDUE'
                        OR (d.batas_waktu IS NOT NULL AND d.batas_waktu < CURDATE())
                    ) THEN 1 ELSE 0 END) as menunggu
                FROM disposisi_penerima dp
                JOIN disposisi d ON d.id = dp.disposisi_id
                WHERE dp.user_id = ? AND d.status_global != 'ARSIP'
            ";
            $counts = $this->db->query($sql, [$bulan . '%', $bulan . '%', $user_id])->row_array();

            $total_disposisi   = (int)($counts['total_disposisi'] ?? 0);
            $total_aktif       = (int)($counts['total_aktif'] ?? 0);
            $total_selesai     = (int)($counts['total_selesai'] ?? 0);
            $selesai_bulan_ini = (int)($counts['selesai_bulan_ini'] ?? 0);
            $total_overdue     = (int)($counts['total_overdue'] ?? 0);
            $sedang_proses     = (int)($counts['sedang_proses'] ?? 0);
            $menunggu          = (int)($counts['menunggu'] ?? 0);

            // Progress unit staf sendiri
            $unit = $this->current_user->unit ?? null;
            if (!empty($unit)) {
                $this->db->select('u.unit, COUNT(dp.id) as total, SUM(IF(dp.status = "SELESAI", 1, 0)) as selesai');
                $this->db->from('disposisi_penerima dp');
                $this->db->join('users u', 'u.id = dp.user_id');
                $this->db->join('disposisi d', 'd.id = dp.disposisi_id');
                $this->db->where('d.status_global !=', 'ARSIP');
                $this->db->where('u.unit', $unit);
                $this->db->group_by('u.unit');
                $unit_progress = $this->db->get()->result_array();
            } else {
                $unit_progress = [];
            }

            // Aktifitas terbaru terkait disposisi staf ini
            $this->db->select('pl.created_at, pl.status_baru, pl.catatan, u.nama_lengkap, d.id as disposisi_id, sm.perihal');
            $this->db->from('progress_log pl');
            $this->db->join('users u', 'u.id = pl.user_id', 'left');
            $this->db->join('disposisi_penerima dp', 'dp.id = pl.disposisi_penerima_id');
            $this->db->join('disposisi d', 'd.id = dp.disposisi_id');
            $this->db->join('surat_masuk sm', 'sm.id = d.surat_masuk_id', 'left');
            $this->db->where('dp.user_id', $user_id);
            $this->db->where('d.status_global !=', 'ARSIP');
            $this->db->order_by('pl.created_at', 'DESC');
            $this->db->limit(5);
            $aktivitas_terbaru = $this->db->get()->result_array();
        }

        $this->response([
            'status' => 'success',
            'data'   => [
                'total_disposisi'    => $total_disposisi,
                'total_aktif'        => $total_aktif,
                'sedang_proses'      => $sedang_proses,
                'menunggu'           => $menunggu,
                'selesai'            => $total_selesai,
                'total_selesai'      => $total_selesai,
                'selesai_bulan_ini'  => $selesai_bulan_ini,
                'total_overdue'      => $total_overdue,
                'unit_progress'      => $unit_progress,
                'aktivitas_terbaru'  => $aktivitas_terbaru
            ]
        ], 200);
    }
}

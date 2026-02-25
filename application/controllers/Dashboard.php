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
        $user_id = $this->current_user->id;
        $bulan   = date('Y-m');

        // --- Stats umum untuk DIREKTUR & ADMIN (global view) ---
        if ($role === 'DIREKTUR' || $role === 'ADMIN') {

            // Total aktif (semua kecuali SELESAI & ARSIP)
            $this->db->from('disposisi');
            $this->db->where_not_in('status_global', ['SELESAI', 'ARSIP']);
            $total_aktif = $this->db->count_all_results();

            // Selesai bulan ini
            $this->db->from('disposisi');
            $this->db->where('status_global', 'SELESAI');
            $this->db->like('updated_at', $bulan);
            $selesai_bulan_ini = $this->db->count_all_results();

            // Overdue
            $this->db->from('disposisi');
            $this->db->where('status_global', 'OVERDUE');
            $total_overdue = $this->db->count_all_results();

            // Sedang diproses
            $this->db->from('disposisi');
            $this->db->where('status_global', 'PROSES');
            $sedang_proses = $this->db->count_all_results();

            // Progress per unit (% selesai dari yang ditugaskan)
            $this->db->select('u.unit, COUNT(dp.id) as total, SUM(IF(dp.status = "SELESAI", 1, 0)) as selesai');
            $this->db->from('disposisi_penerima dp');
            $this->db->join('users u', 'u.id = dp.user_id');
            $this->db->group_by('u.unit');
            $unit_progress = $this->db->get()->result_array();

            // Aktifitas terbaru (5 log terakhir)
            $this->db->select('pl.created_at, pl.status_baru, pl.catatan, u.nama_lengkap, d.id as disposisi_id');
            $this->db->from('progress_log pl');
            $this->db->join('users u', 'u.id = pl.user_id', 'left');
            $this->db->join('disposisi_penerima dp', 'dp.id = pl.disposisi_penerima_id');
            $this->db->join('disposisi d', 'd.id = dp.disposisi_id');
            $this->db->order_by('pl.created_at', 'DESC');
            $this->db->limit(5);
            $aktivitas_terbaru = $this->db->get()->result_array();

        } else {
            // --- Stats untuk STAF / PEJABAT (personal view) ---

            // Disposisi yang ditugaskan ke user ini
            $this->db->from('disposisi_penerima');
            $this->db->where('user_id', $user_id);
            $total_aktif = $this->db->count_all_results();

            $this->db->from('disposisi_penerima');
            $this->db->where('user_id', $user_id);
            $this->db->where('status', 'SELESAI');
            $selesai_bulan_ini = $this->db->count_all_results();

            $this->db->from('disposisi_penerima');
            $this->db->where('user_id', $user_id);
            $this->db->where('status', 'OVERDUE');
            $total_overdue = $this->db->count_all_results();

            $this->db->from('disposisi_penerima');
            $this->db->where('user_id', $user_id);
            $this->db->where('status', 'PROSES');
            $sedang_proses = $this->db->count_all_results();

            $unit_progress = [];
            $aktivitas_terbaru = [];
        }

        $this->response([
            'status' => 'success',
            'data'   => [
                'total_aktif'        => $total_aktif,
                'selesai_bulan_ini'  => $selesai_bulan_ini,
                'total_overdue'      => $total_overdue,
                'sedang_proses'      => $sedang_proses,
                'unit_progress'      => $unit_progress,
                'aktivitas_terbaru'  => $aktivitas_terbaru
            ]
        ], 200);
    }
}

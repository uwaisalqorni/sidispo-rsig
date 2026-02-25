<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class DashboardRtl extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->database();
    }

    /**
     * GET /api/v1/dashboard-rtl
     * Statistik ringkasan untuk dashboard RTL (role-aware)
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

            // Total aktif (Semua RTL yang belum DONE)
            $this->db->from('rtl');
            $this->db->where('status_progress !=', 'DONE');
            $total_aktif = $this->db->count_all_results();

            // Selesai bulan ini
            $this->db->from('rtl');
            $this->db->where('status_progress', 'DONE');
            // Assuming updated_at or created_at marks completion, let's use like on id as a fallback or if u have updated_at
            // Since we don't have updated_at in table based on previous query, we can query rtl_progress
            $this->db->from('rtl_progress');
            $this->db->where('status_baru', 'DONE');
            $this->db->like('rtl_progress.dibuat_at', $bulan);
            $this->db->group_by('rtl_id');
            $selesai_bulan_ini = $this->db->get()->num_rows();

            // Prioritas Tinggi
            $this->db->from('rtl');
            $this->db->where_in('prioritas', ['Penting', 'Segera', 'Rahasia']);
            $this->db->where('status_progress !=', 'DONE');
            $high_priority = $this->db->count_all_results();

            // Overdue
            $this->db->from('rtl');
            $this->db->where('batas_waktu <', date('Y-m-d'));
            $this->db->where('status_progress !=', 'DONE');
            $total_overdue = $this->db->count_all_results();

            // Aktifitas terbaru (5 log terakhir progress RTL)
            $this->db->select('rp.dibuat_at, rp.status_baru, rp.catatan, u.nama_lengkap, r.disposisi_id, r.id as rtl_id');
            $this->db->from('rtl_progress rp');
            $this->db->join('users u', 'u.id = rp.dibuat_oleh', 'left');
            $this->db->join('rtl r', 'r.id = rp.rtl_id');
            $this->db->order_by('rp.id', 'DESC');
            $this->db->limit(5);
            $aktivitas_terbaru = $this->db->get()->result_array();
            
            // Progress per RTL (Sebagai pengganti Progress per Unit)
            // Menghitung berapa persen penerima yang sudah DONE untuk RTL yang masih aktif
            $this->db->select('r.id, r.deskripsi_rtl as unit, COUNT(rp.id) as total, SUM(CASE WHEN rp.status = "DONE" THEN 1 ELSE 0 END) as selesai');
            $this->db->from('rtl r');
            $this->db->join('rtl_penerima rp', 'r.id = rp.rtl_id', 'left');
            $this->db->where('r.status_progress !=', 'DONE');
            $this->db->group_by('r.id');
            $this->db->order_by('r.diupdate_at', 'DESC');
            $this->db->limit(5);
            $unit_progress = $this->db->get()->result_array();

        } else {
            // --- Stats untuk STAF / PEJABAT (personal view) ---

            // Total aktif form my assignments
            $this->db->from('rtl_penerima rp');
            $this->db->join('rtl r', 'r.id = rp.rtl_id');
            $this->db->where('rp.user_id', $user_id);
            $this->db->where('rp.status !=', 'DONE');
            $total_aktif = $this->db->count_all_results();

            // Selesai bulan ini
            $this->db->from('rtl_progress prog');
            $this->db->join('rtl_penerima rp', 'rp.id = prog.rtl_penerima_id');
            $this->db->where('rp.user_id', $user_id);
            $this->db->where('prog.status_baru', 'DONE');
            $this->db->like('prog.dibuat_at', $bulan);
            $this->db->group_by('rp.rtl_id');
            $selesai_bulan_ini = $this->db->get()->num_rows();

            // Prioritas Tinggi
            $this->db->from('rtl_penerima rp');
            $this->db->join('rtl r', 'r.id = rp.rtl_id');
            $this->db->where('rp.user_id', $user_id);
            $this->db->where_in('r.prioritas', ['Penting', 'Segera', 'Rahasia']);
            $this->db->where('rp.status !=', 'DONE');
            $high_priority = $this->db->count_all_results();
            
            // Overdue
            $this->db->from('rtl_penerima rp');
            $this->db->join('rtl r', 'r.id = rp.rtl_id');
            $this->db->where('rp.user_id', $user_id);
            $this->db->where('r.batas_waktu <', date('Y-m-d'));
            $this->db->where('rp.status !=', 'DONE');
            $total_overdue = $this->db->count_all_results();

            $aktivitas_terbaru = [];
            
            // Progress per RTL view khusus (Tugas staf terkait)
            $this->db->select('r.id, r.deskripsi_rtl as unit, COUNT(rp_all.id) as total, SUM(CASE WHEN rp_all.status = "DONE" THEN 1 ELSE 0 END) as selesai');
            $this->db->from('rtl_penerima rp_me');
            $this->db->join('rtl r', 'r.id = rp_me.rtl_id');
            // Join lagi untuk mendapatkan total dari SEMUA penerima di RTL yang sama
            $this->db->join('rtl_penerima rp_all', 'r.id = rp_all.rtl_id', 'left');
            $this->db->where('rp_me.user_id', $user_id);
            $this->db->where('r.status_progress !=', 'DONE');
            $this->db->group_by('r.id');
            $this->db->order_by('r.diupdate_at', 'DESC');
            $this->db->limit(5);
            $unit_progress = $this->db->get()->result_array();
        }

        $this->response([
            'status' => 'success',
            'data'   => [
                'total_aktif'        => $total_aktif,
                'selesai_bulan_ini'  => $selesai_bulan_ini,
                'total_overdue'      => $total_overdue,
                'high_priority'      => $high_priority,
                'aktivitas_terbaru'  => $aktivitas_terbaru,
                'unit_progress'      => $unit_progress
            ]
        ], 200);
    }
}

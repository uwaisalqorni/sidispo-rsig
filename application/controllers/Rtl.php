<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rtl extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Rtl_model', 'rtl');
    }

    /**
     * GET /rtl
     * Get all RTL records
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 100;
        $offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;
        
        $role = $this->current_user->role;
        $user_id = $this->current_user->id;

        $data = $this->rtl->get_all($limit, $offset, $user_id, $role);
        
        $this->response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * GET /rtl/{id}
     * Get detail of RTL including timeline
     */
    public function detail($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $data = $this->rtl->get_detail_with_timeline($id);
        
        if (!$data) {
            return $this->response(['status' => 'error', 'message' => 'Data RTL tidak ditemukan'], 404);
        }

        $this->response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * GET /rtl/disposisi-selesai
     * Get list of completed disposisi for RTL creation dropdown
     */
    public function disposisi_selesai()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $data = $this->rtl->get_disposisi_selesai();
        
        $this->response([
            'status' => 'success',
            'data' => $data
        ], 200);
    }

    /**
     * POST /rtl/create
     * Create new RTL. Only admins.
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        // Only ADMIN can create RTL
        if ($this->current_user->role !== 'ADMIN') {
            return $this->response(['status' => 'error', 'message' => 'Akses ditolak. Hanya Admin yang dapat membuat RTL.'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $this->input->post();
        }

        if (empty($input['disposisi_id']) || empty($input['deskripsi_rtl'])) {
            return $this->response(['status' => 'error', 'message' => 'Disposisi dan Deskripsi RTL wajib diisi'], 400);
        }

        if (empty($input['penerima']) || !is_array($input['penerima'])) {
            return $this->response(['status' => 'error', 'message' => 'Minimal satu Penerima wajib dipilih'], 400);
        }

        $data = [
            'disposisi_id' => $input['disposisi_id'],
            'prioritas' => $input['prioritas'] ?? 'Biasa',
            'deskripsi_rtl' => $input['deskripsi_rtl'],
            'batas_waktu' => !empty($input['batas_waktu']) ? $input['batas_waktu'] : null,
            'status_progress' => 'TO_DO',
            'dibuat_oleh' => $this->current_user->id
        ];

        $id = $this->rtl->insert($data, $input['penerima']);

        if ($id) {
            $newRtl = $this->rtl->get_by_id($id);
            $this->response(['status' => 'success', 'message' => 'RTL berhasil dibuat', 'data' => $newRtl], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal membuat RTL'], 500);
        }
    }

    /**
     * PUT /rtl/progress/{id}
     * Update progress status of RTL. Only Admin or Director.
     */
    public function progress($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        // Only ADMIN or DIREKTUR can update RTL progress
        if (!in_array($this->current_user->role, ['ADMIN', 'DIREKTUR'])) {
            return $this->response(['status' => 'error', 'message' => 'Akses ditolak. Hanya Admin dan Direktur yang dapat update progress RTL.'], 403);
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $status_baru = $input['status'] ?? null;
        $catatan = $input['catatan'] ?? '';

        $valid_statuses = ['TO_DO', 'ON_PROGRESS', 'REVIEW', 'DONE'];
        if (!$status_baru || !in_array($status_baru, $valid_statuses)) {
            return $this->response(['status' => 'error', 'message' => 'Status tidak valid'], 400);
        }
        
        if (empty(trim($catatan))) {
            return $this->response(['status' => 'error', 'message' => 'Catatan progress wajib diisi.'], 400);
        }

        $success = $this->rtl->update_progress_penerima($id, $status_baru, trim($catatan), $this->current_user->id);

        if ($success) {
            $this->response(['status' => 'success', 'message' => 'Progress RTL berhasil diupdate'], 200);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal mengupdate progress RTL'], 500);
        }
    }
}

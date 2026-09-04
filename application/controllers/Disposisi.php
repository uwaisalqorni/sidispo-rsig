<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Disposisi extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Disposisi_model', 'disposisi');
        $this->load->model('Surat_model', 'surat');
        // Notifikasi_model would be loaded here later for notifications
    }

    /**
     * GET /disposisi
     * List based on role
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 100;
        $offset = $this->input->get('offset') ? (int)$this->input->get('offset') : 0;
        $role = $this->current_user->role;

        // Ambil filter dari query params
        $filters = [];
        $q               = $this->input->get('q');
        $prioritas       = $this->input->get('prioritas');
        $folder_id       = $this->input->get('folder_id');
        $tanggal_dari    = $this->input->get('tanggal_dari');
        $tanggal_sampai  = $this->input->get('tanggal_sampai');

        if (!empty($q))             $filters['q'] = trim($q);
        if (!empty($prioritas))     $filters['prioritas'] = trim($prioritas);
        if (!empty($folder_id))     $filters['folder_id'] = (int)$folder_id;
        if (!empty($tanggal_dari))   $filters['tanggal_dari'] = $tanggal_dari;
        if (!empty($tanggal_sampai)) $filters['tanggal_sampai'] = $tanggal_sampai;

        // Director & Admin see global tracking, Staff see their own assigned tasks
        if ($role === 'DIREKTUR' || $role === 'ADMIN') {
            $data = $this->disposisi->get_aktif($limit, $offset, $filters);
        } else {
            $data = $this->disposisi->get_by_user($this->current_user->id, $limit, $offset, $filters);
        }

        $this->response(['status' => 'success', 'data' => $data], 200);
    }
    
    /**
     * GET /disposisi/detail/{id}
     */
    public function detail($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID disposisi dibutuhkan'], 400);
        }

        $data = $this->disposisi->get_by_id($id);
        
        if ($data) {
            $this->response(['status' => 'success', 'data' => $data], 200);
        } else {
            $this->response(['status' => 'error', 'message' => 'Disposisi tidak ditemukan'], 404);
        }
    }

    /**
     * POST /disposisi/create
     * Create new disposisi (Director role usually)
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        // Validation
        if (empty($input['surat_masuk_id']) || empty($input['isi_disposisi']) || empty($input['penerima_ids'])) {
            return $this->response(['status' => 'error', 'message' => 'Isi disposisi, surat masuka, dan minimal 1 penerima harus diisi'], 400);
        }

        // Verify surat exists
        $surat = $this->surat->get_by_id($input['surat_masuk_id']);
        if (!$surat) {
            return $this->response(['status' => 'error', 'message' => 'Surat masuk tidak valid'], 404);
        }

        $data = [
            'surat_masuk_id' => $input['surat_masuk_id'],
            'folder_id' => isset($input['folder_id']) ? $input['folder_id'] : $surat['folder_id'], // Inherit from surat if not overridden
            'dibuat_oleh' => $this->current_user->id,
            'isi_disposisi' => $input['isi_disposisi'],
            'prioritas' => $input['prioritas'] ?? 'NORMAL',
            'batas_waktu' => $input['batas_waktu'] ?? null,
            'catatan_direktur' => $input['catatan_direktur'] ?? null
        ];

        $penerima_ids = is_array($input['penerima_ids']) ? $input['penerima_ids'] : explode(',', $input['penerima_ids']);

        $disposisi_id = $this->disposisi->insert($data, $penerima_ids);

        if ($disposisi_id) {
            // Trigger notifications here later
            
            $this->response([
                'status' => 'success',
                'message' => 'Disposisi berhasil dibuat dan diteruskan',
                'disposisi_id' => $disposisi_id
            ], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal membuat disposisi'], 500);
        }
    }

    /**
     * POST /disposisi/progress/{dp_id}
     * Update progress from staff
     */
    public function progress($dp_id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$dp_id) {
            return $this->response(['status' => 'error', 'message' => 'ID Penerima dibutuhkan'], 400);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        
        $status_baru = $input['status'] ?? null;
        $catatan = $input['catatan'] ?? null;

        if (!$status_baru) {
             return $this->response(['status' => 'error', 'message' => 'Status baru diperlukan'], 400);
        }

        // Ensure user is updating their own progress
        $updated = $this->disposisi->update_progress($dp_id, $this->current_user->id, $status_baru, $catatan);

        if ($updated) {
            $this->response(['status' => 'success', 'message' => 'Progress berhasil diperbarui'], 200);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal memperbarui progress atau unauthorized'], 400);
        }
    }

    /**
     * PUT /disposisi/{id} or POST /disposisi/{id}
     * Update metadata & penerima disposisi
     */
    public function update($id = null)
    {
        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID disposisi dibutuhkan'], 400);
        }

        $existing = $this->disposisi->get_by_id($id);
        if (!$existing) {
            return $this->response(['status' => 'error', 'message' => 'Disposisi tidak ditemukan'], 404);
        }

        // Authorization: Hanya ADMIN, DIREKTUR, atau pembuat disposisi yang boleh mengedit
        $role = $this->current_user->role;
        if ($role !== 'ADMIN' && $role !== 'DIREKTUR' && $existing['dibuat_oleh'] != $this->current_user->id) {
            return $this->response(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk mengedit disposisi ini'], 403);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true) ?? [];
        if (empty($input)) {
            $input = $this->input->post();
        }

        $update_data = [];
        if (isset($input['isi_disposisi']) && trim($input['isi_disposisi']) !== '') {
            $update_data['isi_disposisi'] = trim($input['isi_disposisi']);
        }
        if (isset($input['prioritas'])) {
            $update_data['prioritas'] = trim($input['prioritas']);
        }
        if (isset($input['batas_waktu'])) {
            $update_data['batas_waktu'] = !empty($input['batas_waktu']) ? $input['batas_waktu'] : null;
        }
        if (isset($input['catatan_direktur'])) {
            $update_data['catatan_direktur'] = trim($input['catatan_direktur']);
        }
        if (isset($input['folder_id'])) {
            $update_data['folder_id'] = !empty($input['folder_id']) ? (int)$input['folder_id'] : null;
        }

        $penerima_ids = null;
        if (isset($input['penerima_ids'])) {
            $penerima_ids = is_array($input['penerima_ids']) ? $input['penerima_ids'] : explode(',', $input['penerima_ids']);
        }

        if (empty($update_data) && $penerima_ids === null) {
            return $this->response(['status' => 'error', 'message' => 'Tidak ada perubahan data yang dikirim'], 400);
        }

        $success = $this->disposisi->update($id, $update_data, $penerima_ids);

        if ($success) {
            $updatedData = $this->disposisi->get_by_id($id);
            return $this->response([
                'status'  => 'success',
                'message' => 'Disposisi berhasil diperbarui',
                'data'    => $updatedData
            ], 200);
        } else {
            return $this->response(['status' => 'error', 'message' => 'Gagal memperbarui disposisi'], 500);
        }
    }

    /**
     * DELETE /disposisi/{id}
     * Hapus disposisi
     */
    public function destroy($id = null)
    {
        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID disposisi dibutuhkan'], 400);
        }

        $existing = $this->disposisi->get_by_id($id);
        if (!$existing) {
            return $this->response(['status' => 'error', 'message' => 'Disposisi tidak ditemukan'], 404);
        }

        // Authorization: Hanya ADMIN, DIREKTUR, atau pembuat disposisi yang boleh menghapus
        $role = $this->current_user->role;
        if ($role !== 'ADMIN' && $role !== 'DIREKTUR' && $existing['dibuat_oleh'] != $this->current_user->id) {
            return $this->response(['status' => 'error', 'message' => 'Anda tidak memiliki hak akses untuk menghapus disposisi ini'], 403);
        }

        $deleted = $this->disposisi->delete($id);

        if ($deleted) {
            return $this->response([
                'status'  => 'success',
                'message' => 'Disposisi berhasil dihapus'
            ], 200);
        } else {
            return $this->response(['status' => 'error', 'message' => 'Gagal menghapus disposisi'], 500);
        }
    }

    /**
     * GET /disposisi/penerima-options
     * Get list of active staff / users for recipient selection with optional search keyword and role
     */
    public function penerima_options()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $q = $this->input->get('q');
        $role = $this->input->get('role');
        $data = $this->disposisi->get_penerima_options($q, $role);

        $this->response(['status' => 'success', 'data' => $data], 200);
    }
}


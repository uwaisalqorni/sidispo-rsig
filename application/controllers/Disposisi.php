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

        // Director & Admin see global tracking, Staff see their own assigned tasks
        if ($role === 'DIREKTUR' || $role === 'ADMIN') {
            $data = $this->disposisi->get_aktif($limit, $offset);
        } else {
            $data = $this->disposisi->get_by_user($this->current_user->id, $limit, $offset);
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
}

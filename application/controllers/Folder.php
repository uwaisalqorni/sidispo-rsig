<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Folder extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth(); // Secure all endpoints
        $this->load->model('Folder_model', 'folder');
    }

    /**
     * GET /folder
     * List all folders (flattened)
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $folders = $this->folder->get_all();
        
        // Build Tree Structure optionally if frontend prefers it
        // OR just send flat and let frontend build it.
        $this->response([
            'status' => 'success',
            'data' => $folders
        ], 200);
    }

    /**
     * POST /folder/create
     * Create new folder
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);

        if (empty($input['nama'])) {
            return $this->response(['status' => 'error', 'message' => 'Nama folder dibutuhkan'], 400);
        }

        $data = [
            'nama' => $input['nama'],
            'deskripsi' => $input['deskripsi'] ?? null,
            'parent_id' => !empty($input['parent_id']) ? $input['parent_id'] : null,
            'warna' => $input['warna'] ?? '#2563eb',
            'urutan' => $input['urutan'] ?? 0,
            'created_by' => $this->current_user->id
        ];

        $id = $this->folder->insert($data);

        if ($id) {
            $this->response([
                'status' => 'success',
                'message' => 'Folder berhasil dibuat',
                'data' => array_merge(['id' => $id], $data)
            ], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal membuat folder'], 500);
        }
    }

    /**
     * PUT /folder/update/{id}
     * Update an existing folder
     */
    public function update($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID folder dibutuhkan'], 400);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        
        $folder = $this->folder->get_by_id($id);
        if (!$folder) {
            return $this->response(['status' => 'error', 'message' => 'Folder tidak ditemukan'], 404);
        }

        $data = [];
        if (isset($input['nama'])) $data['nama'] = $input['nama'];
        if (isset($input['deskripsi'])) $data['deskripsi'] = $input['deskripsi'];
        if (array_key_exists('parent_id', $input)) $data['parent_id'] = $input['parent_id'] ?: null;
        if (isset($input['warna'])) $data['warna'] = $input['warna'];
        if (isset($input['urutan'])) $data['urutan'] = $input['urutan'];

        if (empty($data)) {
            return $this->response(['status' => 'error', 'message' => 'Tidak ada data yang diupdate'], 400);
        }

        $updated = $this->folder->update($id, $data);

        if ($updated) {
            $this->response([
                'status' => 'success',
                'message' => 'Folder berhasil diupdate'
            ], 200);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal mengupdate folder'], 500);
        }
    }

    /**
     * DELETE /folder/delete/{id}
     * Deletes a folder by ID
     */
    public function delete($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID folder dibutuhkan'], 400);
        }

        $folder = $this->folder->get_by_id($id);
        if (!$folder) {
            return $this->response(['status' => 'error', 'message' => 'Folder tidak ditemukan'], 404);
        }

        $deleted = $this->folder->delete($id);

        if ($deleted) {
             $this->response([
                'status' => 'success',
                'message' => 'Folder berhasil dihapus'
            ], 200);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal menghapus folder. Mungkin masih memiliki sub-folder atau dokumen yang terhubung.'], 400);
        }
    }
}

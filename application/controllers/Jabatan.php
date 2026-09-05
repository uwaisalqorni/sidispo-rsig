<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Jabatan Controller — REST API untuk Master Jabatan & Hierarki
 */
class Jabatan extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->require_auth();
        $this->load->model('Jabatan_model', 'jabatan');
    }

    /**
     * GET /api/v1/jabatan
     * Get all jabatan (accessible by all authenticated users)
     */
    public function index()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $active_only = $this->input->get('active') === '1';
        $data = $this->jabatan->get_all($active_only);

        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/jabatan/{id}
     * Get jabatan detail
     */
    public function show($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID jabatan dibutuhkan'], 400);
        }

        $data = $this->jabatan->get_by_id($id);
        if (!$data) {
            return $this->response(['status' => 'error', 'message' => 'Jabatan tidak ditemukan'], 404);
        }

        $this->response(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/jabatan/hierarki
     * Get full hierarchy tree
     */
    public function hierarki()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        $data = $this->jabatan->get_hierarki();
        $stats = $this->jabatan->count_users_per_jabatan();

        $this->response([
            'status' => 'success',
            'data' => $data,
            'stats' => $stats
        ], 200);
    }

    /**
     * POST /api/v1/admin/jabatan
     * Create new jabatan (Admin only)
     */
    public function create()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if ($this->current_user->role !== 'ADMIN') {
            return $this->response(['status' => 'error', 'message' => 'Akses ditolak'], 403);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (!$input) $input = $this->input->post();

        if (empty($input['nama'])) {
            return $this->response(['status' => 'error', 'message' => 'Nama jabatan wajib diisi'], 400);
        }

        if (empty($input['level']) || !is_numeric($input['level'])) {
            return $this->response(['status' => 'error', 'message' => 'Level jabatan wajib diisi (angka)'], 400);
        }

        // Check unique kode
        if (!empty($input['kode']) && $this->jabatan->kode_exists($input['kode'])) {
            return $this->response(['status' => 'error', 'message' => 'Kode jabatan sudah digunakan'], 409);
        }

        $data = [
            'nama'      => trim($input['nama']),
            'kode'      => !empty($input['kode']) ? strtoupper(trim($input['kode'])) : null,
            'level'     => (int)$input['level'],
            'deskripsi' => $input['deskripsi'] ?? null,
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : 1
        ];

        $id = $this->jabatan->create($data);

        if ($id) {
            // Set parent if provided
            if (!empty($input['parent_id'])) {
                $this->jabatan->set_parent($id, (int)$input['parent_id']);
            }

            $jabatan = $this->jabatan->get_by_id($id);
            $this->response([
                'status'  => 'success',
                'message' => 'Jabatan berhasil ditambahkan',
                'data'    => $jabatan
            ], 201);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal menambahkan jabatan'], 500);
        }
    }

    /**
     * PUT /api/v1/admin/jabatan/{id}
     * Update jabatan (Admin only)
     */
    public function update($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if ($this->current_user->role !== 'ADMIN') {
            return $this->response(['status' => 'error', 'message' => 'Akses ditolak'], 403);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID jabatan dibutuhkan'], 400);
        }

        $existing = $this->jabatan->get_by_id($id);
        if (!$existing) {
            return $this->response(['status' => 'error', 'message' => 'Jabatan tidak ditemukan'], 404);
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        if (!$input) $input = $this->input->post();

        if (empty($input['nama'])) {
            return $this->response(['status' => 'error', 'message' => 'Nama jabatan wajib diisi'], 400);
        }

        // Check unique kode (exclude current)
        if (!empty($input['kode']) && $this->jabatan->kode_exists($input['kode'], $id)) {
            return $this->response(['status' => 'error', 'message' => 'Kode jabatan sudah digunakan'], 409);
        }

        $data = [
            'nama'      => trim($input['nama']),
            'kode'      => !empty($input['kode']) ? strtoupper(trim($input['kode'])) : null,
            'level'     => isset($input['level']) ? (int)$input['level'] : $existing['level'],
            'deskripsi' => $input['deskripsi'] ?? $existing['deskripsi'],
            'is_active' => isset($input['is_active']) ? (int)$input['is_active'] : $existing['is_active']
        ];

        $this->jabatan->update($id, $data);

        // Update parent if provided
        if (isset($input['parent_id'])) {
            $this->jabatan->set_parent($id, $input['parent_id'] ? (int)$input['parent_id'] : null);
        }

        $updated = $this->jabatan->get_by_id($id);
        $this->response([
            'status'  => 'success',
            'message' => 'Jabatan berhasil diperbarui',
            'data'    => $updated
        ], 200);
    }

    /**
     * DELETE /api/v1/admin/jabatan/{id}
     * Delete jabatan (Admin only)
     */
    public function destroy($id = null)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
        }

        if ($this->current_user->role !== 'ADMIN') {
            return $this->response(['status' => 'error', 'message' => 'Akses ditolak'], 403);
        }

        if (!$id) {
            return $this->response(['status' => 'error', 'message' => 'ID jabatan dibutuhkan'], 400);
        }

        $existing = $this->jabatan->get_by_id($id);
        if (!$existing) {
            return $this->response(['status' => 'error', 'message' => 'Jabatan tidak ditemukan'], 404);
        }

        $result = $this->jabatan->delete($id);

        if (is_array($result) && isset($result['error'])) {
            return $this->response(['status' => 'error', 'message' => $result['error']], 409);
        }

        if ($result) {
            $this->response(['status' => 'success', 'message' => 'Jabatan berhasil dihapus'], 200);
        } else {
            $this->response(['status' => 'error', 'message' => 'Gagal menghapus jabatan'], 500);
        }
    }
}

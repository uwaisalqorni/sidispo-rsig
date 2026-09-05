<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use \Firebase\JWT\JWT;

class Auth extends MY_Controller {

    public function __construct()
    {
        // Skip API Auth on login endpoint
        parent::__construct();
        $this->load->model('User_model', 'user');
    }

    /**
     * Endpoint: POST /auth/login
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->response(['status' => 'error', 'message' => 'Method not allowed'], 405);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        
        $username = isset($input['username']) ? $input['username'] : null;
        $password = isset($input['password']) ? $input['password'] : null;

        if (!$username || !$password) {
            $this->response(['status' => 'error', 'message' => 'Username/NIP and password are required'], 400);
            return;
        }

        $user = $this->user->get_user_by_nip_or_email($username);

        if ($user) {
            if (!$user['is_active']) {
                $this->response(['status' => 'error', 'message' => 'Account is inactive'], 403);
                return;
            }

            if ($this->user->verify_password($password, $user['password_hash'])) {
                // Generate JWT Token
                $payload = [
                    'iss' => base_url(),
                    'aud' => base_url(),
                    'iat' => time(),
                    'exp' => time() + (60 * 60 * 24), // 24 hours
                    'data' => [
                        'id' => $user['id'],
                        'nip' => $user['nip'],
                        'nama_lengkap' => $user['nama_lengkap'],
                        'email' => $user['email'],
                        'no_hp' => $user['no_hp'],
                        'role' => $user['role'],
                        'unit' => $user['unit'],
                        'jabatan' => $user['jabatan']
                    ]
                ];

                $token = JWT::encode($payload, $this->jwt_key, 'HS256');

                $this->response([
                    'status' => 'success',
                    'message' => 'Login successful',
                    'token' => $token,
                    'user' => $payload['data']
                ], 200);
                return;
            }
        }

        $this->response(['status' => 'error', 'message' => 'Invalid username or password'], 401);
    }
    
    /**
     * Endpoint: GET /auth/me
     * Verify current token
     */
    public function me()
    {
        $this->require_auth(); // Will exit if token is invalid
        
        $user = $this->user->get_user_by_id($this->current_user->id);
        
        if ($user) {
            unset($user['password_hash']);
            $this->response([
                'status' => 'success',
                'user' => $user
            ], 200);
        } else {
            $this->response([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }
    }

    /**
     * Endpoint: PUT /auth/profile
     * Update profile (nama_lengkap, email) + optional foto profil upload
     */
    public function update_profile()
    {
        $this->require_auth();

        $user_id = $this->current_user->id;
        $user = $this->user->get_user_by_id($user_id);

        if (!$user) {
            $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            return;
        }

        // Handle both multipart form-data and JSON
        $content_type = $this->input->get_request_header('Content-Type');
        
        if (strpos($content_type, 'multipart/form-data') !== false) {
            $nama_lengkap = $this->input->post('nama_lengkap');
            $email = $this->input->post('email');
            $no_hp = $this->input->post('no_hp');
        } else {
            $input = json_decode(trim(file_get_contents('php://input')), true);
            $nama_lengkap = isset($input['nama_lengkap']) ? $input['nama_lengkap'] : null;
            $email = isset($input['email']) ? $input['email'] : null;
            $no_hp = isset($input['no_hp']) ? $input['no_hp'] : null;
        }

        $update_data = [];

        if ($nama_lengkap && trim($nama_lengkap) !== '') {
            $update_data['nama_lengkap'] = trim($nama_lengkap);
        }

        if ($email && trim($email) !== '') {
            // Check if email already used by another user
            $this->db->where('email', trim($email));
            $this->db->where('id !=', $user_id);
            $existing = $this->db->get('users')->row();
            if ($existing) {
                $this->response(['status' => 'error', 'message' => 'Email sudah digunakan user lain.'], 422);
                return;
            }
            $update_data['email'] = trim($email);
        }

        if ($no_hp !== null) {
            $update_data['no_hp'] = trim($no_hp) !== '' ? trim($no_hp) : null;
        }

        // Handle foto profil upload
        if (!empty($_FILES['foto_profil']['name'])) {
            $upload_path = FCPATH . 'uploads/profil/';
            if (!is_dir($upload_path)) {
                mkdir($upload_path, 0777, true);
            }

            // Delete old photo if exists
            if (!empty($user['foto_profil']) && file_exists(FCPATH . $user['foto_profil'])) {
                unlink(FCPATH . $user['foto_profil']);
            }

            $config = [
                'upload_path'   => $upload_path,
                'allowed_types' => 'jpg|jpeg|png|gif|webp',
                'max_size'      => 2048, // 2MB
                'file_name'     => 'profil_' . $user_id . '_' . time()
            ];

            $this->load->library('upload', $config);
            $this->upload->initialize($config);

            if ($this->upload->do_upload('foto_profil')) {
                $file_data = $this->upload->data();
                $update_data['foto_profil'] = 'uploads/profil/' . $file_data['file_name'];
            } else {
                $this->response([
                    'status' => 'error',
                    'message' => 'Upload foto gagal: ' . $this->upload->display_errors('', '')
                ], 400);
                return;
            }
        }

        if (empty($update_data)) {
            $this->response(['status' => 'error', 'message' => 'Tidak ada data yang diubah.'], 400);
            return;
        }

        $this->user->update_user($user_id, $update_data);

        // Return updated user data
        $updated_user = $this->user->get_user_by_id($user_id);
        unset($updated_user['password_hash']);

        $this->response([
            'status' => 'success',
            'message' => 'Profil berhasil diperbarui.',
            'user' => $updated_user
        ]);
    }

    /**
     * Endpoint: PUT /auth/change-password
     * Change password (validate old password first)
     */
    public function change_password()
    {
        $this->require_auth();

        $user_id = $this->current_user->id;
        $user = $this->user->get_user_by_id($user_id);

        if (!$user) {
            $this->response(['status' => 'error', 'message' => 'User not found'], 404);
            return;
        }

        $input = json_decode(trim(file_get_contents('php://input')), true);
        $old_password = isset($input['old_password']) ? $input['old_password'] : null;
        $new_password = isset($input['new_password']) ? $input['new_password'] : null;
        $confirm_password = isset($input['confirm_password']) ? $input['confirm_password'] : null;

        if (!$old_password || !$new_password || !$confirm_password) {
            $this->response(['status' => 'error', 'message' => 'Semua field password harus diisi.'], 400);
            return;
        }

        if ($new_password !== $confirm_password) {
            $this->response(['status' => 'error', 'message' => 'Password baru dan konfirmasi tidak cocok.'], 422);
            return;
        }

        if (strlen($new_password) < 8) {
            $this->response(['status' => 'error', 'message' => 'Password baru minimal 8 karakter.'], 422);
            return;
        }

        // Verify old password
        if (!$this->user->verify_password($old_password, $user['password_hash'])) {
            $this->response(['status' => 'error', 'message' => 'Password lama tidak sesuai.'], 422);
            return;
        }

        $this->user->update_user($user_id, [
            'password_hash' => password_hash($new_password, PASSWORD_BCRYPT)
        ]);

        $this->response([
            'status' => 'success',
            'message' => 'Password berhasil diubah.'
        ]);
    }
}

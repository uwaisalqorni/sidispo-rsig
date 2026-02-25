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
}

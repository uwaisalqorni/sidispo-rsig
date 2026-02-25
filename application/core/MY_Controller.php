<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;

class MY_Controller extends CI_Controller {

    protected $jwt_key = 'sidispo_super_secret_key_2026';
    protected $current_user = null;

    public function __construct()
    {
        parent::__construct();
        $this->handle_cors();
    }

    /**
     * Handle preflight requests — CORS headers are set by Apache (.htaccess)
     * This just exits early for OPTIONS requests that reach PHP.
     */
    protected function handle_cors()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Send standard JSON response
     */
    protected function response($data, $status_code = 200)
    {
        $this->output
             ->set_content_type('application/json')
             ->set_status_header($status_code)
             ->set_output(json_encode($data));
    }

    /**
     * Validate JWT Token middleware
     * Returns true if valid, else sends 401 response and exits
     */
    protected function require_auth()
    {
        $headers = $this->input->get_request_header('Authorization');
        
        if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
            $token = $matches[1];
            try {
                $decoded = JWT::decode($token, new Key($this->jwt_key, 'HS256'));
                $this->current_user = $decoded->data;
                return true;
            } catch (Exception $e) {
                $this->response([
                    'status' => 'error',
                    'message' => 'Invalid or expired token',
                    'error' => $e->getMessage()
                ], 401);
                exit();
            }
        }

        $this->response([
            'status' => 'error',
            'message' => 'Authorization header missing or invalid format'
        ], 401);
        exit();
    }
}

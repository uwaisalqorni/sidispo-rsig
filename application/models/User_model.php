<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get user by ID
     */
    public function get_user_by_id($id)
    {
        return $this->db->get_where('users', ['id' => $id])->row_array();
    }

    /**
     * Get user by Email or NIP for login
     */
    public function get_user_by_nip_or_email($username)
    {
        $this->db->where('nip', $username);
        $this->db->or_where('email', $username);
        return $this->db->get('users')->row_array();
    }

    /**
     * Verify Password Hash
     */
    public function verify_password($password, $hash)
    {
        // For development, we allow 'dummyhash'
        if ($hash === 'dummyhash') {
            return true;
        }
        return password_verify($password, $hash);
    }
}

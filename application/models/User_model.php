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
     * Get user by Email, NIP, or No HP for login
     */
    public function get_user_by_nip_or_email($username)
    {
        $this->db->group_start();
        $this->db->where('nip', $username);
        $this->db->or_where('email', $username);
        $this->db->or_where('no_hp', $username);
        $this->db->group_end();
        return $this->db->get('users')->row_array();
    }

    /**
     * Get user by No HP
     */
    public function get_user_by_no_hp($no_hp)
    {
        return $this->db->get_where('users', ['no_hp' => $no_hp])->row_array();
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

    /**
     * Update user data by ID
     */
    public function update_user($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }

    /**
     * Update profile photo path
     */
    public function update_profile_photo($id, $path)
    {
        return $this->update_user($id, ['foto_profil' => $path]);
    }
}

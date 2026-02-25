<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifikasi_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    /**
     * Get user notifications
     */
    public function get_by_user($user_id, $limit = 50, $offset = 0)
    {
        $this->db->where('user_id', $user_id);
        $this->db->order_by('id', 'DESC');
        $this->db->limit($limit, $offset);
        return $this->db->get('notifikasi')->result_array();
    }
    
    /**
     * Get unread count
     */
    public function get_unread_count($user_id)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('dibaca_at IS NULL', null, false);
        return $this->db->count_all_results('notifikasi');
    }

    /**
     * Insert new notification
     */
    public function insert($data)
    {
        $this->db->insert('notifikasi', $data);
        return $this->db->insert_id();
    }
    
    /**
     * Insert multiple notifications
     */
    public function insert_batch($data)
    {
        return $this->db->insert_batch('notifikasi', $data);
    }

    /**
     * Mark notification as read
     */
    public function mark_as_read($id, $user_id)
    {
        $this->db->where('id', $id);
        $this->db->where('user_id', $user_id);
        return $this->db->update('notifikasi', ['dibaca_at' => date('Y-m-d H:i:s')]);
    }
    
    /**
     * Mark all as read for user
     */
    public function mark_all_as_read($user_id)
    {
        $this->db->where('user_id', $user_id);
        $this->db->where('dibaca_at IS NULL', null, false);
        return $this->db->update('notifikasi', ['dibaca_at' => date('Y-m-d H:i:s')]);
    }
}

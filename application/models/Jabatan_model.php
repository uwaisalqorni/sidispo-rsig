<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Jabatan_model — CRUD Master Jabatan & Hierarki
 */
class Jabatan_model extends CI_Model {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    // ── CRUD Master Jabatan ──────────────────────────────────────────────

    /**
     * Get all jabatan, optionally filtered by active status
     */
    public function get_all($active_only = false)
    {
        $this->db->select('*');
        if ($active_only) {
            $this->db->where('is_active', 1);
        }
        $this->db->order_by('level', 'ASC');
        $this->db->order_by('nama', 'ASC');
        return $this->db->get('master_jabatan')->result_array();
    }

    /**
     * Get jabatan by ID
     */
    public function get_by_id($id)
    {
        return $this->db->get_where('master_jabatan', ['id' => $id])->row_array();
    }

    /**
     * Create new jabatan
     */
    public function create($data)
    {
        $this->db->insert('master_jabatan', $data);
        return $this->db->insert_id();
    }

    /**
     * Update jabatan
     */
    public function update($id, $data)
    {
        $this->db->where('id', $id)->update('master_jabatan', $data);
        return $this->db->affected_rows();
    }

    /**
     * Delete jabatan (will cascade to jabatan_hierarki)
     */
    public function delete($id)
    {
        // Check if any users reference this jabatan
        $users_count = $this->db->where('jabatan_id', $id)->count_all_results('users');
        if ($users_count > 0) {
            return ['error' => "Jabatan ini masih digunakan oleh {$users_count} user. Nonaktifkan saja atau pindahkan user terlebih dahulu."];
        }

        $this->db->where('id', $id);
        return $this->db->delete('master_jabatan');
    }

    /**
     * Check if kode already exists
     */
    public function kode_exists($kode, $exclude_id = null)
    {
        $this->db->where('kode', $kode);
        if ($exclude_id) $this->db->where('id !=', $exclude_id);
        return $this->db->count_all_results('master_jabatan') > 0;
    }

    // ── Hierarki ─────────────────────────────────────────────────────────

    /**
     * Get full hierarchy tree
     * Returns jabatan list with parent info
     */
    public function get_hierarki()
    {
        $sql = "
            SELECT 
                mj.id, mj.nama, mj.kode, mj.level, mj.is_active,
                jh.parent_id,
                parent.nama AS parent_nama,
                parent.kode AS parent_kode,
                parent.level AS parent_level
            FROM master_jabatan mj
            LEFT JOIN jabatan_hierarki jh ON jh.jabatan_id = mj.id
            LEFT JOIN master_jabatan parent ON parent.id = jh.parent_id
            WHERE mj.is_active = 1
            ORDER BY mj.level ASC, mj.nama ASC
        ";
        return $this->db->query($sql)->result_array();
    }

    /**
     * Set parent for a jabatan
     */
    public function set_parent($jabatan_id, $parent_id)
    {
        // Remove existing parent
        $this->db->where('jabatan_id', $jabatan_id)->delete('jabatan_hierarki');

        if ($parent_id) {
            $this->db->insert('jabatan_hierarki', [
                'jabatan_id' => $jabatan_id,
                'parent_id'  => $parent_id
            ]);
        }
        return true;
    }

    /**
     * Get children of a jabatan
     */
    public function get_children($jabatan_id)
    {
        $this->db->select('mj.*');
        $this->db->from('jabatan_hierarki jh');
        $this->db->join('master_jabatan mj', 'mj.id = jh.jabatan_id');
        $this->db->where('jh.parent_id', $jabatan_id);
        return $this->db->get()->result_array();
    }

    /**
     * Get parent of a jabatan
     */
    public function get_parent($jabatan_id)
    {
        $this->db->select('mj.*');
        $this->db->from('jabatan_hierarki jh');
        $this->db->join('master_jabatan mj', 'mj.id = jh.parent_id');
        $this->db->where('jh.jabatan_id', $jabatan_id);
        return $this->db->get()->row_array();
    }

    /**
     * Get jabatan level for a user
     */
    public function get_level_for_user($user_id)
    {
        $this->db->select('mj.level');
        $this->db->from('users u');
        $this->db->join('master_jabatan mj', 'mj.id = u.jabatan_id');
        $this->db->where('u.id', $user_id);
        $row = $this->db->get()->row();
        return $row ? (int)$row->level : 0;
    }

    /**
     * Compute urutan_level for an array of user IDs based on their jabatan level.
     * Returns array of [ user_id => urutan_level ]
     * Users with same jabatan level get the same urutan_level.
     * Urutan starts from 1 (lowest level) upwards.
     */
    public function compute_urutan_levels($user_ids)
    {
        if (empty($user_ids)) return [];

        $this->db->select('u.id AS user_id, COALESCE(mj.level, 0) AS jab_level');
        $this->db->from('users u');
        $this->db->join('master_jabatan mj', 'mj.id = u.jabatan_id', 'left');
        $this->db->where_in('u.id', $user_ids);
        $this->db->order_by('jab_level', 'ASC');
        $rows = $this->db->get()->result_array();

        // Collect distinct levels and assign sequential urutan
        $levels = [];
        foreach ($rows as $row) {
            $levels[] = (int)$row['jab_level'];
        }
        $distinct_levels = array_unique($levels);
        sort($distinct_levels);
        $level_to_urutan = [];
        $urutan = 1;
        foreach ($distinct_levels as $lv) {
            $level_to_urutan[$lv] = $urutan++;
        }

        // Map user_id => urutan_level
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['user_id']] = $level_to_urutan[(int)$row['jab_level']];
        }

        return $result;
    }

    /**
     * Count users per jabatan for stats
     */
    public function count_users_per_jabatan()
    {
        $sql = "
            SELECT mj.id, mj.nama, mj.level, 
                   COUNT(u.id) AS total_users,
                   GROUP_CONCAT(u.nama_lengkap ORDER BY u.nama_lengkap ASC SEPARATOR ', ') AS user_names
            FROM master_jabatan mj
            LEFT JOIN users u ON u.jabatan_id = mj.id AND u.is_active = 1
            GROUP BY mj.id
            ORDER BY mj.level ASC
        ";
        return $this->db->query($sql)->result_array();
    }
}

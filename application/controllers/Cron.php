<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Cron extends CI_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        
        // Optional: restrict to CLI or specific token
        if (!is_cli() && $this->input->get('token') !== 'cron_secret_123') {
            http_response_code(403);
            die('Forbidden');
        }
    }

    /**
     * Check and set Overdue status
     * Run this daily via cron: php index.php cron check_overdue
     */
    public function check_overdue()
    {
        // Find disposisi that are past batas_waktu
        $this->db->select('d.id, d.batas_waktu, dp.id as dp_id, dp.user_id, dp.status');
        $this->db->from('disposisi d');
        $this->db->join('disposisi_penerima dp', 'dp.disposisi_id = d.id');
        $this->db->where('d.batas_waktu <', date('Y-m-d'));
        $this->db->where_in('dp.status', ['DITERIMA', 'PROSES', 'TUNGGU']); // not finished
        
        $overdue_items = $this->db->get()->result_array();
        
        if (empty($overdue_items)) {
            echo "No overdue items found.\n";
            return;
        }

        $this->db->trans_start();

        foreach ($overdue_items as $item) {
            // 1. Update status to OVERDUE
            $this->db->where('id', $item['dp_id']);
            $this->db->update('disposisi_penerima', ['status' => 'OVERDUE', 'catatan_akhir' => 'Sistem: Melewati batas waktu']);

            // 2. Log progress
            $this->db->insert('progress_log', [
                'disposisi_penerima_id' => $item['dp_id'],
                'user_id' => $item['user_id'], // Or system ID
                'status_lama' => $item['status'],
                'status_baru' => 'OVERDUE',
                'catatan' => 'Auto-flagged by System due to missed deadline'
            ]);

            // 3. Insert notification & send email
            $this->load->model('Notifikasi_model', 'notifikasi');
            $this->notifikasi->insert([
                'user_id' => $item['user_id'],
                'jenis' => 'OVERDUE',
                'judul' => 'Disposisi Overdue',
                'pesan' => 'Disposisi melewati batas waktu: ' . $item['batas_waktu'],
                'disposisi_id' => $item['id']
            ]);
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            echo "Error processing overdue items.\n";
        } else {
            echo "Successfully processed " . count($overdue_items) . " overdue items.\n";
        }
    }
}

<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Model_custody extends CI_Model {
    protected $table;

    public function __construct()
    {
        parent::__construct();
        $this->table = 'tbl_user_custody';
    }

    public function get_custodys($where = array()) 
    {
        $this->db->order_by('id', 'asc');
        $this->db->where($where);
        $query = $this->db->get($this->table);
        return $query->result_array();
    }

    public function get_custody($where = array()) 
    {
        $this->db->order_by('id', 'asc');
        $this->db->where($where);
        $query = $this->db->get($this->table);
        return $query->row_array();
    }

    public function add_custody($data) {
        $this->db->insert($this->table, $data);
        return $this->db->affected_rows() === 1;
    }

    public function delete_custody_by_serial($serial)
    {
        $this->db->where('custody_serial', $serial);
        $this->db->delete($this->table);
    }

    public function check_for_serial($serial) {
        $this->db->where('serial_number', $serial);

        $query = $this->db->get($this->table);
        if (empty($query->result_array())) {
            return false;
        } else {
            return true;
        }
    }

    public function delete_custody($where) {
        $this->db->delete($this->table, $where);
        return $this->db->affected_rows();
    }

    public function get_custody_by_number($number)
    {
        $number = strtoupper(trim((string) $number));
        if ($number === '') {
            return null;
        }

        $number_without_s = preg_replace('/^S/', '', $number, 1);
        $number_with_s = 'S' . $number_without_s;
        $escaped_values = $this->db->escape($number) . ', '
            . $this->db->escape($number_without_s) . ', '
            . $this->db->escape($number_with_s);

        $this->db->where(
            '(UPPER(TRIM(serial_number)) IN (' . $escaped_values . ')'
            . ' OR UPPER(TRIM(barcode)) IN (' . $escaped_values . '))',
            null,
            false
        );
        $this->db->order_by('id', 'desc');
        return $this->db->get($this->table)->row_array();
    }

    /**
     * The canonical custody return operation used by direct admin returns,
     * approved employee requests, and Excel sale reconciliation.
     */
    public function return_to_stock($custody_id)
    {
        $custody = $this->get_custody(array('id' => (int) $custody_id));
        if (empty($custody)) {
            return array('status' => false, 'reason' => 'not_found', 'custody' => null);
        }

        $this->db->trans_begin();
        $this->db->where('id', (int) $custody_id)->delete($this->table);
        $custody_deleted = $this->db->affected_rows() === 1;

        if ($custody_deleted) {
            $this->db->where('custody_id', (int) $custody_id)->delete('tbl_sales');
        }

        if (!$custody_deleted || $this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => false, 'reason' => 'delete_failed', 'custody' => $custody);
        }

        $this->db->trans_commit();
        return array('status' => true, 'reason' => '', 'custody' => $custody);
    }
    
}

?>

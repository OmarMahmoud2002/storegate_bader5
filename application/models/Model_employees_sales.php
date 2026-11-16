<?php 
defined('BASEPATH') OR exit('No direct script access allowed');

class Model_employees_sales extends CI_Model {
    protected $table;
    public function __construct()
    {
        parent::__construct();
        $this->table = 'tbl_employees_sales';
    }

    public function get_upload_record_by_date($individualsDate) {
        $query = $this->db->get_where($this->table, array('individuals_date' => $individualsDate));
        return $query->row();
    }

    public function insert_upload_record($filepath, $filename, $individualsDate, $businessDate) {
        // Append each upload so id always reflects upload order, even for identical dates.
        return $this->db->insert($this->table, array('filepath' => $filepath, 'filename' => $filename, 'individuals_date' => $individualsDate, 'business_date' => $businessDate));
    }

    public function get_latest_upload_for_month($month) {
        if (!is_string($month) || !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month)) {
            return null;
        }
        $start = $month . '-01';
        $end = date('Y-m-d', strtotime($start . ' +1 month'));
        return $this->db->where('individuals_date >=', $start)
            ->where('individuals_date <', $end)
            ->order_by('id', 'DESC')->limit(1)->get($this->table)->row();
    }

    public function get_available_months() {
        return $this->db->select("DATE_FORMAT(individuals_date, '%Y-%m') AS sales_month", false)
            ->where('individuals_date >=', '1000-01-01')
            ->group_by('sales_month')->order_by('sales_month', 'DESC')
            ->get($this->table)->result();
    }

    public function get_upload_record_by_filepath($filepath) {
        $query = $this->db->get_where($this->table, array('filepath' => $filepath));
        return $query->row();
    }
}

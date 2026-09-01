<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Model_return_requests extends CI_Model
{
    protected $table = 'tbl_custody_return_requests';

    public function __construct()
    {
        parent::__construct();
        $this->ensure_schema();
    }

    /**
     * MySQL rejects a CREATE TABLE whose COLLATE does not belong to its CHARACTER SET,
     * and CI appends the collation from the connection config whenever the statement
     * does not already carry one - which is how the two halves drifted apart. Emitting
     * a self-consistent pair here keeps the statement valid whatever the config says,
     * and utf8mb4 matches tbl_user and the database default so Arabic notes round-trip.
     */
    private function table_attributes()
    {
        $charset = 'utf8mb4';
        $collation = trim((string) $this->db->dbcollat);

        if ($collation === '' || strpos($collation, $charset . '_') !== 0) {
            $collation = $charset . '_unicode_ci';
        }

        return array(
            'ENGINE' => 'InnoDB',
            'DEFAULT CHARACTER SET' => $charset,
            'COLLATE' => $collation,
        );
    }

    private function ensure_schema()
    {
        if (!$this->db->table_exists($this->table)) {
            $this->load->dbforge();
            $this->dbforge->add_field(array(
                'id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true),
                'custody_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true),
                'employee_id' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true),
                'item_code' => array('type' => 'VARCHAR', 'constraint' => 120, 'null' => true),
                'item_description' => array('type' => 'VARCHAR', 'constraint' => 500, 'null' => true),
                'serial_number' => array('type' => 'VARCHAR', 'constraint' => 120, 'null' => true),
                'barcode' => array('type' => 'VARCHAR', 'constraint' => 255, 'null' => true),
                'order_number' => array('type' => 'TEXT', 'null' => true),
                'custody_type' => array('type' => 'INT', 'constraint' => 11, 'default' => 1),
                'custody_date' => array('type' => 'DATETIME', 'null' => true),
                'employee_note' => array('type' => 'VARCHAR', 'constraint' => 500, 'null' => true),
                'status' => array('type' => 'VARCHAR', 'constraint' => 20, 'default' => 'pending'),
                'requested_at' => array('type' => 'DATETIME'),
                'reviewed_at' => array('type' => 'DATETIME', 'null' => true),
                'reviewed_by' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true),
                'admin_note' => array('type' => 'VARCHAR', 'constraint' => 500, 'null' => true),
            ));
            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('custody_id');
            $this->dbforge->add_key('employee_id');
            $this->dbforge->add_key('status');
            $this->dbforge->create_table($this->table, true, $this->table_attributes());
            return;
        }

        $columns = array(
            'item_description' => array('type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'item_code'),
            'custody_type' => array('type' => 'INT', 'constraint' => 11, 'default' => 1, 'after' => 'order_number'),
            'custody_date' => array('type' => 'DATETIME', 'null' => true, 'after' => 'custody_type'),
            'employee_note' => array('type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'custody_date'),
            'reviewed_at' => array('type' => 'DATETIME', 'null' => true, 'after' => 'requested_at'),
            'reviewed_by' => array('type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true, 'after' => 'reviewed_at'),
            'admin_note' => array('type' => 'VARCHAR', 'constraint' => 500, 'null' => true, 'after' => 'reviewed_by'),
        );

        foreach ($columns as $column_name => $definition) {
            if (!$this->db->field_exists($column_name, $this->table)) {
                $this->load->dbforge();
                $this->dbforge->add_column($this->table, array($column_name => $definition));
            }
        }
    }

    public function get_request($id)
    {
        return $this->db->where('id', (int) $id)->get($this->table)->row_array();
    }

    public function get_pending_by_custody($custody_id)
    {
        return $this->db
            ->where('custody_id', (int) $custody_id)
            ->where_in('status', array('pending', 'reviewing'))
            ->order_by('id', 'desc')
            ->get($this->table)
            ->row_array();
    }

    public function add_request($data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->affected_rows() === 1 ? (int) $this->db->insert_id() : 0;
    }

    public function get_employee_requests($employee_id, $limit = 30)
    {
        $this->db->select('r.*, reviewer.user_fillname AS reviewer_name');
        $this->db->from($this->table . ' r');
        $this->db->join('tbl_user reviewer', 'reviewer.user_id = r.reviewed_by', 'left');
        $this->db->where('r.employee_id', (int) $employee_id);
        $this->db->order_by('r.requested_at', 'desc');
        $this->db->order_by('r.id', 'desc');
        $this->db->limit((int) $limit);
        return $this->db->get()->result_array();
    }

    /**
     * Applies the dashboard filters to the current query builder state.
     * The serial box matches either identifier, since the UI presents
     * serial number and barcode as one column.
     */
    private function apply_filters($filters, $alias = 'r.')
    {
        if (!empty($filters['employee_id'])) {
            $this->db->where($alias . 'employee_id', (int) $filters['employee_id']);
        }

        $serial = isset($filters['serial']) ? trim((string) $filters['serial']) : '';
        if ($serial !== '') {
            $this->db->group_start();
            $this->db->like($alias . 'serial_number', $serial);
            $this->db->or_like($alias . 'barcode', $serial);
            $this->db->group_end();
        }
    }

    public function get_dashboard_requests($limit = 100, $filters = array())
    {
        $this->db->select('r.*, employee.user_fillname AS employee_name, employee.user_employee_Id AS employee_number, reviewer.user_fillname AS reviewer_name');
        $this->db->from($this->table . ' r');
        $this->db->join('tbl_user employee', 'employee.user_id = r.employee_id', 'left');
        $this->db->join('tbl_user reviewer', 'reviewer.user_id = r.reviewed_by', 'left');
        $this->apply_filters($filters);
        $this->db->order_by("CASE WHEN r.status = 'pending' THEN 0 WHEN r.status = 'reviewing' THEN 1 ELSE 2 END", '', false);
        $this->db->order_by('r.requested_at', 'desc');
        $this->db->order_by('r.id', 'desc');
        $this->db->limit((int) $limit);
        return $this->db->get()->result_array();
    }

    /**
     * Employees that actually have return requests, so the filter list never
     * offers a choice that can only produce an empty result.
     */
    public function get_request_employees()
    {
        $this->db->distinct();
        $this->db->select('r.employee_id, employee.user_fillname AS employee_name, employee.user_employee_Id AS employee_number');
        $this->db->from($this->table . ' r');
        $this->db->join('tbl_user employee', 'employee.user_id = r.employee_id', 'left');
        $this->db->order_by('employee.user_fillname', 'asc');
        return $this->db->get()->result_array();
    }

    public function get_status_counts($filters = array())
    {
        $counts = array('pending' => 0, 'approved' => 0, 'rejected' => 0, 'sold' => 0);
        $this->db->select('r.status, COUNT(*) AS total', false);
        $this->db->from($this->table . ' r');
        $this->apply_filters($filters);
        $this->db->group_by('r.status');
        $rows = $this->db->get()->result_array();

        foreach ($rows as $row) {
            $status = (string) $row['status'];
            if ($status === 'reviewing') {
                $counts['pending'] += (int) $row['total'];
            } elseif (array_key_exists($status, $counts)) {
                $counts[$status] += (int) $row['total'];
            }
        }

        return $counts;
    }

    public function claim_pending_request($id, $admin_id)
    {
        $this->db->where('id', (int) $id);
        $this->db->where('status', 'pending');
        $this->db->update($this->table, array(
            'status' => 'reviewing',
            'reviewed_by' => (int) $admin_id,
            'reviewed_at' => date('Y-m-d H:i:s'),
        ));
        if ($this->db->affected_rows() === 1) {
            return true;
        }

        // Allow the same admin to safely resume a request that was already
        // claimed by their previous submission, while still excluding others.
        return $this->db
            ->where('id', (int) $id)
            ->where('status', 'reviewing')
            ->where('reviewed_by', (int) $admin_id)
            ->count_all_results($this->table) === 1;
    }

    public function release_review($id, $note = null)
    {
        $data = array('status' => 'pending', 'reviewed_by' => null, 'reviewed_at' => null);
        if ($note !== null) {
            $data['admin_note'] = $note;
        }
        $this->db->where('id', (int) $id)->where('status', 'reviewing')->update($this->table, $data);
        return $this->db->affected_rows() === 1;
    }

    public function complete_review($id, $status, $admin_id, $admin_note = '')
    {
        if (!in_array($status, array('approved', 'rejected'), true)) {
            return false;
        }

        $allowed_current_statuses = $status === 'approved' ? array('reviewing') : array('pending', 'reviewing');
        $this->db->where('id', (int) $id);
        $this->db->where_in('status', $allowed_current_statuses);
        $this->db->update($this->table, array(
            'status' => $status,
            'reviewed_by' => (int) $admin_id,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'admin_note' => trim((string) $admin_note),
        ));
        return $this->db->affected_rows() === 1;
    }

    public function resolve_pending_as_sold($custody_id, $note = '')
    {
        $this->db->where('custody_id', (int) $custody_id);
        $this->db->where_in('status', array('pending', 'reviewing'));
        $this->db->update($this->table, array(
            'status' => 'sold',
            'reviewed_at' => date('Y-m-d H:i:s'),
            'admin_note' => trim((string) $note),
        ));
        return $this->db->affected_rows();
    }

    public function approve_pending_by_custody($custody_id, $admin_id, $note = '')
    {
        $this->db->where('custody_id', (int) $custody_id);
        $this->db->where_in('status', array('pending', 'reviewing'));
        $this->db->update($this->table, array(
            'status' => 'approved',
            'reviewed_by' => (int) $admin_id,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'admin_note' => trim((string) $note),
        ));
        return $this->db->affected_rows();
    }
}

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Model_employee_leaves extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        // Like the other optional modules, initialize storage on first use.
        if (!$this->db->table_exists('employee_leaves')) {
            $this->db->query("CREATE TABLE IF NOT EXISTS employee_leaves (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                all_employees TINYINT(1) NOT NULL DEFAULT 1,
                employee_ids TEXT NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY leave_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        if (!$this->db->table_exists('employee_leave_overrides')) {
            $this->db->query("CREATE TABLE IF NOT EXISTS employee_leave_overrides (
                user_id INT NOT NULL,
                schedule_date DATE NOT NULL,
                leave_signature TEXT NOT NULL,
                schedule_type VARCHAR(20) NOT NULL,
                description TEXT NOT NULL,
                PRIMARY KEY (user_id, schedule_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function get_all()
    {
        $rows = $this->db->order_by('start_date', 'DESC')->order_by('id', 'DESC')->get('employee_leaves')->result_array();
        foreach ($rows as &$row) {
            $row['employee_ids'] = array_map('intval', json_decode($row['employee_ids'], true) ?: array());
        }
        unset($row);
        return $rows;
    }

    public function create($data)
    {
        $data['employee_ids'] = json_encode(array_values($data['employee_ids']));
        return $this->db->insert('employee_leaves', $data);
    }

    public function delete($id)
    {
        return $this->db->delete('employee_leaves', array('id' => $id));
    }

    public function covers($leaves, $employee_id, $date)
    {
        return $this->signature($leaves, $employee_id, $date) !== '';
    }

    public function signature($leaves, $employee_id, $date)
    {
        $ids = array();
        foreach ($leaves as $leave) {
            if ($date >= $leave['start_date'] && $date <= $leave['end_date'] &&
                ($leave['all_employees'] || in_array((int) $employee_id, $leave['employee_ids'], true))) {
                $ids[] = (int) $leave['id'];
            }
        }
        sort($ids, SORT_NUMERIC);
        return implode(',', $ids);
    }

    public function get_overrides()
    {
        $overrides = array();
        foreach ($this->db->get('employee_leave_overrides')->result_array() as $row) {
            $overrides[$row['user_id'] . '_' . $row['schedule_date']] = array(
                'signature' => $row['leave_signature'],
                'type' => $row['schedule_type'],
                'status' => $row['description'],
            );
        }
        return $overrides;
    }

    public function save_override($data, $signature)
    {
        return $this->db->replace('employee_leave_overrides', array(
            'user_id' => (int) $data['user_id'],
            'schedule_date' => $data['date'],
            'leave_signature' => $signature,
            'schedule_type' => $data['type'],
            'description' => $data['description'],
        ));
    }
}

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
                leave_type VARCHAR(20) NOT NULL DEFAULT 'annual',
                color CHAR(7) NOT NULL DEFAULT '#151515',
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                all_employees TINYINT(1) NOT NULL DEFAULT 1,
                employee_ids TEXT NOT NULL,
                created_by INT NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY leave_dates (start_date, end_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        foreach (array('leave_type' => "VARCHAR(20) NOT NULL DEFAULT 'annual'", 'color' => "CHAR(7) NOT NULL DEFAULT '#151515'") as $column => $definition) {
            if (!$this->db->field_exists($column, 'employee_leaves')) {
                $this->db->query("ALTER TABLE employee_leaves ADD COLUMN `$column` $definition");
            }
        }
        if (!$this->db->table_exists('employee_leave_month_limits')) {
            $this->db->query("CREATE TABLE IF NOT EXISTS employee_leave_month_limits (
                month DATE NOT NULL PRIMARY KEY,
                employee_limit INT UNSIGNED NOT NULL,
                updated_by INT NOT NULL,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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

    public function get_month_limit($month, $default)
    {
        $row = $this->db->where('month', $month . '-01')
            ->limit(1)->get('employee_leave_month_limits')->row_array();
        return $row ? (int) $row['employee_limit'] : (int) $default;
    }

    public function get_month_limits()
    {
        $limits = array();
        foreach ($this->db->get('employee_leave_month_limits')->result_array() as $row) {
            $limits[substr($row['month'], 0, 7)] = (int) $row['employee_limit'];
        }
        return $limits;
    }

    public function save_month_limit($month, $limit, $user_id)
    {
        return $this->db->replace('employee_leave_month_limits', array(
            'month' => $month . '-01', 'employee_limit' => $limit, 'updated_by' => $user_id,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function save_year_limits($year, $limits, $user_id)
    {
        $this->db->trans_begin();
        foreach ($limits as $number => $limit) {
            if (!$this->save_month_limit(sprintf('%04d-%02d', $year, $number), $limit, $user_id)) {
                $this->db->trans_rollback();
                return false;
            }
        }
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        return $this->db->trans_commit();
    }

    // Count distinct employees per month, including the proposed leave, never leave records.
    public function capacity_warnings($values, $employees, $leaves)
    {
        $warnings = array();
        $first = new DateTime(substr($values['start_date'], 0, 7) . '-01');
        $last = substr($values['end_date'], 0, 7);
        $proposed = $values;
        $proposed['id'] = 0;
        $leaves[] = $proposed;
        while ($first->format('Y-m') <= $last) {
            $month = $first->format('Y-m');
            $start = $first->format('Y-m-d');
            $end = $first->format('Y-m-t');
            $ids = array();
            foreach ($leaves as $leave) {
                if ($leave['start_date'] > $end || $leave['end_date'] < $start) continue;
                foreach ($employees as $employee) {
                    $id = (int) $employee['user_id'];
                    if ($leave['all_employees'] || in_array($id, $leave['employee_ids'], true)) $ids[$id] = true;
                }
            }
            $limit = $this->get_month_limit($month, count($employees));
            if (count($ids) > $limit) $warnings[] = array('month' => $month, 'count' => count($ids), 'limit' => $limit);
            $first->modify('+1 month');
        }
        return $warnings;
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

<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Model_timetable extends CI_Model
{
    public function people($supervisors = false)
    {
        $query = $this->db->select('user_id, user_fillname, employee_type')->where('user_type', 'user')
            ->where('is_deleted', 0);
        if ($supervisors) $query->where('employee_type', 'supervisor');
        else $query->where_in('employee_type', array('employee', 'consultant'));
        return $query->order_by('user_id', 'ASC')->get('tbl_user')->result_array();
    }

    public function cells($supervisors = false)
    {
        $ids = array_column($this->people($supervisors), 'user_id');
        if (!$ids) return array();
        $cells = array();
        foreach (array('users_schedules', 'employee_timetable_cells') as $table) {
            foreach ($this->db->where_in('user_id', $ids)->get($table)->result_array() as $row) {
                $cells[$row['user_id'] . '_' . $row['schedule_date']] = array(
                    'type' => $row['schedule_type'], 'status' => $row['description']);
            }
        }
        return $cells;
    }

    public function approvals($dates, $groups)
    {
        $result = array();
        foreach ($this->db->where_in('schedule_date', $dates)->where_in('schedule_group', $groups)
            ->get('employee_timetable_approvals')->result_array() as $row) {
            $result[$row['schedule_group'] . '_' . $row['schedule_date']] = json_decode($row['snapshot'], true);
        }
        return $result;
    }

    // Serialize approval and cell writes across tabs and users, including empty periods.
    public function exclusive($callback)
    {
        $lock = $this->db->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':employee-timetable'), 5) AS acquired")->row_array();
        if (!$lock || (int) $lock['acquired'] !== 1) throw new RuntimeException('الجدول مشغول، حاول مرة أخرى.');
        $this->db->trans_begin();
        try {
            $result = $callback();
            if (!$this->db->trans_status()) throw new RuntimeException('تعذر حفظ الجدول.');
            $this->db->trans_commit();
            return $result;
        } catch (Throwable $e) {
            $this->db->trans_rollback();
            throw $e;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':employee-timetable'))");
        }
    }

    public function save_cells($payload, $groups)
    {
        if (!is_array($payload) || !$payload) throw new InvalidArgumentException('بيانات الجدول غير صحيحة.');
        $people = array();
        foreach ($groups as $group) {
            foreach ($this->people($group === 'supervisor') as $person) $people[$person['user_id']] = $person;
        }
        $validated = array();
        foreach ($payload as $key => $cell) {
            if (!preg_match('/^(\d+)_(\d{4}-\d{2}-\d{2})$/D', $key, $parts) || !isset($people[$parts[1]]) ||
                !is_array($cell) || !isset($cell['type'], $cell['status']) || !is_string($cell['status']) ||
                !is_string($cell['type']) || !$this->timetable_period->allowed_type($people[$parts[1]]['employee_type'], $cell['type']) ||
                mb_strlen($cell['status']) > 1000) throw new InvalidArgumentException('نوع الموظف أو حالة الدوام غير صحيحة.');
            $date = $parts[2];
            if (!in_array($date, $this->timetable_period->dates(substr($date, 0, 7)), true)) throw new InvalidArgumentException('التاريخ غير صحيح.');
            $group = $people[$parts[1]]['employee_type'] === 'supervisor' ? 'supervisor' : 'employee';
            if ($this->approvals(array($date), array($group))) throw new RuntimeException('الجدول معتمد. اضغط تعديل الجدول أولًا.');
            $labels = array('am' => 'AM', 'pm' => 'PM', 'store_am' => 'Store AM', 'store_pm' => 'Store PM', '' => '');
            $validated[] = array('user_id' => (int) $parts[1], 'date' => $date, 'type' => $cell['type'],
                'description' => $cell['type'] === 'note' ? $cell['status'] : $labels[$cell['type']]);
        }
        $this->load->model('Model_employee_leaves', 'Leaves');
        $leaves = $this->Leaves->get_all();
        foreach ($validated as $data) {
            $person = $people[$data['user_id']];
            $signature = $person['employee_type'] !== 'supervisor' ? $this->Leaves->signature($leaves, $data['user_id'], $data['date']) : '';
            if ($signature !== '') $ok = $this->Leaves->save_override($data, $signature);
            else $ok = $this->db->replace('employee_timetable_cells', array('user_id' => $data['user_id'],
                'schedule_date' => $data['date'], 'schedule_type' => $data['type'], 'description' => $data['description']));
            if (!$ok) throw new RuntimeException('تعذر حفظ الحالة.');
        }
    }

    public function set_approval($dates, $groups, $approve, $user_id)
    {
        if (!$approve) {
            $this->db->where_in('schedule_date', $dates)->where_in('schedule_group', $groups)->delete('employee_timetable_approvals');
            return;
        }
        $this->load->model('Model_employee_leaves', 'Leaves');
        $leaves = $this->Leaves->get_all();
        $overrides = $this->Leaves->get_overrides();
        $existing = $this->approvals($dates, $groups);
        foreach ($groups as $group) {
            $people = $this->people($group === 'supervisor');
            $cells = $this->cells($group === 'supervisor');
            foreach ($dates as $date) {
                if (isset($existing[$group . '_' . $date])) continue;
                $snapshot = array();
                foreach ($people as $person) {
                    $id = $person['user_id'];
                    $key = $id . '_' . $date;
                    $cell = isset($cells[$key]) ? $cells[$key] : array('type' => '', 'status' => '');
                    $signature = $group === 'employee' ? $this->Leaves->signature($leaves, $id, $date) : '';
                    if ($signature !== '') {
                        if (isset($overrides[$key]) && $overrides[$key]['signature'] === $signature) $cell = $overrides[$key];
                        else {
                            $names = array();
                            foreach ($leaves as $leave) {
                                if (in_array((string) $leave['id'], explode(',', $signature), true)) $names[] = $leave['name'];
                            }
                            $cell = array('type' => 'leave', 'status' => implode(' / ', array_unique($names)));
                        }
                    }
                    $snapshot[$id] = $cell;
                }
                $this->db->insert('employee_timetable_approvals', array('schedule_date' => $date, 'schedule_group' => $group,
                    'snapshot' => json_encode((object) $snapshot, JSON_UNESCAPED_UNICODE), 'approved_by' => $user_id));
            }
        }
    }
}

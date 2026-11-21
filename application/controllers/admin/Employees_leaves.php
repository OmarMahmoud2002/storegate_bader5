<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Employees_leaves extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
            exit;
        }
        $this->Permissions->ensure_employee_leave_permissions();
        $this->load->model('Model_employee_leaves', 'Leaves');
        date_default_timezone_set('Africa/Cairo');
    }

    public function index()
    {
        $this->check_permission('employees_leaves.view');
        $data = array('employees' => $this->Model_admin->getemployees(), 'leaves' => $this->Leaves->get_all());
        $this->load->model('Model_notifications', 'Notifications');
        $notification_filter = array('user_id' => $this->session->userdata('user_id'));
        $data['notifications'] = array_reverse($this->Notifications->get_notifications($notification_filter));
        $data['notifications_count'] = $this->Notifications->get_notifications_length(array_merge($notification_filter, array('status' => 'unread')));
        $data['page_title'] = 'إجازات الموظفين';
        $data['can_create'] = $this->get_permission_enabled('employees_leaves.create');
        $data['can_delete'] = $this->get_permission_enabled('employees_leaves.delete');
        $data['can_set_limit'] = $this->get_permission_enabled('employees_leaves.limit.update');
        $data['can_view_timetable'] = $this->get_permission_enabled('employees_timetable.view');
        $data['values'] = array('leave_type' => '', 'color' => '#151515', 'name' => '', 'start_date' => '', 'end_date' => '', 'all_employees' => 1, 'employee_ids' => array());
        require_once dirname(__DIR__, 2) . '/libraries/Timetable_period.php';
        $period = new Timetable_period();
        $data['latest_month'] = $period->latest_month();
        $month = (string) $this->input->get('month');
        if (!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $month) || $month < '2024-01' || $month > $data['latest_month']) $month = $period->today()->format('Y-m');
        $data['month'] = $month;
        $data['capacity_warnings'] = array();
        $data['confirmation_token'] = '';
        $data['error'] = '';
        $data['limit_error'] = '';
        if ($this->input->method() === 'post' && $this->input->post('action') === 'save_limit') {
            $this->check_permission('employees_leaves.limit.update');
            $limit_year = filter_var($this->input->post('limit_year'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 2024, 'max_range' => (int) substr($data['latest_month'], 0, 4) + 1)));
            $posted_limits = $this->input->post('employee_limits');
            $limits = array();
            if ($limit_year === false || $limit_year === null) {
                $data['limit_error'] = 'اختر سنة صحيحة.';
            } else {
                $data['limit_year'] = $limit_year;
                $data['limit_values'] = is_array($posted_limits) ? $posted_limits : array();
                for ($number = 1; $number <= 12; $number++) {
                    $value = is_array($posted_limits) && isset($posted_limits[$number]) ? $posted_limits[$number] : null;
                    $limit = is_scalar($value) ? filter_var($value, FILTER_VALIDATE_INT, array('options' => array('min_range' => 0, 'max_range' => 2147483647))) : false;
                    if ($limit === false || $limit === null) {
                        $data['limit_error'] = 'اكتب عددًا صحيحًا يساوي صفرًا أو أكثر لكل شهر من شهور السنة.';
                        break;
                    }
                    $limits[$number] = $limit;
                }
                if (is_array($posted_limits) && count($posted_limits) !== 12) $data['limit_error'] = 'حدد العدد للشهور الاثني عشر فقط.';
            }
            if (!$data['limit_error'] && $this->Leaves->save_year_limits($limit_year, $limits, (int) $this->session->userdata('user_id'))) {
                $this->session->set_flashdata('leave_success', 'تم حفظ أعداد الموظفين المسموح لهم بالإجازة لكل شهور سنة ' . $limit_year . '.');
                redirect(base_url() . MOD_VALUE . 'admin/employees_leaves?month=' . $month);
                return;
            } elseif (!$data['limit_error']) {
                $data['limit_error'] = 'تعذر حفظ أعداد السنة. حاول مرة أخرى.';
            }
        }
        $data['month_limits'] = $this->Leaves->get_month_limits();
        $data['employee_limit'] = $this->Leaves->get_month_limit($month, count($data['employees']));
        if ($this->input->method() === 'post' && $this->input->post('action') !== 'save_limit') {
            $this->check_permission('employees_leaves.create');
            $values = array(
                'leave_type' => (string) ($this->input->post('leave_type') ?? 'annual'),
                'color' => (string) ($this->input->post('color') ?? '#151515'),
                'name' => trim((string) $this->input->post('name')),
                'start_date' => (string) $this->input->post('start_date'),
                'end_date' => (string) $this->input->post('end_date'),
                'all_employees' => $this->input->post('all_employees') === '1' ? 1 : 0,
                'employee_ids' => array_values(array_unique(array_map('intval', (array) $this->input->post('employee_ids')))),
            );
            $data['values'] = $values;
            $valid_ids = array_map('intval', array_column($data['employees'], 'user_id'));
            $valid_date = function ($value) {
                $date = DateTime::createFromFormat('!Y-m-d', $value);
                return $date && $date->format('Y-m-d') === $value && $value >= '1000-01-01';
            };
            if (!in_array($values['leave_type'], array('annual', 'occasion'), true)) {
                $data['error'] = 'اختر نوع الإجازة: سنوية أو مناسبات.';
            } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/D', $values['color'])) {
                $data['error'] = 'اختر لونًا صحيحًا لمربع الإجازة.';
            } elseif ($values['name'] === '' || mb_strlen($values['name']) > 150) {
                $data['error'] = 'اكتب اسم الإجازة بحد أقصى 150 حرفًا.';
            } elseif (!$valid_date($values['start_date']) || !$valid_date($values['end_date'])) {
                $data['error'] = 'حدد تاريخ بداية ونهاية صحيحين.';
            } elseif ($values['end_date'] < $values['start_date']) {
                $data['error'] = 'تاريخ النهاية يجب أن يكون بعد البداية أو في نفس اليوم.';
            } elseif (!$values['all_employees'] && (empty($values['employee_ids']) || array_diff($values['employee_ids'], $valid_ids))) {
                $data['error'] = 'اختر موظفًا واحدًا على الأقل من قائمة الموظفين.';
            } else {
                $warnings = $this->Leaves->capacity_warnings($values, $data['employees'], $data['leaves']);
                $fingerprint = hash('sha256', serialize(array($values, $warnings)));
                $pending = $this->session->userdata('leave_capacity_confirmation');
                $token = (string) $this->input->post('confirmation_token');
                $confirmed = is_array($pending) && isset($pending['token'], $pending['fingerprint']) &&
                    hash_equals($pending['token'], $token) && hash_equals($pending['fingerprint'], $fingerprint);
                if ($warnings && !$confirmed) {
                    $data['capacity_warnings'] = $warnings;
                    $data['confirmation_token'] = bin2hex(random_bytes(24));
                    $this->session->set_userdata('leave_capacity_confirmation', array(
                        'token' => $data['confirmation_token'], 'fingerprint' => $fingerprint,
                    ));
                    $data['employee_limit'] = $this->Leaves->get_month_limit($month, count($data['employees']));
                    $this->load->view('view_admin_employees_leaves', $data);
                    return;
                }
                $this->session->unset_userdata('leave_capacity_confirmation');
                if ($values['all_employees']) $values['employee_ids'] = array();
                $values['created_by'] = (int) $this->session->userdata('user_id');
                if ($this->Leaves->create($values)) {
                    $this->session->set_flashdata('leave_success', 'تم حفظ الإجازة وإضافتها إلى جداول الموظفين.');
                    redirect(base_url() . MOD_VALUE . 'admin/employees_leaves?month=' . substr($values['start_date'], 0, 7));
                    return;
                }
                $data['error'] = 'تعذر حفظ الإجازة، حاول مرة أخرى.';
            }
        }
        $this->load->view('view_admin_employees_leaves', $data);
    }

    public function data()
    {
        // Timetable viewers still need the leave overlay without access to leave management.
        if (!$this->get_permission_enabled('employees_timetable.view')) {
            $this->check_permission('employees_leaves.view');
        }
        $data = array('leaves' => $this->Leaves->get_all(), 'overrides' => (object) $this->Leaves->get_overrides());
        $this->output->set_content_type('application/json')->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function delete()
    {
        $this->check_permission('employees_leaves.view');
        $this->check_permission('employees_leaves.delete');
        if ($this->input->method() !== 'post') show_404();
        $success = $this->Leaves->delete((int) $this->input->post('leave_id'));
        $this->session->set_flashdata('leave_success', $success ? 'تم حذف الإجازة واستعادة عرض الدوام الأصلي.' : 'تعذر حذف الإجازة.');
        $month = (string) $this->input->post('month');
        if (!preg_match('/^[1-9][0-9]{3}-(0[1-9]|1[0-2])$/D', $month)) $month = date('Y-m');
        redirect(base_url() . MOD_VALUE . 'admin/employees_leaves?month=' . $month);
    }
}

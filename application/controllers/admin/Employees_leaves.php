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
        $this->check_permission('employees_timetable.view');
        $this->load->model('Model_employee_leaves', 'Leaves');
        date_default_timezone_set('Asia/Riyadh');
    }

    public function index()
    {
        $data = array('employees' => $this->Model_admin->getemployees(), 'leaves' => $this->Leaves->get_all());
        $this->load->model('Model_notifications', 'Notifications');
        $notification_filter = array('user_id' => $this->session->userdata('user_id'));
        $data['notifications'] = array_reverse($this->Notifications->get_notifications($notification_filter));
        $data['notifications_count'] = $this->Notifications->get_notifications_length(array_merge($notification_filter, array('status' => 'unread')));
        $data['page_title'] = 'إجازات الموظفين';
        $data['can_manage'] = $this->get_permission_enabled('employees_timetable.save');
        $data['values'] = array('name' => '', 'start_date' => '', 'end_date' => '', 'all_employees' => 1, 'employee_ids' => array());
        $data['error'] = '';
        if ($this->input->method() === 'post') {
            $this->check_permission('employees_timetable.save');
            $values = array(
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
            if ($values['name'] === '' || mb_strlen($values['name']) > 150) {
                $data['error'] = 'اكتب اسم الإجازة بحد أقصى 150 حرفًا.';
            } elseif (!$valid_date($values['start_date']) || !$valid_date($values['end_date'])) {
                $data['error'] = 'حدد تاريخ بداية ونهاية صحيحين.';
            } elseif ($values['end_date'] < $values['start_date']) {
                $data['error'] = 'تاريخ النهاية يجب أن يكون بعد البداية أو في نفس اليوم.';
            } elseif (!$values['all_employees'] && (empty($values['employee_ids']) || array_diff($values['employee_ids'], $valid_ids))) {
                $data['error'] = 'اختر موظفًا واحدًا على الأقل من قائمة الموظفين.';
            } else {
                if ($values['all_employees']) $values['employee_ids'] = array();
                $values['created_by'] = (int) $this->session->userdata('user_id');
                if ($this->Leaves->create($values)) {
                    $this->session->set_flashdata('leave_success', 'تم حفظ الإجازة وإضافتها إلى جداول الموظفين.');
                    redirect(base_url() . MOD_VALUE . 'admin/employees_leaves');
                    return;
                }
                $data['error'] = 'تعذر حفظ الإجازة، حاول مرة أخرى.';
            }
        }
        $this->load->view('view_admin_employees_leaves', $data);
    }

    public function data()
    {
        $data = array('leaves' => $this->Leaves->get_all(), 'overrides' => (object) $this->Leaves->get_overrides());
        $this->output->set_content_type('application/json')->set_output(json_encode($data, JSON_UNESCAPED_UNICODE));
    }

    public function delete()
    {
        $this->check_permission('employees_timetable.save');
        if ($this->input->method() !== 'post') show_404();
        $success = $this->Leaves->delete((int) $this->input->post('leave_id'));
        $this->session->set_flashdata('leave_success', $success ? 'تم حذف الإجازة واستعادة عرض الدوام الأصلي.' : 'تعذر حذف الإجازة.');
        redirect(base_url() . MOD_VALUE . 'admin/employees_leaves');
    }
}

<?php
// Isolated regression checks: php deploy/tests/employee_leaves.php
define('BASEPATH', __DIR__);
define('MOD_VALUE', '');
class CI_Model {}
require dirname(__DIR__, 2) . '/application/models/Model_employee_leaves.php';
function checkLeave($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
class LeaveFixture extends Model_employee_leaves {
    public $limits = array('2026-01' => 1, '2026-02' => 2);
    public function __construct() {}
    public function get_month_limits() { return $this->limits; }
    public function get_month_limit($month, $default) {
        return $this->limits[$month] ?? $default;
    }
}
$model = new LeaveFixture();
$employees = array(
    array('user_id' => 1, 'user_fillname' => 'Employee A', 'user_employee_Id' => '001', 'job_number' => '10134'),
    array('user_id' => 2, 'user_fillname' => 'Employee B'),
    array('user_id' => 3, 'user_fillname' => 'Employee C'),
);
$leave = array('id' => 1, 'name' => '<Annual "leave">', 'start_date' => '2026-01-30',
    'end_date' => '2026-02-02', 'all_employees' => 0, 'employee_ids' => array(1), 'leave_type' => 'occasion', 'color' => '#be123c');
$proposal = array_merge($leave, array('employee_ids' => array(2)));
$warnings = $model->capacity_warnings($proposal, $employees, array($leave, $leave));
checkLeave($warnings === array(array('month' => '2026-01', 'count' => 2, 'limit' => 1)), 'Cross-month or unique employee count failed.');
checkLeave($model->capacity_warnings($leave, $employees, array($leave)) === array(), 'Duplicate employee counted twice.');
$proposal['all_employees'] = 1;
$warnings = $model->capacity_warnings($proposal, $employees, array($leave));
checkLeave(count($warnings) === 2 && $warnings[1]['count'] === 3, 'All-employee leave did not check every month.');
$model->limits['2026-01'] = 0;
checkLeave(count($model->capacity_warnings($leave, $employees, array())) === 1, 'Zero limit failed.');

function base_url($path = '') { return '/' . $path; }
function form_open($action, $attributes = array()) {
    $html = '<form action="' . htmlspecialchars($action, ENT_QUOTES) . '" method="post"';
    foreach ($attributes as $key => $value) $html .= ' ' . $key . '="' . htmlspecialchars($value, ENT_QUOTES) . '"';
    return $html . '>';
}
function form_close() { return '</form>'; }
class LeaveViewFixture {
    public $load;
    public $session;
    public function __construct() {
        $this->load = new class { public function view($name) {} };
        $this->session = new class { public function flashdata($name) { return ''; } };
    }
    public function render($data) {
        extract($data);
        ob_start();
        include dirname(__DIR__, 2) . '/application/views/view_admin_employees_leaves.php';
        return ob_get_clean();
    }
}
$data = array('employees' => $employees, 'leaves' => array($leave, $leave), 'month' => '2026-02',
    'can_create' => true, 'can_delete' => true, 'can_set_limit' => true, 'can_view_timetable' => true,
    'employee_limit' => 2, 'error' => '', 'limit_error' => '',
    'capacity_warnings' => array(), 'confirmation_token' => '',
    'values' => array('name' => '', 'start_date' => '', 'end_date' => '', 'all_employees' => 1, 'employee_ids' => array()));
$view = new LeaveViewFixture();
$html = $view->render($data);
$dom = new DOMDocument();
@$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);
checkLeave($xpath->query('//table[@class="leaves-calendar"]/tbody/tr')->length === 1, 'Employees without leave should be hidden.');
checkLeave($xpath->query('//td[contains(@class,"is-leave")]')->length === 2, 'Leave was not clipped to selected month.');
checkLeave($xpath->query('//td[@class="leave-total"]')->item(0)->textContent === '2', 'Overlapping leave days counted twice.');
checkLeave($xpath->query('//th[@scope="col"]')->length === 28, 'February has incorrect number of days.');
checkLeave($xpath->query('//td[contains(@class,"is-weekend")]')->length === 8, 'Friday/Saturday coloring failed.');
checkLeave(strpos($html, 'title="&lt;Annual &quot;leave&quot;&gt; — من 2026-01-30 إلى 2026-02-02') !== false, 'Tooltip name, full date range or escaping failed.');
checkLeave(strpos($html, 'style="background:#be123c"') !== false, 'Custom leave color missing.');
checkLeave(strpos($html, 'الرقم الوظيفي') !== false && strpos($html, '<td>10134</td>') !== false && strpos($html, '<td>001</td>') === false, 'Employment number must come from job_number.');
checkLeave($xpath->query('//input[@name="leave_type"]')->length === 2, 'Leave categories missing.');
checkLeave($xpath->query('//input[@id="leave-color"]')->length === 1, 'Color picker duplicated or missing.');
checkLeave($xpath->query('//button[@class="leaves-delete"]')->item(0)->textContent === '', 'Delete button should only contain an icon.');
checkLeave($xpath->query('//table[@class="leaves-management-table"]')->item(0)->getAttribute('dir') === 'rtl', 'Management table must start at the right.');
$data['latest_month'] = '2026-11';
$data['month'] = '2026-11';
$filtered = $view->render($data);
checkLeave(substr_count($filtered, 'data-limit-month=') === 12, 'Capacity dialog must display all twelve months.');
$filteredDom = new DOMDocument();
@$filteredDom->loadHTML('<?xml encoding="UTF-8">' . $filtered);
$filteredXpath = new DOMXPath($filteredDom);
checkLeave($filteredXpath->query('//select[@id="leave-month"]/option')->length === 11, 'Unavailable future month is exposed.');
$data['month'] = '2026-02';
$data['month'] = '2024-02';
$html = $view->render($data);
checkLeave(strpos($html, 'colspan="29"') !== false, 'Leap year failed.');
$data['capacity_warnings'] = $warnings;
$data['confirmation_token'] = 'test-token';
$html = $view->render($data);
checkLeave(strpos($html, 'استمرار وحفظ الإجازة') !== false && strpos($html, 'إلغاء الطلب') !== false, 'Confirmation controls missing.');
checkLeave(strpos($html, 'name="confirmation_token"') !== false, 'Confirmation token missing.');
echo "Employee leave capacity and calendar checks passed.\n";

// Exercise the real controller: warning -> changed request -> confirmation -> one insert.
class MY_Controller {
    public $denied = array();
    public function check_permission($permission) {
        if (!$this->get_permission_enabled($permission)) throw new RuntimeException('Denied: ' . $permission);
    }
    public function get_permission_enabled($permission) { return !in_array($permission, $this->denied, true); }
}
require dirname(__DIR__, 2) . '/application/controllers/admin/Employees_leaves.php';
class LeaveControllerFixture extends Employees_leaves {
    public $input;
    public $session;
    public $load;
    public $Model_admin;
    public $Notifications;
    public $Leaves;
    public function __construct() {}
}
function redirect($url) { $GLOBALS['leave_redirect'] = $url; }
$controller = new LeaveControllerFixture();
$controller->input = new class {
    public $values = array();
    public function method() { return 'post'; }
    public function get($key) { return '2026-01'; }
    public function post($key) { return $this->values[$key] ?? null; }
};
$controller->session = new class {
    public $data = array();
    public function userdata($key) { return $this->data[$key] ?? null; }
    public function set_userdata($key, $value) { $this->data[$key] = $value; }
    public function unset_userdata($key) { unset($this->data[$key]); }
    public function set_flashdata($key, $value) {}
};
$controller->Model_admin = new class($employees) {
    private $employees;
    public function __construct($employees) { $this->employees = $employees; }
    public function getemployees() { return $this->employees; }
};
$controller->Notifications = new class {
    public function get_notifications($filter) { return array(); }
    public function get_notifications_length($filter) { return 0; }
};
$controller->load = new class {
    public $data;
    public function model($name, $alias) {}
    public function view($name, $data) { $this->data = $data; }
};
$controller->Leaves = new class extends LeaveFixture {
    public $created = array();
    public function get_all() { return array(); }
    public function save_year_limits($year, $limits, $user_id) { foreach ($limits as $number => $limit) $this->limits[sprintf('%04d-%02d', $year, $number)] = $limit; return true; }
    public function create($data) { $this->created[] = $data; return true; }
};
$controller->Leaves->limits['2026-01'] = 0;
$controller->input->values = array('name' => 'Annual', 'start_date' => '2026-01-01',
    'end_date' => '2026-01-02', 'employee_ids' => array('1'), 'leave_type' => 'occasion', 'color' => '#123abc');
$controller->index();
checkLeave(count($controller->Leaves->created) === 0, 'Leave was saved before confirmation.');
$firstToken = $controller->load->data['confirmation_token'];
checkLeave($firstToken !== '', 'Warning did not issue a token.');
$controller->input->values['confirmation_token'] = $firstToken;
$controller->input->values['name'] = 'Changed request';
$controller->index();
checkLeave(count($controller->Leaves->created) === 0, 'Changed request reused old approval.');
$controller->input->values['confirmation_token'] = $controller->load->data['confirmation_token'];
$controller->Leaves->limits['2026-01'] = 0;
$controller->index();
checkLeave(count($controller->Leaves->created) === 1, 'Confirmed leave was not saved.');
checkLeave(!isset($controller->session->data['leave_capacity_confirmation']), 'Approval token was not consumed.');
checkLeave($GLOBALS['leave_redirect'] === '/admin/employees_leaves?month=2026-01', 'Save lost selected leave month.');
checkLeave($controller->Leaves->created[0]['leave_type'] === 'occasion' && $controller->Leaves->created[0]['color'] === '#123abc', 'Leave category/color were not persisted after confirmation.');
$controller->input->values['color'] = 'red; background:url(x)';
$controller->index();
checkLeave(count($controller->Leaves->created) === 1 && $controller->load->data['error'] !== '', 'Invalid color was accepted.');
$controller->input->values['color'] = '#123abc';
$controller->input->values['leave_type'] = 'invalid';
$controller->index();
checkLeave(count($controller->Leaves->created) === 1 && $controller->load->data['error'] !== '', 'Invalid category was accepted.');
echo "Employee leave confirmation flow checks passed.\n";

function expectLeaveDenial($controller, $permission, $method) {
    $controller->denied = array($permission);
    try {
        $controller->$method();
    } catch (RuntimeException $error) {
        checkLeave($error->getMessage() === 'Denied: ' . $permission, 'Unexpected permission failure.');
        return;
    }
    throw new RuntimeException('Missing permission check: ' . $permission);
}
expectLeaveDenial($controller, 'employees_leaves.view', 'index');
expectLeaveDenial($controller, 'employees_leaves.create', 'index');
$controller->input->values['action'] = 'save_limit';
expectLeaveDenial($controller, 'employees_leaves.limit.update', 'index');
expectLeaveDenial($controller, 'employees_leaves.delete', 'delete');
$controller->denied = array('employees_timetable.view', 'employees_leaves.view');
try {
    $controller->data();
    throw new RuntimeException('Data endpoint allowed an unauthorized viewer.');
} catch (RuntimeException $error) {
    checkLeave($error->getMessage() === 'Denied: employees_leaves.view', 'Data endpoint did not enforce view access.');
}
$data['capacity_warnings'] = array();
$data['month'] = '2026-02';
$data['can_create'] = false;
$data['can_set_limit'] = false;
$data['can_delete'] = true;
$html = $view->render($data);
checkLeave(strpos($html, 'id="leave-form"') === false, 'Create form visible without permission.');
checkLeave(strpos($html, 'id="leave-delete-modal"') !== false, 'Delete-only role cannot delete.');
checkLeave(strpos($html, 'id="employee-limit-1"') !== false && strpos($html, 'disabled') !== false, 'Monthly limit should be read-only.');
$data['can_create'] = true;
$data['can_delete'] = false;
$html = $view->render($data);
checkLeave(strpos($html, 'id="leave-form"') !== false, 'Create-only role cannot create.');
checkLeave(strpos($html, 'id="leave-delete-modal"') === false && strpos($html, 'class="leave-delete-form"') === false, 'Delete controls visible without permission.');

require dirname(__DIR__, 2) . '/application/models/Model_permissions.php';
$registry = new class extends Model_permissions {
    public $registrations = array();
    public function __construct() {}
    public function ensure_permissions($permissions, $parent = null) { $this->registrations[] = array($permissions, $parent); }
};
$registry->ensure_employee_leave_permissions();
$registry->ensure_employee_leave_permissions();
checkLeave(count($registry->registrations) === 2, 'Permission setup repeated within one request.');
checkLeave($registry->registrations[0][1] === 'employees_timetable.view', 'View permission inherited from wrong parent.');
checkLeave($registry->registrations[1][1] === 'employees_timetable.save', 'Write permissions inherited from wrong parent.');
checkLeave(count($registry->get_employee_leave_permissions()) === 4, 'Leave permission catalog is incomplete.');
echo "Employee leave permission and visibility checks passed.\n";

$period = new Timetable_period();
foreach (array('2026-11-20' => '2026-11', '2026-11-21' => '2026-12', '2026-12-21' => '2026-12', '2026-12-22' => '2027-01', '2028-02-19' => '2028-02', '2028-02-20' => '2028-03') as $today => $expected) {
    checkLeave($period->latest_month(new DateTimeImmutable($today)) === $expected, 'Ten-day month boundary failed: ' . $today);
}
echo "Leave month availability boundary checks passed.\n";

$controller->denied = array();
$year_limits = array_fill(1, 12, '3');
$year_limits[2] = '0';
$year_limits[12] = '7';
$controller->input->values = array('action' => 'save_limit', 'limit_year' => '2026', 'employee_limits' => $year_limits);
$controller->index();
checkLeave($controller->Leaves->limits['2026-02'] === 0 && $controller->Leaves->limits['2026-12'] === 7, 'Yearly dialog did not save all months or zero.');
$before_limits = $controller->Leaves->limits;
$controller->input->values['limit_year'] = 'invalid';
$controller->index();
checkLeave($controller->load->data['limit_error'] !== '', 'Invalid capacity year accepted.');
$controller->input->values['limit_year'] = '2026';
$controller->input->values['employee_limits'][2] = '-1';
$controller->index();
checkLeave($controller->load->data['limit_error'] !== '' && $controller->Leaves->limits === $before_limits, 'Invalid limit partially saved the year.');
checkLeave($controller->load->data['limit_values'][12] === '7', 'Failed validation lost entered limits.');
$controller->input->values['employee_limits'] = $year_limits;
unset($controller->input->values['employee_limits'][12]);
$controller->index();
checkLeave($controller->load->data['limit_error'] !== '' && $controller->Leaves->limits === $before_limits, 'Incomplete year was saved.');
echo "Yearly capacity save validation checks passed.\n";

$storage = new class extends Model_employee_leaves {
    public $db;
    public function __construct() {}
};
$storage->db = new class {
    public $rows = array();
    public $fail_month = '';
    private $before;
    public function trans_begin() { $this->before = $this->rows; }
    public function trans_status() { return true; }
    public function trans_commit() { return true; }
    public function trans_rollback() { $this->rows = $this->before; }
    public function replace($table, $row) {
        if ($row['month'] === $this->fail_month) return false;
        $this->rows[$row['month']] = $row;
        return true;
    }
};
checkLeave($storage->save_year_limits(2026, array_map('intval', $year_limits), 1), 'Yearly storage save failed.');
checkLeave(count($storage->db->rows) === 12 && $storage->db->rows['2026-02-01']['employee_limit'] === 0, 'Monthly records or zero missing.');
$before_rows = $storage->db->rows;
$storage->db->fail_month = '2026-06-01';
checkLeave(!$storage->save_year_limits(2026, array_fill(1, 12, 99), 1) && $storage->db->rows === $before_rows, 'Failed yearly save must roll back every month.');
echo "Yearly capacity transaction checks passed.\n";

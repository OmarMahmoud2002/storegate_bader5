<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class Other extends MY_Controller {

	function __construct()
	{
		// Increase memory limit for large Excel file processing
		ini_set('memory_limit', '512M');
        parent::__construct();
        $this->load->model('Model_admin');
        $this->load->model('Excel_import_model');
        $this->load->model('Model_shelves', 'Shelves');
        $this->load->model('Model_notifications', 'Notifications');
        $this->load->model('Model_requests', 'Requests');
        $this->load->model('Model_custody', 'Custodys');
		$this->load->model('Model_return_requests', 'ReturnRequests');
		$this->load->model('Model_sales', 'Sales');
        $this->load->model('Model_shipments', 'Shipments');
        $this->load->model('Model_operations', 'Operations');
		$this->load->model('Model_settings', 'Settings');
		$this->load->model('Model_employee_schedule', 'Schedule');
		$this->load->model('Model_variables', 'Variables');
		$this->load->model('Model_employees_sales', 'EmployeesSales');
		$this->load->model('Model_store_items', 'Store');
		$this->load->model('Model_permissions', 'Permissions');
		$this->load->helper('cupload');
		$this->load->helper('employees_sales');
        notifications($this);
        date_default_timezone_set('Asia/Riyadh');
    }

	private function getCellByZeroBasedColumn($sheet, $columnIndex, $row)
	{
		return $sheet->getCell(Coordinate::stringFromColumnIndex($columnIndex + 1) . $row);
	}

	private function columnLetterFromZeroBasedIndex($columnIndex)
	{
		return Coordinate::stringFromColumnIndex($columnIndex + 1);
	}
	
	
	public function index()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('home.view');
		$this->ensure_home_tab_permissions();

		$home_tab_visibility = array();
		foreach ($this->get_home_tab_permissions() as $tab_permission) {
			$home_tab_visibility[$tab_permission['tab']] = $this->get_permission_enabled($tab_permission['key']);
		}
		$this->data['home_tab_visibility'] = $home_tab_visibility;

		$filters = $this->get_return_request_filters();
		$this->data['return_requests'] = !empty($home_tab_visibility['returns']) ? $this->ReturnRequests->get_dashboard_requests(100, $filters) : array();
		$this->data['return_request_counts'] = !empty($home_tab_visibility['returns']) ? $this->ReturnRequests->get_status_counts($filters) : array('pending' => 0, 'approved' => 0, 'rejected' => 0, 'sold' => 0);
		$this->data['return_request_employees'] = !empty($home_tab_visibility['returns']) ? $this->ReturnRequests->get_request_employees() : array();
		$this->data['return_filters'] = $filters;
		$this->data['return_filter_query'] = $this->build_return_filter_query($filters);
		$this->data['page_title'] = 'الصفحة الرئيسية';
		$this->load->view('view_admin_home', $this->data);
	}

	private function get_return_request_filters($source = 'get')
	{
		$read = function ($key) use ($source) {
			return $source === 'post'
				? $this->input->post($key, true)
				: $this->input->get($key, true);
		};

		return array(
			'employee_id' => (int) $read('employee_id'),
			'serial' => mb_substr(trim((string) $read('serial')), 0, 120),
		);
	}

	/**
	 * Rebuilds the filter query string from validated values only, so the
	 * review redirect can return the admin to the same filtered view without
	 * ever echoing back an attacker-supplied target.
	 */
	private function build_return_filter_query($filters)
	{
		$query = array();
		if (!empty($filters['employee_id'])) {
			$query['employee_id'] = (int) $filters['employee_id'];
		}
		if ($filters['serial'] !== '') {
			$query['serial'] = $filters['serial'];
		}

		return empty($query) ? '' : '?' . http_build_query($query);
	}

	public function review_return_request()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}
		$this->check_permission('home.view');
		$this->ensure_home_tab_permissions();
		$this->check_permission('home.tabs.returns.view');

		$return_to = base_url().MOD_VALUE.'admin/index'
			. $this->build_return_filter_query($this->get_return_request_filters('post'))
			. '#return-requests';

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			redirect($return_to);
		}

		$request_id = (int) $this->input->post('request_id');
		$decision = trim((string) $this->input->post('decision', true));
		$admin_note = mb_substr(trim((string) $this->input->post('admin_note', true)), 0, 500);
		$admin_id = (int) $this->session->userdata('user_id');
		$request = $this->ReturnRequests->get_request($request_id);

		if (empty($request) || !in_array($request['status'], array('pending', 'reviewing'), true)) {
			$this->session->set_flashdata('error', 'تمت مراجعة هذا الطلب بالفعل أو لم يعد متاحًا');
			redirect($return_to);
		}

		if ($decision === 'reject') {
			if (!$this->ReturnRequests->claim_pending_request($request_id, $admin_id)) {
				$this->session->set_flashdata('error', 'الطلب قيد المراجعة بواسطة مدير آخر');
				redirect($return_to);
			}
			if ($admin_note === '') {
				$admin_note = 'تم رفض طلب الإرجاع من الإدارة';
			}
			if (!$this->ReturnRequests->complete_review($request_id, 'rejected', $admin_id, $admin_note)) {
				$this->session->set_flashdata('error', 'تعذر رفض الطلب، ربما تمت مراجعته من مدير آخر');
				redirect($return_to);
			}

			$this->notify_employee_about_return_decision($request, 'rejected', $admin_note);
			$this->session->set_flashdata('success', 'تم رفض طلب الإرجاع وإبلاغ الموظف');
			redirect($return_to);
		}

		if ($decision !== 'approve') {
			$this->session->set_flashdata('error', 'قرار المراجعة غير صحيح');
			redirect($return_to);
		}

		if (!$this->ReturnRequests->claim_pending_request($request_id, $admin_id)) {
			$this->session->set_flashdata('error', 'الطلب قيد المراجعة بواسطة مدير آخر');
			redirect($return_to);
		}

		$custody = $this->Custodys->get_custody(array(
			'id' => (int) $request['custody_id'],
			'user_id' => (int) $request['employee_id'],
		));
		if (empty($custody)) {
			$this->ReturnRequests->release_review($request_id, 'العهدة لم تعد مسجلة على الموظف؛ راجع سجل العمليات');
			$this->session->set_flashdata('error', 'العهدة لم تعد مسجلة على الموظف، لم يتم تنفيذ الطلب');
			redirect($return_to);
		}

		$return_result = $this->Custodys->return_to_stock((int) $request['custody_id']);
		if (!$return_result['status']) {
			$this->ReturnRequests->release_review($request_id, 'تعذر تنفيذ حركة الإرجاع، حاول مرة أخرى');
			$this->session->set_flashdata('error', 'تعذر إرجاع العهدة إلى المخزون');
			redirect($return_to);
		}

		if ($admin_note === '') {
			$admin_note = 'تمت الموافقة وإرجاع العهدة إلى المخزون';
		}
		if (!$this->ReturnRequests->complete_review($request_id, 'approved', $admin_id, $admin_note)) {
			$this->session->set_flashdata('error', 'تم إرجاع العهدة لكن تعذر تحديث حالة الطلب؛ راجع سجل العمليات');
			redirect($return_to);
		}

		$number = !empty($custody['serial_number']) ? $custody['serial_number'] : $custody['barcode'];
		$employee = $this->Model_admin->get_user_by_id($request['employee_id']);
		$employee_name = !empty($employee['user_fillname']) ? $employee['user_fillname'] : 'موظف غير متاح';
		$this->Operations->add_operation(array(
			'item_description' => $request['item_description'],
			'item_code' => $request['item_code'],
			'serial' => $number,
			'operation' => 'تمت الموافقة على طلب إرجاع العهدة من ' . $employee_name . ' إلى المخزون',
			'sales_order' => '0',
		), $admin_id);

		$this->notify_employee_about_return_decision($request, 'approved', $admin_note);
		$this->session->set_flashdata('success', 'تمت الموافقة وإرجاع العهدة وإبلاغ الموظف');
		redirect($return_to);
	}

	private function notify_employee_about_return_decision($request, $status, $note)
	{
		$description = $status === 'approved'
			? 'تمت الموافقة على طلب إرجاع العهدة: '
			: 'تم رفض طلب إرجاع العهدة: ';
		$description .= !empty($request['item_description']) ? $request['item_description'] : $request['item_code'];

		$this->Notifications->add_notification(array(
			'user_id' => (int) $request['employee_id'],
			'description' => $description . ($note !== '' ? ' - ' . $note : ''),
			'link' => base_url().MOD_VALUE.'user/custody#return-requests',
			'status' => 'unread',
			'date_created' => date('Y-m-d H:i:s'),
		));
	}

	public function profile()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$admin = $this->Model_admin->get_user_by_id($this->session->userdata('user_id'));
		if (empty($admin) || $admin['user_type'] !== 'admin') {
			redirect(base_url());
		}

		$this->data['page_title'] = 'الملف التعريفي';
		$this->data['admin'] = $admin;
		$this->load->view('view_admin_profile', $this->data);
	}

	public function change_admin_password()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			redirect(base_url().MOD_VALUE.'admin/profile');
		}

		$admin_id = $this->session->userdata('user_id');
		$current_password = $this->input->post('current_user_password', true);
		$new_password = $this->input->post('new_user_password', true);
		$confirm_password = $this->input->post('confirm_user_password', true);

		if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
			$this->session->set_flashdata('error', 'يجب إدخال كلمة السر الحالية والجديدة وتأكيدها');
			redirect(base_url().MOD_VALUE.'admin/profile');
		}

		if ($new_password !== $confirm_password) {
			$this->session->set_flashdata('error', 'كلمة السر الجديدة وتأكيدها غير متطابقين');
			redirect(base_url().MOD_VALUE.'admin/profile');
		}

		$admin = $this->Model_admin->get_user_by_id($admin_id);
		if (empty($admin) || $admin['user_type'] !== 'admin' || !hash_equals($admin['user_password'], md5($current_password))) {
			$this->session->set_flashdata('error', 'كلمة السر الحالية غير صحيحة');
			redirect(base_url().MOD_VALUE.'admin/profile');
		}

		$this->Model_admin->edit_employee($admin_id, [
			'user_password' => secure_data(md5($new_password))
		]);
		$this->session->set_flashdata('success', 'تم تغيير كلمة السر بنجاح');
		redirect(base_url().MOD_VALUE.'admin/profile');
	}

	public function permissions()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('roles.view');
		$this->ensure_home_tab_permissions();
		$this->ensure_product_search_note_permission();

		$this->data['permission_groups'] = $this->get_admin_permissions_catalog();
		

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$permissions = $this->input->post('permissions');
			if (!is_array($permissions)) {
				$permissions = [];
			}

			$role_id = $this->input->post('role_id');
			$role_name = $this->input->post('role_name');
			$role_description = $this->input->post('role_description');
			$action = $this->input->post('action');

			if (empty($role_name)) {
				$this->session->set_flashdata('error', 'اسم الصلاحية لا يمكن أن يكون فارغًا');
				redirect(base_url().MOD_VALUE.'admin/permissions');
			}

			$role_exists = $this->Permissions->get_role($role_id);

			if ($role_exists) {
				if ($action === 'create') {
					$this->session->set_flashdata('error', 'الصلاحية التي تريد إنشاءها موجودة بالفعل');
					redirect(base_url().MOD_VALUE.'admin/permissions');
				}
				if ($action === 'update') {
					$this->check_permission('roles.update');
				} else {
					$this->session->set_flashdata('error', 'الإجراء غير معروف');
					redirect(base_url().MOD_VALUE.'admin/permissions');
				}
			} else {
				if ($action === 'update') {
					$this->session->set_flashdata('error', 'الصلاحية التي تريد تحديثها غير موجودة');
					redirect(base_url().MOD_VALUE.'admin/permissions');
				} else if ($action === 'create') {
					$this->check_permission('roles.create');
				} else {
					$this->session->set_flashdata('error', 'الإجراء غير معروف');
					redirect(base_url().MOD_VALUE.'admin/permissions');
				}
			}

			if ($action == 'create') {
				$role_id = $this->Permissions->create_role($role_name, $role_description);
			}

			$this->Permissions->update_role($role_id, $role_name, $role_description);			
			$this->Permissions->add_role_permissions($role_id, $permissions);
			$this->session->set_flashdata('success', 'تم حفظ الصلاحيات بنجاح');
		}

		

		$segment = $this->uri->segment('3');

		if ($segment == 'create') {
			$this->data['selected_role'] = null;
			$this->data['selected_role_permissions'] = [];
		}

		if ($segment == 'update') {
			$role_id = $this->input->get('role_id');
			$role_selected = $this->Permissions->get_role($role_id);
			$this->data['selected_role'] = $role_selected;
			$this->data['selected_role_permissions'] = $this->Permissions->get_role_permissions_by_role_id($role_selected['id']);
		}

		$this->data['roles'] = $this->Permissions->get_roles();
		$this->data['segment'] = $segment;
		$this->load->view('view_admin_permissions', $this->data);
	}

	public function delete_permissions() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('roles.delete');

		$role_id = $this->input->get('role_id');
		if (empty($role_id)) {
			$this->session->set_flashdata('error', 'معرف الصلاحية لا يمكن أن يكون فارغًا');
			redirect(base_url().MOD_VALUE.'admin/permissions');
		}

		$this->Permissions->update_role_for_admins($role_id);

		$this->Permissions->delete_role_permissions($role_id);
		$this->Permissions->delete_role($role_id);
		$this->session->set_flashdata('success', 'تم حذف الصلاحيات بنجاح');
		redirect(base_url().MOD_VALUE.'admin/permissions');
	}

	private function get_admin_permissions_catalog()
	{
		$home_tab_permissions = array_map(function ($permission) {
			return array(
				'key' => $permission['key'],
				'label' => $permission['label'],
				'type' => $permission['type'],
			);
		}, $this->get_home_tab_permissions());

		return [
			[
				'section' => 'الصفحات الرئيسية والمخزون',
				'icon' => 'bi-box-seam',
				'pages' => [
					[
						'label' => 'الصفحة الرئيسية',
						'route' => 'admin/index',
						'permissions' => array_merge([
							['key' => 'home.view', 'label' => 'عرض الصفحة', 'type' => 'view'],
						], $home_tab_permissions),
					],
					[
						'label' => 'البحث عن منتج',
						'route' => 'admin/products/search',
						'permissions' => [
							['key' => 'product_search.view', 'label' => 'عرض الصفحة', 'type' => 'view', 'checked' => true],
							['key' => 'product_search.cart.add', 'label' => 'إضافة للسلة', 'type' => 'create', 'checked' => true],
							['key' => 'product_search.cart.remove', 'label' => 'حذف من السلة', 'type' => 'delete', 'checked' => true],
							['key' => 'product_search.custody.create', 'label' => 'تسليم لموظف', 'type' => 'special', 'checked' => true],
							['key' => 'product_search.delivery.create', 'label' => 'تسليم للتوصيل', 'type' => 'special', 'checked' => true],
							['key' => 'product_search.custody.return', 'label' => 'إرجاع من عهدة', 'type' => 'special', 'checked' => true],
							['key' => 'product_search.delivery.return', 'label' => 'إرجاع من التوصيل', 'type' => 'special', 'checked' => true],
							['key' => 'product_search.notes.create', 'label' => 'إضافة ملاحظات للسيريال', 'type' => 'create', 'checked' => true],
						],
					],
					[
						'label' => 'المنتجات',
						'route' => 'admin/dashboard',
						'permissions' => [
							['key' => 'products.view', 'label' => 'عرض المنتجات', 'type' => 'view'],
							['key' => 'products.import', 'label' => 'رفع ملف مخزون', 'type' => 'create'],
							['key' => 'products.edit.view', 'label' => 'فتح صفحة التعديل', 'type' => 'update'],
							['key' => 'products.update.basic', 'label' => 'تعديل البيانات الأساسية', 'type' => 'update'],
							['key' => 'products.update.extra', 'label' => 'تعديل بيانات إضافية', 'type' => 'update'],
						],
					],
					[
						'label' => 'منخفض الكمية',
						'route' => 'admin/products/low',
						'permissions' => [
							['key' => 'products.low.view', 'label' => 'عرض الصفحة', 'type' => 'view'],
						],
					],
					[
						'label' => 'العمليات',
						'route' => 'admin/products/operations',
						'permissions' => [
							['key' => 'operations.view', 'label' => 'عرض سجل العمليات', 'type' => 'view'],
						],
					],
				],
			],
			[
				'section' => 'الرفوف والموظفين',
				'icon' => 'bi-people',
				'pages' => [
					[
						'label' => 'الرفوف',
						'route' => 'admin/shelves',
						'permissions' => [
							['key' => 'shelves.view', 'label' => 'عرض الرفوف', 'type' => 'view'],
							['key' => 'shelves.create', 'label' => 'إضافة رف', 'type' => 'create'],
							['key' => 'shelves.rearrange', 'label' => 'ترتيب/نقل منتجات', 'type' => 'update'],
							['key' => 'shelves.delete', 'label' => 'حذف رف', 'type' => 'delete'],
							['key' => 'shelves.items.view', 'label' => 'عرض منتجات الرف', 'type' => 'special'],
							['key' => 'shelves.items.remove', 'label' => 'إزالة منتج من الرف', 'type' => 'special'],
						],
					],
					[
						'label' => 'الموظفين',
						'route' => 'admin/employees',
						'permissions' => [
							['key' => 'employees.view', 'label' => 'عرض الموظفين', 'type' => 'view'],
							['key' => 'employees.create', 'label' => 'إضافة موظف', 'type' => 'create'],
							['key' => 'employees.detail', 'label' => 'فتح تفاصيل موظف', 'type' => 'view'],
							['key' => 'employees.update', 'label' => 'تعديل بيانات موظف', 'type' => 'update'],
							['key' => 'employees.delete', 'label' => 'حذف موظف', 'type' => 'delete'],
							['key' => 'employees.custody.create', 'label' => 'إضافة عهدة', 'type' => 'special'],
							['key' => 'employees.settlement.create', 'label' => 'إضافة تسوية', 'type' => 'special'],
						],
					],
					[
						'label' => 'مبيعات الموظفين',
						'route' => 'admin/employees_sales_page',
						'permissions' => [
							['key' => 'employees_sales.view', 'label' => 'عرض الصفحة', 'type' => 'view'],
							['key' => 'employees_sales.upload', 'label' => 'رفع ملف مبيعات', 'type' => 'create'],
							['key' => 'employees_sales.search', 'label' => 'بحث في المبيعات', 'type' => 'special'],
							['key' => 'employees_sales.download', 'label' => 'تحميل بيانات موظف', 'type' => 'special'],
						],
					],
					[
						'label' => 'جداول الموظفين',
						'route' => 'admin/employees_timetable',
						'permissions' => [
							['key' => 'employees_timetable.view', 'label' => 'عرض الجداول', 'type' => 'view'],
							['key' => 'employees_timetable.save', 'label' => 'حفظ جدول موظف', 'type' => 'update'],
							['key' => 'employees_timetable.supervisors.view', 'label' => 'عرض جداول المشرفين', 'type' => 'special'],
						],
					],
				],
			],
			[
				'section' => 'الإدارة والحركة المالية',
				'icon' => 'bi-shield-check',
				'pages' => [
					[
						'label' => 'المديرين',
						'route' => 'admin/admins',
						'permissions' => [
							['key' => 'admins.view', 'label' => 'عرض المديرين', 'type' => 'view'],
							['key' => 'admins.create', 'label' => 'إضافة مدير', 'type' => 'create'],
							['key' => 'admins.update', 'label' => 'تعديل مدير', 'type' => 'update'],
							['key' => 'admins.delete', 'label' => 'حذف مدير', 'type' => 'delete'],
							['key' => 'admins.assign_role', 'label' => 'تعيين صلاحية', 'type' => 'special'],
						],
					],
					[
						'label' => 'الصلاحيات',
						'route' => 'admin/permissions',
						'permissions' => [
							['key' => 'roles.view', 'label' => 'عرض الصفحة', 'type' => 'view'],
							['key' => 'roles.create', 'label' => 'إنشاء صلاحية', 'type' => 'create'],
							['key' => 'roles.update', 'label' => 'تعديل صلاحية', 'type' => 'update'],
							['key' => 'roles.delete', 'label' => 'حذف صلاحية', 'type' => 'delete'],
						],
					],
					[
						'label' => 'الطلبات',
						'route' => 'admin/requests',
						'permissions' => [
							['key' => 'requests.view', 'label' => 'عرض الطلبات', 'type' => 'view'],
							['key' => 'requests.return', 'label' => 'إرجاع منتج للتخزين', 'type' => 'special'],
						],
					],
					[
						'label' => 'المبيعات',
						'route' => 'admin/sales',
						'permissions' => [
							['key' => 'sales.view', 'label' => 'عرض المبيعات', 'type' => 'view'],
						],
					],
					[
						'label' => 'الفروقات',
						'route' => 'admin/settlements',
						'permissions' => [
							['key' => 'settlements.view', 'label' => 'عرض الفروقات', 'type' => 'view'],
							['key' => 'settlements.create', 'label' => 'إضافة تسوية', 'type' => 'create'],
						],
					],
				],
			],
			[
				'section' => 'الشحنات والتقفيلة والنماذج',
				'icon' => 'bi-truck',
				'pages' => [
					[
						'label' => 'الشحنات',
						'route' => 'admin/shipments',
						'permissions' => [
							['key' => 'shipments.view', 'label' => 'عرض الشحنات', 'type' => 'view'],
							['key' => 'shipments.create', 'label' => 'إضافة شحنة', 'type' => 'create'],
							['key' => 'shipments.deliver', 'label' => 'تسليم شحنة', 'type' => 'update'],
							['key' => 'shipments.delete', 'label' => 'حذف شحنة', 'type' => 'delete'],
							['key' => 'shipments.packs.view', 'label' => 'رؤية الأكياس', 'type' => 'special'],
						],
					],
					[
						'label' => 'التقفيلة',
						'route' => 'admin/lock-admin',
						'permissions' => [
							['key' => 'lock_admin.view', 'label' => 'عرض التقفيلة', 'type' => 'view'],
							['key' => 'lock_admin.update', 'label' => 'تعديل تمارا والمحفظة والرصيد الإضافي', 'type' => 'update'],
							['key' => 'lock_admin.download', 'label' => 'تحميل تقفيلة موظف', 'type' => 'special'],
						],
					],
					[
						'label' => 'تأكيد التقفيلة',
						'route' => 'admin/lock-track',
						'permissions' => [
							['key' => 'lock_track.view', 'label' => 'عرض التأكيد', 'type' => 'view'],
							['key' => 'lock_track.update', 'label' => 'تعديل سجل', 'type' => 'update'],
							['key' => 'lock_track.delete', 'label' => 'حذف سجل', 'type' => 'delete'],
						],
					],
					[
						'label' => 'جرد المخزون',
						'route' => 'admin/stocktaking',
						'permissions' => [
							['key' => 'stocktaking.view', 'label' => 'عرض الجرد', 'type' => 'view'],
						],
					],
					[
						'label' => 'النماذج',
						'route' => 'admin/forms',
						'permissions' => [
							['key' => 'forms.view', 'label' => 'عرض النماذج', 'type' => 'view'],
							['key' => 'forms.settlement.view', 'label' => 'نموذج تسوية الغرامة', 'type' => 'special'],
							['key' => 'forms.replacement.view', 'label' => 'نموذج استبدال الجهاز', 'type' => 'special'],
						],
					],
					[
						'label' => 'الإعدادات',
						'route' => 'admin/settings',
						'permissions' => [
							['key' => 'settings.view', 'label' => 'عرض الإعدادات', 'type' => 'view'],
							['key' => 'settings.update', 'label' => 'تعديل الإعدادات', 'type' => 'update'],
						],
					],
				],
			],
		];
	}	

	private function get_home_tab_permissions()
	{
		$common = array(
			'type' => 'special',
			'page_label' => 'الصفحة الرئيسية',
			'route' => 'admin/index',
			'section_name' => 'الصفحات الرئيسية والمخزون',
			'section_icon' => 'bi-box-seam',
		);

		return array(
			array_merge($common, array('tab' => 'returns', 'key' => 'home.tabs.returns.view', 'label' => 'إظهار صفحه طلبات الإرجاع')),
			array_merge($common, array('tab' => 'low-stock', 'key' => 'home.tabs.low_stock.view', 'label' => 'إظهار صفحه المنتجات منخفضة الكمية')),
			array_merge($common, array('tab' => 'over-five-days', 'key' => 'home.tabs.over_five_days.view', 'label' => 'إظهار صفحه المنتجات التي تجاوزت 5 أيام')),
			array_merge($common, array('tab' => 'over-three-days', 'key' => 'home.tabs.over_three_days.view', 'label' => 'إظهار صفحه المنتجات التي تجاوزت 3 أيام')),
			array_merge($common, array('tab' => 'shortage', 'key' => 'home.tabs.shortage.view', 'label' => 'إظهار صفحه العجز')),
			array_merge($common, array('tab' => 'missing-details', 'key' => 'home.tabs.missing_details.view', 'label' => 'إظهار صفحه المنتجات التي تحتاج إضافة تفاصيل')),
		);
	}

	private function ensure_home_tab_permissions()
	{
		$this->Permissions->ensure_permissions($this->get_home_tab_permissions(), 'home.view');
	}

	private function ensure_product_search_note_permission()
	{
		$this->Permissions->ensure_permissions(array(
			array(
				'key' => 'product_search.notes.create',
				'label' => 'إضافة ملاحظات للسيريال',
				'type' => 'create',
				'page_label' => 'البحث عن منتج',
				'route' => 'admin/products/search',
				'section_name' => 'الصفحات الرئيسية والمخزون',
				'section_icon' => 'bi-box-seam',
			),
		), 'product_search.view');
	}



	function getSubColumnLettersByGroupLabel($filePath, $groupLabel, $topRow = 1, $subRow = 2) {
		$spreadsheet = IOFactory::load($filePath);
		$sheet = $spreadsheet->getActiveSheet();

		$highestColumn = $sheet->getHighestColumn();
		$highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

		// Step 1: Find the center column of the group label
		$centerCol = null;
		for ($col = 0; $col < $highestColumnIndex; $col++) {
			$topValue = trim($this->getCellByZeroBasedColumn($sheet, $col, $topRow)->getValue());
			if ($topValue === $groupLabel) {
				$centerCol = $col;
				break;
			}
		}

		if ($centerCol === null) {
			return array(); // Group label not found
		}

		$matchedColumns = array();

		// Step 2: Expand left (up to 2 columns)
		for ($offset = 2; $offset >= 1; $offset--) {
			$col = $centerCol - $offset;
			if ($col < 0) {
				continue;
			}

			$topValue = trim($this->getCellByZeroBasedColumn($sheet, $col, $topRow)->getValue());
			$subValue = trim($this->getCellByZeroBasedColumn($sheet, $col, $subRow)->getValue());

			if ($topValue === '' && $subValue !== '') {
				$matchedColumns[] = $this->columnLetterFromZeroBasedIndex($col);
			}
		}

		// Step 3: Include center column if it has a sub-label
		$subCenter = trim($this->getCellByZeroBasedColumn($sheet, $centerCol, $subRow)->getValue());
		if ($subCenter !== '') {
			$matchedColumns[] = $this->columnLetterFromZeroBasedIndex($centerCol);
		}

		// Step 4: Expand right (up to 2 columns)
		for ($offset = 1; $offset <= 2; $offset++) {
			$col = $centerCol + $offset;
			if ($col >= $highestColumnIndex) {
				break;
			}

			$topValue = trim($this->getCellByZeroBasedColumn($sheet, $col, $topRow)->getValue());
			$subValue = trim($this->getCellByZeroBasedColumn($sheet, $col, $subRow)->getValue());

			if ($topValue === '' && $subValue !== '') {
				$matchedColumns[] = $this->columnLetterFromZeroBasedIndex($col);
			}
		}

		return $matchedColumns;
	}

	function ValueOrNull($array, $key) {
		if (array_key_exists($key, $array)) {
			return $array[$key];
		} else {
			return ['TGT' => null, 'Digital Sales' => null, 'Sales' => null, 'Branch sales' => null, 'Total' => null, '%' => null];
		}
	}

	function requests() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('requests.view');

		if ($this->input->get('dates')) {
            $dates = explode(' - ', $this->input->get('dates'));
        } else {
            $dates = [null, null];
        }
        
		$datefrom = $dates[0];
		$dateto = $dates[1];

        $date_range = [
            date_format(date_create(), 'Y/m/d 00:00:00'),
            date_format(date_create(), 'Y/m/d 23:59:59'),
        ];

        if ($datefrom != null) {
            $date_range[0] = date_format(date_create($datefrom), 'Y/m/d 00:00:00');
        }

        if ($dateto != null) {
            $date_range[1] = date_format(date_create($dateto), 'Y/m/d 23:59:59');
        } 
    

        $this->data['requests'] = $this->Requests->get_requests_by_date($date_range[0], $date_range[1]);

		$this->load->view('view_admin_requests', $this->data);
	}


	function sales() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('sales.view');

        if ($this->input->get('dates')) {
            $dates = explode(' - ', $this->input->get('dates'));
        } else {
            $dates = [null, null];
        }
        
		$datefrom = $dates[0];
		$dateto = $dates[1];

        $date_range = [
            date_format(date_create(), 'Y/m/d 00:00:00'),
            date_format(date_create(), 'Y/m/d 23:59:59'),
        ];

        if ($datefrom != null) {
            $date_range[0] = date_format(date_create($datefrom), 'Y/m/d 00:00:00');
        }

        if ($dateto != null) {
            $date_range[1] = date_format(date_create($dateto), 'Y/m/d 23:59:59');
        } 
    
		$sales = $this->Sales->get_sales_by_date($date_range[0], $date_range[1]);
		$this->data['sales'] = $sales;

		$this->load->view('view_admin_sales', $this->data);
	}

    function shipments() {
        if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}


		$this->Shipments->ensure_delivery_date_column();

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $actionRequested = $this->input->post('action');
            if ($actionRequested == 'add_shipment') {
				$this->check_permission('shipments.create');
				$packs = $this->input->post('packs');
                if (!is_array($packs)) {
                    $packs = [];
                }

                $rowData = [
                    'shipment_number' => strtoupper($this->input->post('shipment_number')),
                    'packs_number' => $this->input->post('packs_number'),
                    'packs' => strtoupper(json_encode($packs)),
                    'date_created' => date('Y-m-d H:i:s A'),
                ];

                $msg = $this->Shipments->add_shipment($rowData);
                if ($msg['status'] == 'wrong') {
                    $this->session->set_flashdata('error', $msg['description']);
				} else {
                    $this->session->set_flashdata('success', 'تمت إضافة الشحنة');
                }
            } else if ($actionRequested == 'deliver_shipment') {
				$this->check_permission('shipments.deliver');
                $msg = $this->Shipments->deliver_shipment(
                    $this->input->post('shipment_identifier'),
                    $this->input->post('delivery_date')
                );

                if ($msg['status'] == 'wrong') {
                    $this->session->set_flashdata('error', $msg['description']);
                } else {
                    $this->session->set_flashdata('success', $msg['description']);
                }
            }
        }
        
		$this->check_permission('shipments.view');

        $this->data['shipments'] = $this->Shipments->get_shipments_by_lastdate();
        $this->load->view('view_admin_shipments', $this->data);
    }

    function delete_shipment() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('shipments.delete');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $id = $this->input->post('id');
            $this->Shipments->delete_shipment($id);
        }
    }

	function forms() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('forms.view');

		$this->data['forms_data_url'] = base_url().MOD_VALUE.'admin/forms-data';
		$this->load->view('view_admin_forms', $this->data);
	}

	function forms_settings() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->Variables->setdata('forms_manager_name', $this->encode_forms_value($this->input->post('manager_name', true)));
			$this->Variables->setdata('forms_manager_employee_id', $this->encode_forms_value($this->input->post('manager_employee_id', true)));
			$this->Variables->setdata('forms_stamp', $this->encode_forms_value($this->input->post('stamp', true)));
			$this->Variables->setdata('forms_store_name', $this->encode_forms_value($this->input->post('store_name', true)));
			$this->Variables->setdata('forms_return_reason', $this->encode_forms_value($this->input->post('return_reason', true)));
			$this->Variables->setdata('forms_settlement_service_package', $this->encode_forms_value($this->input->post('settlement_service_package', true)));
			$this->Variables->setdata('forms_settlement_contract_duration', $this->encode_forms_value($this->input->post('settlement_contract_duration', true)));

			$this->session->set_flashdata('success', 'تم حفظ إعدادات النماذج بنجاح');
			redirect(base_url().MOD_VALUE.'admin/settings');
		}

		redirect(base_url().MOD_VALUE.'admin/settings');
	}

	function forms_data() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			$this->output->set_status_header(403);
			echo json_encode(['status' => 'error'], JSON_UNESCAPED_UNICODE);
			return;
		}

		$employees = [];
		foreach ($this->Model_admin->getemployees() as $employee) {
			$employee_number = '';
			if (isset($employee['job_number']) && $employee['job_number'] !== '') {
				$employee_number = $employee['job_number'];
			} else if (isset($employee['user_employee_Id'])) {
				$employee_number = $employee['user_employee_Id'];
			}

			$employees[] = [
				'id' => isset($employee['user_id']) ? $employee['user_id'] : '',
				'name' => isset($employee['user_fillname']) ? $employee['user_fillname'] : '',
				'employee_number' => $employee_number,
			];
		}

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode([
				'status' => 'ok',
				'settings' => $this->get_forms_defaults(),
				'employees' => $employees,
			], JSON_UNESCAPED_UNICODE));
	}

	private function get_forms_defaults() {
		$keys = ['forms_manager_name', 'forms_manager_employee_id', 'forms_stamp', 'forms_store_name', 'forms_return_reason', 'forms_settlement_service_package', 'forms_settlement_contract_duration'];
		$values = $this->Variables->getmany($keys);

		return [
			'manager_name' => isset($values['forms_manager_name']) ? $this->decode_forms_value($values['forms_manager_name']) : '',
			'manager_employee_id' => isset($values['forms_manager_employee_id']) ? $this->decode_forms_value($values['forms_manager_employee_id']) : '',
			'stamp' => isset($values['forms_stamp']) ? $this->decode_forms_value($values['forms_stamp']) : '',
			'store_name' => isset($values['forms_store_name']) ? $this->decode_forms_value($values['forms_store_name']) : '',
			'return_reason' => isset($values['forms_return_reason']) ? $this->decode_forms_value($values['forms_return_reason']) : '',
			'settlement_service_package' => isset($values['forms_settlement_service_package']) ? $this->decode_forms_value($values['forms_settlement_service_package']) : '',
			'settlement_contract_duration' => isset($values['forms_settlement_contract_duration']) ? $this->decode_forms_value($values['forms_settlement_contract_duration']) : '',
		];
	}

	private function encode_forms_value($value) {
		$value = $value === null ? '' : $value;
		return 'b64:' . base64_encode($value);
	}

	private function decode_forms_value($value) {
		if ($value === null || $value === '') {
			return '';
		}

		if (strpos($value, 'b64:') === 0) {
			$decoded = base64_decode(substr($value, 4), true);
			return $decoded === false ? '' : $decoded;
		}

		if (preg_match('/^[?\s]+$/', $value)) {
			return '';
		}

		return $value;
	}


	function settings() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('settings.view');

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$this->check_permission('settings.update');
			
			$settings_section = $this->input->post('settings_section');

			if ($settings_section === 'forms') {
				$this->Variables->setdata('forms_manager_name', $this->encode_forms_value($this->input->post('manager_name', true)));
				$this->Variables->setdata('forms_manager_employee_id', $this->encode_forms_value($this->input->post('manager_employee_id', true)));
				$this->Variables->setdata('forms_stamp', $this->encode_forms_value($this->input->post('stamp', true)));
				$this->Variables->setdata('forms_store_name', $this->encode_forms_value($this->input->post('store_name', true)));
				$this->Variables->setdata('forms_return_reason', $this->encode_forms_value($this->input->post('return_reason', true)));
				$this->Variables->setdata('forms_settlement_service_package', $this->encode_forms_value($this->input->post('settlement_service_package', true)));
				$this->Variables->setdata('forms_settlement_contract_duration', $this->encode_forms_value($this->input->post('settlement_contract_duration', true)));
				$this->session->set_flashdata('success', 'تم حفظ إعدادات النماذج بنجاح');
			} else if ($settings_section === 'smtp') {
				$data = [
					'SMTP_mail_encoding' => $this->input->post('smtp_mail_encoding'),
					'SMTP_port' => $this->input->post('smtp_port'),
					'SMTP_host' => $this->input->post('smtp_host'),
					'SMTP_username' => $this->input->post('smtp_username'),
					'SMTP_password' => $this->input->post('smtp_password')
				];
				$this->Settings->update_fields($data);
			} else {
				$data = [
					'Website_name' => $this->input->post('website_name'),
					'Website_email' => $this->input->post('website_email'),
					'Website_domain' => $this->input->post('website_domain'),
					'Website_backup_email' => $this->input->post('website_backup_email')
				];
				$this->Settings->update_fields($data);
			}
		}

		$this->data['settings'] = $this->Settings->get_fields();
		$this->data['forms_settings'] = $this->get_forms_defaults();

		$this->load->view('view_admin_settings', $this->data);
	}

  function lock_track()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('lock_track.view');

		$this->data['locktrack'] = $this->Model_admin->getlock();
		$this->data['excel'] = $this->Excel_import_model->select2();
		$this->load->view('view_admin_lock_track',$this->data);
	}

	function stocktaking()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('stocktaking.view');

		$this->load->view('view_admin_stocktaking', $this->data);
	}

	  function employees()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('employees.view');

		$this->data['employees'] = $this->Model_admin->getemployees();
		$this->load->view('view_admin_employees',$this->data);
	}  

	private function prepare_employee_sales_month() {
		$month = $this->session->userdata('employee_sales_month');
		$record = $month ? $this->EmployeesSales->get_latest_upload_for_month($month) : null;
		if (!$month) {
			$current = $this->EmployeesSales->get_upload_record_by_filepath($this->Variables->getdata('uploaded_file_path'));
			if ($current) {
				$month = substr($current->individuals_date, 0, 7);
				$record = $this->EmployeesSales->get_latest_upload_for_month($month);
			}
		}
		$this->data['sales_months'] = $this->EmployeesSales->get_available_months();
		$this->data['selected_sales_month'] = $month;
		$this->data['file_uploaded_name'] = $record ? $record->filename : 'غير محدد';
		if ($this->session->userdata('employee_data') && (!$record ||
			$this->session->userdata('employee_sales_result_filepath') !== $record->filepath)) {
			$this->session->unset_userdata('employee_data');
			$this->data['employee_data'] = null;
		}
		if ($record) {
			$this->session->set_userdata('individuals_date', $record->individuals_date);
			$this->session->set_userdata('business_date', $record->business_date);
		}
		return $record;
	}

	function employees_sales_search() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('employees_sales.search');
		

		if (!$this->Variables->getdata('uploaded_file_path')) {
			$this->session->set_flashdata('error', 'يرجى رفع ملف Excel أولاً.');
		} else {
			// get individuals_date and business_date from database using filepath and then set them in session
			$uploadRecord = $this->EmployeesSales->get_upload_record_by_filepath($this->Variables->getdata('uploaded_file_path'));
			if ($uploadRecord) {
				$this->session->set_userdata('individuals_date', $uploadRecord->individuals_date);
				$this->session->set_userdata('business_date', $uploadRecord->business_date);
			}
			
		}

		// استرجاع مصطلح البحث لملء الحقل إذا كان البحث سابقًا غير ناجح
		$searchTerm = $this->session->flashdata('search_term_repopulate');
		if (!isset($searchTerm) || $searchTerm === null) {
			$searchTerm = $this->session->userdata('search_term_repopulate');
		}
		if(($searchTerm === null || !isset($searchTerm)) && $this->input->post('search_term')){ // إذا كان أول بحث
			$searchTerm = trim($this->input->post('search_term'));
		}

		$data = array(
			'employee_data' => $this->session->userdata('employee_data'), // للحفاظ على بيانات الموظف المعروض
			'search_error' => $this->session->flashdata('search_error') ?: $this->session->flashdata('error'),
			'search_term_value' => $searchTerm, // لإعادة ملء حقل البحث
			'file_uploaded_name' => ($this->Variables->getdata('uploaded_file_name')) ? $this->Variables->getdata('uploaded_file_name') : 'الملف المرفوع'
		);

		$this->data = array_merge($this->data, $data);
		$this->prepare_employee_sales_month();


		// إذا كانت هناك بيانات موظف، اعرض صفحة النتائج
		if ($this->data['employee_data']) {
            $this->load->view('employee_sales/view_admin_employee_sales_details', $this->data);
			return;
        }

        // إذا لم يكن هناك بيانات موظف، اعرض صفحة البحث العادية (نموذج فارغ)
        $this->load->view('employee_sales/view_admin_search_employees_sales', $this->data);
	}

	function employees_sales_page() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('employees_sales.view');
	
		// get individuals_date and business_date from database using filepath and then set them in session
		$uploadRecord = $this->EmployeesSales->get_upload_record_by_filepath($this->Variables->getdata('uploaded_file_path'));
		if ($uploadRecord) {
			$this->session->set_userdata('individuals_date', $uploadRecord->individuals_date);
			$this->session->set_userdata('business_date', $uploadRecord->business_date);
		}
			
		
		$this->prepare_employee_sales_month();
		$this->load->view('employee_sales/view_admin_search_employees_sales', $this->data);
	}

	function employees_sales_upload() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('employees_sales.upload');
		
		$this->load->view('employee_sales/view_admin_employees_sales_uploader', $this->data);
	}

	public function handle_employees_sales_upload()
    {
		$individualsDate = $this->input->post('individuals_date');
		$businessDate = $this->input->post('business_date');
		foreach ([$individualsDate, $businessDate] as $uploadDate) {
			$date = is_string($uploadDate) ? \DateTime::createFromFormat('!Y-m-d', $uploadDate) : false;
			if (!$date || $date->format('Y-m-d') !== $uploadDate) {
				$this->session->set_flashdata('upload_error', 'يرجى إدخال تاريخ صحيح للأفراد والأعمال. شهر الملف يُحدد من تاريخ الأفراد.');
				redirect(base_url('admin/employees_sales_upload'));
				return;
			}
		}
		$original_filename = $_FILES['userfile']['name'];

		// اسم تخزين فريد لكل رفعة (تاريخ+وقت+عشوائي+الاسم الأصلي مُطهَّرًا) بدل استخدام الاسم
		// الأصلي كما هو: يمنع استبدال ملف تاريخي مرفوع سابقًا عند تطابق اسم الملف (overwrite=TRUE
		// في do_upload). الاسم الأصلي يبقى محفوظًا كما هو في عمود filename بقاعدة البيانات.
		$extension = pathinfo($original_filename, PATHINFO_EXTENSION);
		$safeBase = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($original_filename, PATHINFO_FILENAME));
		$storageFilename = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '_' . $safeBase . '.' . $extension;

        $uploadStatus = do_upload($this, 'userfile', $storageFilename, './public/employees_sales/', 'xls|xlsx', 10 * 1024 * 1024);

		if ($uploadStatus['status'] == 'wrong') {
			$this->session->set_flashdata('upload_error', 'خطأ في رفع الملف: ' . $uploadStatus['description']);
			redirect(base_url('admin/employees_sales_upload'));
		} else {
			$filepath = './public/employees_sales/' . $uploadStatus['data']['file_name'];

			// تحقق من بنية الملف (أعمدة الهوية الإلزامية) قبل تفعيله كـ"الملف الحالي"
			// يستخدم load_employees_sales_workbook() (يقرأ شيت "staff sales" فقط متى أمكن
			// بدل الملف كاملًا - انظر تقرير تحليل الأداء) بدل IOFactory::load() المباشر
			try {
				$loaded = load_employees_sales_workbook($filepath);
				$spreadsheet = $loaded['spreadsheet'];
				$sheet = $loaded['sheet'];
				$validation = validate_employees_sales_workbook($sheet);
				$spreadsheet->disconnectWorksheets();
				unset($spreadsheet);
			} catch (\Exception $e) {
				log_message('error', '[handle_employees_sales_upload] ' . $e->getMessage());
				$validation = ['valid' => false, 'errors' => ['تعذّر قراءة الملف كملف Excel صالح: ' . $e->getMessage()], 'warnings' => []];
			}

			if (!$validation['valid']) {
				@unlink($filepath);
				$this->session->set_flashdata('upload_error', 'الملف غير متوافق مع بنية مبيعات الموظفين ولم يتم تفعيله: ' . implode('، ', $validation['errors']));
				redirect(base_url('admin/employees_sales_upload'));
			}

			 // حفظ التواريخ في الـ session
			$individualsDate = $this->input->post('individuals_date');
			$businessDate = $this->input->post('business_date');

			if ($individualsDate) {
				$this->session->set_userdata('individuals_date', $individualsDate);
			}
			if ($businessDate) {
				$this->session->set_userdata('business_date', $businessDate);
			}

			$successMessage = 'تم رفع الملف بنجاح: ' . htmlspecialchars($original_filename);
			if (!empty($validation['warnings'])) {
				$successMessage .= ' (تنبيه: ' . implode('، ', $validation['warnings']) . ')';
			}

			if (!$this->EmployeesSales->insert_upload_record($filepath, $original_filename, $individualsDate, $businessDate)) {
				@unlink($filepath);
				$this->session->set_flashdata('upload_error', 'تعذر حفظ سجل الملف. يرجى إعادة المحاولة.');
				redirect(base_url('admin/employees_sales_upload'));
				return;
			}
			$this->session->set_flashdata('upload_success', $successMessage);
			$this->Variables->setdata('uploaded_file_path', $filepath);
			$this->Variables->setdata('uploaded_file_name', $original_filename);
			$this->session->set_userdata('employee_sales_month', substr($individualsDate, 0, 7));

			$this->session->unset_userdata('employee_data');
			$this->session->unset_userdata('search_term');

			redirect(base_url('admin/employees_sales_search'));
		}

    }

	// getGroupsBySubLabelTriggers(), generate_fields_map(), generateTables(), getUserSales(),
	// getSalesAgentSales(), getBranchSupervisorSales(), get_direct_down_position(),
	// getBranchManagerSales(), getAreaManagerSales() and getSalesByOption() moved to
	// application/helpers/employees_sales_helper.php as the employees_sales_* free
	// functions (see load_employees_sales_data()): they now operate on an in-memory
	// dataset/index built once per request instead of re-scanning the whole worksheet
	// for every KPI group and every hierarchy level - see
	// EMPLOYEES_SALES_PERFORMANCE_ANALYSIS.md and
	// EMPLOYEES_SALES_PERFORMANCE_IMPLEMENTATION_REPORT.md.

	public function process_employee_sales_search()
    {
        $month = $this->input->post('sales_month');
        if ($month !== null && $month !== '') {
            if (!is_string($month) || !preg_match('/^[0-9]{4}-(0[1-9]|1[0-2])$/D', $month)) {
                $this->session->set_flashdata('search_error', 'يرجى اختيار شهر صحيح.');
                $this->session->unset_userdata('employee_data');
                redirect(base_url('admin/employees_sales_search'));
                return;
            }
            $this->session->set_userdata('employee_sales_month', $month);
        }
        $uploadRecord = $this->prepare_employee_sales_month();
        $filePath = $uploadRecord ? $uploadRecord->filepath : null;
        $searchTerm = trim((string) $this->input->post('search_term'));
		$calculation_method = $this->input->post('calculation_method') ? $this->input->post('calculation_method') : 1;
        
        // تخزين مصطلح البحث لإعادة ملء الحقل في حالة الخطأ أو عدم العثور
        $this->session->set_userdata('search_term_repopulate', $searchTerm);


        if (!$filePath || !file_exists($filePath)) {
            $this->session->set_flashdata('search_error', 'لا يوجد ملف متاح للشهر المحدد. يرجى اختيار شهر آخر أو إعادة رفع ملف هذا الشهر.');
            $this->session->unset_userdata(['employee_data']);
            redirect(base_url('admin/employees_sales_search'));
            return;
        }

		$individualsDate = $uploadRecord->individuals_date;

		if (empty($searchTerm)) {
            $this->session->set_flashdata('search_error', 'يرجى إدخال نص للبحث.');
            $this->session->unset_userdata('employee_data');
            redirect(base_url('admin/employees_sales_search'));
        }

		$option = 1;

        try {
            // يحمّل شيت "staff sales" فقط متى أمكن (بدل الملف كاملًا)، ويبني خريطة الأعمدة
            // وتصنيف مجموعات الـKPI وفهرس الموظفين (emp_id/supervisor_id) مرة واحدة، من ملف
            // كاش على القرص إن وُجد ومطابق لنفس الملف الفعلي، وإلا يبنيها الآن ويخزّنها للطلب
            // التالي (انظر تقرير تحليل الأداء وتقرير تنفيذه)
            $data = load_employees_sales_data($filePath);
            $headerMap = $data['headerMap'];
            $table1Items = $data['table1Items'];
            $table2Items = $data['table2Items'];
            $dataset = $data['dataset'];

            // أعمدة البحث الأساسية (قيم افتراضية احتياطية تطابق البنية القديمة لو تعذّر الاكتشاف)
            $empIdCol = isset($headerMap['emp_id']) ? $headerMap['emp_id'] : 'A';
            $empNationalIdCol = isset($headerMap['national_id']) ? $headerMap['national_id'] : 'C';
            $empNameCol = isset($headerMap['name']) ? $headerMap['name'] : 'D';
            $cbuUserCol = isset($headerMap['cbu_user']) ? $headerMap['cbu_user'] : 'G';
            $supervisorIdCol = isset($headerMap['supervisor_id']) ? $headerMap['supervisor_id'] : 'B';
            $positionCol = isset($headerMap['position']) ? $headerMap['position'] : 'E';
            $positionUnitedCol = isset($headerMap['position_united']) ? $headerMap['position_united'] : 'F';
            $ebuUserCol = isset($headerMap['ebu_user']) ? $headerMap['ebu_user'] : 'H';
            $regionCol = isset($headerMap['region']) ? $headerMap['region'] : 'I';
            $areaCol = isset($headerMap['area']) ? $headerMap['area'] : 'J';
            $branchNameCol = isset($headerMap['branch_name']) ? $headerMap['branch_name'] : 'K';
            $branchCodeCol = isset($headerMap['branch_code']) ? $headerMap['branch_code'] : 'L';
            $noteCol = isset($headerMap['note']) ? $headerMap['note'] : 'M';
            $vacationDaysCol = isset($headerMap['vacation_days']) ? $headerMap['vacation_days'] : 'N';
            $discountCol = isset($headerMap['discount_percentage']) ? $headerMap['discount_percentage'] : 'O';

            $found = false;
            $employeeData = null;
            foreach ($dataset['rows'] as $row => $rowData) {
                $currentEmpId = trim((string)($rowData[$empIdCol] ?? ''));
                $currentNationalId = trim((string)($rowData[$empNationalIdCol] ?? ''));
                $currentName = trim((string)($rowData[$empNameCol] ?? ''));
                $currentCbuUser = trim((string)($rowData[$cbuUserCol] ?? ''));


                if (strcasecmp($currentNationalId, $searchTerm) == 0 ||
                    mb_stripos($currentName, $searchTerm) !== false ||
                    strcasecmp($currentCbuUser, $searchTerm) == 0 ||
                    strcasecmp($currentEmpId, $searchTerm) == 0) {
                    
                    $employeeData = [
						'calculation_method' => $calculation_method,
                        'employee_info' => [
                            'name' => $currentName,
                            'national_id' => $currentNationalId,
                            'cbu_user' => $currentCbuUser,
                            'emp_id' => (string) ($rowData[$empIdCol] ?? ''),
                            'supervisor_id' => (string) ($rowData[$supervisorIdCol] ?? ''),
                            'position' => (string) ($rowData[$positionCol] ?? ''),
                            'position_united' => (string) ($rowData[$positionUnitedCol] ?? ''),
                            'ebu_user' => (string) ($rowData[$ebuUserCol] ?? ''),
                            'region' => (string) ($rowData[$regionCol] ?? ''),
                            'area' => (string) ($rowData[$areaCol] ?? ''),
                            'branch_name' => (string) ($rowData[$branchNameCol] ?? ''),
                            'branch_code' => (string) ($rowData[$branchCodeCol] ?? ''),
                            'note' => (string) ($rowData[$noteCol] ?? ''),
                            'vacation_days' => (string) ($rowData[$vacationDaysCol] ?? ''),
                            'discount_percentage' => (string) ($rowData[$discountCol] ?? '')
                        ],
                        'table1' => [],
                        'table2' => []
                    ];
                    

                    foreach ($table1Items as $key => $itemDetails) {
						if (in_array(null, array_values($itemDetails))){
							$employeeData['table1'][$key] = [
								'label' => $itemDetails['label'],
								'tgt' => 0,
								'branch_sales' => 0,
								'digital_sales' => 0,
								'total' => 0,
								'remaining' => 0,
								'percentage' => 0
							];
							continue;
						}
                        
						
						$sales = employees_sales_get_sales_by_option($dataset, $itemDetails, intval($calculation_method), $employeeData['employee_info']['emp_id'], $employeeData['employee_info']['position'], 1, $row);
						
						// calculate percentage
						$percentage = 0;
						if ($sales['tgt'] > 0) {
							$percentage = round(($sales['total'] * 100) / $sales['tgt'], 0);
						} elseif ($sales['total'] > 0) {
							$percentage = 100;
						}

						$employeeData['table1'][$key] = [
                            'label' => $itemDetails['label'],
                            'tgt' => $sales['tgt'],
                            'branch_sales' => $sales['branch_sales'],
                            'digital_sales' => $sales['digital_sales'],
                            'total' => $sales['total'],
                            'remaining' => $sales['tgt'] - $sales['total'],
                            'percentage' => $percentage . '%'
                        ];
                    }

                    

                    foreach ($table2Items as $key => $itemDetails) {

						if (in_array(null, array_values($itemDetails))){
							$employeeData['table2'][$key] = [
								'label' => $itemDetails['label'],
								'tgt' => 0,
								'sales' => 0,
								'remaining' => 0,
								'percentage' => 0
							];
							continue;
						}

						$sales = employees_sales_get_sales_by_option($dataset, $itemDetails, intval($calculation_method), $employeeData['employee_info']['emp_id'], $employeeData['employee_info']['position'], 2, $row);

						// calculate percentage
						$percentage = 0;
						if ($sales['tgt'] > 0) {
							$percentage = round(($sales['sales'] * 100) / $sales['tgt'], 0);
						} elseif ($sales['sales'] > 0) {
							$percentage = 100;
						}
						
						
						$employeeData['table2'][$key] = [
                            'label' => $itemDetails['label'],
                            'tgt' => $sales['tgt'],
                            'sales' => $sales['sales'],
                            'remaining' => $sales['tgt'] - $sales['sales'],
                            'percentage' => $percentage . '%'
                        ];
                    }

					
                    // تحويل نسبة الخصم
                    if (isset($employeeData['employee_info']['discount_percentage'])) {
                        $discount = str_replace('%', '', $employeeData['employee_info']['discount_percentage']);
                        $discount = floatval($discount) * 100;
                        $employeeData['employee_info']['discount_percentage'] = $discount . '%';
                    }

                    // ترتيب بيانات الجدول الأول تصاعدياً حسب النسبة المئوية
                    if (!empty($employeeData['table1'])) {
                        uasort($employeeData['table1'], function($a, $b) {
                            $percentA = (float)str_replace('%', '', $a['percentage']);
                            $percentB = (float)str_replace('%', '', $b['percentage']);
                            return $percentA - $percentB;
                        });
                    }

                    // ترتيب بيانات الجدول الثاني تصاعدياً حسب النسبة المئوية

					if (!empty($employeeData['table2'])) {
                        uasort($employeeData['table2'], function($a, $b) {
                            $percentA = (float)str_replace('%', '', $a['percentage']);
                            $percentB = (float)str_replace('%', '', $b['percentage']);
                            return $percentA - $percentB;
                        });
                    }

                    $found = true;
                    break;
                }
            }

            if ($found) {
                $this->session->set_userdata('employee_data', $employeeData);
                $this->session->set_userdata('employee_sales_result_filepath', $filePath);
                $this->session->unset_userdata('search_term_repopulate');
            } else {
                $this->session->set_flashdata('search_error', 'لم يتم العثور على موظف بالمعلومات المقدمة (رقم الموظف، رقم الهوية، الاسم، أو كود المستخدم): ' . htmlspecialchars($searchTerm));
                $this->session->unset_userdata('employee_data');
            }

        } catch (SpreadsheetException $e) {
            $this->session->setFlashdata('search_error', 'خطأ في قراءة ملف Excel: ' . htmlspecialchars($e->getMessage()));
            log_message('error', '[SpreadsheetException] ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
        } catch (\Exception $e) {
            $this->session->setFlashdata('search_error', 'حدث خطأ عام أثناء البحث: ' . htmlspecialchars($e->getMessage()));
            log_message('error', '[Exception] ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
        } finally {
            // Clean up spreadsheet object to free memory
            if (isset($spreadsheet)) {
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }
            // Force garbage collection
            gc_collect_cycles();
        }
        
        redirect(base_url('admin/employees_sales_search'));
    }

    

    

	public function getSubordinatesData()
    {
        // التحقق من أن الطلب هو AJAX
        if (!$this->input->is_ajax_request()) {
            echo json_encode([
                'success' => false,
                'message' => 'طلب غير صالح'
            ]);
            return;
        }

        $uploadRecord = $this->prepare_employee_sales_month();
        $filePath = $uploadRecord ? $uploadRecord->filepath : null;

        // الحصول على البيانات من الطلب
        $empId = $this->input->post('emp_id');
        $fieldKey = $this->input->post('field_key');
        $tableType = $this->input->post('table_type');
		$option = $this->input->post('option');

        if (!$empId || !$fieldKey || !$tableType) {
            echo json_encode([
                'success' => false,
                'message' => 'بيانات غير كاملة'
            ]);
            return;
        }

        if (!$filePath || !file_exists($filePath)) {
            echo json_encode([
                'success' => false,
                'message' => 'ملف البيانات غير موجود'
            ]);
            return;
        }

        try {
            // نفس مصدر البيانات المستخدم في البحث (شيت "staff sales" فقط + فهرس الموظفين +
            // تصنيف مجموعات الـKPI)، من الكاش إن وُجد ومطابق لنفس الملف، وإلا يُبنى الآن
            $data = load_employees_sales_data($filePath);
            $tables = [$data['table1Items'], $data['table2Items']];
            $dataset = $data['dataset'];

            // الحصول على معلومات المدير
            $managerInfo = $dataset['byId'][$empId] ?? null;

            if (!$managerInfo) {
                echo json_encode([
                    'success' => false,
                    'message' => 'لم يتم العثور على بيانات المدير'
                ]);
                return;
            }

			// يطابق منطق generate_fields_map()[1] الأصلي تمامًا (خريطة مقلوبة: التسمية
			// الأصلية => formatColName الخاص بها)، دون الحاجة لتخزين مجموعات الـKPI الخام
			$fieldsMap = [];
			foreach (array_merge(array_keys($data['table1Items']), array_keys($data['table2Items'])) as $topLabel) {
				$fieldsMap[$topLabel] = formatColName($topLabel);
			}

            // الحصول على جميع الموظفين التابعين في الهيكل
            $subordinates = employees_sales_find_all_subordinates_recursive($dataset, $empId, $fieldKey, $tableType, $tables, $managerInfo['position'], $option);

            // ترتيب حسب نسبة الإنجاز (من الأقل للأعلى)
            usort($subordinates, function($a, $b) {
                $percentA = (float)str_replace('%', '', $a['percentage']);
                $percentB = (float)str_replace('%', '', $b['percentage']);
                return $percentA - $percentB;
            });

            // حساب المجموع الإجمالي
            $totals = calculateTotals($subordinates, $tableType);

            // الحصول على اسم الحقل بالعربي
            $fieldLabel = $fieldsMap[$fieldKey];

            echo json_encode([
                'success' => true,
                'manager' => [
                    'name' => $managerInfo['name'],
                    'position' => $managerInfo['position'],
                    'emp_id' => $empId
                ],
                'field_info' => [
                    'key' => $fieldKey,
                    'label' => $fieldLabel,
                    'table_type' => $tableType
                ],
                'subordinates' => $subordinates,
                'totals' => $totals
            ]);

        } catch (Exception $e) {
            log_message('error', 'Error in getSubordinatesData: ' . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'حدث خطأ أثناء جلب البيانات: ' . $e->getMessage()
            ]);
        }
    }

	

	// findAllSubordinatesRecursive(), extractFieldData(), extractTable1FieldData() and
	// extractTable2FieldData() moved to application/helpers/employees_sales_helper.php
	// as employees_sales_find_all_subordinates_recursive() and
	// employees_sales_extract_*_field_data(): they now walk the dataset's bySupervisor
	// index instead of re-scanning the whole worksheet at every recursion level - see
	// EMPLOYEES_SALES_PERFORMANCE_ANALYSIS.md section 5.2.

	function employees_sales_details() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}
		
		$this->check_permission('employees_sales.search');
		
		$this->prepare_employee_sales_month();
		$this->data['employee_data'] = $this->session->userdata('employee_data');
		$this->data['search_term_value'] = $this->session->userdata('search_term_repopulate');
		$this->load->view('employee_sales/view_admin_employee_sales_details', $this->data);
	}

	public function download_employee_sales_data()
	{
		$this->check_permission('employees_sales.download');
		
		$employeeData = $this->session->userdata('employee_data');

		if (!$employeeData || !isset($employeeData['employee_info'])) {
			$this->session->set_flashdata('search_error', 'لا توجد بيانات موظف لتحميلها. يرجى البحث أولاً.');
			redirect(base_url('admin/employees_sales_search'));
		}

		// Create new spreadsheet object
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		
		// Set RTL direction
		$sheet->setRightToLeft(true);

		// Format title
		$sheet->mergeCells('A1:O1');
		$sheet->setCellValue('A1', 'تقرير أداء الموظف');
		$sheet->getStyle('A1')->applyFromArray(array(
			'font' => array('bold' => true, 'size' => 16),
			'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER)
		));
		
		// Employee info section title
		$sheet->setCellValue('A3', '━━━━━━━━━━ معلومات الموظف ━━━━━━━━━━');
		$sheet->mergeCells('A3:O3');
		$sheet->getStyle('A3')->applyFromArray(array(
			'font' => array('bold' => true),
			'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER)
		));

		// Employee info headers
		$infoHeaders = array(
			'👤 الاسم', '🆔 رقم الهوية', '👨‍💼 رقم الموظف', '👥 المشرف', '💼 المنصب',
			'📋 المنصب الموحد', '🏢 مستخدم CBU', '🏭 مستخدم EBU', '🌍 المنطقة', '🏙️ المدينة',
			'🏬 اسم الفرع', '🔢 كود الفرع', '📝 ملاحظات', '📅 أيام الإجازة', '💰 نسبة الخصم'
		);
		$sheet->fromArray($infoHeaders, NULL, 'A4');
		
		// Employee data
		$infoData = array(
			isset($employeeData['employee_info']['name']) ? $employeeData['employee_info']['name'] : '',
			isset($employeeData['employee_info']['national_id']) ? $employeeData['employee_info']['national_id'] : '',
			isset($employeeData['employee_info']['emp_id']) ? $employeeData['employee_info']['emp_id'] : '',
			isset($employeeData['employee_info']['supervisor_id']) ? $employeeData['employee_info']['supervisor_id'] : '',
			isset($employeeData['employee_info']['position']) ? $employeeData['employee_info']['position'] : '',
			isset($employeeData['employee_info']['position_united']) ? $employeeData['employee_info']['position_united'] : '',
			isset($employeeData['employee_info']['cbu_user']) ? $employeeData['employee_info']['cbu_user'] : '',
			isset($employeeData['employee_info']['ebu_user']) ? $employeeData['employee_info']['ebu_user'] : '',
			isset($employeeData['employee_info']['region']) ? $employeeData['employee_info']['region'] : '',
			isset($employeeData['employee_info']['area']) ? $employeeData['employee_info']['area'] : '',
			isset($employeeData['employee_info']['branch_name']) ? $employeeData['employee_info']['branch_name'] : '',
			isset($employeeData['employee_info']['branch_code']) ? $employeeData['employee_info']['branch_code'] : '',
			isset($employeeData['employee_info']['note']) ? $employeeData['employee_info']['note'] : '',
			isset($employeeData['employee_info']['vacation_days']) ? $employeeData['employee_info']['vacation_days'] : '',
			isset($employeeData['employee_info']['discount_percentage']) ? $employeeData['employee_info']['discount_percentage'] : '0%'
		);
		$sheet->fromArray($infoData, NULL, 'A5');

		// Format employee info cells
		$sheet->getStyle('A4:O5')->applyFromArray(array(
			'borders' => array(
				'allBorders' => array('style' => Border::BORDER_THIN)
			)
		));
		$sheet->getStyle('A4:O4')->applyFromArray(array(
			'fill' => array(
				'fillType' => Fill::FILL_SOLID,
				'color' => array('rgb' => 'E2EFDA')
			),
			'font' => array('bold' => true)
		));

		// Table 1 (Sales with digital sales)
		$currentRow = 7;
		if (isset($employeeData['table1']) && !empty($employeeData['table1'])) {
			// Table 1 title
			$sheet->mergeCells("A{$currentRow}:G{$currentRow}");
			$sheet->setCellValue("A{$currentRow}", '━━━━━━━━━━ جدول المبيعات مع المبيعات الرقمية ━━━━━━━━━━');
			$sheet->getStyle("A{$currentRow}")->applyFromArray(array(
				'font' => array('bold' => true),
				'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER)
			));
			
			$currentRow++;
			
			// Table 1 headers
			$table1Headers = array(
				'📊 المنتج/الخدمة', '🎯 الهدف', '🏬 مبيعات الفرع',
				'💻 Promo Code', '📈 الإجمالي', '⭐ المتبقي', '📊 نسبة الإنجاز'
			);
			$sheet->fromArray($table1Headers, NULL, "A{$currentRow}");
			
			// Format headers
			$sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray(array(
				'fill' => array(
					'fillType' => Fill::FILL_SOLID,
					'color' => array('rgb' => 'BDD7EE')
				),
				'font' => array('bold' => true),
				'borders' => array(
					'allBorders' => array('style' => Border::BORDER_THIN)
				)
			));
			
			$currentRow++;
			
			// Sort table1 data by percentage
			$table1Data = $employeeData['table1'];
			uasort($table1Data, function($a, $b) {
				$percentA = (float)str_replace('%', '', $a['percentage']);
				$percentB = (float)str_replace('%', '', $b['percentage']);
				return $percentB - $percentA;
			});
			
			// Add table1 data
			foreach ($table1Data as $item) {
				$rowData = array(
					'【' . (isset($item['label']) ? $item['label'] : '') . '】',
					isset($item['tgt']) ? $item['tgt'] : 0,
					isset($item['branch_sales']) ? $item['branch_sales'] : 0,
					isset($item['digital_sales']) ? $item['digital_sales'] : 0,
					isset($item['total']) ? $item['total'] : 0,
					isset($item['remaining']) ? $item['remaining'] : 0,
					'▶ ' . (isset($item['percentage']) ? $item['percentage'] : '0%')
				);
				$sheet->fromArray($rowData, NULL, "A{$currentRow}");
				
				$sheet->getStyle("A{$currentRow}:G{$currentRow}")->applyFromArray(array(
					'borders' => array(
						'allBorders' => array('style' => Border::BORDER_THIN)
					)
				));
				
				$sheet->getStyle("B{$currentRow}:F{$currentRow}")
					->getNumberFormat()
					->setFormatCode('#,##0');
				
				$currentRow++;
			}
			
			$currentRow += 2;
		}

		// Table 2 (Sales without digital)
		if (isset($employeeData['table2']) && !empty($employeeData['table2'])) {
			$sheet->mergeCells("A{$currentRow}:E{$currentRow}");
			$sheet->setCellValue("A{$currentRow}", '━━━━━━━━━━ جدول المبيعات بدون مبيعات رقمية ━━━━━━━━━━');
			$sheet->getStyle("A{$currentRow}")->applyFromArray(array(
				'font' => array('bold' => true),
				'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER)
			));
			
			$currentRow++;
			
			$table2Headers = array(
				'📊 المنتج/الخدمة', '🎯 الهدف', '💰 المبيعات',
				'⭐ المتبقي', '📊 نسبة الإنجاز'
			);
			$sheet->fromArray($table2Headers, NULL, "A{$currentRow}");
			
			$sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray(array(
				'fill' => array(
					'fillType' => Fill::FILL_SOLID,
					'color' => array('rgb' => 'BDD7EE')
				),
				'font' => array('bold' => true),
				'borders' => array(
					'allBorders' => array('style' => Border::BORDER_THIN)
				)
			));
			
			$currentRow++;
			
			// Sort table2 data
			$table2Data = $employeeData['table2'];
			uasort($table2Data, function($a, $b) {
				$percentA = (float)str_replace('%', '', $a['percentage']);
				$percentB = (float)str_replace('%', '', $b['percentage']);
				return $percentB - $percentA;
			});
			
			foreach ($table2Data as $item) {
				$rowData = array(
					'【' . (isset($item['label']) ? $item['label'] : '') . '】',
					isset($item['tgt']) ? $item['tgt'] : 0,
					isset($item['sales']) ? $item['sales'] : 0,
					isset($item['remaining']) ? $item['remaining'] : 0,
					'▶ ' . (isset($item['percentage']) ? $item['percentage'] : '0%')
				);
				$sheet->fromArray($rowData, NULL, "A{$currentRow}");
				
				$sheet->getStyle("A{$currentRow}:E{$currentRow}")->applyFromArray(array(
					'borders' => array(
						'allBorders' => array('style' => Border::BORDER_THIN)
					)
				));
				
				$sheet->getStyle("B{$currentRow}:D{$currentRow}")
					->getNumberFormat()
					->setFormatCode('#,##0');
				
				$currentRow++;
			}
		}

		// Auto-size columns
		foreach (range('A', 'O') as $col) {
			$sheet->getColumnDimension($col)->setAutoSize(true);
		}

		// Add report footer
		$currentRow += 2;
		$sheet->mergeCells("A{$currentRow}:O{$currentRow}");
		$sheet->setCellValue("A{$currentRow}", '━━━━━━━━━━ نهاية التقرير ━━━━━━━━━━');
		$sheet->getStyle("A{$currentRow}")->applyFromArray(array(
			'font' => array('bold' => true),
			'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER)
		));

		// Set filename
		$nationalId = isset($employeeData['employee_info']['national_id']) ? 
			$employeeData['employee_info']['national_id'] : 'data';
		$fileName = "employee_data_" . preg_replace('/[^a-z0-9_]/i', '', $nationalId) . ".xlsx";

		// Set headers for download
		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $fileName . '"');
		header('Cache-Control: max-age=0');

		// Save and output file
		$objWriter = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$objWriter->save('php://output');
		exit;
	}


	function employees_timetable() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('employees_timetable.view');

		$this->data['employees'] = $this->Model_admin->getemployees();
		$this->load->view('view_admin_employees_timetable', $this->data);
	}

	function get_employees() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}
		$employees = $this->Model_admin->getemployees();
		echo json_encode($employees);
	}

	function get_supervisors() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$supervisors = $this->Model_admin->getsupervisors();
		echo json_encode($supervisors);
	}

	function save_user_schedule() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		if ($this->get_permission_enabled('employees_timetable.save') == false) {
			echo json_encode(['success' => false, 'message' => 'ليس لديك صلاحية تعديل جدول الموظفين ولن يتم حفظ الجدول']);
			return;
		}

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$scheduleData = json_decode($this->input->post('scheduleData'), true);
			if (!is_array($scheduleData)) {
				echo json_encode(['success' => false, 'message' => 'بيانات الجدول غير صحيحة']);
				return;
			}
			$this->load->model('Model_employee_leaves', 'Leaves');
			$leaves = $this->Leaves->get_all();
			$employee_ids = array_map('intval', array_column($this->Model_admin->getemployees(), 'user_id'));
			$changed_key = (string) $this->input->post('changed_key');
			$success = true;

			foreach ($scheduleData as $key => $info) {
				if (!preg_match('/^(\d+)_(\d{4}-\d{2}-\d{2})$/', $key, $parts) || !is_array($info) ||
					!isset($info['type'], $info['status']) || !in_array($info['type'], array('am', 'pm', 'leave', 'note', 'add', 'store', 'event', 'off', ''), true) || !is_string($info['status'])) {
					echo json_encode(['success' => false, 'message' => 'بيانات الجدول غير صحيحة']);
					return;
				}
				$user_id = $parts[1];
				$date = $parts[2];
				$type = $scheduleData[$key]['type'];

				$data = [
					'user_id' => $user_id,
					'date' => $date,
					'type' => $type,
					'description' => $scheduleData[$key]['status']
				];

				$signature = in_array((int) $user_id, $employee_ids, true) ? $this->Leaves->signature($leaves, $user_id, $date) : '';
				if ($signature !== '') {
					// Only an explicitly edited cell may override a managed holiday.
					// Older clients posting the entire timetable must not create exceptions.
					if ($changed_key === $key) {
						$success = $this->Leaves->save_override($data, $signature) && $success;
					}
				} else {
					$success = $this->Schedule->update_schedule($data) && $success;
				}
			}
			echo json_encode(['success' => $success, 'message' => $success ? '' : 'تعذر حفظ الحالة، حاول مرة أخرى']);

		}
	}

	function get_schedule() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}
		
		
		$schedules = $this->Schedule->get_schedule();
		
		if (empty($schedules)) {
			echo '{}';
			return;
		}
		
		echo json_encode($schedules);
	}

	function get_supervisors_schedule() {
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}
		
		$user_id = $this->input->get('user_id');
		$schedules = $this->Schedule->get_schedule(true);
		
		if (empty($schedules)) {
			echo '{}';
			return;
		}
		
		echo json_encode($schedules);
	}
		
	function employee()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('employees.detail');
		
		$segment = $this->uri->segment('3');

		$employee = $this->Model_admin->get_user_by_id($segment);
		$custody1 = $this->Custodys->get_custodys(['user_id' => $segment, 'type' => 1]);
		$custody2 = $this->Custodys->get_custodys(['user_id' => $segment, 'type' => 2]);
		$sold_devices = $this->Excel_import_model->get_employee_sold_devices($segment);
		
		$this->data['employee'] = $employee;
		$this->data['custody_devices'] = $custody1;
		$this->data['custody_cards'] = $custody2;
		$this->data['sold_devices'] = $sold_devices;
		$this->load->view('view_admin_employee',$this->data);
	}

	function employees_has_sales()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}
		$this->data['employees'] = $this->Model_admin->getemployees();
		$this->load->view('view_admin_employees',$this->data);
	}  
	

	
	 function admins()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('admins.view');

		$this->data['admins'] = $this->Model_admin->getadmins();
		$this->load->view('view_admin_admins',$this->data);
	}  
	
	public function registration()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$error = '';
		$success = '';

		
       $user_create_date = date('Y-m-d');
		if(isset($_POST['form_registration'])) 
		{
		

			$valid = 1;
            $user_employee_Id = $this->input->post('user_employee_Id', true);
			$user_name        = $this->input->post('user_name', true);
			$user_fillname    = $this->input->post('user_fillname', true);
			$user_email       = $this->input->post('user_email', true);
			$user_password    = $this->input->post('user_password', true);
		    $user_type    = $this->input->post('user_type', true);
			$user_role_id = $this->input->post('user_role_id', true);

			if ($user_type == 'admin') {
				$this->check_permission('admins.create');
			} else if ($user_type == 'user') {
				$this->check_permission('employees.create');
			}

            if(empty($user_fillname)) {
            	$valid = 0;
			    $error .= "ادخل اسم الموظف".'<br>';
            }
            
            
            
			if(empty($user_name)) {
            	$valid = 0;
			    $error .= "ادخل اسم الدخول".'<br>';
            }

            if(empty($user_email)) {
            	$valid = 0;
			    $error .= "ادخل البريد الالكتروني".'<br>';
            }

            if( empty($user_password)) {
		    	$valid = 0;
		        $error .= "ادخل الرقم السري".'<br>';
		    }			
			
		    if (empty($user_role_id)) {
				$user_role_id = null;
			}

		    $chk = $this->Model_admin->check_duplicate_username($user_name);
            if($chk) {
            	$valid = 0;
            	$error .= "اسم الدخول مستخدم".'<br>';
            }

		    if($valid == 1)
		    {

		    	$form_data = array(
		    	    'user_employee_Id'        => secure_data($user_employee_Id),
		    	    'user_fillname'        => secure_data($user_fillname),
					'user_name'        => secure_data($user_name),
					'user_email'       => secure_data($user_email),
					'user_password'    => secure_data(md5($user_password)),
					'user_type'       => secure_data($user_type),
					'user_create_date'  => secure_data($user_create_date),
					'user_role_id'     => secure_data($user_role_id)
	            );
	            $this->Model_admin->registration($form_data);


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    }
		    else
		    {
		    	$this->session->set_flashdata('error',$error);
		    	redirect($this->agent->referrer());
		    }

		}
		else
		{
			redirect($this->agent->referrer());
		}
	}
	
	public function edit_employee()
	{
		$error = '';
		$success = '';

		
		if(isset($_POST['form1'])) 
		{
			

			$valid = 1;
			$user_id    = $this->input->post('user_id', true);
            $user_employee_Id = $this->input->post('user_employee_Id', true);
			$user_name        = $this->input->post('user_name', true);
			$user_fillname    = $this->input->post('user_fillname', true);
			$user_email       = $this->input->post('user_email', true);
			$user_password    = $this->input->post('user_password', true);
			$user_role_id = $this->input->post('user_role_id', true);

			# check if user edited is an admin
			$user = $this->Model_admin->get_user_by_id($user_id);
			if($user['user_type'] == 'admin') {
				$this->check_permission('admins.update');
			}
		    
            if(empty($user_fillname)) {
            	$valid = 0;
			    $error .= "ادخل اسم الموظف".'<br>';
            }
            
            
            
			if(empty($user_name)) {
            	$valid = 0;
			    $error .= "ادخل اسم الدخول".'<br>';
            }

            if(empty($user_email)) {
            	$valid = 0;
			    $error .= "ادخل البريد الالكتروني".'<br>';
            }

            if( empty($user_password)) {
		    	$valid = 0;
		        $error .= "ادخل الرقم السري".'<br>';
		    }
			
		    if (empty($user_role_id)) {
				$user_role_id = null;
			}

		    $chk = $this->Model_admin->username_duplication_check_edit($user_name,$user_id);
            if($chk) {
            	$valid = 0;
            	$error .= "اسم الدخول مستخدم".'<br>';
            }

		    if($valid == 1)
		    {

		    	$form_data = array(
		    	    'user_employee_Id'     => secure_data($user_employee_Id),
		    	    'user_fillname'        => secure_data($user_fillname),
					'user_name'            => secure_data($user_name),
					'user_email'           => secure_data($user_email),
					'user_password'        => secure_data(md5($user_password)),
					'user_role_id' => secure_data($user_role_id)
	            );
	            $this->Model_admin->edit_employee($user_id,$form_data);


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    }
		    else
		    {
		    	$this->session->set_flashdata('error',$error);
		    	redirect($this->agent->referrer());
		    }

		}
		else
		{
			redirect($this->agent->referrer());
		}
	}
	
	
	
	public function edit_emp_info()
	{
		$error = '';
		$success = '';

		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->check_permission('employees.update');
		
		if(isset($_POST['empinfoform'])) 
		{
			
			$valid = 1;
			$user_id    = $this->input->post('user_id', true);
            $user_fillname = $this->input->post('user_fillname', true);
			$user_name        = $this->input->post('user_name', true);
			$user_email    = $this->input->post('user_email', true);
            $job_number = $this->input->post('job_number', true);
			$new_user_password = $this->input->post('new_user_password', true);
			$confirm_user_password = $this->input->post('confirm_user_password', true);

		    	$form_data = array(
		    	    'user_fillname'     => secure_data($user_fillname),
		    	    'user_name'        => secure_data($user_name),
					'user_email'            => secure_data($user_email),
					'job_number' => secure_data($job_number),
	            );

	            $wants_password_change = !empty($new_user_password) || !empty($confirm_user_password);

	            if ($wants_password_change) {
	                if (empty($new_user_password) || empty($confirm_user_password)) {
	                    $this->session->set_flashdata('error', 'يجب إدخال كلمة السر الجديدة وتأكيدها');
	            		redirect($this->agent->referrer());
	            	}

	            	if ($new_user_password !== $confirm_user_password) {
	            		$this->session->set_flashdata('error', 'كلمة السر الجديدة وتأكيدها غير متطابقين');
	            		redirect($this->agent->referrer());
	            	}

	            	$form_data['user_password'] = secure_data(md5($new_user_password));
	            }

	            $this->Model_admin->edit_employee($user_id,$form_data);


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    
		}
		else
		{
			redirect($this->agent->referrer());
		}
	}

	
	public function edit_emp_users()
	{
		$error = '';
		$success = '';
		
		$this->check_permission('employees.update');
		
		if(isset($_POST['empusersform'])) 
		{
			
			$valid = 1;
			$user_id    = $this->input->post('user_id', true);
            $user_twasol = $this->input->post('user_twasol', true);
			$user_shahn        = $this->input->post('user_shahn', true);
			$user_jawwi    = $this->input->post('user_jawwi', true);
			$user_cubic_plus       = $this->input->post('user_cubic_plus', true);
		    		    
		    	$form_data = array(
		    	    'user_twasol'     => secure_data($user_twasol),
		    	    'user_shahn'        => secure_data($user_shahn),
					'user_jawwi'            => secure_data($user_jawwi),
					'user_cubic_plus'           => secure_data($user_cubic_plus)
	            );
	            $this->Model_admin->edit_employee($user_id,$form_data);


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    
		} else {
			redirect($this->agent->referrer());
		}
	}

		
	function add_tswya()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}
		
		$this->check_permission('settlements.create');
		

		$error = '';
		$success = '';

		
		if(isset($_POST['tswyaform'])) 
		{
			
			$valid = 1;
			$date = $this->input->post('date', true);
			$user_id    = $this->input->post('user_id', true);            
			$reason    = $this->input->post('reason', true);            
			$amount    = $this->input->post('insertedAmount', true);            
			

			if(empty($reason)) {
				$valid = 0;
				$error .= "ادخل السبب".'<br>';
			}

			if(empty($amount)) {
				$valid = 0;
				$error .= "ادخل المبلغ".'<br>';
			}
			if(empty($user_id)) {
				$valid = 0;
				$error .= "اختر الموظف".'<br>';
			}

			if ($valid == 1) {
				$form_data = array(
					'settlement_user'     => secure_data($user_id),
					'settlement_amount'     => secure_data($amount),
					'settlement_reason'        => secure_data($reason),
					'settlement_date'        => secure_data($date)
				);
				$this->Model_admin->insert_settlement($form_data);


				$success = "تم الحفظ بنجاح";
				
				$this->session->set_flashdata('success',$success);
				redirect($this->agent->referrer());
			} else {
				$this->session->set_flashdata('error',$error);
				redirect($this->agent->referrer());
			}
		} else {
			redirect($this->agent->referrer());
		}

	}
	
	/*
	public function add_user_custody()
	{
		$error = '';
		$success = '';

		
		if(isset($_POST['addcustodyform'])) 
		{
			
			$valid = 1;
			$user_create_date = date('Y-m-d H:i:s');
			$user_id    = $this->input->post('user_id', true);            
			$custody_type    = $this->input->post('custody_type', true);            
			$serials    = $this->input->post('serials', true);            
		    		    

			if(empty($custody_type)) {
            	$valid = 0;
			    $error .= "ادخل نوع العهدة".'<br>';
            }

			if (isset($serials)) {
				if (count($serials) == 0) {
					$valid = 0;
					$error .= "ادخل السيريالات".'<br>';
				}
				
				$found_serials = [];
				foreach ($serials as $serial) {
					if ($serial == '') {
						$valid = 0;
						$error .= 'يوجد سيريال فارغ' . '<br><br>';
						break;
					} else if (in_array($serial, $found_serials)) {
						$valid = 0;
						$error .= 'يوجد سيريال متكرر';
						break;
					}
					array_push($found_serials, $serial);
				}
			} else {
				$valid = 0;
				$error .= "ادخل السيريالات".'<br>';
			}
			

		    if($valid == 1) {
				foreach ($serials as $serial) {
					$form_data = array(
						'custody_user'     => secure_data($user_id),
						'custody_type'     => secure_data($custody_type),
						'custody_product_unified' => 0,
						'custody_date'        => secure_data($user_create_date),
						'custody_serial'            => secure_data($serial),
						'custody_note'           => ''
					);

                    $this->Model_admin->insert_custody($form_data);
					$this->Sales->add_sale([
						'userid' => $user_id,
						'identifier' => $serial,
						'user_custody_id' => $this->db->insert_id(),
						'date_created' => date('Y-m-d H:i:s')
					]);

                    if (!$this->Serials->check_for_serial($serial)) {
                        $this->Serials->update_serial(['location' => 1], $serial);
						$serialObj = $this->Serials->get_serials(['serial' => $serial])[0];

						$user_id = $this->session->userdata('user_id');
						$this->Products->check_low_product_and_update($user_id, $serialObj->productid, null, $this->Serials, $this->Barcodes, $this->Notifications);

                        $old_shelf_number = $this->Serials->get_serials(['serial' => $serial])[0]->shelf_number;
                        $this->Serials->update_serial(['shelf_number' => "$old_shelf_number-movedToEmployee"], $serial);
                        $this->Operations->add_operation(['serial' => $serial, 'operation' => 'تم تسجيل المنتج في عهدة ' . $this->Model_admin->get_user_by_id($user_id)['user_fillname'], 'sales_order' => '0'], $this->session->userdata('user_id'));
                    }
					
				}


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
				redirect($this->agent->referrer());
			} else {
		    	$this->session->set_flashdata('error',$error);
		    	redirect($this->agent->referrer());
		    }

		} else {
			redirect($this->agent->referrer());
		}
	}
	*/


  	public function delete_employee() {

		if(isset($_POST['form2'])) {
			$user_id    = $this->input->post('user_id', true);
	
			if(!$this->session->userdata('user_id')) {
				redirect(base_url());
				exit;
			}

			# check if the user deleted is an admin
			$user = $this->Model_admin->get_user_by_id($user_id);
			if ($user['user_type'] == 'admin') {
				$this->check_permission('admins.delete');
			} else if ($user['user_type'] == 'user') {
				$this->check_permission('employees.delete');
			}

			$this->Model_admin->delete_employee($user_id);
			$success = "تم الحذف بنجاح";
			$this->session->set_flashdata('success',$success);
			redirect(base_url().MOD_VALUE.'index.php/admin/employees');
		}
    }

	public function edit_lock()
	{

		$this->check_permission('lock_track.update');
		$error = '';
		$success = '';

		
		if(isset($_POST['form'])) 
		{
			
			$valid = 1;
			$user_lock_id           = $this->input->post('user_lock_id', true);
            $user_lock_cash        = $this->input->post('user_lock_cash', true);
			$user_lock_span        = $this->input->post('user_lock_span', true);

            if(empty($user_lock_cash)) {
            	$valid = 0;
			    $error .= "ادخل المبلغ كاش ".'<br>';
            }
            

			if(empty($user_lock_span)) {
            	$valid = 0;
			    $error .= "ادخل المبلغ بطاقة ".'<br>';
            }

           
		    if($valid == 1)
		    {

		    	$form_data = array(
		    	    'user_lock_cash'        => secure_data($user_lock_cash),
		    	    'user_lock_span'        => secure_data($user_lock_span)
	            );
	            $this->Model_admin->edit_lock($user_lock_id,$form_data);


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    }
		    else
		    {
		    	$this->session->set_flashdata('error',$error);
		    	redirect($this->agent->referrer());
		    }

		}
		else
		{
			redirect($this->agent->referrer());
		}
	}
  
  
  	public function delete_lock()
    {
		$this->check_permission('lock_track.delete');

		if (isset($_POST['form2'])) {
			$user_lock_id    = $this->input->post('user_lock_id', true);
	
			if(!$this->session->userdata('user_id')) {
				redirect(base_url());
				exit;
			}

			$this->Model_admin->delete_lock($user_lock_id);
			$success = "تم الحذف بنجاح";
			$this->session->set_flashdata('success', $success);
			redirect(base_url().MOD_VALUE.'admin/lock-track');
		}
    }
    
    
	function settlements()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->check_permission('settlements.view');

		//$this->data['locktrack'] = $this->Model_admin->getlock();
		//$this->load->view('view_admin_lock_admin',$this->data);
		
		//$this->data['excel'] = $this->Excel_import_model->select();
		
        // Get employees
        $employees = $this->Model_admin->getemployees();
        $this->data['employees'] = $employees;
        $this->load->view('view_admin_settlements',$this->data);
	}

	function lock_admin()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

		$this->ensure_lock_admin_permissions();
		$this->check_permission('lock_admin.view');

		$this->data['excel'] = $this->Excel_import_model->select();
        $this->load->view('view_admin_lock_admin',$this->data);
	}

	/**
	 * Register the التقفيلة permissions added after the page shipped so older
	 * databases do not fail the check_permission() lookup. Roles that can
	 * already open the page keep working until an admin narrows them.
	 */
	private function ensure_lock_admin_permissions()
	{
		$this->Permissions->ensure_permissions(array(
			array(
				'key' => 'lock_admin.update',
				'label' => 'تعديل تمارا والمحفظة والرصيد الإضافي',
				'type' => 'update',
				'page_label' => 'التقفيلة',
				'route' => 'admin/lock-admin',
				'section_name' => 'الشحنات والتقفيلة والنماذج',
				'section_icon' => 'bi-truck',
			),
			array(
				'key' => 'lock_admin.download',
				'label' => 'تحميل تقفيلة موظف',
				'type' => 'special',
				'page_label' => 'التقفيلة',
				'route' => 'admin/lock-admin',
				'section_name' => 'الشحنات والتقفيلة والنماذج',
				'section_icon' => 'bi-truck',
			),
		), 'lock_admin.view');
	}

	/**
	 * Store one of the manual closing amounts (تمارا / المحفظة / رصيد إضافي)
	 * for a single employee and day, and hand back the recalculated الفروقات
	 * so the open popup and the report table stay in step.
	 */
	function lock_admin_save_extra()
	{
		$this->output->set_content_type('application/json');

		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			$this->output->set_status_header(403);
			$this->output->set_output(json_encode(array('status' => 'error', 'message' => 'غير مصرح لك بهذا الإجراء'), JSON_UNESCAPED_UNICODE));
			return;
		}

		$this->ensure_lock_admin_permissions();
		if (!$this->get_permission_enabled('lock_admin.update')) {
			$this->output->set_status_header(403);
			$this->output->set_output(json_encode(array('status' => 'error', 'message' => 'ليس لديك صلاحية تعديل هذه المبالغ'), JSON_UNESCAPED_UNICODE));
			return;
		}

		$this->load->model('Model_lock_extras');
		$this->load->helper('lock_admin');

		$user_id = (int) $this->input->post('user_id', true);
		$date = trim((string) $this->input->post('date', true));
		$field = trim((string) $this->input->post('field', true));
		$amount = $this->input->post('amount', true);

		$fields = Model_lock_extras::$fields;

		if ($user_id < 1 || !isset($fields[$field])) {
			$this->output->set_status_header(400);
			$this->output->set_output(json_encode(array('status' => 'error', 'message' => 'بيانات غير صحيحة'), JSON_UNESCAPED_UNICODE));
			return;
		}

		$parsed_date = DateTime::createFromFormat('Y-m-d', $date);
		if (!$parsed_date || $parsed_date->format('Y-m-d') !== $date) {
			$this->output->set_status_header(400);
			$this->output->set_output(json_encode(array('status' => 'error', 'message' => 'التاريخ غير صحيح'), JSON_UNESCAPED_UNICODE));
			return;
		}

		if (!is_numeric($amount)) {
			$this->output->set_status_header(400);
			$this->output->set_output(json_encode(array('status' => 'error', 'message' => 'من فضلك أدخل مبلغًا صحيحًا'), JSON_UNESCAPED_UNICODE));
			return;
		}

		$date_start = trim((string) $this->input->post('date_start', true));
		$date_end = trim((string) $this->input->post('date_end', true));
		if ($date_start === '') {
			$date_start = $date;
		}
		if ($date_end === '') {
			$date_end = $date;
		}

		$parsed_start = DateTime::createFromFormat('Y-m-d', $date_start);
		$parsed_end = DateTime::createFromFormat('Y-m-d', $date_end);
		if (
			!$parsed_start || $parsed_start->format('Y-m-d') !== $date_start
			|| !$parsed_end || $parsed_end->format('Y-m-d') !== $date_end
			|| $date_start > $date_end
			|| $date < $date_start || $date > $date_end
		) {
			$this->output->set_status_header(400);
			$this->output->set_output(json_encode(array('status' => 'error', 'message' => 'نطاق التاريخ غير صحيح'), JSON_UNESCAPED_UNICODE));
			return;
		}

		$amount = round((float) $amount, 2);
		$saved = $this->Model_lock_extras->replace_range_total(
			$user_id,
			$date_start,
			$date_end,
			$date,
			$field,
			$amount,
			$this->session->userdata('user_id')
		);
		if (!$saved) {
			$this->output->set_status_header(500);
			$this->output->set_output(json_encode(array('status' => 'error', 'message' => 'تعذر حفظ المبلغ'), JSON_UNESCAPED_UNICODE));
			return;
		}

		$breakdown = lock_admin_employee_breakdown($user_id, $date_start, $date_end);

		$this->output->set_output(json_encode(array(
			'status' => 'ok',
			'field' => $field,
			'amount' => (float) $breakdown[$field],
			'difference' => (float) $breakdown['difference'],
		), JSON_UNESCAPED_UNICODE));
	}

	/**
	 * Formatted per-employee التقفيلة workbook, mirroring the popup rows.
	 */
	function lock_admin_export()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
			redirect(base_url());
		}

		$this->ensure_lock_admin_permissions();
		$this->check_permission('lock_admin.download');

		$this->load->helper('lock_admin');

		$user_id = (int) $this->input->get('user_id', true);
		$date_start = trim((string) $this->input->get('date_start', true));
		$date_end = trim((string) $this->input->get('date_end', true));

		if ($date_start === '') {
			$date_start = date('Y-m-d');
		}
		if ($date_end === '') {
			$date_end = date('Y-m-d');
		}

		$employee = $this->Model_admin->get_user_by_id($user_id);
		if (empty($employee)) {
			$this->session->set_flashdata('error', 'الموظف غير موجود');
			redirect(base_url().MOD_VALUE.'admin/lock-admin');
		}

		$breakdown = lock_admin_employee_breakdown($user_id, $date_start, $date_end);
		$spreadsheet = lock_admin_build_workbook($employee, $breakdown, $date_start, $date_end);

		$employee_number = '';
		if (!empty($employee['job_number'])) {
			$employee_number = $employee['job_number'];
		} else if (isset($employee['user_employee_Id'])) {
			$employee_number = $employee['user_employee_Id'];
		}

		$file_slug = preg_replace('/[^a-z0-9_]/i', '', (string) $employee_number);
		if ($file_slug === '') {
			$file_slug = (string) $user_id;
		}
		$file_name = 'lock_admin_' . $file_slug . '_' . $date_start . '_' . $date_end . '.xlsx';

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $file_name . '"');
		header('Cache-Control: max-age=0');

		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		exit;
	}

	
	
	function upload_form()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
		}

        $page = $this->input->get('page');
        if ($page == null) {
            $page = 0;
            $next = 2;
            $previous = 0;
        } else if ($page <= 0) {
            $page = 0;
            $next = 2;
            $previous = 0;
        } else {
            $next = $page + 1;
            $previous = $page - 1;
            $page -= 1;
        }

        $length = 20;
        $offset = $page * $length;

        $this->data['page'] = $page;
        $this->data['length'] = $length;
        $this->data['next'] = $next;
        $this->data['previous'] = $previous;
        $items = $this->Excel_import_model->select();
		$this->data['excel'] = array_slice($items, $offset, $length);
        $this->load->view('view_admin_upload_form', $this->data);
	}
	


public function delete_excellock()
    {
  if(isset($_POST['form2'])) 
		{
        $insert_excel_id    = $this->input->post('insert_excel_id', true);
  
    	if(!in_array($this->session->userdata('user_id'), $this->get_admins())) {
		    redirect(base_url());
            exit;
		}

        $this->Model_admin->delete_excellock($insert_excel_id);
        $success = "تم الحذف بنجاح";
		$this->session->set_flashdata('success',$success);
		redirect(base_url().MOD_VALUE.'admin/upload-form');
    }
    }
	
	public function edit_excellock()
	{
		$error = '';
		$success = '';

		
		if(isset($_POST['form1'])) 
		{
			
			$valid = 1;
			$insert_excel_uid           = $this->input->post('insert_excel_id', true);
            $insert_excel_twasel        = $this->input->post('insert_excel_twasel', true);

            if(empty($insert_excel_twasel)) {
            	$valid = 0;
			    $error .= "ادخل مبيعات تواصل	  ".'<br>';
            }
            


           
		    if($valid == 1)
		    {

		    	$form_data = array(
		    	    'insert_excel_twasel'        => secure_data($insert_excel_twasel)
	            );
	            $this->Model_admin->edit_excellock($insert_excel_uid,$form_data);


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    }
		    else
		    {
		    	$this->session->set_flashdata('error',$error);
		    	redirect($this->agent->referrer());
		    }

		}
		else
		{
			redirect($this->agent->referrer());
		}
	}
	
		public function edit_lock_track()
	{
		$error = '';
		$success = '';

		
		if(isset($_POST['form1'])) 
		{
			
			$valid = 1;
			$insert_excel_uid           = $this->input->post('insert_excel_id', true);
            $insert_excel_electronic    = $this->input->post('insert_excel_electronic', true);
            $insert_excel_jowy          = $this->input->post('insert_excel_jowy', true);
            $insert_excel_quickplus     = $this->input->post('insert_excel_quickplus', true);

		    if($valid == 1)
		    {

		    	$form_data = array(
		    	    'insert_excel_electronic'        => secure_data($insert_excel_electronic),
		    	    'insert_excel_jowy'              => secure_data($insert_excel_jowy),
		    	    'insert_excel_quickplus'         => secure_data($insert_excel_quickplus)
	            );
	            $this->Model_admin->edit_excellock($insert_excel_uid,$form_data);


	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    }
		    else
		    {
		    	$this->session->set_flashdata('error',$error);
		    	redirect($this->agent->referrer());
		    }

		}
		else
		{
			redirect($this->agent->referrer());
		}
	}
  

}

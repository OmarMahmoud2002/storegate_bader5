<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class User extends MY_Controller {

	function __construct()
	{
        parent::__construct();
        $this->load->model('Model_user');
        $this->load->model('Model_admin');
        $this->load->model('Excel_import_model');
        $this->load->model('Model_store_items', 'Store');
        $this->load->model('Model_users', 'Users');
        $this->load->model('Model_requests', 'Requests');
        $this->load->model('Model_notifications', 'Notifications');
		$this->load->model('Model_custody', 'Custody');
        $this->load->model('Model_return_requests', 'ReturnRequests');
        $this->notifications();
    }

    private function notifications() {
        $notifications = $this->Notifications->get_notifications(array('user_id' => $this->session->userdata('user_id')));
        $this->data['notifications'] = array_reverse($notifications);
        $this->data['notifications_count'] = $this->Notifications->get_notifications_length(
			array(
				'status' => 'unread', 
				'user_id' => $this->session->userdata('user_id'),
			)
		);
    }

    function delete_notification() {
        $id = $this->input->post('noti_id');
        $this->Notifications->delete_notification(array('id' => $id, 'user_id' => $this->session->userdata('user_id')));
        echo 'deleted';
    }

    private function get_employees() {
        $data = $this->Model_admin->getemployees();
        $ids = [];
        foreach ($data as $employee) {
            array_push($ids, strval($employee['user_id']));
        }
        return $ids;
    }

	public function index()
	{
        if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
		    redirect(base_url());
		}
		redirect(base_url() . 'user/products');
	}
	
	function login()
	{
		$error = '';
		$success = '';

		

		if(isset($_POST['form_login'])) 
		{
			$user_name = $this->input->post('user_name',true);
            $user_password = $this->input->post('user_password',true);

            $chk = $this->Model_user->check_access($user_name,$user_password);
            
            if(!$chk) 
            {
				$error = "خطأ اسم المستخدم او كلمة المرور";
                $this->session->set_flashdata('error',$error);
                redirect($this->agent->referrer());
            }
            else 
            {

	           	$user_data = array(
					'user_id'        => $chk['user_id'],
					'user_name'      => $chk['user_name'],
					'user_email'     => $chk['user_email'],
					'user_password'  => $chk['user_password']
                );
                $this->session->set_userdata($user_data);
                
                if ($chk['user_type'] == 'user'){
            	        redirect(base_url().MOD_VALUE.'user/products');
            	    }else{
            	        redirect(base_url().MOD_VALUE.'admin/dashboard');
            	    }
            }

		}
		else
		{
			redirect($this->agent->referrer());
		}
	}

	function products() {
		if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
		    redirect(base_url());
		}

		$date_range = $this->get_sold_devices_date_range();
		$sold_devices = $this->Excel_import_model->get_employee_sold_devices(
			$this->session->userdata('user_id'),
			$date_range['from_date'],
			$date_range['to_date']
		);

		$this->data['sold_devices'] = $sold_devices;
		$this->data['fromdate'] = $date_range['from_date'];
		$this->data['todate'] = $date_range['to_date'];
		$this->data['page_title'] = 'الأجهزة المباعة';

		$this->load->view('view_user_products', $this->data);

	}

	public function export_products()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
			redirect(base_url());
		}

		$date_range = $this->get_sold_devices_date_range();
		$sold_devices = $this->Excel_import_model->get_employee_sold_devices(
			$this->session->userdata('user_id'),
			$date_range['from_date'],
			$date_range['to_date']
		);

		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		$sheet->setTitle('Sold Devices');
		$sheet->setRightToLeft(true);
		$sheet->fromArray(array(
			'التاريخ',
			'رقم الطلب',
			'نوع المبيعات',
			'الوصف',
			'PRODUCT SERIAL NUMBER',
			'مبلغ المبيعات'
		), null, 'A1');

		$row_number = 2;
		foreach ($sold_devices as $sold_device) {
			$order_number = !empty($sold_device['insert_excel_new_ordern'])
				? $sold_device['insert_excel_new_ordern']
				: $sold_device['insert_excel_ordern'];

			$sheet->setCellValueExplicit('A' . $row_number, (string) $sold_device['insert_excel_date'], DataType::TYPE_STRING);
			$sheet->setCellValueExplicit('B' . $row_number, (string) $order_number, DataType::TYPE_STRING);
			$sheet->setCellValueExplicit('C' . $row_number, (string) $sold_device['insert_excel_sales_type'], DataType::TYPE_STRING);
			$sheet->setCellValueExplicit('D' . $row_number, (string) $sold_device['insert_excel_description'], DataType::TYPE_STRING);
			$sheet->setCellValueExplicit('E' . $row_number, (string) $sold_device['insert_excel_product_serial_number'], DataType::TYPE_STRING);
			$sheet->setCellValue('F' . $row_number, (float) $sold_device['insert_excel_sales_amount']);
			$row_number++;
		}

		$last_row = max(1, $row_number - 1);
		$sheet->getStyle('A1:F1')->applyFromArray(array(
			'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
			'fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => '667EEA')),
			'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER),
		));
		$sheet->getStyle('A1:F' . $last_row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
		if ($last_row >= 2) {
			$sheet->getStyle('F2:F' . $last_row)->getNumberFormat()->setFormatCode('#,##0.00');
			$sheet->getStyle('A2:F' . $last_row)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
		}
		$sheet->freezePane('A2');
		$sheet->setAutoFilter('A1:F' . $last_row);

		foreach (range('A', 'F') as $column) {
			$sheet->getColumnDimension($column)->setAutoSize(true);
		}

		$filename = 'sold_devices_' . $date_range['from_date'] . '_to_' . $date_range['to_date'] . '.xlsx';
		while (ob_get_level() > 0) {
			ob_end_clean();
		}

		header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		header('Content-Disposition: attachment;filename="' . $filename . '"');
		header('Cache-Control: max-age=0');
		header('Pragma: public');

		$writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
		$writer->save('php://output');
		exit;
	}

	private function get_sold_devices_date_range()
	{
		$default_from = date('Y-m-01');
		$default_to = date('Y-m-d');
		$from_date = $this->normalize_filter_date($this->input->get('from_date', true), $default_from);
		$to_date = $this->normalize_filter_date($this->input->get('to_date', true), $default_to);

		if ($from_date > $to_date) {
			$temporary_date = $from_date;
			$from_date = $to_date;
			$to_date = $temporary_date;
		}

		return array('from_date' => $from_date, 'to_date' => $to_date);
	}

	private function normalize_filter_date($date, $fallback)
	{
		if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
			return $fallback;
		}

		$parsed_date = DateTime::createFromFormat('!Y-m-d', $date);
		$date_errors = DateTime::getLastErrors();
		if (!$parsed_date || ($date_errors !== false && ($date_errors['warning_count'] > 0 || $date_errors['error_count'] > 0))) {
			return $fallback;
		}

		return $parsed_date->format('Y-m-d');
	}

    public function custody()
    {
        if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
            redirect(base_url());
        }

        $employee_id = (int) $this->session->userdata('user_id');
        $custodies = $this->Custody->get_custodys(array('user_id' => $employee_id));
        $requests = $this->ReturnRequests->get_employee_requests($employee_id, 50);
        $latest_requests = array();

        foreach ($requests as $request) {
            $request_custody_id = (int) $request['custody_id'];
            if (!isset($latest_requests[$request_custody_id])) {
                $latest_requests[$request_custody_id] = $request;
            }
        }

        foreach ($custodies as $index => $custody) {
            $number = !empty($custody['serial_number']) ? $custody['serial_number'] : $custody['barcode'];
            $item = $this->Store->get_store_item(array(
                'item_code' => $custody['item_code'],
                'serial_number' => $custody['serial_number'],
                'barcode' => $custody['barcode'],
            ));
            if (empty($item) && !empty($number)) {
                $item = $this->Store->get_serial_history_by_number($number);
            }

            $custodies[$index]['item_description'] = !empty($item['item_description'])
                ? $item['item_description']
                : $custody['item_code'];
            $custodies[$index]['request'] = isset($latest_requests[(int) $custody['id']])
                ? $latest_requests[(int) $custody['id']]
                : null;
        }

        $this->data['custodies'] = $custodies;
        $this->data['return_requests'] = $requests;
        $this->data['page_title'] = 'العهد';
        $this->load->view('view_user_custody', $this->data);
    }

    public function export_custody()
    {
        if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
            redirect(base_url());
        }

        $custodies = $this->Custody->get_custodys(array('user_id' => (int) $this->session->userdata('user_id')));
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Custody');
        $sheet->setRightToLeft(true);
        $sheet->fromArray(array('النوع', 'الوصف', 'كود المنتج', 'السيريال / الباركود', 'رقم الطلب', 'تاريخ الاستلام'), null, 'A1');

        $row_number = 2;
        foreach ($custodies as $custody) {
            $number = !empty($custody['serial_number']) ? $custody['serial_number'] : $custody['barcode'];
            $item = $this->Store->get_store_item(array('item_code' => $custody['item_code']));
            if (empty($item) && !empty($number)) {
                $item = $this->Store->get_serial_history_by_number($number);
            }
            $description = !empty($item['item_description']) ? $item['item_description'] : $custody['item_code'];

            $sheet->setCellValueExplicit('A' . $row_number, (int) $custody['type'] === 2 ? 'شريحة' : 'جهاز', DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('B' . $row_number, (string) $description, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('C' . $row_number, (string) $custody['item_code'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('D' . $row_number, (string) $number, DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('E' . $row_number, (string) $custody['order_number'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit('F' . $row_number, (string) $custody['date_created'], DataType::TYPE_STRING);
            $row_number++;
        }

        $last_row = max(1, $row_number - 1);
        $sheet->getStyle('A1:F1')->applyFromArray(array(
            'font' => array('bold' => true, 'color' => array('rgb' => 'FFFFFF')),
            'fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => '667EEA')),
            'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER),
        ));
        $sheet->getStyle('A1:F' . $last_row)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:F' . $last_row);
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="employee_custody_' . date('Y-m-d') . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save('php://output');
        exit;
    }

    public function request_product_return()
    {
        if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
            redirect(base_url());
        }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(base_url() . MOD_VALUE . 'user/custody');
        }

        $employee_id = (int) $this->session->userdata('user_id');
        $custody_id = (int) $this->input->post('custody_id');
        $employee_note = mb_substr(trim((string) $this->input->post('employee_note', true)), 0, 500);
        $custody = $this->Custody->get_custody(array('id' => $custody_id, 'user_id' => $employee_id));

        if (empty($custody)) {
            $this->session->set_flashdata('error', 'العهدة غير موجودة أو لم تعد مسجلة عليك');
            redirect(base_url() . MOD_VALUE . 'user/custody');
        }
        if ($this->ReturnRequests->get_pending_by_custody($custody_id)) {
            $this->session->set_flashdata('error', 'يوجد طلب إرجاع قيد المراجعة لهذه العهدة بالفعل');
            redirect(base_url() . MOD_VALUE . 'user/custody');
        }

        $number = !empty($custody['serial_number']) ? $custody['serial_number'] : $custody['barcode'];
        $item = $this->Store->get_store_item(array('item_code' => $custody['item_code']));
        if (empty($item) && !empty($number)) {
            $item = $this->Store->get_serial_history_by_number($number);
        }
        $description = !empty($item['item_description']) ? $item['item_description'] : $custody['item_code'];

        $request_id = $this->ReturnRequests->add_request(array(
            'custody_id' => $custody_id,
            'employee_id' => $employee_id,
            'item_code' => $custody['item_code'],
            'item_description' => $description,
            'serial_number' => $custody['serial_number'],
            'barcode' => $custody['barcode'],
            'order_number' => $custody['order_number'],
            'custody_type' => (int) $custody['type'],
            'custody_date' => $custody['date_created'],
            'employee_note' => $employee_note,
            'status' => 'pending',
            'requested_at' => date('Y-m-d H:i:s'),
        ));

        if (!$request_id) {
            $this->session->set_flashdata('error', 'تعذر تسجيل طلب الإرجاع، حاول مرة أخرى');
            redirect(base_url() . MOD_VALUE . 'user/custody');
        }

        $employee = $this->Model_admin->get_user_by_id($employee_id);
        foreach ($this->get_admins() as $admin_id) {
            $this->Notifications->add_notification(array(
                'user_id' => $admin_id,
                'description' => 'طلب إرجاع عهدة جديد من ' . $employee['user_fillname'] . ' - ' . $description,
                'link' => base_url() . MOD_VALUE . 'admin/index#return-requests',
                'status' => 'unread',
                'date_created' => date('Y-m-d H:i:s'),
            ));
        }

        $this->session->set_flashdata('success', 'تم إرسال طلب الإرجاع للإدارة بنجاح');
        redirect(base_url() . MOD_VALUE . 'user/custody#return-requests');
    }

	function lock()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
		    redirect(base_url());
		}

		$this->data['page_title'] = 'التقفيلة اليومية';
		$this->load->view('view_user_lock', $this->data);
	}

	function profile()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
		    redirect(base_url());
		}

		$this->data['employee'] = $this->Model_admin->get_user_by_id($this->session->userdata('user_id'));
		$this->data['page_title'] = 'الملف التعريفي';
		$this->load->view('view_user_profile', $this->data);
	}

	function change_password()
	{
		if(!in_array($this->session->userdata('user_id'), $this->get_employees())) {
		    redirect(base_url());
		}

		if ($_SERVER['REQUEST_METHOD'] != 'POST') {
			redirect(base_url().MOD_VALUE.'user/profile');
		}

		$user_id = $this->session->userdata('user_id');
		$current_user_password = $this->input->post('current_user_password', true);
		$new_user_password = $this->input->post('new_user_password', true);
		$confirm_user_password = $this->input->post('confirm_user_password', true);

		if (empty($current_user_password) || empty($new_user_password) || empty($confirm_user_password)) {
			$this->session->set_flashdata('error', 'يجب إدخال كلمة السر القديمة والجديدة وتأكيدها');
			redirect(base_url().MOD_VALUE.'user/profile');
		}

		if ($new_user_password !== $confirm_user_password) {
			$this->session->set_flashdata('error', 'كلمة السر الجديدة وتأكيدها غير متطابقين');
			redirect(base_url().MOD_VALUE.'user/profile');
		}

		$employee = $this->Model_admin->get_user_by_id($user_id);
		if (empty($employee) || md5($current_user_password) !== $employee['user_password']) {
			$this->session->set_flashdata('error', 'كلمة السر القديمة غير صحيحة');
			redirect(base_url().MOD_VALUE.'user/profile');
		}

		$this->Model_admin->edit_employee($user_id, ['user_password' => secure_data(md5($new_user_password))]);
		$this->session->set_flashdata('success', 'تم تغيير كلمة السر بنجاح');
		redirect(base_url().MOD_VALUE.'user/profile');
	}

	private function normalize_lock_date($date)
	{
		$date = trim((string) $date);
		$date_parts = array();

		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $date_parts)) {
			$year = (int) $date_parts[1];
			$month = (int) $date_parts[2];
			$day = (int) $date_parts[3];
		} else if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $date_parts)) {
			// Some browsers display and submit the date as MM/DD/YYYY.
			$year = (int) $date_parts[3];
			$month = (int) $date_parts[1];
			$day = (int) $date_parts[2];
		} else {
			return null;
		}

		if (!checkdate($month, $day, $year)) {
			return null;
		}

		return sprintf('%04d-%02d-%02d', $year, $month, $day);
	}


public function sendlock()
	{
		$error = '';
		$success = '';

		if(isset($_POST['form_lock'])) 
		{

			$valid = 1;

			$user_lock_cash        = $this->input->post('user_lock_cash', true);
			$user_lock_span        = $this->input->post('user_lock_span', true);
			$user_lock_time        = $this->input->post('user_lock_time', true);
			
			$user_lock_electronic  = $this->input->post('user_lock_electronic', true);
			$user_lock_jowy        = $this->input->post('user_lock_jowy', true);
			$user_lock_quick_plus  = $this->input->post('user_lock_quick_plus', true);
			


			$session_user_id = (string) $this->session->userdata('user_id');
			$is_admin = in_array($session_user_id, $this->get_admins(), true);

			if ($is_admin) {
				$user_id = (string) $this->input->post('user_id', true);
				$send_date = $this->normalize_lock_date($user_lock_time);

				if (!in_array($user_id, $this->get_employees(), true)) {
					$valid = 0;
					$error .= "اختر الموظف ".'<br>';
				}

				if ($send_date === null) {
					$valid = 0;
					$error .= "ادخل تاريخ صحيح".'<br>';
				} else if ($send_date > date('Y-m-d')) {
					$valid = 0;
					$error .= "لا يمكن إضافة تقفيلة بتاريخ مستقبلي".'<br>';
				}
			} else {
				if (!in_array($session_user_id, $this->get_employees(), true)) {
					redirect(base_url());
					exit;
				}

				// Employee submissions remain restricted to the current day.
				$user_id = $session_user_id;
				$send_date = date('Y-m-d');
			}
	        
	        
			if($user_lock_cash < 0 ) {
            	$valid = 0;
			    $error .= "ادخل المبلغ كاش (نقدي)	".'<br>';
            }

            if($user_lock_span < 0 ) {
            	$valid = 0;
			    $error .= "ادخل المبلغ بطاقة (سبان)".'<br>';
            }
            
            if($user_lock_cash ==0 and $user_lock_span == 0 ) {
            	$valid = 0;
			    $error .= "ادخل المبلغ كاش + بطاقة (سبان)".'<br>';
            }
            
            if($valid == 1)
		    {
		        
				$chk = $this->Model_user->check_duplicate_day($user_id,$send_date);
				if($chk) {
					$valid = 0;
					$error .= "لم يتم الحفظ تم اضافة التقفيلة لهذا اليوم".'<br>';
					if(!$is_admin){
						$error .= "للتعديل تواصل مع مدير النظام".'<br>';
					}
					$this->session->set_flashdata('error',$error);
					redirect($this->agent->referrer());
            }else{
		        $form_data = array(
		            'user_lock_userid'        => secure_data($user_id),
					'user_lock_cash'        => secure_data($user_lock_cash),
					'user_lock_span'       => secure_data($user_lock_span),
					'user_lock_time'       => secure_data($send_date)
	            );
	            $this->Model_user->sendlock($form_data);
		        
		        $form_data_sales = array(
		            'insert_excel_uid'        => secure_data($user_id),
		            'insert_excel_date'          => secure_data($send_date),
					'insert_excel_electronic' => secure_data($user_lock_electronic),
					'insert_excel_jowy'       => secure_data($user_lock_jowy),
					'insert_excel_quickplus'  => secure_data($user_lock_quick_plus)
	            );
	           $this->Excel_import_model->insert_sales($form_data_sales);
		        
		        
		        
	            $success = "تم الحفظ بنجاح";
	            
        		$this->session->set_flashdata('success',$success);
        		redirect($this->agent->referrer());
		    }}
		    else
		    {
		    	$this->session->set_flashdata('error',$error);
		    	redirect($this->agent->referrer());
		    }

            
}
}


	function logout() 
	{
        $this->session->sess_destroy();
        redirect(base_url());
    }

    
}

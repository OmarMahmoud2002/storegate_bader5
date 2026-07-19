<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Shelves extends MY_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('Model_admin');
        $this->load->model('Model_store_items', 'Store');
        $this->load->model('Model_shelves', 'Shelves');
        $this->load->model('Model_custody', 'Custodys');
        $this->load->model('Model_requests', 'Requests');
        $this->load->model('Model_notifications', 'Notifications');
        $this->load->model('Model_operations', 'Operations');
        $this->load->model('Model_permissions', 'Permissions');
        notifications($this);
        ini_set('memory_limit', '512M');
    }

    public function index()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('shelves.view');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $action = $this->input->post('action');


            if ($action == 'add') {
                $this->check_permission('shelves.create');
                $shelf_num = $this->input->post('shelf_num');
                $result = $this->Shelves->add_shelf(['shelf_number' => $shelf_num]);

                if ($result['status'] == false) {
                    $this->session->set_flashdata('error', $result['description']);
                }

            } else if ($action == 'rearrange_shelf') {
                $this->check_permission('shelves.rearrange');
                $serials = $this->input->post('serials');
                $shelf_id = $this->input->post('shelf_id');
                $posters = $this->input->post('posters');
                $errors = '';

                if (!is_array($serials)) {
                    $serials = array();
                }

                if (!is_array($posters)) {
                    $posters = array();
                }

                foreach ($serials as $i => $serial_number) {
                    if ($serial_number == '') {
                        $errors .= 'السيريال فارغ' . '<br><br>';
                        continue;
                    }

                    $item_without_s = $this->Store->get_store_item(['serial_number' => $serial_number]);
                    $item_with_s = $this->Store->get_store_item(['serial_number' => preg_replace('/^[Ss]/', '', $serial_number, 1)]);

                    $item = null;

                    if ($item_without_s != null) {
                        $item = $item_without_s;
                    } else if ($item_with_s != null) {
                        $item = $item_with_s;
                    }

                    if ($item) {
                        if ($item['serial_control'] == 'yes') {
                            $this->Operations->add_operation(['item_description' => $item['item_description'], 'item_code' => $item['item_code'], 'serial' => $item['serial_number'], 'operation' => 'تم نقل المنتج إلى رف ' . $this->Shelves->get_one_shelf_by_id($shelf_id)->shelf_number, 'sales_order' => '0'], $this->session->userdata('user_id'));
                        }
                        $this->Store->move_item_to_shelf($item['item_code'], $item['serial_control'], $item['serial_number'], $item['barcode'], $shelf_id);

                        $poster = isset($posters[$i]) ? trim($posters[$i]) : '';
                        if (!$this->db->field_exists('poster_number', 'current_store_items')) {
                            $this->load->dbforge();
                            $this->dbforge->add_column('current_store_items', array(
                                'poster_number' => array(
                                    'type' => 'VARCHAR',
                                    'constraint' => '255',
                                    'null' => TRUE,
                                    'default' => ''
                                )
                            ));
                        }
                        $this->Store->update_store_item(['poster_number' => $poster], ['id' => $item['id']]);

                    } else {
                        $errors .= 'السيريال غير موجود' . '<br>';
                        continue;
                    }
                }

                if ($errors != '') {
                    $this->session->set_flashdata('shelf_reorder_error', $errors);
                }

            }
        }

        $this->data['shelves'] = $this->Shelves->get_shelves();
        $this->load->view('view_admin_shelves', $this->data);
    }

    public function check_serial()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            $this->output
                ->set_status_header(403)
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'status' => 'error',
                    'exists' => false,
                    'subinventory_code' => null,
                    'description' => 'غير مصرح'
                )));
            return;
        }

        $this->check_permission('shelves.rearrange');

        $serial_number = trim((string) $this->input->post('serial'));

        if ($serial_number === '') {
            $this->output
                ->set_content_type('application/json')
                ->set_output(json_encode(array(
                    'status' => 'error',
                    'exists' => false,
                    'subinventory_code' => null,
                    'description' => 'السيريال فارغ'
                )));
            return;
        }

        // نفس لوجيك صفحة العمليات:
        // 1) جرّب القيمة الأصلية كما هي
        // 2) إذا فشل، جرّب بعد إزالة البادئة S أو s (كبير أو صغير)
        // 3) اختر أول نتيجة تطابق
        $item_without_s = $this->Store->get_store_item(array('serial_number' => $serial_number));
        $item_with_s = $this->Store->get_store_item(array('serial_number' => preg_replace('/^[Ss]/', '', $serial_number, 1)));

        $item = null;
        if ($item_without_s != null) {
            $item = $item_without_s;
        } else if ($item_with_s != null) {
            $item = $item_with_s;
        }

        $this->output
            ->set_content_type('application/json')
            ->set_output(json_encode(array(
                'status' => 'success',
                'exists' => !empty($item),
                'serial' => !empty($item) ? $item['serial_number'] : $serial_number,
                'subinventory_code' => !empty($item) ? $item['subinventory_code'] : null
            )));
    }

    public function delete_shelf()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('shelves.delete');

        $id = $this->input->get('id');

        $this->Shelves->delete_shelf(array('id' => $id));

        $items = $this->Store->get_items_in_shelf($id);

        foreach ($items as $item) {
            if ($item['serial_control'] == 'yes') {
                $this->Operations->add_operation(['item_description' => $item['item_description'], 'item_code' => $item['item_code'], 'serial' => $item['serial_number'], 'operation' => 'تم إزالة المنتج من الرف بسبب حذف الرف', 'sales_order' => '0'], $this->session->userdata('user_id'));
            }

            $this->Store->remove_item_from_shelf($item['item_code'], $item['serial_control'], $item['serial_number'], $item['barcode']);
        }

        redirect(base_url('admin/shelves'));
    }

    public function show()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }


        $this->check_permission('shelves.items.view');

        $id = $this->input->get('id');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $action = $this->input->post('action');

            if ($action == 'delete') {
                $this->check_permission('shelves.items.remove');
                $item_id = $this->input->post('itemid');
                $item = $this->Store->get_store_item(['id' => $item_id]);
                $this->Store->remove_item_from_shelf($item['item_code'], $item['serial_control'], $item['serial_number'], $item['barcode']);
            }
        }

        $this->data['shelf'] = $this->Shelves->get_one_shelf_by_id($id);
        $this->data['items'] = $this->Store->get_items_in_shelf($id);
        $this->load->view('view_admin_shelves_products', $this->data);
    }
}

?>
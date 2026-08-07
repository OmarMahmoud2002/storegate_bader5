<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\IOFactory;

class Products extends MY_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->load->model('Model_admin');
        $this->load->model('Model_store_items', 'Store');
        $this->load->model('Model_notifications', 'Notifications');
        $this->load->model('Model_cart', 'Cart');
        $this->load->model('Model_shelves', 'Shelves');
        $this->load->model('Model_custody', 'Custodys');
        $this->load->model('Model_requests', 'Requests');
        $this->load->model('Model_sales', 'Sales');
        $this->load->model('Model_operations', 'Operations');
        $this->load->model('Model_permissions', 'Permissions');
        $this->load->model('Model_settings', 'Settings');

        notifications($this);
        ini_set('memory_limit', '512M');
        date_default_timezone_set('Asia/Riyadh');
    }


    public function add()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('products.import');

        $uploadStatus = '';
        $uploadMessage = '';

        if (isset($_FILES['excel_file'])) {
            try {
                $filePath = $_FILES['excel_file']['tmp_name'];
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, true);

                // حذف الصفوف من 1 إلى 9 (الرؤوس)
                for ($i = 1; $i <= 10; $i++) {
                    unset($rows[$i]);
                }
                $rows = array_values($rows);

                // مصفوفة المنتجات الجديدة
                $newData = array();

                foreach ($rows as $row) {
                    if (empty(array_filter($row, function ($v) {
                        return trim((string) $v) !== ''; }))) {
                        continue;
                    }

                    $serialControl = strtolower(trim(isset($row['E']) ? $row['E'] : ''));
                    $serial = trim(isset($row['F']) ? $row['F'] : '');
                    $itemCategory = strtolower(trim(isset($row['B']) ? $row['B'] : ''));
                    $subinventoryCode = trim(isset($row['A']) ? $row['A'] : '');

                    // تجاهل بطاقات SIM
                    if (stripos($itemCategory, 'sim') !== false)
                        continue;

                    $key = null;
                    if ($serialControl === 'yes') {
                        // المنتج بسيريال → مفتاح فريد مع السيريال
                        $key = $row['C'] . '|' . $row['D'] . '|' . $serial;
                        $newData[$key] = array(
                            'subinventory_code' => $row['A'],
                            'item_category' => $row['B'],
                            'item_code' => $row['C'],
                            'item_description' => $row['D'],
                            'serial_control' => $serialControl,
                            'serial_number' => $serial,
                            'quantity_total' => (int) (isset($row['G']) ? $row['G'] : 0),
                        );
                    } else {
                        // المنتج بدون سيريال → ندمج الكميات حسب الكود والوصف
                        // نحافظ على كمية مستقلة لكل مخزون حتى يمكن عرض توزيع المتوفر بدقة.
                        $key = $row['C'] . '|' . $row['D'] . '|NOSERIAL|' . strtoupper($subinventoryCode);
                        if (!isset($newData[$key])) {
                            $newData[$key] = array(
                                'subinventory_code' => $subinventoryCode,
                                'item_category' => $row['B'],
                                'item_code' => $row['C'],
                                'item_description' => $row['D'],
                                'serial_control' => $serialControl,
                                'serial_number' => null,
                                'quantity_total' => 0,
                            );
                        }
                        $newData[$key]['quantity_total'] += (int) (isset($row['G']) ? $row['G'] : 0);
                    }
                }

                // جلب البيانات الحالية من قاعدة البيانات

                $currentData = $this->Store->get_store_items();

                // إنشاء قائمة مفاتيح البيانات الحالية
                $currentKeys = array();
                foreach ($currentData as $item) {
                    if (strtolower($item['serial_control']) === 'yes') {
                        $currentKeys[$item['item_code'] . '|' . $item['item_description'] . '|' . $item['serial_number']] = $item;
                    } else {
                        $currentKeys[$item['item_code'] . '|' . $item['item_description'] . '|NOSERIAL|' . strtoupper(trim((string) $item['subinventory_code']))] = $item;
                    }
                }

                // 1️⃣ حذف السيريالات غير الموجودة أو تصفير الكمية للمنتجات بدون سيريال
                foreach ($currentKeys as $key => $item) {
                    if (!isset($newData[$key])) {
                        if (strtolower($item['serial_control']) === 'yes') {
                            // حذف السيريال من الجدول
                            $item_id = $item['id'];
                            $this->Store->delete_store_item(
                                array(
                                    'item_code' => $item['item_code'],
                                    'item_description' => $item['item_description'],
                                    'serial_number' => $item['serial_number']
                                )
                            );

                            $this->Cart->delete_cart_item(['item_id' => $item_id]);
                            $this->Store->remove_item_from_shelf($item['item_code'], $item['serial_control'], $item['serial_number'], $item['barcode']);

                        } else {
                            // تصفير الكمية
                            $item_id = $item['id'];
                            $this->Store->update_store_item(
                                array('quantity_total' => 0),
                                array(
                                    'item_code' => $item['item_code'],
                                    'item_description' => $item['item_description'],
                                    'subinventory_code' => $item['subinventory_code'],
                                    'serial_number' => null
                                )
                            );

                            $this->Cart->delete_cart_item(['item_id' => $item_id]);

                        }
                    }
                }

                // 2️⃣ إدخال أو تحديث البيانات الجديدة
                foreach ($newData as $key => $data) {
                    if (isset($currentKeys[$key])) {

                        $this->Store->update_store_item(
                            array(
                                'subinventory_code' => $data['subinventory_code'],
                                'item_category' => $data['item_category'],
                                'serial_control' => $data['serial_control'],
                                'quantity_total' => $data['quantity_total'],
                            ),
                            $data['serial_control'] === 'yes' ?
                            array(
                                'item_code' => $data['item_code'],
                                'item_description' => $data['item_description'],
                                'serial_number' => $data['serial_number']
                            ) :
                                array(
                                    'item_code' => $data['item_code'],
                                    'item_description' => $data['item_description'],
                                    'subinventory_code' => $data['subinventory_code'],
                                    'serial_number' => null
                            )
                        );
                    } else {
                        $insertData = array(
                            'subinventory_code' => $data['subinventory_code'],
                            'item_category' => $data['item_category'],
                            'item_code' => $data['item_code'],
                            'item_description' => $data['item_description'],
                            'serial_control' => $data['serial_control'],
                            'serial_number' => $data['serial_number'],
                            'quantity_total' => $data['quantity_total'],
                        );
                        $this->Store->add_store_item($insertData);
                    }
                }

                // نسجل وقت نجاح رفع ملف المخزون كحدث مستقل عن تعديلات المنتجات اليدوية.
                $this->Settings->set_field('stock_excel_last_uploaded_at', date('Y-m-d H:i:s'));

                $uploadStatus = 'success';
                $uploadMessage = "تم تحديث المخزون بنجاح!";


            } catch (Exception $e) {
                $uploadStatus = 'error';
                $uploadMessage = "حدث خطأ أثناء معالجة الملف: " . $e->getMessage();

            }
        }

        $this->data['uploadMessage'] = $uploadMessage;
        $this->data['uploadStatus'] = $uploadStatus;

        $this->load->view('products/add', $this->data);
    }

    function edit()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('products.edit.view');

        // الحصول على اسم المنتج من الرابط
        $item_code = $this->input->get('item_code');
        if (!$item_code) {
            die("❌ كود المنتج غير موجود.");
        }

        // إجمالي العرض يعتمد فقط على المخازن الثلاثة المعتمدة.
        $stockBreakdown = $this->Store->get_counted_subinventory_breakdown($item_code);
        $totalQuantity = $stockBreakdown['total'];


        $text_danger = ((int) $totalQuantity === 0) ? 'text-danger' : '';

        // جلب بيانات المنتج الأساسية
        $product = $this->Store->get_store_item(['item_code' => $item_code]);

        if (!$product) {
            die("❌ المنتج غير موجود.");
        }

        $statuses = $this->Store->get_serial_control_statuses(['item_code' => $item_code]);

        // تحديد الحالة
        $hasSerial = in_array('yes', $statuses);
        $hasNoSerial = in_array('no', $statuses);

        // السيريالات المعروضة هي الموجودة داخل المخازن الثلاثة المعتمدة، مع اسم مخزن كل سيريال.
        $serials = $this->Store->get_counted_subinventory_serials($item_code);
        $serialBreakdown = array('23012' => 0, 'SAgent_STK' => 0, 'RESV_23012' => 0);
        foreach ($serials as $serialItem) {
            $serialCode = isset($serialItem['subinventory_code']) ? $serialItem['subinventory_code'] : '';
            if (isset($serialBreakdown[$serialCode])) {
                $serialBreakdown[$serialCode]++;
            }
        }

        $this->data['statuses'] = $statuses;
        $this->data['hasSerial'] = $hasSerial;
        $this->data['hasNoSerial'] = $hasNoSerial;
        $this->data['serials'] = $serials;
        $this->data['serial_breakdown'] = $serialBreakdown;
        $this->data['stock_breakdown'] = $stockBreakdown['items'];
        $this->data['product'] = $product;
        $this->data['text_danger'] = $text_danger;
        $this->data['totalQuantity'] = $totalQuantity;
        $this->data['images'] = $this->Store->get_store_item_images($item_code);
        $this->data['page_title'] = !empty($product['item_description']) ? $product['item_description'] : $item_code;

        $this->load->view('products/edit', $this->data);
    }

    function update_basic_product_data()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('products.update.basic');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $item_id = $this->input->post('item_id');

            if ($item_id) {
                $item_description = $this->input->post('item_description');
                $item_code = $this->input->post('item_code');
                $item_category = $this->input->post('item_category');
                $barcode = $this->input->post('barcode');

                $this->Store->update_store_item(
                    [
                        'item_description' => $item_description,
                        'item_category' => $item_category,
                        'barcode' => $barcode,
                        'updated_at' => date_create()->format('Y-m-d H:i:s')
                    ],
                    [
                        'id' => $item_id
                    ]
                );

                header("Location:../products/edit?item_code=" . $item_code);
            }
        }
    }

    function update_extra_product_data()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('products.update.extra');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            /* Get the uploaded images */
            $images = $_FILES['product_images'];

            $uploadedImages = [];

            // Loop through each uploaded image
            foreach ($images['name'] as $key => $image) {
                if ($image) {
                    // Generate a unique image name
                    $imageName = uniqid() . '_' . preg_replace('/\s+/', '_', $image);

                    $_FILES['userfile']['name'] = $imageName;
                    $_FILES['userfile']['type'] = $images['type'][$key];
                    $_FILES['userfile']['tmp_name'] = $images['tmp_name'][$key];
                    $_FILES['userfile']['error'] = $images['error'][$key];
                    $_FILES['userfile']['size'] = $images['size'][$key];

                    // Move the uploaded image to the desired directory
                    $config['upload_path'] = './public/img/products';
                    $config['allowed_types'] = 'gif|jpg|png';
                    $config['max_size'] = 1024 * 10; // 2MB

                    $this->load->library('upload', $config);

                    if (!$this->upload->do_upload('userfile')) {
                        // Handle the upload error
                        $error = $this->upload->display_errors();
                        // Handle the error or display an error message
                    } else {
                        // Get the uploaded image details
                        $uploadedImage = $this->upload->data();

                        // Store the uploaded image path in the array
                        $uploadedImages[] = $imageName;

                        // register the image in the database
                        $this->Store->add_item_image(
                            $this->input->post('item_code'),
                            $imageName
                        );
                    }
                }
            }

            $price = $this->input->post('price');
            $low_quantity = $this->input->post('low_quantity');
            $serial_control = $this->input->post('serial_control');
            $item_id = $this->input->post('item_id');
            $item_code = $this->input->post('item_code');

            $removed_images = $this->input->post('removed_images');

            if ($removed_images) {
                $removed_images = explode(',', $removed_images);
                foreach ($removed_images as $image_id) {
                    # remove the file from storage
                    $image = $this->Store->get_store_item_image($image_id);
                    unlink('./public/img/products/' . $image['filename']);
                    $this->Store->delete_item_image($image_id);
                }
            }


            $this->Store->update_store_item(
                [
                    'price' => $price != '' ? $price : null,
                    'low_quantity' => $low_quantity != '' ? $low_quantity : null,
                    'serial_control' => $serial_control,
                    'updated_at' => date_create()->format('Y-m-d H:i:s')
                ],
                [
                    'item_code' => $item_code
                ]
            );

            header("Location:../products/edit?item_code=" . $item_code);
        }

    }

    function low()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('products.low.view');

        $this->data['low_products'] = $this->Store->get_low_products();

        $this->load->view('products/low', $this->data);
    }

    function search()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('product_search.view');

        $this->data['cart'] = $this->Cart->get_cart_items(['user_id' => $this->session->userdata('user_id')]);
        $this->data['employees'] = $this->Model_admin->getemployees();
        $stock_upload_settings = $this->Settings->get_fields(['field' => 'stock_excel_last_uploaded_at']);
        $this->data['stock_excel_last_uploaded_at'] = isset($stock_upload_settings['stock_excel_last_uploaded_at'])
            ? $stock_upload_settings['stock_excel_last_uploaded_at']
            : null;
        $this->load->view('products/search', $this->data);
    }


    function get_item_categories_by_serial_part()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $serial_part = $this->input->get('serial_part');
        $items = $this->Store->get_items_by_serialpart($serial_part);


        $serials_by_category = array();

        foreach ($items as $item) {
            $serials_by_category[] = [$item['serial_number'], $item['item_category']];
        }

        echo json_encode($serials_by_category);
    }

    /**
     * يحدد نوع القيمة المدخلة في خانة البحث الموحدة
     * (رقم سريال / جزء من سريال / رقم صنف / باركود)
     * حتى يستخدم الواجهة نفس منطق البحث الحالي بدون تغيير.
     */
    function resolve_search_term()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $term = trim((string) $this->input->get('term'));

        if ($term === '') {
            echo json_encode(['status' => 'error', 'description' => 'يرجى إدخال رقم السريال أو رقم الصنف أو الباركود']);
            return;
        }

        $upper_term = strtoupper($term);
        $term_length = strlen($term);

        // بحث بجزء من السريال (نفس منطق الخانة القديمة: 6 خانات)
        if ($term_length == 6) {
            $serial_matches = $this->Store->get_items_by_serialpart($upper_term);
            if (count($serial_matches) > 0) {
                echo json_encode(['status' => 'ok', 'type' => 'serial_part', 'value' => $upper_term]);
                return;
            }
        }

        // سريال كامل (مع أو بدون حرف S في البداية)
        if ($term_length >= 7) {
            $item = $this->Store->get_store_item(['serial_number' => $upper_term]);

            if ($item == null) {
                $item = $this->Store->get_store_item(['serial_number' => preg_replace('/^S/', '', $upper_term, 1)]);
            }

            if ($item != null) {
                echo json_encode(['status' => 'ok', 'type' => 'serial', 'value' => $term]);
                return;
            }
        }

        // رقم الصنف
        $item = $this->Store->get_store_item(['item_code' => $term]);
        if ($item != null) {
            echo json_encode(['status' => 'ok', 'type' => 'item_code', 'value' => $item['item_code']]);
            return;
        }

        // الباركود
        $item = $this->Store->get_store_item(['barcode' => $term]);
        if ($item != null) {
            echo json_encode(['status' => 'ok', 'type' => 'item_code', 'value' => $item['item_code'], 'matched_by' => 'barcode']);
            return;
        }

        // جزء من السريال لأي طول أكبر من 6 خانات
        if ($term_length > 6) {
            $serial_matches = $this->Store->get_items_by_serialpart($upper_term);
            if (count($serial_matches) > 0) {
                echo json_encode(['status' => 'ok', 'type' => 'serial_part', 'value' => $upper_term]);
                return;
            }
        }

        echo json_encode(['status' => 'error', 'description' => 'لا يوجد نتائج مطابقة لرقم السريال أو الصنف أو الباركود']);
    }

    function get_product_by_serial()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $serialnumber_input = strtoupper($this->input->get('serial'));

        $item_without_s = $this->Store->get_store_item(['serial_number' => $serialnumber_input]);
        $item_with_s = $this->Store->get_store_item(['serial_number' => preg_replace('/^S/', '', $serialnumber_input, 1)]);

        if ($item_without_s == null && $item_with_s == null) {
            echo json_encode(['status' => 'error', 'description' => 'السيريال غير موجود']);
            return;
        }

        if ($item_without_s != null) {
            $item = $item_without_s;
        } else if ($item_with_s != null) {
            $item = $item_with_s;
        }

        $serial_number = $item['serial_number'];
        $stock_breakdown = $this->Store->get_counted_subinventory_breakdown($item['item_code']);

        $shelf_id = $this->Store->get_item_shelf($item['item_code'], $item['serial_number'], $item['barcode']);
        $shelf_name = 'Not Available';

        if ($shelf_id != null) {
            $shelf = $this->Shelves->get_one_shelf_by_id($shelf_id);

            if ($shelf != null) {
                $shelf_name = $shelf->shelf_number;
            }
        }

        $item_imgs = $this->Store->get_store_item_images($item['item_code']);

        $images = array();
        if (count($item_imgs) > 0) {
            foreach ($item_imgs as $img) {
                $images[] = $img['filename'];
            }
        }


        $data = [
            'status' => 'ok',
            'id' => $item['id'],
            'name' => $item['item_description'],
            'code' => $item['item_code'],
            'subinventory_code' => $item['subinventory_code'],
            'serial_control' => $item['serial_control'],
            'total_quantity' => $stock_breakdown['total'],
            'stock_breakdown' => $stock_breakdown['items'],
            'shelf' => $shelf_name,
            'serial_number' => $serial_number,
            'poster_number' => empty($item['poster_number']) ? 'غير محدد' : $item['poster_number'],
            'images' => $images
        ];

        $custody = $this->Custodys->get_custody(['item_code' => $item['item_code'], 'serial_number' => $item['serial_number']]);
        if ($custody != null) {
            $permission_to_return_custody = $this->get_permission_enabled('product_search.custody.return');

            $data['status'] = 'custody_product';
            $data['description'] = 'السيريال مسجل بالفعل في عهدة موظف';
            if ($permission_to_return_custody) {
                $data['description'] .= " <span class = 'remCustody' onclick = 'remove_custody(" . $custody['id'] . ")'>إرجاع</span>";
            }
            echo json_encode($data);
            return;
        }

        $requests = $this->Requests->get_requests(['item_code' => $item['item_code'], 'serial_number' => $item['serial_number']]);
        if (count($requests) > 0) {
            $permission_to_return_delivery = $this->get_permission_enabled('product_search.delivery.return');

            $request = $requests[0];
            $data['status'] = 'delivered_product';
            $data['description'] = 'السيريال مسجل لدى احد مناديب التوصيل';

            if ($permission_to_return_delivery) {
                $data['description'] .= " <span class = 'remCustody' onclick = 'remove_request(" . $request['id'] . ")'>إرجاع</span>";
            }
            echo json_encode($data);
            return;
        }


        if ($this->Cart->search_item(['item_id' => $item['id']]) == true) {
            $data['status'] = 'in_cart';
            $data['description'] = 'السيريال مسجل بالفعل في السلة';
            echo json_encode($data);
            return;
        }

        echo json_encode($data);
    }

    function get_product_by_item_code()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $item_code = $this->input->get('item_code');

        $item = $this->Store->get_store_item(['item_code' => $item_code]);

        if ($item == null) {
            echo json_encode(['status' => 'error', 'description' => 'المنتج غير موجود']);
            return;
        }

        $stock_breakdown = $this->Store->get_counted_subinventory_breakdown($item['item_code']);
        $remaining_quantity = $stock_breakdown['total'];

        if ($item['serial_control'] == 'yes') {
            echo json_encode(['status' => 'error', 'description' => 'المنتج يحتوي على سيريالات']);
            return;
        }

        if ($remaining_quantity <= 0) {
            echo json_encode(['status' => 'error', 'description' => 'المنتج غير متوفر في المخزون']);
            return;
        }

        $totalQuantity = $this->Store->get_store_unique_items_count(['i.item_code' => $item['item_code']]);

        $shelf_id = $this->Store->get_item_shelf($item['item_code'], $item['serial_number'], $item['barcode']);
        $shelf_name = 'غير موجود في رف';

        if ($shelf_id != null) {
            $shelf = $this->Shelves->get_one_shelf_by_id($shelf_id);

            if ($shelf != null) {
                $shelf_name = $shelf->shelf_number;
            }
        }

        $item_imgs = $this->Store->get_store_item_images($item['item_code']);

        $images = array();
        if (count($item_imgs) > 0) {
            foreach ($item_imgs as $img) {
                $images[] = $img['filename'];
            }
        }

        $data = [
            'status' => 'ok',
            'id' => $item['id'],
            'name' => $item['item_description'],
            'code' => $item['item_code'],
            'subinventory_code' => $item['subinventory_code'],
            'serial_control' => $item['serial_control'],
            'total_quantity' => $stock_breakdown['total'],
            'stock_breakdown' => $stock_breakdown['items'],
            'shelf' => $shelf_name,
            'barcode' => $item['barcode'] != null ? $item['barcode'] : '',
            'poster_number' => empty($item['poster_number']) ? 'غير محدد' : $item['poster_number'],
            'images' => $images
        ];

        echo json_encode($data);
    }

    function add_item_to_cart()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->get_permission_enabled('product_search.cart.add') == false) {
                echo json_encode(['status' => 'no_permission', 'msg' => 'Permission Denied']);
                return;
            }

            $item_id = $this->input->post('item_id');
            $serial_control = $this->input->post('serial_control');

            if ($this->Cart->search_item(['item_id' => $item_id]) == true && $serial_control == 'yes') {
                echo json_encode(['status' => 'error', 'msg' => 'Item already added to cart']);


            } else if ($serial_control == 'no') {
                $item = $this->Store->get_store_item(['id' => $item_id]);
                $remaining_quantity = $this->Store->get_remaining_quantity_by_item_code($item['item_code']);
                if ($remaining_quantity <= 0) {
                    echo json_encode(['status' => 'error', 'msg' => 'Item is out of stock']);
                    return;
                } else {
                    $this->Cart->add_cart_item(['item_id' => $item_id, 'serial_control' => $serial_control, 'user_id' => $this->session->userdata('user_id')]);
                    echo json_encode(['status' => 'ok', 'msg' => 'Item added to cart']);
                }
            } else {
                $this->Cart->add_cart_item(['item_id' => $item_id, 'serial_control' => $serial_control, 'user_id' => $this->session->userdata('user_id')]);
                echo json_encode(['status' => 'ok', 'msg' => 'Item added to cart']);
            }


        }
    }

    function remove_cart_item()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->get_permission_enabled('product_search.cart.remove') == false) {
                echo 'no_permission';
                return;
            }

            $this->Cart->delete_cart_item(['id' => $this->input->post('id'), 'user_id' => $this->session->userdata('user_id')]);
            echo $this->input->post('id');
        }
    }

    function delete_selected_cart_items()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $selected_cart_items = $this->input->post('selected');
            foreach ($selected_cart_items as $selected_cart_item) {
                $this->Cart->delete_cart_item(['id' => $selected_cart_item, 'user_id' => $this->session->userdata('user_id')]);
            }
        }
    }

    function add_item_as_custody()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->get_permission_enabled('product_search.custody.create') == false) {
                echo json_encode(['status' => 'wrong', 'msg' => 'ليس لديك صلاحية']);
                return;
            }

            $item_id = $this->input->post('item_id');
            $emp_id = $this->input->post('emp_id');
            $order_number = $this->input->post('order_number');

            $item = $this->Store->get_store_item(['id' => $item_id]);
            $remaining_count = $this->Store->get_remaining_quantity_by_item_code($item['item_code']);


            if ($item['serial_control'] == 'yes') {
                if (count($this->Custodys->get_custodys(['item_code' => $item['item_code'], 'serial_number' => $item['serial_number']])) > 0) {
                    echo json_encode(['msg' => 'المنتج بالفعل في عهدة موظف', 'status' => 'wrong']);
                    return;
                }

                if ($this->Requests->get_requests_length(['item_code' => $item['item_code'], 'serial_number' => $item['serial_number']]) > 0) {
                    echo json_encode(['msg' => 'المنتج بالفعل تم تسليمه للتوصيل', 'status' => 'wrong']);
                    return;
                }
            } else {
                if ($remaining_count <= 0) {
                    echo json_encode(['msg' => 'المنتج غير متوفر في المخزون', 'status' => 'wrong']);
                    return;
                }
            }

            $custody_data = [
                'user_id' => $emp_id,
                'item_code' => $item['item_code'],
                'serial_number' => $item['serial_number'],
                'barcode' => $item['barcode'],
                'type' => 1,
                'note' => '',
                'date_created' => date('Y-m-d H:i:s'),
                'order_number' => $order_number
            ];
            $this->Custodys->add_custody($custody_data);

            $sales_data = [
                'item_code' => $item['item_code'],
                'serial_number' => $item['serial_number'],
                'barcode' => $item['barcode'],
                'user_id' => $emp_id,
                'custody_id' => $this->db->insert_id(),
                'date_created' => date('Y-m-d H:i:s')
            ];
            $this->Sales->add_sale($sales_data);

            if ($item['serial_control'] == 'yes') {
                $this->Operations->add_operation(['item_description' => $item['item_description'], 'item_code' => $item['item_code'], 'serial' => $item['serial_number'], 'operation' => 'تم تسجيل المنتج في عهدة ' . $this->Model_admin->get_user_by_id($emp_id)['user_fillname'], 'sales_order' => '0'], $this->session->userdata('user_id'));
            }

            echo json_encode(['msg' => 'المنتج تم اضافته في عهدة الموظف', 'status' => 'ok']);
        }
    }

    function remove_custody()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('product_search.custody.return');

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $custody_id = $this->input->post('custody_id');
            $custody = $this->Custodys->get_custody(['id' => $custody_id]);
            $item = $this->Store->get_store_item(['item_code' => $custody['item_code'], 'serial_number' => $custody['serial_number'], 'barcode' => $custody['barcode']]);

            $this->Custodys->delete_custody(['id' => $custody_id]);
            $this->Sales->delete_sale(['custody_id' => $custody_id]);

            if ($item['serial_control'] == 'yes') {
                $this->Operations->add_operation(['item_description' => $item['item_description'], 'item_code' => $item['item_code'], 'serial' => $item['serial_number'], 'operation' => 'تم ارجاع المنتج من عهدة ' . $this->Model_admin->get_user_by_id($custody['user_id'])['user_fillname'] . ' إلي المخزون', 'sales_order' => '0'], $this->session->userdata('user_id'));
            }

            echo json_encode(['msg' => 'المنتج تم حذفه من عهدة الموظف', 'status' => 'ok']);
        }
    }

    function add_product_to_delivery()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if ($this->get_permission_enabled('product_search.delivery.create') == false) {
                echo json_encode(['status' => 'wrong', 'msg' => 'ليس لديك صلاحية']);
                return;
            }

            $item_id = $this->input->post('item_id');
            $order_number = $this->input->post('order_number');

            $item = $this->Store->get_store_item(['id' => $item_id]);

            if ($item['serial_control'] == 'yes') {
                if (count($this->Custodys->get_custodys(['item_code' => $item['item_code'], 'serial_number' => $item['serial_number']])) > 0) {
                    echo json_encode(['msg' => 'المنتج بالفعل في عهدة موظف', 'status' => 'wrong']);
                    return;
                }


                if ($this->Requests->get_requests_length(['item_code' => $item['item_code'], 'serial_number' => $item['serial_number']]) > 0) {
                    echo json_encode(['msg' => 'المنتج بالفعل تم تسليمه للتوصيل', 'status' => 'wrong']);
                    return;
                }

            }

            $data = [
                'item_code' => $item['item_code'],
                'serial_number' => $item['serial_number'],
                'barcode' => $item['barcode'],
                'order_number' => $order_number,
                'date_created' => date('Y-m-d H:i:s')
            ];

            $this->Requests->add_request($data);


            if ($item['serial_control'] == 'yes') {
                $this->Operations->add_operation(['item_description' => $item['item_description'], 'item_code' => $item['item_code'], 'serial' => $item['serial_number'], 'operation' => 'تم تسليم المنتج للتوصيل', 'sales_order' => '0'], $this->session->userdata('user_id'));
            }

            echo json_encode(['msg' => 'تم تسليم المنتج للتوصيل', 'status' => 'ok']);
        }
    }

    function product_delivery_return()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $requests_return_permission = $this->get_permission_enabled('requests.return');
        $product_search_delivery_return_permission = $this->get_permission_enabled('product_search.delivery.return');

        if ($requests_return_permission == false && $product_search_delivery_return_permission == false) {
            redirect(base_url() . MOD_VALUE . 'admin/products/search');
        }


        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $request_id = $this->input->post('id');
            $request = $this->Requests->get_one_request_by_id($request_id);

            $this->Requests->delete_request(['id' => $request_id]);

            if ($request != null && $request['serial_number'] != null) {
                $item = $this->Store->get_store_item(['serial_number' => $request['serial_number']]);

                $this->Operations->add_operation([
                    'item_description' => isset($item['item_description']) ? $item['item_description'] : '',
                    'item_code' => isset($item['item_code']) ? $item['item_code'] : $request['item_code'],
                    'serial' => $request['serial_number'],
                    'operation' => 'تم إرجاع المنتج من التوصيل إلى المخزون',
                    'sales_order' => '0'
                ], $this->session->userdata('user_id'));
            }

            echo json_encode(['msg' => 'تم إرجاع المنتج من التوصيل', 'status' => 'ok']);
        }
    }

    /**
     * يجلب اسم وكود المنتج من كل المصادر المتاحة، وليس من المخزون الحالي فقط.
     * الأولوية للمخزون، ثم بيانات العملية المحفوظة، ثم الجداول التشغيلية وملف التقفيلة.
     */
    private function get_item_info_by_number($number, $operations = array(), $sales = array())
    {
        $number = strtoupper(trim((string) $number));

        if ($number === '') {
            return ['name' => '', 'code' => ''];
        }

        $serial_without_s = preg_replace('/^S/', '', $number, 1);
        $serial_variants = array_values(array_unique(array(
            $number,
            $serial_without_s,
            'S' . $serial_without_s
        )));
        $item = null;

        foreach ($serial_variants as $serial_variant) {
            $item = $this->Store->get_store_item(['serial_number' => $serial_variant]);
            if ($item != null) {
                break;
            }
        }

        if ($item == null) {
            $item = $this->Store->get_store_item(['barcode' => $number]);
        }

        if ($item != null) {
            return [
                'name' => isset($item['item_description']) ? trim((string) $item['item_description']) : '',
                'code' => isset($item['item_code']) ? trim((string) $item['item_code']) : '',
            ];
        }

        $history_item = $this->Store->get_serial_history_by_number($number);
        $info = [
            'name' => !empty($history_item['item_description'])
                ? trim((string) $history_item['item_description'])
                : '',
            'code' => !empty($history_item['item_code'])
                ? trim((string) $history_item['item_code'])
                : ''
        ];

        // New operation records retain the product identity even after stock refreshes.
        foreach ($operations as $operation) {
            if ($info['code'] === '' && !empty($operation['item_code'])) {
                $info['code'] = trim((string) $operation['item_code']);
            }
            if ($info['name'] === '' && !empty($operation['item_description'])) {
                $info['name'] = trim((string) $operation['item_description']);
            }
            if ($info['code'] !== '' && $info['name'] !== '') {
                break;
            }
        }

        // Old records may still have their item code in custody, delivery, sales, or shelf tables.
        if ($info['code'] === '') {
            $info['code'] = $this->get_related_item_code_by_number($serial_variants);
        }

        // The closing Excel files contain the product description, even when stock no longer does.
        if ($info['name'] === '' && count($sales) > 0 && !empty($sales[0]['insert_excel_description'])) {
            $info['name'] = trim((string) $sales[0]['insert_excel_description']);
        }

        // Complete whichever side is missing using another current row for the same product.
        $matching_item = null;
        if ($info['code'] !== '') {
            $matching_item = $this->Store->get_store_item(['item_code' => $info['code']]);
        } else if ($info['name'] !== '') {
            $this->db->where(
                'UPPER(TRIM(item_description)) = ' . $this->db->escape(strtoupper($info['name'])),
                null,
                false
            );
            $matching_item = $this->db->get('current_store_items')->row_array();
        }

        if ($matching_item != null) {
            if ($info['code'] === '' && !empty($matching_item['item_code'])) {
                $info['code'] = trim((string) $matching_item['item_code']);
            }
            if ($info['name'] === '' && !empty($matching_item['item_description'])) {
                $info['name'] = trim((string) $matching_item['item_description']);
            }
        }

        return $info;
    }

    /**
     * Finds an item code left in operational tables for historical serials.
     */
    private function get_related_item_code_by_number($serial_variants)
    {
        $escaped_variants = array();
        foreach ($serial_variants as $serial_variant) {
            $escaped_variants[] = $this->db->escape(strtoupper(trim((string) $serial_variant)));
        }

        if (count($escaped_variants) === 0) {
            return '';
        }

        $sources = array('tbl_sales', 'tbl_user_custody', 'tbl_requests', 'tbl_shelves_items');
        $variants_sql = implode(', ', $escaped_variants);

        foreach ($sources as $table) {
            if (!$this->db->table_exists($table)
                || !$this->db->field_exists('item_code', $table)
                || !$this->db->field_exists('serial_number', $table)) {
                continue;
            }

            $this->db->select('item_code');
            $this->db->from($table);
            $this->db->where(
                '(UPPER(TRIM(serial_number)) IN (' . $variants_sql . ')'
                . ($this->db->field_exists('barcode', $table)
                    ? ' OR UPPER(TRIM(barcode)) IN (' . $variants_sql . ')'
                    : '')
                . ')',
                null,
                false
            );
            $this->db->where("TRIM(item_code) != ''", null, false);
            if ($this->db->field_exists('id', $table)) {
                $this->db->order_by('id', 'DESC');
            }
            $row = $this->db->limit(1)->get()->row_array();

            if (!empty($row['item_code'])) {
                return trim((string) $row['item_code']);
            }
        }

        return '';
    }

    function operations()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('operations.view');

        $this->data['info'] = ['name' => '', 'code' => ''];
        $this->data['operations'] = [];
        $this->data['sales'] = [];
        $this->data['timeline'] = [];
        $this->data['last_sale'] = null;

        $serial = trim((string) $this->input->get('serial'));
        $this->data['serial'] = $serial;

        if ($serial === '') {
            $this->load->view('view_admin_operations', $this->data);
            return;
        }

        // نجلب كل تاريخ السيريال سواء كان مخزنًا بحرف S أو بدونه.
        $operations = $this->Operations->get_operations_by_serial($serial);

        // سجلات البيع المرفوعة من ملف التقفيلة
        $this->load->model('Excel_import_model');
        $sales = $this->Excel_import_model->get_sales_by_serial($serial);

        if ($operations != null) {
            $this->data['serial'] = $operations[0]['serial'];
        } else if (count($sales) > 0) {
            $this->data['serial'] = $sales[0]['insert_excel_product_serial_number'];
        }

        $info = $this->get_item_info_by_number($this->data['serial'], $operations, $sales);

        $this->data['info'] = $info;
        $this->data['operations'] = $operations;
        $this->data['sales'] = $sales;
        $this->data['last_sale'] = count($sales) > 0 ? $this->format_sale_entry($sales[0]) : null;
        $this->data['timeline'] = $this->build_operations_timeline($operations, $sales);

        $this->load->view('view_admin_operations', $this->data);
    }

    /**
     * يحوّل سجل بيع من ملف التقفيلة إلى صيغة موحدة للعرض.
     */
    private function format_sale_entry($sale)
    {
        $order_number = trim((string) $sale['insert_excel_ordern']);

        if ($order_number === '') {
            $order_number = trim((string) $sale['insert_excel_new_ordern']);
        }

        $employee = $this->Model_admin->get_user_by_id($sale['insert_excel_uid']);

        return [
            'date' => $sale['insert_excel_date'],
            'order_number' => $order_number,
            'employee_name' => isset($employee['user_fillname']) ? $employee['user_fillname'] : 'غير محدد',
            'employee_id' => $sale['insert_excel_uid'],
            'description' => $sale['insert_excel_description'],
            'sales_type' => $sale['insert_excel_sales_type'],
            'sales_amount' => $sale['insert_excel_sales_amount'],
        ];
    }

    /**
     * يدمج عمليات المخزون مع سجلات البيع في سجل واحد مرتب من الأحدث للأقدم.
     */
    private function build_operations_timeline($operations, $sales)
    {
        $timeline = [];

        foreach ($operations as $operation) {
            $operation_text = (string) $operation['operation'];
            $movement = '';

            if (strpos($operation_text, 'ارجاع') !== false || strpos($operation_text, 'إرجاع') !== false) {
                $movement = '1';
            } elseif (strpos($operation_text, 'تسجيل') !== false || strpos($operation_text, 'تسليم') !== false) {
                $movement = '-1';
            }

            $made_by = $this->Model_admin->get_user_by_id($operation['made_by']);

            $timeline[] = [
                'source' => 'operation',
                'date' => $operation['date_created'],
                'sort_id' => isset($operation['id']) ? (int) $operation['id'] : 0,
                'sales_order' => $operation['sales_order'],
                'operation' => $operation_text,
                'movement' => $movement,
                'user_name' => isset($made_by['user_fillname']) ? $made_by['user_fillname'] : '',
                'sales_type' => '',
                'sales_amount' => 0,
            ];
        }

        foreach ($sales as $sale) {
            $entry = $this->format_sale_entry($sale);

            $timeline[] = [
                'source' => 'sale',
                'date' => $entry['date'],
                'sort_id' => isset($sale['insert_excel_id']) ? (int) $sale['insert_excel_id'] : 0,
                'sales_order' => $entry['order_number'],
                'operation' => 'تم بيع المنتج ',
                'movement' => '-1',
                'user_name' => $entry['employee_name'],
                'sales_type' => $entry['sales_type'],
                'sales_amount' => $entry['sales_amount'],
            ];
        }

        usort($timeline, function ($first, $second) {
            $first_time = strtotime((string) $first['date']);
            $second_time = strtotime((string) $second['date']);
            $first_time = $first_time === false ? 0 : $first_time;
            $second_time = $second_time === false ? 0 : $second_time;

            if ($first_time === $second_time) {
                if ($first['sort_id'] === $second['sort_id']) {
                    return 0;
                }

                return ($first['sort_id'] < $second['sort_id']) ? 1 : -1;
            }

            return ($first_time < $second_time) ? 1 : -1;
        });

        return $timeline;
    }
}
?>

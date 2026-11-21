<?php
defined('BASEPATH') OR exit('No direct script access allowed');

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

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
        $this->load->model('Model_return_requests', 'ReturnRequests');
        $this->load->model('Model_requests', 'Requests');
        $this->load->model('Model_sales', 'Sales');
        $this->load->model('Model_operations', 'Operations');
        $this->load->model('Model_serial_notes', 'SerialNotes');
        $this->load->model('Model_permissions', 'Permissions');
        $this->load->model('Model_settings', 'Settings');

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
            array(
                'key' => 'product_search.notes.delete',
                'label' => 'حذف ملاحظات السيريال',
                'type' => 'delete',
                'page_label' => 'البحث عن منتج',
                'route' => 'admin/products/search',
                'section_name' => 'الصفحات الرئيسية والمخزون',
                'section_icon' => 'bi-box-seam',
            ),
        ), 'product_search.view');

        notifications($this);
        ini_set('memory_limit', '512M');
        date_default_timezone_set('Asia/Riyadh');
    }

    /**
     * Reads the report timestamp from A2. The source report sometimes appends
     * AM/PM to a 24-hour value (for example 17:56:29 PM), so hours above 12
     * are treated as 24-hour values and the suffix is ignored.
     */
    private function getStockReportDate($sheet)
    {
        $rawValue = $sheet->getCell('A2')->getValue();
        $rawValue = html_entity_decode((string) $rawValue, ENT_QUOTES, 'UTF-8');
        $rawValue = str_replace("\xC2\xA0", ' ', $rawValue);
        $rawValue = preg_replace('/\s+/', ' ', trim($rawValue));

        if (!preg_match(
            '/^Report\s*Date\s*:\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{2}):(\d{2})(?:\s*(AM|PM))?\s*$/i',
            $rawValue,
            $matches
        )) {
            throw new UnexpectedValueException(
                'تعذر قراءة تاريخ التقرير من الخلية A2. يجب أن يكون بالشكل: Report Date: 4/4/2026 17:56:29 PM',
                422
            );
        }

        // The Oracle report date is month/day/year.
        $month = (int) $matches[1];
        $day = (int) $matches[2];
        $year = (int) $matches[3];
        $hour = (int) $matches[4];
        $minute = (int) $matches[5];
        $second = (int) $matches[6];
        $meridiem = isset($matches[7]) ? strtoupper($matches[7]) : '';

        if (!checkdate($month, $day, $year) || $hour > 23 || $minute > 59 || $second > 59) {
            throw new UnexpectedValueException('تاريخ التقرير الموجود في الخلية A2 غير صحيح.', 422);
        }

        if ($meridiem !== '' && $hour <= 12) {
            if ($meridiem === 'AM' && $hour === 12) {
                $hour = 0;
            } elseif ($meridiem === 'PM' && $hour < 12) {
                $hour += 12;
            }
        }

        $reportDate = new DateTime('now', new DateTimeZone('Asia/Riyadh'));
        $reportDate->setDate($year, $month, $day);
        $reportDate->setTime($hour, $minute, $second);

        return $reportDate;
    }

    private function formatStockReportAge($ageSeconds)
    {
        $days = (int) floor($ageSeconds / 86400);
        $hours = (int) floor(($ageSeconds % 86400) / 3600);
        $minutes = (int) floor(($ageSeconds % 3600) / 60);

        if ($days === 0 && $hours === 0) {
            return $minutes === 0 ? 'أقل من دقيقة' : $minutes . ' دقيقة';
        }

        $parts = array();
        if ($days > 0) {
            $parts[] = $days . ' يوم';
        }
        if ($hours > 0) {
            $parts[] = $hours . ' ساعة';
        }
        if ($minutes > 0) {
            $parts[] = $minutes . ' دقيقة';
        }

        return implode(' و', $parts);
    }


    public function add()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('products.import');

        $uploadStatus = '';
        $uploadMessage = '';
        $uploadReason = '';
        $requiresConfirmation = false;

        if (isset($_FILES['excel_file'])) {
            // تُملأ قبل أول كتابة، ويُقارَن بها بعد انتهاء الاستيراد لمعرفة
            // المنتجات التي تغيّرت كميتها فعليًا. تبقى null لو فشل الملف قبل
            // أي تعديل، فلا نسجّل تغييرًا لم يحدث.
            $quantitiesBeforeImport = null;

            try {
                if ((int) $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new UnexpectedValueException('لم يكتمل رفع ملف Excel. يرجى اختيار الملف والمحاولة مرة أخرى.', 422);
                }

                $filePath = $_FILES['excel_file']['tmp_name'];
                $sourceFilename = isset($_FILES['excel_file']['name'])
                    ? basename((string) $_FILES['excel_file']['name'])
                    : '';
                if (!in_array(strtolower(pathinfo($sourceFilename, PATHINFO_EXTENSION)), array('xls', 'xlsx'), true)) {
                    throw new UnexpectedValueException('تم رفض الملف: يجب اختيار ملف Excel بصيغة .xlsx أو .xls. لم يتم تعديل المخزون أو الرفوف.', 422);
                }
                if ((int) $_FILES['excel_file']['size'] > 50 * 1024 * 1024) {
                    throw new UnexpectedValueException('تم رفض الملف: الحجم يتجاوز الحد الأقصى 50 ميجابايت. لم يتم تعديل المخزون أو الرفوف.', 422);
                }
                $uploadTime = new DateTime('now', new DateTimeZone('Asia/Riyadh'));
                $uploadTimestamp = $uploadTime->format('Y-m-d H:i:s');
                $uploadedBy = $this->session->userdata('user_id');
                try {
                    $readerType = IOFactory::identify($filePath);
                    if (!in_array($readerType, array('Xls', 'Xlsx'), true)) {
                        throw new UnexpectedValueException('Unsupported stock workbook format');
                    }
                    $spreadsheet = IOFactory::load($filePath);
                } catch (Exception $e) {
                    throw new UnexpectedValueException('تم رفض الملف: تعذر قراءته كملف Excel صحيح. يرجى رفع تقرير المخزون بصيغة .xlsx أو .xls. لم يتم تعديل المخزون أو الرفوف.', 422);
                }
                $sheet = $spreadsheet->getActiveSheet();
                $reportDate = $this->getStockReportDate($sheet);
                $this->load->helper('stock_import');
                $rows = stock_import_rows_with_merged_headers($sheet);
                validate_stock_import_rows($rows);
                $ageSeconds = $uploadTime->getTimestamp() - $reportDate->getTimestamp();

                // Allow a small clock difference, but reject a report genuinely dated in the future.
                if ($ageSeconds < -300) {
                    throw new UnexpectedValueException('تاريخ التقرير الموجود في الخلية A2 لاحق للوقت الحالي. يرجى التحقق من تاريخ الملف.', 422);
                }

                $ageSeconds = max(0, $ageSeconds);
                $reportAgeText = $this->formatStockReportAge($ageSeconds);

                if ($ageSeconds >= 6 * 3600) {
                    throw new UnexpectedValueException(
                        'تم رفض رفع الملف لأن تاريخه قديم (منذ ' . $reportAgeText . '). برجاء تحديث الملف ثم إعادة رفعه.',
                        410
                    );
                }

                if ($ageSeconds > 3600 && $this->input->post('confirm_old_file') !== '1') {
                    throw new RuntimeException(
                        'تاريخ هذا الملف منذ ' . $reportAgeText . '. هل أنت متأكد من متابعة الرفع؟',
                        409
                    );
                }

                // البيانات تبدأ من الصف 11 بعد التحقق من الملف بالكامل.
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

                // نحتفظ بسجل واحد فارغ للسيريال عندما يختفي المنتج بالكامل من
                // الملف الجديد، حتى يبقى المنتج وبياناته ظاهرين بكمية صفر.
                $newProductCodes = array();
                $newSerialProductCodes = array();
                foreach ($newData as $data) {
                    $productIdentity = strtoupper(trim((string) $data['item_code']));
                    if ($productIdentity === '') {
                        $productIdentity = 'DESC|' . strtoupper(trim((string) $data['item_description']));
                    }
                    $newProductCodes[$productIdentity] = true;
                    if (strtolower(trim((string) $data['serial_control'])) === 'yes') {
                        $newSerialProductCodes[$productIdentity] = true;
                    }
                }

                $serialPlaceholderIds = array();
                $reusableSerialPlaceholderIds = array();
                foreach ($currentData as $item) {
                    if (strtolower(trim((string) $item['serial_control'])) !== 'yes') {
                        continue;
                    }

                    $productIdentity = strtoupper(trim((string) $item['item_code']));
                    if ($productIdentity === '') {
                        $productIdentity = 'DESC|' . strtoupper(trim((string) $item['item_description']));
                    }

                    $hasEmptySerial = trim((string) $item['serial_number']) === '';
                    if ($hasEmptySerial && isset($newSerialProductCodes[$productIdentity])) {
                        $reusableSerialPlaceholderIds[$productIdentity] = (int) $item['id'];
                    }

                    if (isset($newProductCodes[$productIdentity])) {
                        continue;
                    }

                    if (!isset($serialPlaceholderIds[$productIdentity]) || $hasEmptySerial) {
                        $serialPlaceholderIds[$productIdentity] = (int) $item['id'];
                    }
                }

                $quantitiesBeforeImport = $this->Store->snapshot_counted_quantities();

                // 1️⃣ إزالة السيريالات غير الموجودة مع إبقاء سجل المنتج بكمية صفر،
                // وتصفير الكمية للمنتجات بدون سيريال.
                foreach ($currentKeys as $key => $item) {
                    if (!isset($newData[$key])) {
                        if (strtolower($item['serial_control']) === 'yes') {
                            $item_id = $item['id'];
                            $serialNumber = trim((string) $item['serial_number']);
                            $productIdentity = strtoupper(trim((string) $item['item_code']));
                            if ($productIdentity === '') {
                                $productIdentity = 'DESC|' . strtoupper(trim((string) $item['item_description']));
                            }

                            // آخر سجل للمنتج يتحول إلى سجل تعريفي بدون سيريال،
                            // وبذلك يظهر المنتج في القوائم بإجمالي كمية صفر.
                            if (isset($serialPlaceholderIds[$productIdentity])
                                && (int) $serialPlaceholderIds[$productIdentity] === (int) $item_id) {
                                if ($serialNumber !== '' || (int) $item['quantity_total'] !== 0) {
                                    $updatedRows = $this->Store->update_store_item(
                                        array(
                                            'serial_number' => null,
                                            'quantity_total' => 0,
                                        ),
                                        array('id' => $item_id)
                                    );

                                    if ($updatedRows > 0 && $serialNumber !== '') {
                                        $this->Store->remember_serial_shelf($item, $uploadTimestamp);
                                        $this->Store->record_stock_movement(
                                            $item,
                                            'out',
                                            $uploadTimestamp,
                                            $uploadedBy,
                                            $sourceFilename
                                        );
                                    }
                                }

                                $this->Cart->delete_cart_item(array('item_id' => $item_id));
                                if ($serialNumber !== '') {
                                    $this->Store->remove_item_from_shelf(
                                        $item['item_code'],
                                        $item['serial_control'],
                                        $item['serial_number'],
                                        $item['barcode']
                                    );
                                }
                                continue;
                            }

                            // لو المنتج عاد بسيريالات في الملف الجديد، نُبقي السجل
                            // الصفري مؤقتًا لإعادة استخدامه مع أول سيريال جديد.
                            if ($serialNumber === ''
                                && isset($reusableSerialPlaceholderIds[$productIdentity])
                                && (int) $reusableSerialPlaceholderIds[$productIdentity] === (int) $item_id) {
                                continue;
                            }

                            // نحذف السيريال القديم نفسه، لكن ليس سجل المنتج التعريفي.
                            $deletedRows = $this->Store->delete_store_item(
                                array('id' => $item_id)
                            );
                            if ($deletedRows > 0 && $serialNumber !== '') {
                                $this->Store->remember_serial_shelf($item, $uploadTimestamp);
                                $this->Store->record_stock_movement(
                                    $item,
                                    'out',
                                    $uploadTimestamp,
                                    $uploadedBy,
                                    $sourceFilename
                                );
                            }

                            $this->Cart->delete_cart_item(['item_id' => $item_id]);
                            if ($serialNumber !== '') {
                                $this->Store->remove_item_from_shelf($item['item_code'], $item['serial_control'], $item['serial_number'], $item['barcode']);
                            }

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
                $usedSerialPlaceholders = array();
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
                        $productIdentity = strtoupper(trim((string) $data['item_code']));
                        if ($productIdentity === '') {
                            $productIdentity = 'DESC|' . strtoupper(trim((string) $data['item_description']));
                        }

                        if ($data['serial_control'] === 'yes'
                            && isset($reusableSerialPlaceholderIds[$productIdentity])
                            && !isset($usedSerialPlaceholders[$productIdentity])) {
                            $placeholderId = (int) $reusableSerialPlaceholderIds[$productIdentity];
                            $updatedRows = $this->Store->update_store_item(
                                array(
                                    'subinventory_code' => $data['subinventory_code'],
                                    'item_category' => $data['item_category'],
                                    'item_code' => $data['item_code'],
                                    'item_description' => $data['item_description'],
                                    'serial_control' => $data['serial_control'],
                                    'serial_number' => $data['serial_number'],
                                    'quantity_total' => $data['quantity_total'],
                                ),
                                array('id' => $placeholderId)
                            );
                            $usedSerialPlaceholders[$productIdentity] = true;

                            if ($updatedRows > 0) {
                                $this->Store->restore_serial_shelf($data, $uploadTimestamp);
                                $this->Store->record_stock_movement(
                                    $data,
                                    'in',
                                    $uploadTimestamp,
                                    $uploadedBy,
                                    $sourceFilename
                                );
                            }
                            continue;
                        }

                        $insertData = array(
                            'subinventory_code' => $data['subinventory_code'],
                            'item_category' => $data['item_category'],
                            'item_code' => $data['item_code'],
                            'item_description' => $data['item_description'],
                            'serial_control' => $data['serial_control'],
                            'serial_number' => $data['serial_number'],
                            'quantity_total' => $data['quantity_total'],
                        );
                        $insertedItemId = $this->Store->add_store_item($insertData);
                        if ($insertedItemId && $data['serial_control'] === 'yes') {
                            $this->Store->restore_serial_shelf($insertData, $uploadTimestamp);
                            $this->Store->record_stock_movement(
                                $insertData,
                                'in',
                                $uploadTimestamp,
                                $uploadedBy,
                                $sourceFilename
                            );
                        }
                    }
                }

                // نسجل وقت نجاح رفع ملف المخزون كحدث مستقل عن تعديلات المنتجات اليدوية.
                $this->Settings->set_field('stock_excel_last_uploaded_at', $uploadTimestamp);

                $uploadStatus = 'success';
                $uploadMessage = "تم تحديث المخزون بنجاح!";


            } catch (Exception $e) {
                if ((int) $e->getCode() === 409) {
                    $uploadStatus = 'warning';
                    $uploadMessage = $e->getMessage();
                    $requiresConfirmation = true;
                } elseif ((int) $e->getCode() === 410) {
                    $uploadStatus = 'error';
                    $uploadReason = 'old_file';
                    $uploadMessage = $e->getMessage();
                } elseif ((int) $e->getCode() === 422) {
                    $uploadStatus = 'error';
                    $uploadMessage = $e->getMessage();
                } else {
                    $uploadStatus = 'error';
                    $uploadMessage = "حدث خطأ أثناء معالجة الملف: " . $e->getMessage();
                }

            } finally {
                // حتى لو توقف الاستيراد في المنتصف، ما كُتب فعلًا في المخزون
                // لازم ينعكس على تاريخ آخر تحديث للكمية.
                if (is_array($quantitiesBeforeImport)) {
                    $this->Store->commit_quantity_changes(
                        $quantitiesBeforeImport,
                        'رفع ملف التقفيلة',
                        $uploadedBy,
                        $uploadTimestamp
                    );
                }
            }
        }

        $this->data['uploadMessage'] = $uploadMessage;
        $this->data['uploadStatus'] = $uploadStatus;

        if ($this->input->is_ajax_request()) {
            $this->output
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(array(
                    'status' => $uploadStatus ?: 'error',
                    'message' => $uploadMessage ?: 'لم يتم استلام ملف Excel.',
                    'reason' => $uploadReason,
                    'requires_confirmation' => $requiresConfirmation,
                    'uploaded_at_label' => $uploadStatus === 'success'
                        ? 'آخر رفع لملف المخزون: ' . $uploadTime->format('d/m/Y h:i') . ' ' . ($uploadTime->format('A') === 'AM' ? 'ص' : 'م') . ' (منذ أقل من دقيقة)'
                        : '',
                ), JSON_UNESCAPED_UNICODE));
            return;
        }

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


            // تغيير serial_control ينقل المنتج بين فرعَي معادلة حساب الكمية
            // (عدّ سيريالات مقابل جمع quantity_total)، فقد يغيّر الكمية المعروضة.
            $quantities_before = $this->Store->snapshot_counted_quantities(array($item_code));

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

            $this->Store->commit_quantity_changes(
                $quantities_before,
                'تعديل يدوي للمنتج',
                $this->session->userdata('user_id'),
                null,
                array($item_code)
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

    function export_low()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('products.low.view');

        $low_products = $this->Store->get_low_products();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Low Stock');
        $sheet->setRightToLeft(true);

        $headers = array(
            'A1' => 'المنتج',
            'B1' => 'رقم الصنف',
            'C1' => 'تصنيف المنتج',
            'D1' => 'العدد المتبقي',
            'E1' => 'الحد الأدنى',
            'F1' => 'آخر تحديث',
            'G1' => 'الحالة',
        );

        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $rowNumber = 2;
        foreach ($low_products as $product) {
            $sheet->setCellValue('A' . $rowNumber, $product['item_description']);
            $sheet->setCellValue('B' . $rowNumber, $product['item_code']);
            $sheet->setCellValue('C' . $rowNumber, $product['item_category']);
            $sheet->setCellValue('D' . $rowNumber, (int) $product['total_quantity']);
            $sheet->setCellValue('E' . $rowNumber, (int) $product['low_quantity']);
            $sheet->setCellValue('F' . $rowNumber, quantity_update_date($product['quantity_changed_at']));
            $sheet->setCellValue('G' . $rowNumber, 'الكمية منخفضة');
            $rowNumber++;
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $sheet->freezePane('A2');

        $filename = 'low_products_' . date('Y-m-d_H-i-s') . '.xlsx';

        if (ob_get_length()) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit;
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

    /**
     * نفس قاعدة المطابقة المستخدمة في البحث: السيريال يُقبل بحرف S أو بدونه.
     */
    private function find_item_by_serial($serial)
    {
        $serial = strtoupper(trim((string) $serial));
        if ($serial === '') {
            return null;
        }

        $without_s = preg_replace('/^S/', '', $serial, 1);
        $candidates = array_unique(array($serial, $without_s, 'S' . $without_s));

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $item = $this->Store->get_store_item(array('serial_number' => $candidate));
            if ($item != null) {
                return $item;
            }
        }

        return null;
    }

    private function input_text_length($value)
    {
        return function_exists('mb_strlen')
            ? mb_strlen((string) $value, 'UTF-8')
            : strlen((string) $value);
    }

    /**
     * يعرض حالة السيريال أثناء الكتابة في نافذة إضافة الملاحظة.
     */
    function serial_note_context()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('product_search.notes.create');

        $serial = trim((string) $this->input->get('serial'));
        if ($serial === '') {
            echo json_encode(array('status' => 'error', 'description' => 'يرجى إدخال رقم السيريال'));
            return;
        }

        $item = $this->find_item_by_serial($serial);
        $notes = $this->SerialNotes->get_by_serial($serial);

        echo json_encode(array(
            'status' => 'ok',
            'serial' => strtoupper($serial),
            'item_exists' => $item != null,
            'item_name' => $item != null ? $item['item_description'] : '',
            'item_code' => $item != null ? $item['item_code'] : '',
            'notes_count' => count($notes),
        ));
    }

    /**
     * يحفظ الملاحظة ويسجل نفس الحدث في سجل العمليات.
     */
    function add_serial_note()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
        }

        $this->check_permission('product_search.notes.create');

        if (strtoupper((string) $this->input->method()) !== 'POST') {
            echo json_encode(array('status' => 'error', 'description' => 'طريقة الطلب غير صحيحة'));
            return;
        }

        $serial = strtoupper(trim((string) $this->input->post('serial')));
        $note = trim((string) $this->input->post('note'));

        if ($serial === '') {
            echo json_encode(array('status' => 'error', 'description' => 'يرجى إدخال رقم السيريال'));
            return;
        }

        if ($this->input_text_length($serial) > 190) {
            echo json_encode(array('status' => 'error', 'description' => 'رقم السيريال أطول من الحد المسموح'));
            return;
        }

        if ($note === '') {
            echo json_encode(array('status' => 'error', 'description' => 'يرجى كتابة الملاحظة'));
            return;
        }

        if ($this->input_text_length($note) > 2000) {
            echo json_encode(array('status' => 'error', 'description' => 'الملاحظة يجب ألا تتجاوز 2000 حرف'));
            return;
        }

        $item = $this->find_item_by_serial($serial);
        $note_id = $this->SerialNotes->add_note($serial, $note, $this->session->userdata('user_id'));

        if (!$note_id) {
            echo json_encode(array('status' => 'error', 'description' => 'تعذر حفظ الملاحظة، يرجى المحاولة مرة أخرى'));
            return;
        }

        $operation_saved = $this->Operations->add_operation(array(
            'item_description' => $item != null ? $item['item_description'] : '',
            'item_code' => $item != null ? $item['item_code'] : '',
            'serial' => $item != null ? $item['serial_number'] : $serial,
            'operation' => 'تم إضافة ملاحظة "' . $note . '"',
            'sales_order' => '0',
        ), $this->session->userdata('user_id'));

        if (!$operation_saved) {
            // جدول العمليات قديم ويعمل بمحرك MyISAM، لذلك نستخدم تعويضًا صريحًا
            // حتى لا تبقى ملاحظة بلا عملية مقابلة لو فشل الإدخال الثاني.
            $this->SerialNotes->delete_note($note_id);
            echo json_encode(array('status' => 'error', 'description' => 'تعذر تسجيل العملية، لم يتم حفظ الملاحظة'));
            return;
        }

        echo json_encode(array(
            'status' => 'ok',
            'description' => 'تمت إضافة الملاحظة بنجاح',
            'serial' => $item != null ? $item['serial_number'] : $serial,
            'item_exists' => $item != null,
            'notes' => $this->SerialNotes->get_by_serial($serial),
        ));
    }


    function delete_serial_note()
    {
        if (!in_array($this->session->userdata('user_id'), $this->get_admins())) {
            redirect(base_url());
            return;
        }

        $this->output->set_content_type('application/json', 'utf-8');
        $this->check_permission('product_search.notes.delete');

        if (strtoupper((string) $this->input->method()) !== 'POST') {
            echo json_encode(array('status' => 'error', 'description' => 'طريقة الطلب غير صحيحة'));
            return;
        }

        $id = filter_var($this->input->post('id'), FILTER_VALIDATE_INT, array('options' => array('min_range' => 1)));
        $note = $id ? $this->SerialNotes->get_by_id($id) : null;
        if (!$note) {
            echo json_encode(array('status' => 'error', 'description' => 'الملاحظة غير موجودة، أعد البحث لتحديث النتائج'));
            return;
        }

        $item = $this->find_item_by_serial($note['serial_number']);
        $this->db->trans_begin();
        $deleted = $this->SerialNotes->delete_note($id);
        if (!$deleted || $this->db->affected_rows() !== 1) {
            $this->db->trans_rollback();
            echo json_encode(array('status' => 'error', 'description' => 'تعذر حذف الملاحظة، أعد البحث وحاول مرة أخرى'));
            return;
        }

        $saved = $this->Operations->add_operation(array(
            'item_description' => $item != null ? $item['item_description'] : '',
            'item_code' => $item != null ? $item['item_code'] : '',
            'serial' => $note['serial_number'],
            'operation' => 'تم حذف الملاحظة "' . $note['note'] . '"',
            'sales_order' => '0',
        ), $this->session->userdata('user_id'));

        if (!$saved || !$this->db->trans_status()) {
            $this->db->trans_rollback();
            echo json_encode(array('status' => 'error', 'description' => 'تعذر تسجيل العملية، لم يتم حذف الملاحظة'));
            return;
        }

        $this->db->trans_commit();
        echo json_encode(array('status' => 'ok', 'description' => 'تم حذف الملاحظة بنجاح'));
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
            // نعطي التطابق الكامل والملاحظة الكاملة أولوية قبل بحث البداية،
            // حتى يعمل أي سيريال من 6 خانات ولا يختلط بسيريالات أطول.
            $exact_item = $this->find_item_by_serial($upper_term);
            if ($exact_item != null) {
                echo json_encode(['status' => 'ok', 'type' => 'serial', 'value' => $exact_item['serial_number']]);
                return;
            }

            if ($this->SerialNotes->has_notes($upper_term) || !empty($this->Operations->get_operations_by_serial($upper_term))) {
                echo json_encode([
                    'status' => 'ok',
                    'type' => 'noted_serial',
                    'value' => $upper_term,
                    'notes' => $this->SerialNotes->get_by_serial($upper_term),
                ]);
                return;
            }

            $serial_matches = $this->Store->get_items_by_serialpart($upper_term);
            if (count($serial_matches) > 0) {
                echo json_encode(['status' => 'ok', 'type' => 'serial_part', 'value' => $upper_term]);
                return;
            }
        }

        // سريال كامل (مع أو بدون حرف S في البداية)
        if ($term_length >= 7) {
            $item = $this->find_item_by_serial($upper_term);

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

        // قد يحمل السيريال ملاحظات حتى لو لم يكن مسجلًا في المخزون.
        if ($this->SerialNotes->has_notes($term) || !empty($this->Operations->get_operations_by_serial($term))) {
            echo json_encode([
                'status' => 'ok',
                'type' => 'noted_serial',
                'value' => strtoupper($term),
                'notes' => $this->SerialNotes->get_by_serial($term),
            ]);
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

        $serialnumber_input = strtoupper(trim((string) $this->input->get('serial')));
        $item = $this->find_item_by_serial($serialnumber_input);

        if ($item == null) {
            $notes = $this->SerialNotes->get_by_serial($serialnumber_input);
            echo json_encode([
                'status' => 'error',
                'description' => count($notes) > 0
                    ? 'هذا السيريال غير موجود في المخزون، ولكن توجد له ملاحظات'
                    : 'السيريال غير موجود',
                'serial_number' => $serialnumber_input,
                'notes' => $notes,
                'has_history' => count($notes) > 0 || !empty($this->Operations->get_operations_by_serial($serialnumber_input)),
            ]);
            return;
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
            'images' => $images,
            'notes' => $this->SerialNotes->get_by_serial($serial_number),
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
            if (empty($custody)) {
                echo json_encode(['msg' => 'العهدة غير موجودة أو تم إرجاعها بالفعل', 'status' => 'wrong']);
                return;
            }
            $item = $this->Store->get_store_item(['item_code' => $custody['item_code'], 'serial_number' => $custody['serial_number'], 'barcode' => $custody['barcode']]);

            $return_result = $this->Custodys->return_to_stock($custody_id);
            if (!$return_result['status']) {
                echo json_encode(['msg' => 'تعذر إرجاع المنتج، ربما تم إرجاعه بالفعل', 'status' => 'wrong']);
                return;
            }

            $auto_approved = $this->ReturnRequests->approve_pending_by_custody(
                $custody_id,
                (int) $this->session->userdata('user_id'),
                'تمت الموافقة ضمن عملية الإرجاع المباشر من الإدارة'
            );
            if ($auto_approved > 0) {
                $this->Notifications->add_notification(array(
                    'user_id' => (int) $custody['user_id'],
                    'description' => 'تمت الموافقة على طلب إرجاع العهدة وإعادتها إلى المخزون',
                    'link' => base_url().MOD_VALUE.'user/custody#return-requests',
                    'status' => 'unread',
                    'date_created' => date('Y-m-d H:i:s'),
                ));
            }

            if (!empty($item) && isset($item['serial_control']) && $item['serial_control'] == 'yes') {
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
            return ['name' => '', 'code' => '', 'subinventory_code' => ''];
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
                'subinventory_code' => isset($item['subinventory_code']) ? trim((string) $item['subinventory_code']) : '',
            ];
        }

        $history_item = $this->Store->get_serial_history_by_number($number);
        $info = [
            'name' => !empty($history_item['item_description'])
                ? trim((string) $history_item['item_description'])
                : '',
            'code' => !empty($history_item['item_code'])
                ? trim((string) $history_item['item_code'])
                : '',
            'subinventory_code' => !empty($history_item['subinventory_code'])
                ? trim((string) $history_item['subinventory_code'])
                : '',
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
            if ($info['subinventory_code'] === '' && !empty($matching_item['subinventory_code'])) {
                $info['subinventory_code'] = trim((string) $matching_item['subinventory_code']);
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

        $this->data['info'] = ['name' => '', 'code' => '', 'subinventory_code' => ''];
        $this->data['operations'] = [];
        $this->data['sales'] = [];
        $this->data['stock_movements'] = [];
        $this->data['timeline'] = [];
        $this->data['last_sale'] = null;
        $this->data['stock_entry'] = null;
        $this->data['stock_exit'] = null;
        $this->data['stock_state'] = 'unknown';

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
        $stock_movements = $this->Store->get_stock_movements_by_number($serial);

        if ($operations != null) {
            $this->data['serial'] = $operations[0]['serial'];
        } else if (count($sales) > 0) {
            $this->data['serial'] = $sales[0]['insert_excel_product_serial_number'];
        }

        $info = $this->get_item_info_by_number($this->data['serial'], $operations, $sales);
        if ($info['subinventory_code'] === '') {
            foreach ($stock_movements as $stock_movement) {
                if (!empty($stock_movement['subinventory_code'])) {
                    $info['subinventory_code'] = trim((string) $stock_movement['subinventory_code']);
                    break;
                }
            }
        }

        $this->data['info'] = $info;
        $this->data['operations'] = $operations;
        $this->data['sales'] = $sales;
        $this->data['stock_movements'] = $stock_movements;
        $this->data['last_sale'] = count($sales) > 0 ? $this->format_sale_entry($sales[0]) : null;
        $stock_lifecycle = $this->build_stock_lifecycle($stock_movements);
        $this->data['stock_entry'] = $stock_lifecycle['entry'];
        $this->data['stock_exit'] = $stock_lifecycle['exit'];
        $this->data['stock_state'] = $stock_lifecycle['state'];
        $this->data['timeline'] = $this->build_operations_timeline($operations, $sales, $stock_movements);

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
     * Returns the latest inventory-file cycle for the serial.
     */
    private function build_stock_lifecycle($stock_movements)
    {
        $result = array(
            'entry' => null,
            'exit' => null,
            'state' => 'unknown',
        );

        if (count($stock_movements) === 0) {
            return $result;
        }

        $latest = $stock_movements[0];
        $result['state'] = $latest['movement_type'] === 'in' ? 'in' : 'out';

        if ($result['state'] === 'in') {
            $result['entry'] = $latest;
            return $result;
        }

        $result['exit'] = $latest;
        foreach ($stock_movements as $movement) {
            if ($movement['movement_type'] === 'in') {
                $result['entry'] = $movement;
                break;
            }
        }

        return $result;
    }

    /**
     * يدمج عمليات المخزون والمبيعات وحركات ملفات المخزون في سجل زمني واحد.
     */
    private function build_operations_timeline($operations, $sales, $stock_movements = array())
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

        foreach ($stock_movements as $stock_movement) {
            $is_entry = $stock_movement['movement_type'] === 'in';
            $uploaded_by = !empty($stock_movement['uploaded_by'])
                ? $this->Model_admin->get_user_by_id($stock_movement['uploaded_by'])
                : null;
            $source_filename = trim((string) $stock_movement['source_filename']);

            $timeline[] = [
                'source' => 'stock',
                'date' => $stock_movement['occurred_at'],
                'sort_id' => isset($stock_movement['id']) ? (int) $stock_movement['id'] : 0,
                'sales_order' => $source_filename,
                'operation' => $is_entry
                    ? 'دخول المنتج إلى المخزون من ملف المخزون'
                    : 'خروج المنتج من المخزون لعدم وجوده في ملف المخزون',
                'movement' => $is_entry ? '1' : '-1',
                'user_name' => !empty($uploaded_by['user_fillname'])
                    ? $uploaded_by['user_fillname']
                    : 'تهيئة النظام',
                'sales_type' => '',
                'sales_amount' => 0,
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

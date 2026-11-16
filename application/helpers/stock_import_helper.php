<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** The report's two-level header can merge A9:A10, B9:B10, etc. */
function stock_import_rows_with_merged_headers($sheet)
{
    $rows = $sheet->toArray(null, true, true, true);
    foreach ($sheet->getMergeCells() as $range) {
        $bounds = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::rangeBoundaries($range);
        if ($bounds[0][1] > 10 || $bounds[1][1] < 10 || $bounds[1][1] > 10) {
            continue;
        }
        $anchor = explode(':', $range)[0];
        for ($column = max(1, $bounds[0][0]); $column <= min(7, $bounds[1][0]); $column++) {
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column);
            $rows[10][$letter] = $sheet->getCell($anchor)->getFormattedValue();
        }
    }
    return $rows;
}

/** Validate the complete stock snapshot before any inventory/shelf writes. */
function validate_stock_import_rows($rows)
{
    $fail = function ($message) {
        throw new UnexpectedValueException('تم رفض الملف: ' . $message . ' لم يتم تعديل المخزون أو الرفوف.', 422);
    };
    $columns = array(
        'A' => 'كود المخزون', 'B' => 'تصنيف المنتج', 'C' => 'كود المنتج',
        'D' => 'وصف المنتج', 'E' => 'Serial Control', 'F' => 'السريال', 'G' => 'الكمية',
    );

    $headers = array(
        'A' => array('Subinventory Code', 'Subinventory', 'كود المخزون'),
        'B' => array('Item Category', 'Category', 'تصنيف المنتج', 'التصنيف'),
        'C' => array('Item Code', 'Item Number', 'Item', 'كود المنتج'),
        'D' => array('Item Description', 'Description', 'وصف المنتج', 'اسم المنتج'),
        'E' => array('Serial Control', 'Serial Controlled', 'Serial Control Yes No', 'هل المنتج له سريال'),
        'F' => array('Serial Number', 'Serial No', 'Serial', 'السريال', 'السيريال', 'الرقم التسلسلي'),
        'G' => array('Quantity', 'Qty', 'Onhand Quantity', 'On Hand Quantity', 'Total Quantity', 'الكمية'),
    );
    $normalize_header = function ($value) {
        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        return strtolower(preg_replace('/[\s\x{00A0}\p{P}\p{S}]+/u', '', trim($value)));
    };
    // Accept common report labels, but reject renamed/reordered unrelated columns.
    foreach ($columns as $column => $label) {
        if (!isset($rows[10][$column]) || trim((string) $rows[10][$column]) === '') {
            $fail('هيكل الملف غير مطابق؛ يجب أن يحتوي الصف 10 على عناوين الأعمدة A إلى G. عنوان العمود ' . $column . ' (' . $label . ') غير موجود.');
        }
        if (!in_array($normalize_header($rows[10][$column]), array_map($normalize_header, $headers[$column]), true)) {
            $fail('هيكل الملف غير مطابق؛ عنوان العمود ' . $column . ' في الصف 10 يجب أن يكون ' . $headers[$column][0] . ' (' . $label . '). تأكد من ترتيب الأعمدة واستخدام تقرير المخزون الصحيح.');
        }
    }

    $serials = array();
    $product_controls = array();
    $accepted = 0;
    foreach ($rows as $row_number => $row) {
        if ($row_number <= 10) {
            continue;
        }
        if (!array_filter($row, function ($value) { return trim((string) $value) !== ''; })) {
            continue;
        }

        $values = array();
        foreach ($columns as $column => $label) {
            $values[$column] = isset($row[$column]) ? trim((string) $row[$column]) : '';
        }
        foreach (array('A', 'B', 'C', 'D', 'E', 'G') as $column) {
            if ($values[$column] === '') {
                $fail('الصف ' . $row_number . ': العمود ' . $column . ' (' . $columns[$column] . ') مطلوب. بيانات المنتجات يجب أن تبدأ من الصف 11.');
            }
        }

        $control = strtolower($values['E']);
        if (!in_array($control, array('yes', 'no'), true)) {
            $fail('الصف ' . $row_number . ': العمود E يجب أن يحتوي Yes أو No. تأكد من ترتيب الأعمدة.');
        }
        if (!is_numeric($values['G']) || !is_finite((float) $values['G'])
            || (float) $values['G'] < 0 || (float) $values['G'] > 2147483647
            || floor((float) $values['G']) !== (float) $values['G']) {
            $fail('الصف ' . $row_number . ': الكمية في العمود G يجب أن تكون عددًا صحيحًا غير سالب ضمن الحد المسموح.');
        }
        if ($control === 'yes' && $values['F'] === '') {
            $fail('الصف ' . $row_number . ': السريال في العمود F مطلوب عندما تكون قيمة E هي Yes.');
        }
        if ($control === 'no' && $values['F'] !== '') {
            $fail('الصف ' . $row_number . ': العمود F يجب أن يكون فارغًا للمنتج بدون سريال (No).');
        }

        // Retain the existing SIM exclusion, but do not accept a SIM-only snapshot.
        if (stripos($values['B'], 'sim') !== false) {
            continue;
        }
        $product = strtoupper($values['C']);
        if (isset($product_controls[$product]) && $product_controls[$product] !== $control) {
            $fail('الصف ' . $row_number . ': نفس كود المنتج مستخدم بقيم مختلفة في Serial Control.');
        }
        $product_controls[$product] = $control;
        if ($control === 'yes') {
            $serial = strtoupper($values['F']);
            if (isset($serials[$serial])) {
                $fail('الصف ' . $row_number . ': السريال مكرر، وظهر سابقًا في الصف ' . $serials[$serial] . '.');
            }
            $serials[$serial] = $row_number;
        }
        $accepted++;
    }
    if ($accepted === 0) {
        $fail('الملف لا يحتوي على بيانات منتجات قابلة للاستيراد من الصف 11.');
    }
}

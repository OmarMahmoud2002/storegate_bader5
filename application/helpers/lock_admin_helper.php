<?php

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * One employee's التقفيلة figures for a date range.
 *
 * The report view and the per-employee Excel export both read from here so the
 * downloaded file can never disagree with the numbers on screen.
 */
function lock_admin_employee_breakdown($user_id, $date_start, $date_end)
{
    $CI =& get_instance();
    $CI->load->model('Excel_import_model');
    $CI->load->model('Model_admin');
    $CI->load->model('Model_lock_extras');

    $user_id = (int) $user_id;

    $breakdown = array(
        'twasel' => 0.0,
        'electronic' => 0.0,
        'jowy' => 0.0,
        'quickplus' => 0.0,
        'settlements' => 0.0,
        'network' => 0.0,
        'cash' => 0.0,
        'tamara' => 0.0,
        'wallet' => 0.0,
        'extra_balance' => 0.0,
    );

    foreach ($CI->Excel_import_model->selectbyid($user_id, $date_start, $date_end) as $excel) {
        $breakdown['twasel'] += (float) $excel['insert_excel_twasel'];
        $breakdown['electronic'] += (float) $excel['insert_excel_electronic'];
        $breakdown['jowy'] += (float) $excel['insert_excel_jowy'];
        $breakdown['quickplus'] += (float) $excel['insert_excel_quickplus'];
    }

    foreach ($CI->Model_admin->get_settlement($user_id, $date_start, $date_end) as $settlement) {
        $breakdown['settlements'] += (float) $settlement['settlement_amount'];
    }

    $userlock = $CI->Model_admin->getlock_by_userid($user_id, $date_start, $date_end);
    $breakdown['network'] = isset($userlock['user_lock_span']) ? (float) $userlock['user_lock_span'] : 0;
    $breakdown['cash'] = isset($userlock['user_lock_cash']) ? (float) $userlock['user_lock_cash'] : 0;

    $extras = $CI->Model_lock_extras->get_totals($user_id, $date_start, $date_end);

    return lock_admin_complete_breakdown(array_merge($breakdown, $extras));
}

/**
 * Derive the total / paid / difference rows from the raw amounts. Kept apart
 * from the queries above so the view can reuse it with figures it already
 * fetched in its own loop.
 */
function lock_admin_complete_breakdown($breakdown)
{
    $breakdown['total_sales'] = $breakdown['twasel'] + $breakdown['electronic']
        + $breakdown['jowy'] + $breakdown['quickplus'];
    $breakdown['total_payment'] = $breakdown['cash'] + $breakdown['network'];
    $breakdown['difference'] = lock_admin_difference($breakdown);

    return $breakdown;
}

/**
 * تمارا, المحفظة and رصيد إضافي are collected money like the network and cash
 * rows, so they close the gap against sales in الفروقات.
 */
function lock_admin_difference($breakdown)
{
    $collected = round((float) $breakdown['cash'], 2)
        + round((float) $breakdown['network'], 2)
        + round((float) $breakdown['tamara'], 2)
        + round((float) $breakdown['wallet'], 2)
        + round((float) $breakdown['extra_balance'], 2);

    return round($collected - round((float) $breakdown['total_sales'], 2) + round((float) $breakdown['settlements'], 2), 2);
}

/**
 * Shared CSS class for a difference amount, used by both the table and popup.
 */
function lock_admin_difference_class($difference)
{
    if ($difference < 0) {
        return 'negative';
    }

    return $difference > 0 ? 'positive' : 'zero';
}

/**
 * The report rows in display order: label, breakdown key and how the row
 * behaves. The Excel export walks this so it mirrors the popup exactly.
 */
function lock_admin_report_rows()
{
    return array(
        array('key' => 'twasel', 'label' => 'مبيعات تواصل'),
        array('key' => 'electronic', 'label' => 'شحن إلكتروني'),
        array('key' => 'jowy', 'label' => 'مبيعات جوي'),
        array('key' => 'quickplus', 'label' => 'مبيعات كويك بلس'),
        array('key' => 'total_sales', 'label' => 'الإجمالي', 'emphasis' => true),
        array('key' => 'network', 'label' => 'مبلغ الشبكة'),
        array('key' => 'cash', 'label' => 'النقدي'),
        array('key' => 'total_payment', 'label' => 'إجمالي المدفوع', 'emphasis' => true),
        array('key' => 'tamara', 'label' => 'تمارا', 'editable' => true),
        array('key' => 'wallet', 'label' => 'المحفظة', 'editable' => true),
        array('key' => 'extra_balance', 'label' => 'رصيد إضافي', 'editable' => true),
        array('key' => 'difference', 'label' => 'الفروقات', 'difference' => true),
    );
}

/**
 * Lay out the single-employee التقفيلة sheet: header block, then the same
 * rows the popup shows, in the same order.
 */
function lock_admin_build_workbook($employee, $breakdown, $date_start, $date_end)
{
	$spreadsheet = new Spreadsheet();
	$sheet = $spreadsheet->getActiveSheet();
	$sheet->setTitle('التقفيلة');
	$sheet->setRightToLeft(true);

	$employee_name = isset($employee['user_fillname']) ? $employee['user_fillname'] : '';
	$employee_number = '';
	if (!empty($employee['job_number'])) {
		$employee_number = $employee['job_number'];
	} else if (isset($employee['user_employee_Id'])) {
		$employee_number = $employee['user_employee_Id'];
	}

	$sheet->mergeCells('A1:B1');
	$sheet->setCellValue('A1', 'تقفيلة الموظف');
	$sheet->getStyle('A1')->applyFromArray(array(
		'font' => array('bold' => true, 'size' => 18, 'color' => array('rgb' => 'FFFFFF')),
		'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER),
		'fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => '667EEA')),
	));
	$sheet->getRowDimension(1)->setRowHeight(32);

	$sheet->setCellValue('A2', 'اسم الموظف');
	$sheet->setCellValue('B2', $employee_name);
	$sheet->setCellValue('A3', 'رقم الموظف');
	$sheet->setCellValueExplicit('B3', (string) $employee_number, DataType::TYPE_STRING);
	$sheet->setCellValue('A4', 'الفترة');
	$sheet->setCellValue('B4', 'من ' . $date_start . ' إلى ' . $date_end);
	$sheet->setCellValue('A5', 'تاريخ التحميل');
	$sheet->setCellValue('B5', date('Y-m-d H:i'));

	$sheet->getStyle('A2:A5')->applyFromArray(array(
		'font' => array('bold' => true),
		'fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => 'EEF2FF')),
	));
	$sheet->getStyle('A2:B5')->applyFromArray(array(
		'borders' => array('allBorders' => array('style' => Border::BORDER_THIN, 'color' => array('rgb' => 'D5DBE7'))),
		'alignment' => array('horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER),
	));

	$header_row = 7;
	$sheet->setCellValue('A' . $header_row, 'الصنف');
	$sheet->setCellValue('B' . $header_row, 'القيمة');
	$sheet->getStyle('A' . $header_row . ':B' . $header_row)->applyFromArray(array(
		'font' => array('bold' => true, 'size' => 12, 'color' => array('rgb' => 'FFFFFF')),
		'alignment' => array('horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER),
		'fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => '0F8DCC')),
		'borders' => array('allBorders' => array('style' => Border::BORDER_THIN, 'color' => array('rgb' => 'D5DBE7'))),
	));
	$sheet->getRowDimension($header_row)->setRowHeight(24);

	$current_row = $header_row + 1;
	foreach (lock_admin_report_rows() as $report_row) {
		$value = isset($breakdown[$report_row['key']]) ? (float) $breakdown[$report_row['key']] : 0;

		$sheet->setCellValue('A' . $current_row, $report_row['label']);
		$sheet->setCellValue('B' . $current_row, $value);

		$sheet->getStyle('A' . $current_row . ':B' . $current_row)->applyFromArray(array(
			'borders' => array('allBorders' => array('style' => Border::BORDER_THIN, 'color' => array('rgb' => 'D5DBE7'))),
			'alignment' => array('vertical' => Alignment::VERTICAL_CENTER),
		));
		$sheet->getStyle('A' . $current_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
		$sheet->getStyle('B' . $current_row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
		$sheet->getStyle('B' . $current_row)->getNumberFormat()->setFormatCode('#,##0.00');

		if (!empty($report_row['emphasis'])) {
			$sheet->getStyle('A' . $current_row . ':B' . $current_row)->applyFromArray(array(
				'font' => array('bold' => true),
				'fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => 'F1F5F9')),
			));
		}

		if (!empty($report_row['editable'])) {
			$sheet->getStyle('A' . $current_row)->getFill()
				->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF7E6');
		}

		if (!empty($report_row['difference'])) {
			$difference_color = $value < 0 ? 'E74C3C' : ($value > 0 ? '27AE60' : '94A3B8');
			$sheet->getStyle('A' . $current_row . ':B' . $current_row)->applyFromArray(array(
				'font' => array('bold' => true, 'size' => 12, 'color' => array('rgb' => 'FFFFFF')),
				'fill' => array('fillType' => Fill::FILL_SOLID, 'color' => array('rgb' => $difference_color)),
			));
		}

		$current_row++;
	}

	$sheet->setCellValue('A' . ($current_row + 1), 'التسويات المحتسبة');
	$sheet->setCellValue('B' . ($current_row + 1), (float) $breakdown['settlements']);
	$sheet->getStyle('A' . ($current_row + 1))->getFont()->setBold(true);
	$sheet->getStyle('B' . ($current_row + 1))->getNumberFormat()->setFormatCode('#,##0.00');
	$sheet->getStyle('A' . ($current_row + 1) . ':B' . ($current_row + 1))->applyFromArray(array(
		'borders' => array('allBorders' => array('style' => Border::BORDER_THIN, 'color' => array('rgb' => 'D5DBE7'))),
	));

	$sheet->getColumnDimension('A')->setWidth(28);
	$sheet->getColumnDimension('B')->setWidth(22);
	$sheet->freezePane('A' . ($header_row + 1));

	return $spreadsheet;
}

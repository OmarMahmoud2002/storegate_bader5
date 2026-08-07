<?php

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

function calculateTotals($subordinates, $tableType) {
    if (empty($subordinates)) {
        return null;
    }

    $totals = [
        'tgt' => 0,
        'remaining' => 0
    ];

    if ($tableType === 'table1') {
        $totals['branch_sales'] = 0;
        $totals['digital_sales'] = 0;
        $totals['total'] = 0;

        foreach ($subordinates as $sub) {
            $totals['tgt'] += isset($sub['tgt']) ? $sub['tgt'] : 0;
            $totals['branch_sales'] += isset($sub['branch_sales']) ? $sub['branch_sales'] : 0;
            $totals['digital_sales'] += isset($sub['digital_sales']) ? $sub['digital_sales'] : 0;
            $totals['total'] += isset($sub['total']) ? $sub['total'] : 0;
            $totals['remaining'] += isset($sub['remaining']) ? $sub['remaining'] : 0;
        }

        $percentage = $totals['tgt'] > 0
            ? round(($totals['total'] / $totals['tgt']) * 100, 0)
            : ($totals['total'] > 0 ? 100 : 0);

        $totals['percentage'] = $percentage . '%';

    } else {
        $totals['sales'] = 0;

        foreach ($subordinates as $sub) {
            $totals['tgt'] += isset($sub['tgt']) ? $sub['tgt'] : 0;
            $totals['sales'] += isset($sub['sales']) ? $sub['sales'] : 0;
            $totals['remaining'] += isset($sub['remaining']) ? $sub['remaining'] : 0;
        }

        $percentage = $totals['tgt'] > 0
            ? round(($totals['sales'] / $totals['tgt']) * 100, 0)
            : ($totals['sales'] > 0 ? 100 : 0);

        $totals['percentage'] = $percentage . '%';
    }

    return $totals;
}

/**
 * Cleans a header/label value for comparison purposes: strips non-breaking space (U+00A0)
 * and zero-width space (U+200B) that real uploaded files have been observed to contain
 * (e.g. a trailing "Promo Code \xC2\xA0"), collapses whitespace, trims, lowercases.
 */
function normalize_header_text($value) {
    $value = (string) $value;
    $value = preg_replace('/[\x{00A0}\x{200B}]/u', ' ', $value);
    $value = trim($value);
    $value = preg_replace('/\s+/', ' ', $value);
    return mb_strtolower($value);
}

/**
 * Canonical employee identity field => accepted normalized header text(s).
 * Deliberately explicit (no fuzzy/partial matching) so an unrelated column never gets
 * mapped by accident.
 */
function employees_sales_field_aliases() {
    return [
        'emp_id'              => ['emp id'],
        'supervisor_id'       => ['supervisor id'],
        'national_id'         => ['emp national id'],
        'name'                => ['emp name'],
        'position'            => ['position'],
        'position_united'     => ['position united'],
        'sub_position'        => ['sub-position', 'sub position'],
        'cbu_user'            => ['cbu user'],
        'ebu_user'            => ['ebu user'],
        'region'              => ['region'],
        'area'                => ['area'],
        'branch_name'         => ['branch name'],
        'branch_code'         => ['branch code'],
        'note'                => ['note'],
        'vacation_days'       => ['vacation days'],
        'discount_percentage' => ['discount %'],
    ];
}

/** Employee identity fields that are actually required by the search/hierarchy logic. */
function employees_sales_mandatory_fields() {
    return ['emp_id' => 'EMP ID', 'supervisor_id' => 'Supervisor ID', 'position' => 'Position'];
}

/** Employee identity fields used as search terms; missing one only narrows search, doesn't break it. */
function employees_sales_recommended_fields() {
    return ['national_id' => 'EMP National ID', 'name' => 'EMP Name', 'cbu_user' => 'CBU User'];
}

/**
 * Scans row $headerRow of $sheet and builds a map of canonical employee field key => column
 * letter, discovered by header NAME rather than a fixed physical position. Stops at the first
 * "TGT" column (start of the KPI section) so a KPI sub-header can never be mistaken for an
 * employee field. Call this once per request and pass the result down instead of rebuilding it.
 */
function build_employee_header_map($sheet, $headerRow = 2) {
    $highestColumn = $sheet->getHighestColumn();
    $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);
    $aliases = employees_sales_field_aliases();

    $map = [];
    for ($col = 1; $col <= $highestColumnIndex; $col++) {
        $colLetter = Coordinate::stringFromColumnIndex($col);
        $normalized = normalize_header_text((string) $sheet->getCell($colLetter . $headerRow)->getValue());

        if ($normalized === 'tgt') {
            break;
        }

        foreach ($aliases as $canonicalKey => $acceptedNames) {
            if (!isset($map[$canonicalKey]) && in_array($normalized, $acceptedNames, true)) {
                $map[$canonicalKey] = $colLetter;
                break;
            }
        }
    }

    return $map;
}

/**
 * Picks the "staff sales" worksheet by name (case/whitespace-insensitive), regardless of how
 * many other sheets the workbook has or which one Excel last left active. Falls back to
 * getActiveSheet() for legacy single-sheet files that have no sheet literally named
 * "staff sales" (e.g. old files whose only sheet is "Sheet1").
 */
function get_employees_sales_sheet($spreadsheet) {
    foreach ($spreadsheet->getSheetNames() as $sheetName) {
        if (normalize_header_text($sheetName) === 'staff sales') {
            return $spreadsheet->getSheetByName($sheetName);
        }
    }

    return $spreadsheet->getActiveSheet();
}

/**
 * Maps a raw KPI sub-header (row 2 under a product group, e.g. "TGT", "Branch sales",
 * "Digital Sales"/"Promo Code", "Total", "%", "Sales") to its canonical label. Digital Sales and
 * Promo Code resolve to the same canonical slot so old and new files feed the same internal
 * field. Unknown sub-headers are returned trimmed as-is (used for group-boundary detection).
 */
function employees_sales_normalize_sub_label($rawSubLabel) {
    $normalized = normalize_header_text((string) $rawSubLabel);

    $canonical = [
        'tgt'           => 'TGT',
        'branch sales'  => 'Branch sales',
        'digital sales' => 'Digital Sales',
        'promo code'    => 'Digital Sales',
        'total'         => 'Total',
        '%'             => '%',
        'sales'         => 'Sales',
    ];

    if (isset($canonical[$normalized])) {
        return $canonical[$normalized];
    }

    return trim((string) $rawSubLabel);
}

/**
 * Raw Position value (as typed in the source file) => canonical grade used for calculation
 * dispatch and hierarchy/UI checks. "Professional Area Sales Manager" is an explicitly confirmed
 * alias for "Area Manager" (same calculation, same options, same drill-down behaviour) — it is
 * NOT a distinct hierarchy level.
 */
function employees_sales_position_aliases() {
    return [
        'sales agent'                     => 'Sales Agent',
        'branch supervisor'               => 'Branch Supervisor',
        'branch manager'                  => 'Branch Manager',
        'area manager'                    => 'Area Manager',
        'professional area sales manager' => 'Area Manager',
    ];
}

/**
 * Resolves a raw Position cell value to one of the 4 canonical grades used by the calculation
 * logic, or null if the position isn't recognized. Use this for all business-logic decisions;
 * keep the raw value for display so the employee's real job title is never rewritten.
 */
function resolve_canonical_position($rawPosition) {
    $normalized = normalize_header_text((string) $rawPosition);
    $aliases = employees_sales_position_aliases();
    return isset($aliases[$normalized]) ? $aliases[$normalized] : null;
}

/**
 * Structural validation for an uploaded employees-sales workbook, run before it is allowed to
 * become the active file. Does not classify KPI groups (that happens in Other.php, which already
 * has the sheet loaded) - only checks the employee identity section.
 */
function validate_employees_sales_workbook($sheet) {
    $result = ['valid' => true, 'errors' => [], 'warnings' => []];

    $headerMap = build_employee_header_map($sheet);

    foreach (employees_sales_mandatory_fields() as $key => $label) {
        if (!isset($headerMap[$key])) {
            $result['errors'][] = "عمود إلزامي غير موجود في ملف الإكسل: {$label}";
        }
    }

    foreach (employees_sales_recommended_fields() as $key => $label) {
        if (!isset($headerMap[$key])) {
            $result['warnings'][] = "عمود بحث موصى به غير موجود: {$label} (البحث سيظل يعمل بالحقول الأخرى)";
        }
    }

    if ($sheet->getHighestDataRow() < 3) {
        $result['errors'][] = 'لا توجد صفوف بيانات موظفين أسفل صفوف العناوين';
    }

    $result['valid'] = empty($result['errors']);

    return $result;
}

function findEmployeeByIdInSheet($sheet, $empId, $headerMap = null) {
    if ($headerMap === null) {
        $headerMap = build_employee_header_map($sheet);
    }

    $empIdCol = isset($headerMap['emp_id']) ? $headerMap['emp_id'] : 'A';
    $nameCol = isset($headerMap['name']) ? $headerMap['name'] : 'D';
    $positionCol = isset($headerMap['position']) ? $headerMap['position'] : 'E';

    $highestRow = $sheet->getHighestDataRow();
    $firstDataRow = 2;

    for ($row = $firstDataRow; $row <= $highestRow; $row++) {
        $currentEmpId = trim((string)$sheet->getCell($empIdCol . $row)->getValue());

        if ($currentEmpId === $empId) {
            return [
                'name' => trim((string)$sheet->getCell($nameCol . $row)->getValue()),
                'position' => trim((string)$sheet->getCell($positionCol . $row)->getValue()),
                'emp_id' => $currentEmpId,
                'row' => $row
            ];
        }
    }

    return null;
}

// =============================================================================
// Performance optimization layer (see EMPLOYEES_SALES_PERFORMANCE_ANALYSIS.md
// and EMPLOYEES_SALES_PERFORMANCE_IMPLEMENTATION_REPORT.md for the measured
// rationale). Everything below is additive: it does not change what gets
// calculated, only how the "staff sales" sheet is loaded and how employee
// rows are looked up while calculating it.
// =============================================================================

/**
 * Resolves the "staff sales" worksheet name from a list of sheet names,
 * case/whitespace-insensitively. Returns null when no sheet matches (legacy
 * single/multi-sheet files with no sheet literally named this). Shared by
 * get_employees_sales_sheet() (already-loaded workbook) and
 * load_employees_sales_workbook() (selective load, before the workbook is
 * loaded) so both pick the exact same sheet by the exact same rule.
 */
function resolve_employees_sales_sheet_name(array $sheetNames) {
    foreach ($sheetNames as $sheetName) {
        if (normalize_header_text($sheetName) === 'staff sales') {
            return $sheetName;
        }
    }

    return null;
}

/**
 * Loads the employees-sales workbook, reading ONLY the "staff sales" worksheet
 * from disk when the workbook declares one, instead of parsing every sheet -
 * the dominant cost for large multi-sheet uploads (some real uploads carry 17
 * sheets, one of them with a corrupted/huge declared dimension, making a full
 * load take ~25-30s vs ~6-7s for "staff sales" alone - see the performance
 * analysis report). Falls back to a full load for legacy files with no
 * "staff sales"-named sheet, since PhpSpreadsheet's setLoadSheetsOnly() throws
 * if given a name that isn't present in the workbook.
 *
 * Returns ['spreadsheet' => Spreadsheet, 'sheet' => Worksheet].
 */
function load_employees_sales_workbook($filePath) {
    $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
    $sheetNames = $reader->listWorksheetNames($filePath);
    $matchedName = resolve_employees_sales_sheet_name($sheetNames);

    if ($matchedName !== null) {
        $reader->setLoadSheetsOnly([$matchedName]);
    }

    $spreadsheet = $reader->load($filePath);
    $sheet = $matchedName !== null ? $spreadsheet->getSheetByName($matchedName) : $spreadsheet->getActiveSheet();

    return ['spreadsheet' => $spreadsheet, 'sheet' => $sheet];
}

/**
 * Scans row $subRow/$topRow of $sheet and groups KPI columns by product,
 * exactly as the pre-optimization private controller method of the same
 * purpose did (moved here, unchanged logic, so it can be reused by the
 * dataset-cache builder without needing a controller instance). Accepts
 * either a loaded worksheet or a file path (in which case the workbook is
 * loaded via load_employees_sales_workbook() to also get optimization A).
 */
function employees_sales_get_groups_by_sub_label_triggers($sheetOrFilePath, $topRow = 1, $subRow = 2) {
    if (is_string($sheetOrFilePath)) {
        $loaded = load_employees_sales_workbook($sheetOrFilePath);
        $sheet = $loaded['sheet'];
    } else {
        $sheet = $sheetOrFilePath;
    }

    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestColumn());

    $groups = array();
    $currentGroupLabel = '';
    $currentGroup = array();

    for ($col = 0; $col < $highestColumnIndex; $col++) {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
        $rawSubLabel = trim((string) $sheet->getCell($colLetter . $subRow)->getValue());
        $subLabel = employees_sales_normalize_sub_label($rawSubLabel);

        if ($currentGroupLabel == '') {
            $currentGroupLabel = trim((string) $sheet->getCell($colLetter . $topRow)->getValue());
        }

        // A new group starts when we hit TGT, OR when a sub-label repeats within the
        // current group: a trailing group with no TGT of its own would otherwise
        // silently merge into and corrupt the previous, otherwise valid, group.
        $startsNewGroup = ($subLabel === 'TGT') || ($subLabel !== '' && isset($currentGroup[$subLabel]));

        if ($startsNewGroup) {
            if (!empty($currentGroup) && $currentGroupLabel !== '') {
                $groups[$currentGroupLabel] = $currentGroup;
            }
            $currentGroupLabel = trim((string) $sheet->getCell($colLetter . $topRow)->getValue());
            $currentGroup = array();
        }

        if ($subLabel !== '') {
            $currentGroup[$subLabel] = $colLetter;
        }
    }

    if (!empty($currentGroup)) {
        $groups[$currentGroupLabel] = $currentGroup;
    }

    return $groups;
}

/** Moved here unchanged from the controller so the KPI-parsing pipeline is testable standalone. */
function employees_sales_generate_fields_map($data) {
    $fieldsMap = [];
    foreach ($data as $top => $subs) {
        $fieldsMap[formatColName($top)] = $top;
    }
    return [$fieldsMap, array_flip($fieldsMap)];
}

/** Moved here unchanged from the controller so the KPI-parsing pipeline is testable standalone. */
function employees_sales_generate_tables($data) {
    $fieldsMap = employees_sales_generate_fields_map($data)[0];

    $table1 = [];
    $table2 = [];

    foreach ($data as $top => $subs) {
        // Classify by the actual semantic sub-headers present, not just the column count,
        // so a group with the right count but missing/extra fields is never misclassified.
        if (isset($subs['TGT'], $subs['Branch sales'], $subs['Digital Sales'], $subs['Total'], $subs['%']) && count($subs) === 5) {
            $table1[$fieldsMap[formatColName($top)]] = [
                'label' => $top,
                'tgt' => $subs['TGT'],
                'branch_sales' => $subs['Branch sales'],
                'digital_sales' => $subs['Digital Sales'],
                'total' => $subs['Total'],
                'percentage' => $subs['%']
            ];
        } else if (isset($subs['TGT'], $subs['Sales'], $subs['%']) && count($subs) === 3) {
            $table2[$fieldsMap[formatColName($top)]] = [
                'label' => $top,
                'tgt' => $subs['TGT'],
                'sales' => $subs['Sales'],
                'percentage' => $subs['%']
            ];
        }
        // else: unsupported KPI group shape - skipped, never merged into another group.
    }

    return [$table1, $table2];
}

/**
 * The raw value columns (tgt/branch_sales/digital_sales for table1,
 * tgt/sales for table2) actually read by the calculation logic - never the
 * derived Total/% columns, which the app always computes itself from the raw
 * figures and never reads directly (aside from one now-removed dead read -
 * see the implementation report). Used to decide what to pull into the
 * per-request dataset/cache.
 */
function employees_sales_needed_kpi_columns(array $table1Items, array $table2Items) {
    $cols = [];

    foreach ($table1Items as $item) {
        foreach (['tgt', 'branch_sales', 'digital_sales'] as $key) {
            if (!empty($item[$key])) {
                $cols[$item[$key]] = true;
            }
        }
    }

    foreach ($table2Items as $item) {
        foreach (['tgt', 'sales'] as $key) {
            if (!empty($item[$key])) {
                $cols[$item[$key]] = true;
            }
        }
    }

    return array_keys($cols);
}

/**
 * Single-pass extraction of everything the search/hierarchy/KPI-calculation
 * logic needs out of the "staff sales" worksheet, replacing the many repeated
 * full-sheet scans in the pre-optimization code. Built ONCE per request (or
 * loaded from cache - see employees_sales_cache_read()) and passed down
 * instead of the raw $sheet + $headerMap pair.
 *
 * A row with a blank/empty EMP ID (some real uploads carry a "Dummy" filler
 * row for a still-vacant position, keyed only by a Supervisor ID) is
 * deliberately never added to byId/bySupervisor: the pre-optimization code
 * scanned with PHP's loose `==`, under which a blank ID can accidentally
 * match other unrelated blank/zero cells elsewhere in the sheet and pull
 * their figures into a manager's totals. Excluding blank IDs from the index
 * prevents that cross-contamination; the row's raw cell values are still
 * kept in `rows` since the free-text employee search must still be able to
 * scan every row, blank ID or not.
 *
 * Returns:
 *   'rows'         => [ rowNumber => [ colLetter => rawCellValue, ... ], ... ]
 *   'byId'         => [ emp_id => entry ]  (first occurrence wins, matching
 *                      the early-return behaviour of the original linear scan)
 *   'bySupervisor' => [ supervisor_id => [ entry, entry, ... ] ]
 *   'highestRow'   => int
 * where entry = ['emp_id', 'supervisor_id', 'name', 'position', 'position_raw', 'row'].
 * `position` is trimmed (matches the original drill-down/lookup comparisons);
 * `position_raw` is not (matches the original get_direct_down_position()'s
 * raw position field, used by the Area Manager "== 'Sales Agent'" check).
 */
function build_employees_sales_dataset($sheet, array $headerMap, array $kpiColumns, $firstDataRow = 2) {
    $empIdCol = isset($headerMap['emp_id']) ? $headerMap['emp_id'] : 'A';
    $supervisorCol = isset($headerMap['supervisor_id']) ? $headerMap['supervisor_id'] : 'B';
    $nameCol = isset($headerMap['name']) ? $headerMap['name'] : 'D';
    $positionCol = isset($headerMap['position']) ? $headerMap['position'] : 'E';

    $allColumns = array_values(array_unique(array_merge(array_values($headerMap), $kpiColumns)));
    $highestRow = $sheet->getHighestDataRow();

    $rows = [];
    $byId = [];
    $bySupervisor = [];

    for ($row = $firstDataRow; $row <= $highestRow; $row++) {
        $rowData = [];
        foreach ($allColumns as $col) {
            $rowData[$col] = getCellValue($sheet, $col, $row);
        }
        $rows[$row] = $rowData;

        $empId = trim((string) ($rowData[$empIdCol] ?? ''));
        if ($empId === '') {
            continue;
        }

        $entry = [
            'emp_id' => $empId,
            'supervisor_id' => trim((string) ($rowData[$supervisorCol] ?? '')),
            'name' => trim((string) ($rowData[$nameCol] ?? '')),
            'position' => trim((string) ($rowData[$positionCol] ?? '')),
            'position_raw' => (string) ($rowData[$positionCol] ?? ''),
            'row' => $row,
        ];

        if (!isset($byId[$empId])) {
            $byId[$empId] = $entry;
        }

        if ($entry['supervisor_id'] !== '') {
            $bySupervisor[$entry['supervisor_id']][] = $entry;
        }
    }

    return [
        'rows' => $rows,
        'byId' => $byId,
        'bySupervisor' => $bySupervisor,
        'highestRow' => $highestRow,
    ];
}

/** Reads a cell value out of a dataset built by build_employees_sales_dataset(). */
function get_cell_from_dataset($dataset, $col, $row) {
    return $dataset['rows'][$row][$col] ?? null;
}

/** Index-based replacement for the pre-optimization get_direct_down_position(): O(1) instead of a full-sheet scan. */
function employees_sales_get_direct_down_position($dataset, $emp_id) {
    return $dataset['bySupervisor'][$emp_id] ?? [];
}

/**
 * Accumulates one row's KPI figures into $sales, exactly as the
 * pre-optimization getUserSales() did (same math, same field names), just
 * reading from the in-memory dataset instead of the live worksheet.
 */
function employees_sales_get_user_sales($dataset, $itemDetails, $row, $tableType, &$sales) {
    $tgt = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['tgt'], $row));

    if ($tableType == 1) {
        $branchSales = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['branch_sales'], $row));
        $digitalSales = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['digital_sales'], $row));
        $branchSales = is_numeric($branchSales) ? (float)$branchSales : 0;
        $digitalSales = is_numeric($digitalSales) ? (float)$digitalSales : 0;

        $total = $branchSales + $digitalSales;

        $sales['branch_sales'] = $branchSales + (isset($sales['branch_sales']) ? $sales['branch_sales'] : 0);
        $sales['digital_sales'] = $digitalSales + (isset($sales['digital_sales']) ? $sales['digital_sales'] : 0);
        $sales['total'] = $total + (isset($sales['total']) ? $sales['total'] : 0);
    } else if ($tableType == 2) {
        // Note: the pre-optimization code also read this row's "%" formula cell here and
        // discarded the result without ever storing it in $sales - a dead computation,
        // intentionally not reproduced (see the performance implementation report).
        $salesV = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['sales'], $row));
        $sales['sales'] = $salesV + (isset($sales['sales']) ? $sales['sales'] : 0);
    }

    $sales['tgt'] = $tgt + (isset($sales['tgt']) ? $sales['tgt'] : 0);
}

function employees_sales_get_sales_agent_sales($dataset, $itemDetails, $row, $tableType) {
    $sales = [];
    employees_sales_get_user_sales($dataset, $itemDetails, $row, $tableType, $sales);
    return $sales;
}

function employees_sales_get_branch_supervisor_sales($dataset, $itemDetails, $option, $emp_id, $tableType) {
    $sales = [];

    if ($option == 1 || $option == 2) {
        foreach (employees_sales_get_direct_down_position($dataset, $emp_id) as $direct) {
            employees_sales_get_user_sales($dataset, $itemDetails, $direct['row'], $tableType, $sales);
        }
    }

    if ($option == 2 || $option == 3) {
        if (isset($dataset['byId'][$emp_id])) {
            employees_sales_get_user_sales($dataset, $itemDetails, $dataset['byId'][$emp_id]['row'], $tableType, $sales);
        }
    }

    return $sales;
}

function employees_sales_get_branch_manager_sales($dataset, $itemDetails, $option, $emp_id, $tableType) {
    $sales = [];

    if ($option == 1 || $option == 2) {
        foreach (employees_sales_get_direct_down_position($dataset, $emp_id) as $direct) {
            if ($option == 2) {
                employees_sales_get_user_sales($dataset, $itemDetails, $direct['row'], $tableType, $sales);
            }

            foreach (employees_sales_get_direct_down_position($dataset, $direct['emp_id']) as $salesAgent) {
                employees_sales_get_user_sales($dataset, $itemDetails, $salesAgent['row'], $tableType, $sales);
            }
        }
    }

    if ($option == 3) {
        if (isset($dataset['byId'][$emp_id])) {
            employees_sales_get_user_sales($dataset, $itemDetails, $dataset['byId'][$emp_id]['row'], $tableType, $sales);
        }
    }

    return $sales;
}

function employees_sales_get_area_manager_sales($dataset, $itemDetails, $option, $emp_id, $tableType, $row) {
    $sales = [];

    if ($option == 3) {
        employees_sales_get_user_sales($dataset, $itemDetails, $row, $tableType, $sales);
        return $sales;
    }

    $directDownEmployees = employees_sales_get_direct_down_position($dataset, $emp_id);
    $secondLevelEmployees = [];
    $thirdLevelEmployees = [];

    foreach ($directDownEmployees as $directDownEmployee) {
        if ($option == 2) {
            employees_sales_get_user_sales($dataset, $itemDetails, $directDownEmployee['row'], $tableType, $sales);
        }

        $secondLevelEmployees = array_merge($secondLevelEmployees, employees_sales_get_direct_down_position($dataset, $directDownEmployee['emp_id']));
    }

    foreach ($secondLevelEmployees as $secondLevelEmployee) {
        if ($option == 2 || $secondLevelEmployee['position_raw'] == 'Sales Agent') {
            employees_sales_get_user_sales($dataset, $itemDetails, $secondLevelEmployee['row'], $tableType, $sales);
        }

        $thirdLevelEmployees = array_merge($thirdLevelEmployees, employees_sales_get_direct_down_position($dataset, $secondLevelEmployee['emp_id']));
    }

    if ($option == 2 || $option == 1) {
        foreach ($thirdLevelEmployees as $thirdLevelEmployee) {
            employees_sales_get_user_sales($dataset, $itemDetails, $thirdLevelEmployee['row'], $tableType, $sales);
        }
    }

    return $sales;
}

/** Dispatches by canonical position, exactly like the pre-optimization getSalesByOption(). */
function employees_sales_get_sales_by_option($dataset, $itemDetails, $option, $emp_id, $emp_position, $tableType, $row) {
    $canonicalPosition = resolve_canonical_position($emp_position);

    if ($canonicalPosition === "Sales Agent") {
        return employees_sales_get_sales_agent_sales($dataset, $itemDetails, $row, $tableType);
    } else if ($canonicalPosition === "Branch Supervisor") {
        return employees_sales_get_branch_supervisor_sales($dataset, $itemDetails, $option, $emp_id, $tableType);
    } else if ($canonicalPosition === 'Branch Manager') {
        return employees_sales_get_branch_manager_sales($dataset, $itemDetails, $option, $emp_id, $tableType);
    } else if ($canonicalPosition === 'Area Manager') {
        // Covers both a literal "Area Manager" and its confirmed alias
        // "Professional Area Sales Manager" - same calculation, same options.
        return employees_sales_get_area_manager_sales($dataset, $itemDetails, $option, $emp_id, $tableType, $row);
    }

    return null;
}

function employees_sales_extract_table1_field_data($dataset, $row, $fieldKey, $tableItems) {
    if (!isset($tableItems[$fieldKey])) {
        return null;
    }

    $itemDetails = $tableItems[$fieldKey];

    $tgt = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['tgt'], $row));
    $branchSales = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['branch_sales'], $row));
    $digitalSales = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['digital_sales'], $row));

    $total = $branchSales + $digitalSales;
    $remaining = $tgt - $total;

    $percentage = 0;
    if ($tgt > 0) {
        $percentage = round(($total / $tgt) * 100, 0);
    } elseif ($total > 0) {
        $percentage = 100;
    }

    return [
        'tgt' => $tgt,
        'branch_sales' => $branchSales,
        'digital_sales' => $digitalSales,
        'total' => $total,
        'remaining' => $remaining,
        'percentage' => $percentage . '%'
    ];
}

function employees_sales_extract_table2_field_data($dataset, $row, $fieldKey, $tableItems) {
    if (!isset($tableItems[$fieldKey])) {
        return null;
    }

    $itemDetails = $tableItems[$fieldKey];

    $tgt = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['tgt'], $row));
    $sales = getNumericValue(get_cell_from_dataset($dataset, $itemDetails['sales'], $row));

    $remaining = $tgt - $sales;

    $percentage = 0;
    if ($tgt > 0) {
        $percentage = round(($sales / $tgt) * 100, 0);
    } elseif ($sales > 0) {
        $percentage = 100;
    }

    return [
        'tgt' => $tgt,
        'sales' => $sales,
        'remaining' => $remaining,
        'percentage' => $percentage . '%'
    ];
}

function employees_sales_extract_field_data($dataset, $row, $fieldKey, $tableType, $tables) {
    if ($tableType === 'table1') {
        return employees_sales_extract_table1_field_data($dataset, $row, $fieldKey, $tables[0]);
    }

    return employees_sales_extract_table2_field_data($dataset, $row, $fieldKey, $tables[1]);
}

/**
 * Index-based replacement for the pre-optimization findAllSubordinatesRecursive():
 * walks $dataset['bySupervisor'] instead of re-scanning the whole sheet at
 * every recursion level (the single most scan-heavy path in the feature -
 * see the performance analysis report, section 5.2).
 */
function employees_sales_find_all_subordinates_recursive($dataset, $managerId, $fieldKey, $tableType, $tables, $currentPosition, $option, array &$visited = []) {
    // Cycle guard: a couple of real uploaded files contain a supervisor_id chain that loops
    // back on itself (e.g. a placeholder row whose emp_id equals its own supervisor_id, or
    // two placeholder rows referencing each other). A proper hierarchy is a tree, so any
    // manager ID reached twice in one traversal means a cycle, not a legitimate second path -
    // recursing into it again would never terminate. This never triggers on real (acyclic)
    // hierarchy data, confirmed by exhaustive parity testing against the pre-optimization
    // algorithm (see EMPLOYEES_SALES_PERFORMANCE_IMPLEMENTATION_REPORT.md).
    if (isset($visited[$managerId])) {
        return [];
    }
    $visited[$managerId] = true;

    $allSubordinates = [];
    $grades = ['Area Manager', 'Branch Manager', 'Branch Supervisor', 'Sales Agent'];

    $empData = $dataset['byId'][$managerId] ?? null;

    if ($empData && (($option == 3) || ($option == 2 && $empData['position'] == $grades[2] && $currentPosition == $empData['position']))) {
        $fieldData = employees_sales_extract_field_data($dataset, $empData['row'], $fieldKey, $tableType, $tables);
        $allSubordinates[] = array_merge(
            ['name' => $empData['name'], 'position' => $empData['position'], 'emp_id' => $managerId],
            $fieldData
        );
    }

    if ($option == 3) {
        return $allSubordinates;
    }

    foreach (($dataset['bySupervisor'][$managerId] ?? []) as $child) {
        $empId = $child['emp_id'];
        $name = $child['name'];
        $position = $child['position'];
        $row = $child['row'];

        $fieldData = employees_sales_extract_field_data($dataset, $row, $fieldKey, $tableType, $tables);

        if ($option == 2 && $position != $currentPosition) {
            if ($fieldData) {
                $allSubordinates[] = array_merge(['name' => $name, 'position' => $position, 'emp_id' => $empId], $fieldData);
            }
        }

        if ($option == 1 && $position == $grades[3]) {
            if ($fieldData) {
                $allSubordinates[] = array_merge(['name' => $name, 'position' => $position, 'emp_id' => $empId], $fieldData);
            }
        }

        $subSubordinates = employees_sales_find_all_subordinates_recursive($dataset, $empId, $fieldKey, $tableType, $tables, $currentPosition, $option, $visited);
        $allSubordinates = array_merge($allSubordinates, $subSubordinates);
    }

    return $allSubordinates;
}

// =============================================================================
// File cache: avoids re-parsing the Excel file on every search/drill-down
// request against the same active upload. Tied strictly to the uploaded
// file's own physical path (already unique per upload) plus its mtime/size,
// so a different uploaded file, a different historical file, or the same
// path being overwritten on disk can never serve another file's cached data.
// =============================================================================

function employees_sales_cache_dir() {
    return APPPATH . 'cache/employees_sales/';
}

function employees_sales_cache_file_path($filePath) {
    return employees_sales_cache_dir() . sha1($filePath) . '.cache';
}

/**
 * Returns the cached ['headerMap','table1Items','table2Items','dataset']
 * payload for $filePath, or null on any cache miss/mismatch/corruption -
 * callers must treat null as "rebuild from the Excel file", never as an error.
 */
function employees_sales_cache_read($filePath) {
    if (!is_file($filePath)) {
        return null;
    }

    $cacheFile = employees_sales_cache_file_path($filePath);
    if (!is_file($cacheFile)) {
        return null;
    }

    $raw = @file_get_contents($cacheFile);
    if ($raw === false || $raw === '') {
        return null;
    }

    $payload = @unserialize($raw, ['allowed_classes' => false]);
    if (!is_array($payload)) {
        return null;
    }

    if (!isset($payload['_meta']['filepath'], $payload['_meta']['mtime'], $payload['_meta']['size'])) {
        return null;
    }

    // Strict path match: a sha1 collision or a stale file left behind must never be trusted.
    if ($payload['_meta']['filepath'] !== $filePath) {
        return null;
    }

    // The active upload's storage filename is already unique per upload, but this guards
    // against the same path being overwritten on disk by something outside the app too.
    if ($payload['_meta']['mtime'] !== filemtime($filePath) || $payload['_meta']['size'] !== filesize($filePath)) {
        return null;
    }

    if (!isset($payload['headerMap'], $payload['table1Items'], $payload['table2Items'], $payload['dataset'])) {
        return null;
    }

    return $payload;
}

/**
 * Best-effort atomic cache write: never fatal if it fails (disk full,
 * read-only filesystem, race with another request) - caching is purely an
 * optimization, and a request must succeed with or without it.
 */
function employees_sales_cache_write($filePath, array $payload) {
    if (!is_file($filePath)) {
        return false;
    }

    $dir = employees_sales_cache_dir();
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    $payload['_meta'] = [
        'filepath' => $filePath,
        'mtime' => filemtime($filePath),
        'size' => filesize($filePath),
        'built_at' => time(),
    ];

    $serialized = @serialize($payload);
    if ($serialized === false) {
        return false;
    }

    $cacheFile = employees_sales_cache_file_path($filePath);
    $tmpFile = $cacheFile . '.' . uniqid('', true) . '.tmp';

    if (@file_put_contents($tmpFile, $serialized, LOCK_EX) === false) {
        @unlink($tmpFile);
        return false;
    }

    if (!@rename($tmpFile, $cacheFile)) {
        @unlink($tmpFile);
        return false;
    }

    return true;
}

/**
 * Single entry point for search/drill-down/upload-warm requests: returns the
 * ['headerMap','table1Items','table2Items','dataset'] payload for $filePath,
 * from cache when available and valid, otherwise built fresh (optimization A
 * selective load + optimization B dataset/index build) and cached for next
 * time (optimization C). Callers never need to touch IOFactory/Spreadsheet
 * objects directly for search or drill-down.
 */
function load_employees_sales_data($filePath) {
    $cached = employees_sales_cache_read($filePath);
    if ($cached !== null) {
        return $cached;
    }

    $loaded = load_employees_sales_workbook($filePath);
    $sheet = $loaded['sheet'];

    $headerMap = build_employee_header_map($sheet);
    $groups = employees_sales_get_groups_by_sub_label_triggers($sheet);
    list($table1Items, $table2Items) = employees_sales_generate_tables($groups);

    $kpiColumns = employees_sales_needed_kpi_columns($table1Items, $table2Items);
    $dataset = build_employees_sales_dataset($sheet, $headerMap, $kpiColumns);

    $loaded['spreadsheet']->disconnectWorksheets();

    $payload = [
        'headerMap' => $headerMap,
        'table1Items' => $table1Items,
        'table2Items' => $table2Items,
        'dataset' => $dataset,
    ];

    employees_sales_cache_write($filePath, $payload);

    return $payload;
}

?>

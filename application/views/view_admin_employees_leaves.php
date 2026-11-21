<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar'); ?>
<?php
$escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$url = base_url() . MOD_VALUE . 'admin/employees_leaves';
$latest_month = isset($latest_month) ? $latest_month : date('Y-m');
$limit_year = isset($limit_year) ? (int) $limit_year : (int) substr($month, 0, 4);
$month_limits = isset($month_limits) ? $month_limits : array();
$month_url = $url . '?month=' . $month;
$employee_names = array_column($employees, 'user_fillname', 'user_id');
$month_start = $month . '-01';
$month_end = date('Y-m-t', strtotime($month_start));
$day_count = (int) substr($month_end, 8, 2);
$month_names = array(1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر');
$month_label = $month_names[(int) substr($month, 5, 2)] . ' ' . substr($month, 0, 4);
$leaves = array_values(array_filter($leaves, function ($leave) use ($month_start, $month_end) {
    return $leave['start_date'] <= $month_end && $leave['end_date'] >= $month_start;
}));
$calendar = array();
foreach ($employees as $employee) {
    $days = array();
    foreach ($leaves as $leave) {
        if (!$leave['all_employees'] && !in_array((int) $employee['user_id'], $leave['employee_ids'], true)) continue;
        $start = max($month_start, $leave['start_date']);
        $end = min($month_end, $leave['end_date']);
        for ($day = (int) substr($start, 8, 2); $day <= (int) substr($end, 8, 2); $day++) $days[$day][] = $leave;
    }
    if ($days) $calendar[] = array('employee' => $employee, 'days' => $days);
}
?>
<link rel="stylesheet" href="<?=base_url('public/css/admin-ui-pattern.css')?>">
<link rel="stylesheet" href="<?=base_url('public/css/employees_leaves.css')?>?v=10">
<div class="app-content admin-ui-page leaves-page" dir="rtl">
  <header class="leaves-heading">
    <div><span class="leaves-eyebrow">تنظيم الدوام</span><h1>إجازات الموظفين</h1><p>خطّط للإجازات، وستظهر تلقائيًا في جداول الموظفين.</p></div>
    <div class="leaves-heading-actions">
      <?php if ($can_create): ?>
        <button type="button" class="leaves-primary leaves-add-button" data-bs-toggle="modal" data-bs-target="#leave-modal"><i class="bi bi-plus-lg" aria-hidden="true"></i> إضافة إجازة</button>
      <?php endif; ?>
      <?php if ($can_view_timetable): ?>
      <a class="leaves-secondary" href="<?=base_url().MOD_VALUE.'admin/employees_timetable'?>"><i class="bi bi-calendar-week" aria-hidden="true"></i> جداول الموظفين</a>
      <?php endif; ?>
    </div>
  </header>
  <?php if ($message = $this->session->flashdata('leave_success')): ?>
    <div class="leaves-message" role="status"><?=$escape($message)?></div>
  <?php endif; ?>
  <section class="leaves-panel leaves-month-toolbar">
    <form method="get" action="<?=$escape($url)?>" class="leaves-month-filter">
      <input type="hidden" name="month" id="leave-month-value" value="<?=$escape($month)?>">
      <div><label for="leave-year">السنة</label><select id="leave-year" data-latest-month="<?=$escape($latest_month)?>">
        <?php for ($year = 2024; $year <= (int) substr($latest_month, 0, 4); $year++): ?><option value="<?=$year?>" <?=$year === (int) substr($month, 0, 4) ? 'selected' : ''?>><?=$year?></option><?php endfor; ?>
      </select></div>
      <div><label for="leave-month">الشهر</label><select id="leave-month">
        <?php foreach ($month_names as $number => $label): if (sprintf('%s-%02d', substr($month, 0, 4), $number) > $latest_month) continue; ?><option value="<?=$number?>" <?=$number === (int) substr($month, 5, 2) ? 'selected' : ''?>><?=$escape($label)?></option><?php endforeach; ?>
      </select></div>
      <noscript><button type="submit" class="leaves-secondary">عرض الشهر</button></noscript>
    </form>
    <button type="button" class="leaves-secondary" data-bs-toggle="modal" data-bs-target="#leave-limit-modal"><i class="bi bi-people" aria-hidden="true"></i> عدد الموظفين المسموح لهم بالإجازة</button>
  </section>
  <div class="modal fade leaves-page leaves-modal" id="leave-limit-modal" tabindex="-1" aria-labelledby="leave-limit-title" aria-hidden="true" dir="rtl">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content leaves-panel">
      <div class="leaves-modal-heading"><h2 id="leave-limit-title">عدد الموظفين المسموح لهم بالإجازة</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
      <div class="modal-body">
      <?php if ($limit_error): ?><div class="leaves-error" id="leave-limit-error" role="alert"><?=$escape($limit_error)?></div><?php endif; ?>
      <?=form_open($month_url, array('class' => 'leaves-limit-form', 'id' => 'leave-limit-form'))?>
        <input type="hidden" name="action" value="save_limit">
        <label for="limit-year">السنة</label>
        <select id="limit-year" name="limit_year"><?php for ($year = 2024; $year <= (int) substr($latest_month, 0, 4) + 1; $year++): ?><option value="<?=$year?>" <?=$year === $limit_year ? 'selected' : ''?>><?=$year?></option><?php endfor; ?></select>
        <p class="leaves-hint">حدد عدد الموظفين المسموح لهم بالإجازة بجوار كل شهر، ثم احفظ أعداد السنة.</p>
        <div class="leaves-year-limits" role="group" aria-label="عدد الموظفين المسموح لكل شهر">
          <?php foreach ($month_names as $number => $label):
            $limit_key = sprintf('%04d-%02d', $limit_year, $number);
            $limit_value = isset($limit_values[$number]) && is_scalar($limit_values[$number]) ? $limit_values[$number] : ($month_limits[$limit_key] ?? count($employees));
          ?>
          <div class="leaves-month-limit-row">
            <label for="employee-limit-<?=$number?>"><?=$escape($label)?></label>
            <input type="number" id="employee-limit-<?=$number?>" name="employee_limits[<?=$number?>]" data-limit-month="<?=$number?>" min="0" max="2147483647" step="1" required value="<?=$escape($limit_value)?>" aria-label="عدد الموظفين المسموح لهم بالإجازة في <?=$escape($label)?>" <?=$can_set_limit ? '' : 'disabled'?>>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="leaves-modal-actions"><?php if ($can_set_limit): ?><button class="leaves-primary" type="submit">حفظ أعداد السنة</button><?php endif; ?><button type="button" class="leaves-secondary" data-bs-dismiss="modal">إغلاق</button></div>
      <?=form_close()?>
      </div>
      <script type="application/json" id="leave-month-limits"><?=json_encode((object)$month_limits, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)?></script>
      <span id="leave-default-limit" data-count="<?=count($employees)?>" hidden></span>
    </div></div>
  </div>
  <div class="leaves-layout">
    <?php if ($can_create): ?>
    <div class="modal fade leaves-page leaves-modal" id="leave-modal" tabindex="-1" aria-labelledby="leave-modal-title" aria-hidden="true" dir="rtl">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content leaves-panel">
          <div class="leaves-panel-title leaves-modal-heading"><span class="leaves-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span><div><h2 id="leave-modal-title">إضافة إجازة</h2><p>تفاصيل بسيطة، وجدول أوضح.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
          <div class="modal-body">
      <?php if ($error): ?><div class="leaves-error" role="alert" tabindex="-1" id="leave-error"><?=$escape($error)?></div><?php endif; ?>
      <?=form_open($month_url, array('id' => 'leave-form'))?>
        <?php if ($capacity_warnings): ?>
        <div class="leaves-capacity-warning" id="leave-capacity-warning" role="alert" tabindex="-1">
          <strong>تم تجاوز العدد المسموح بالإجازة</strong>
          <?php foreach ($capacity_warnings as $warning): ?>
          <p>شهر <?=$escape($warning['month'])?>: سيصبح العدد <?=(int)$warning['count']?> موظف، والمسموح <?=(int)$warning['limit']?>.</p>
          <?php endforeach; ?>
          <p>اضغط «استمرار وحفظ الإجازة» للموافقة، أو «إلغاء الطلب» لإلغاء الإضافة.</p>
        </div>
        <input type="hidden" name="confirmation_token" id="leave-confirmation-token" value="<?=$escape($confirmation_token)?>">
        <?php endif; ?>
        <fieldset class="leaves-type-options"><legend>نوع الإجازة</legend>
          <div class="leaves-type-cards">
            <label><input type="radio" name="leave_type" value="annual" <?=($values['leave_type'] ?? '') === 'annual' ? 'checked' : ''?> required aria-controls="leave-name-field"><i class="bi bi-calendar-check" aria-hidden="true"></i><span>إجازة سنوية</span></label>
            <label><input type="radio" name="leave_type" value="occasion" <?=($values['leave_type'] ?? '') === 'occasion' ? 'checked' : ''?> required aria-controls="leave-name-field"><i class="bi bi-gift" aria-hidden="true"></i><span>إجازة مناسبات</span></label>
          </div>
        </fieldset>
        <div id="leave-name-field" <?=empty($values['leave_type']) ? 'hidden' : ''?>>
          <label for="leave-name">اسم الإجازة</label>
          <input id="leave-name" name="name" required maxlength="150" value="<?=$escape($values['name'])?>" placeholder="مثال: عيد الفطر، اليوم الوطني" <?=empty($values['leave_type']) ? 'disabled' : ''?>>
        </div>
        <label for="leave-color">لون مربع الإجازة</label>
        <input type="color" id="leave-color" name="color" value="<?=$escape($values['color'] ?? '#151515')?>" required>
        <div class="leaves-dates">
          <div><label for="leave-start">من يوم</label><input type="date" id="leave-start" name="start_date" required value="<?=$escape($values['start_date'])?>"></div>
          <div><label for="leave-end">إلى يوم</label><input type="date" id="leave-end" name="end_date" required value="<?=$escape($values['end_date'])?>"></div>
        </div>
        <p class="leaves-hint" id="leave-duration" aria-live="polite">تشمل الإجازة يوم البداية ويوم النهاية.</p>
        <label class="leaves-all" for="leave-all"><input type="checkbox" id="leave-all" name="all_employees" value="1" <?=$values['all_employees'] ? 'checked' : ''?> aria-controls="leave-employees"><span><strong>كل الموظفين</strong><small>تطبيق الإجازة على جميع الموظفين</small></span><i class="bi bi-people" aria-hidden="true"></i></label>
        <fieldset id="leave-employees" <?=$values['all_employees'] ? 'hidden disabled' : ''?>>
          <legend>حدد الموظفين</legend>
          <label for="leave-search" class="leaves-hint">ابحث واختر موظفًا أو أكثر</label>
          <input type="search" id="leave-search" placeholder="ابحث باسم الموظف…" autocomplete="off">
          <div class="leaves-selection-count" id="leave-selected" aria-live="polite"></div>
          <div class="leaves-employee-list">
            <?php foreach ($employees as $employee): ?>
              <label class="leaves-employee"><input type="checkbox" name="employee_ids[]" value="<?=(int)$employee['user_id']?>" <?=in_array((int)$employee['user_id'], $values['employee_ids'], true) ? 'checked' : ''?>><span><?=$escape($employee['user_fillname'])?></span></label>
            <?php endforeach; ?>
          </div>
          <p id="leave-no-results" class="leaves-hint" hidden>لا يوجد موظفون بهذا الاسم.</p>
        </fieldset>
        <div class="leaves-preview"><i class="bi bi-info-circle" aria-hidden="true"></i><span>ستظهر الأيام المحددة في الجدول باسم الإجازة، ولن تُحتسب ضمن ورديات AM أو PM.</span></div>
        <div class="leaves-modal-actions">
          <button class="leaves-primary" id="leave-save" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> <?=$capacity_warnings ? 'استمرار وحفظ الإجازة' : 'حفظ الإجازة'?></button>
          <a class="leaves-secondary" href="<?=$escape($month_url)?>">إلغاء الطلب</a>
        </div>
      <?=form_close()?>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($can_delete): ?>
    <div class="modal fade leaves-page leaves-modal leaves-confirm-modal" id="leave-delete-modal" tabindex="-1" aria-labelledby="leave-delete-title" aria-describedby="leave-delete-text" aria-hidden="true" dir="rtl">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content leaves-panel">
          <span class="leaves-confirm-icon"><i class="bi bi-trash3" aria-hidden="true"></i></span>
          <h2 id="leave-delete-title">حذف الإجازة؟</h2>
          <p class="leaves-confirm-name" id="leave-delete-name"></p>
          <p class="leaves-confirm-text" id="leave-delete-text">سيعود عرض الدوام الأصلي في أيام هذه الإجازة، ولا يمكن التراجع عن الحذف.</p>
          <div class="leaves-modal-actions">
            <button type="button" class="leaves-danger" id="leave-delete-confirm"><i class="bi bi-trash3" aria-hidden="true"></i> نعم، احذف</button>
            <button type="button" class="leaves-secondary" data-bs-dismiss="modal">إلغاء</button>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
    <section class="leaves-panel leaves-records">
      <div class="leaves-panel-title"><span class="leaves-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span><div><h2>إجازات <?=$escape($month_label)?></h2><p><?=count($calendar)?> موظف لديهم إجازات هذا الشهر — المسموح <?=(int)$employee_limit?></p></div></div>
      <div class="leaves-calendar-scroll" role="region" aria-label="جدول إجازات الشهر" tabindex="0">
      <table class="leaves-calendar" style="--leave-calendar-width: <?=$day_count * 32 + 340?>px">
        <caption>إجازات الموظفين لشهر <?=$escape($month_label)?></caption>
        <colgroup>
          <col class="leave-name-column">
          <col class="leave-number-column">
          <col span="<?=$day_count?>" class="leave-day-column">
          <col class="leave-total-column">
        </colgroup>
        <thead>
          <tr><th rowspan="2" class="employee-name">اسم الموظف</th><th rowspan="2">الرقم الوظيفي</th><th colspan="<?=$day_count?>"><?=$escape($month_label)?></th><th rowspan="2" class="leave-total">إجمالي الأيام</th></tr>
          <tr><?php for ($day = 1; $day <= $day_count; $day++):
            $date = sprintf('%s-%02d', $month, $day);
            $weekend = in_array((int) date('w', strtotime($date)), array(5, 6), true);
          ?><th scope="col" class="<?=$weekend ? 'is-weekend' : ''?>"><?=$day?></th><?php endfor; ?></tr>
        </thead>
        <tbody>
        <?php foreach ($calendar as $row): $employee = $row['employee']; ?>
          <tr><th scope="row" class="employee-name"><?=$escape($employee['user_fillname'])?></th><td><?=$escape(isset($employee['job_number']) && trim((string) $employee['job_number']) !== '' ? $employee['job_number'] : '—')?></td>
          <?php for ($day = 1; $day <= $day_count; $day++):
            $date = sprintf('%s-%02d', $month, $day);
            $weekend = in_array((int) date('w', strtotime($date)), array(5, 6), true);
            $day_leaves = $row['days'][$day] ?? array();
            $details = array();
            $colors = array();
            foreach ($day_leaves as $day_leave) {
                $details[] = $day_leave['name'] . ' — من ' . $day_leave['start_date'] . ' إلى ' . $day_leave['end_date'];
                $color = $day_leave['color'] ?? '#151515';
                $colors[] = preg_match('/^#[0-9a-fA-F]{6}$/D', $color) ? $color : '#151515';
            }
            $details = implode("\n", array_unique($details));
            $colors = array_values(array_unique($colors));
            $background = count($colors) > 1 ? 'linear-gradient(135deg, ' . implode(', ', $colors) . ')' : ($colors[0] ?? '');
          ?><td class="leave-day <?=$details !== '' ? 'is-leave' : ($weekend ? 'is-weekend' : '')?>" title="<?=$escape($details !== '' ? $details : ($weekend ? 'عطلة أسبوعية' : $date))?>" <?php if ($details !== ''): ?>tabindex="0" role="button" data-leave-details="<?=$escape($details)?>" style="background:<?=$escape($background)?>"<?php endif; ?> aria-label="<?=$escape($date . ': ' . ($details !== '' ? $details : ($weekend ? 'عطلة أسبوعية' : 'لا توجد إجازة')))?>"></td><?php endfor; ?>
          <td class="leave-total"><?=count($row['days'])?></td></tr>
        <?php endforeach; ?>
        <?php if (!$calendar): ?><tr><td colspan="<?=$day_count + 3?>" class="leaves-empty">لا توجد إجازات مسجلة خلال هذا الشهر.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
      <?php if ($can_delete && $leaves): ?>
      <details class="leaves-manage-records"><summary>إدارة إجازات الشهر (<?=count($leaves)?>)</summary>
        <div class="leaves-calendar-scroll"><table class="leaves-management-table" dir="rtl"><thead><tr><th>اسم الموظف</th><th>اسم الإجازة</th><th>النوع</th><th>من</th><th>إلى</th><th>إجراء</th></tr></thead><tbody>
        <?php foreach ($leaves as $leave):
          $names = array();
          foreach ($leave['employee_ids'] as $id) $names[] = isset($employee_names[$id]) ? $employee_names[$id] : 'موظف محذوف';
        ?><tr><td><?=$leave['all_employees'] ? 'كل الموظفين' : $escape(implode('، ', $names))?></td><td><?=$escape($leave['name'])?></td><td><?=($leave['leave_type'] ?? 'annual') === 'occasion' ? 'مناسبات' : 'سنوية'?></td><td><?=$escape($leave['start_date'])?></td><td><?=$escape($leave['end_date'])?></td><td>
          <?=form_open($url.'/delete', array('class' => 'leave-delete-form'))?>
          <input type="hidden" name="month" value="<?=$escape($month)?>">
          <input type="hidden" name="leave_id" value="<?=(int)$leave['id']?>" data-leave-name="<?=$escape($leave['name'])?>">
          <button type="submit" class="leaves-delete" title="حذف" aria-label="حذف"><i class="bi bi-trash3" aria-hidden="true"></i></button>
          <?=form_close()?>
        </td></tr><?php endforeach; ?>
        </tbody></table></div>
      </details>
      <?php endif; ?>
    </section>
  </div>
</div>
</div>
<script src="<?=base_url('public/js/employees_leaves.js')?>?v=8"></script>
<script src="<?=base_url('public/js/script.js')?>"></script>

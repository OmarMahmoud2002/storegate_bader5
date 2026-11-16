<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar'); ?>
<?php
$escape = function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
$url = base_url() . MOD_VALUE . 'admin/employees_leaves';
$employee_names = array_column($employees, 'user_fillname', 'user_id');
$today = date('Y-m-d');
$active_count = count(array_filter($leaves, function ($leave) use ($today) { return $leave['start_date'] <= $today && $leave['end_date'] >= $today; }));
?>
<link rel="stylesheet" href="<?=base_url('public/css/employees_leaves.css')?>?v=3">
<div class="app-content admin-ui-page leaves-page" dir="rtl">
  <header class="leaves-heading">
    <div><span class="leaves-eyebrow">تنظيم الدوام</span><h1>إجازات الموظفين</h1><p>خطّط للإجازات، وستظهر تلقائيًا في جداول الموظفين.</p></div>
    <div class="leaves-heading-actions">
      <?php if ($can_manage): ?>
        <button type="button" class="leaves-primary leaves-add-button" data-bs-toggle="modal" data-bs-target="#leave-modal"><i class="bi bi-plus-lg" aria-hidden="true"></i> إضافة إجازة</button>
      <?php endif; ?>
      <a class="leaves-secondary" href="<?=base_url().MOD_VALUE.'admin/employees_timetable'?>"><i class="bi bi-calendar-week" aria-hidden="true"></i> جداول الموظفين</a>
    </div>
  </header>
  <?php if ($message = $this->session->flashdata('leave_success')): ?>
    <div class="leaves-message" role="status"><?=$escape($message)?></div>
  <?php endif; ?>
  <section class="leaves-stats" aria-label="ملخص الإجازات">
    <div><i class="bi bi-calendar2-check" aria-hidden="true"></i><span>الإجازات المسجلة<strong><?=count($leaves)?></strong></span></div>
    <div><i class="bi bi-sun" aria-hidden="true"></i><span>إجازات سارية اليوم<strong><?=$active_count?></strong></span></div>
    <div><i class="bi bi-people" aria-hidden="true"></i><span>الموظفون<strong><?=count($employees)?></strong></span></div>
  </section>
  <div class="leaves-layout">
    <?php if ($can_manage): ?>
    <div class="modal fade leaves-page leaves-modal" id="leave-modal" tabindex="-1" aria-labelledby="leave-modal-title" aria-hidden="true" dir="rtl">
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content leaves-panel">
          <div class="leaves-panel-title leaves-modal-heading"><span class="leaves-icon"><i class="bi bi-plus-lg" aria-hidden="true"></i></span><div><h2 id="leave-modal-title">إضافة إجازة</h2><p>تفاصيل بسيطة، وجدول أوضح.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
          <div class="modal-body">
      <?php if ($error): ?><div class="leaves-error" role="alert" tabindex="-1" id="leave-error"><?=$escape($error)?></div><?php endif; ?>
      <?=form_open($url, array('id' => 'leave-form'))?>
        <label for="leave-name">اسم الإجازة</label>
        <input id="leave-name" name="name" required maxlength="150" value="<?=$escape($values['name'])?>" placeholder="مثال: عيد الفطر، اليوم الوطني">
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
          <button class="leaves-primary" id="leave-save" type="submit"><i class="bi bi-check2-circle" aria-hidden="true"></i> حفظ الإجازة</button>
          <button class="leaves-secondary" type="button" data-bs-dismiss="modal">إلغاء</button>
        </div>
      <?=form_close()?>
          </div>
        </div>
      </div>
    </div>
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
      <div class="leaves-panel-title"><span class="leaves-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></span><div><h2>سجل الإجازات</h2><p>كل الإجازات ومَن تشملهم، في مكان واحد.</p></div></div>
      <?php if (empty($leaves)): ?>
        <div class="leaves-empty"><i class="bi bi-calendar2-plus" aria-hidden="true"></i><h3>أول إجازة تبدأ من هنا</h3><p>عند تسجيل إجازة ستجد تفاصيلها هنا، وتظهر أيامها مباشرة في جداول الموظفين.</p></div>
      <?php else: foreach ($leaves as $leave):
        $state = $leave['end_date'] < $today ? 'past' : ($leave['start_date'] > $today ? 'upcoming' : 'active');
        $state_label = array('past' => 'انتهت', 'upcoming' => 'قادمة', 'active' => 'سارية الآن');
        $names = array();
        foreach ($leave['employee_ids'] as $id) $names[] = isset($employee_names[$id]) ? $employee_names[$id] : 'موظف محذوف';
        $duration = (new DateTime($leave['start_date']))->diff(new DateTime($leave['end_date']))->days + 1;
      ?>
        <article class="leaves-record">
          <div class="leaves-record-heading"><h3><?=$escape($leave['name'])?></h3><span class="leaves-badge <?=$state?>"><?=$state_label[$state]?></span></div>
          <p class="leaves-range"><i class="bi bi-calendar-range" aria-hidden="true"></i><time><?=$escape($leave['start_date'])?></time><span>إلى</span><time><?=$escape($leave['end_date'])?></time><span class="leaves-days"><?=$duration?> يوم</span></p>
          <div class="leaves-record-footer"><p><i class="bi bi-people" aria-hidden="true"></i> <?=$leave['all_employees'] ? 'كل الموظفين' : $escape(implode('، ', $names))?></p>
          <?php if ($can_manage): ?>
            <?=form_open($url.'/delete', array('class' => 'leave-delete-form'))?>
              <input type="hidden" name="leave_id" value="<?=(int)$leave['id']?>" data-leave-name="<?=$escape($leave['name'])?>">
              <button type="submit" class="leaves-delete" aria-label="<?=$escape('حذف إجازة '.$leave['name'])?>"><i class="bi bi-trash3" aria-hidden="true"></i> حذف</button>
            <?=form_close()?>
          <?php endif; ?></div>
        </article>
      <?php endforeach; endif; ?>
    </section>
  </div>
</div>
</div>
<script src="<?=base_url('public/js/employees_leaves.js')?>?v=3"></script>
<script src="<?=base_url('public/js/script.js')?>"></script>

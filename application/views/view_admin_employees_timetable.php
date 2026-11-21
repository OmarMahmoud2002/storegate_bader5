<title>جدول الدوام الأسبوعي</title>
<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar');?>

<?php 
$CI =& get_instance();
$can_show_supervisors_timetable = $CI->get_permission_enabled('employees_timetable.supervisors.view');
?>

<link href="<?=base_url('public/css/employees_timetable.css')?>?a=<?php $date=date_create();echo date_timestamp_get($date);?>" rel="stylesheet">

<style>
  body {
    height: fit-content;
    overflow: auto;
  }

  .app-container {
    height: fit-content;
    overflow: auto;
    max-height: fit-content;
  }
</style>

<div class="app-content admin-ui-page admin-ui-timetable employees-timetable-page" style="height: fit-content;">
  <h2 class="text-center mb-4">جدول الدوام الأسبوعي</h2>

  <div class="d-flex gap-3 mb-4 timetable-toolbar">
    <div class="d-flex align-items-center gap-2">
      <select id="yearSelect" class="form-select" style="width: 100px;">
        <option value="2024">2024</option>
        <option value="2025">2025</option>
        <option value="2026">2026</option>
      </select>
      <select id="monthSelect" class="form-select" style="width: 120px;">
        <option value="0">يناير</option>
        <option value="1">فبراير</option>
        <option value="2">مارس</option>
        <option value="3">أبريل</option>
        <option value="4">مايو</option>
        <option value="5">يونيو</option>
        <option value="6">يوليو</option>
        <option value="7">أغسطس</option>
        <option value="8">سبتمبر</option>
        <option value="9">أكتوبر</option>
        <option value="10">نوفمبر</option>
        <option value="11">ديسمبر</option>
      </select>
    </div>
    <div class="timetable-export-actions">
      <button type="button" class="btn timetable-icon-button" data-export="pdf" data-tooltip="تحميل PDF" aria-label="تحميل PDF" disabled><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></button>
      <button type="button" class="btn timetable-icon-button" data-export="print" data-tooltip="طباعة A4" aria-label="طباعة A4" disabled><i class="bi bi-printer" aria-hidden="true"></i></button>
      <?php if ($CI->get_permission_enabled('employees_timetable.save')): ?>
      <button type="button" class="btn timetable-icon-button timetable-approval-button" id="approveSchedule" data-action="approve" data-tooltip="اعتمد الجدول" aria-label="اعتمد الجدول" disabled><i class="bi bi-check2-circle" aria-hidden="true"></i></button>
      <?php endif; ?>
    </div>
    <?php if ($CI->get_permission_enabled('employees_leaves.view')): ?>
    <a class="btn btn-primary timetable-leaves-button" href="<?=base_url().MOD_VALUE.'admin/employees_leaves'?>">
      <i class="bi bi-calendar2-check" aria-hidden="true"></i>
      <span>الإجازات</span>
    </a>
    <?php endif; ?>
  </div>

  <div class="mb-3 timetable-week-filter">
    <div class="btn-group" role="group">
      <button class="btn btn-outline-primary week-btn" data-week="0">الأسبوع الأول</button>
      <button class="btn btn-outline-primary week-btn" data-week="1">الأسبوع الثاني</button>
      <button class="btn btn-outline-primary week-btn" data-week="2">الأسبوع الثالث</button>
      <button class="btn btn-outline-primary week-btn" data-week="3">الأسبوع الرابع</button>
      <button class="btn btn-outline-primary week-btn" data-week="4">الأسبوع الخامس</button>
      <button class="btn btn-outline-primary week-btn" data-week="5" hidden>الأسبوع السادس</button>
      <button class="btn btn-outline-primary week-btn" data-week="all">عرض الكل</button>
    </div>
  </div>

  <div id="timetableMessage" role="status" hidden></div>

  <div class="table-responsive timetable-table-shell" aria-label="جدول الدوام الأسبوعي">
    <div id="scheduleTableContainer"></div>
  </div>

  <?php if($can_show_supervisors_timetable): ?>
  <div>
    <div class="table-responsive timetable-table-shell" aria-label="جدول المشرفين الأسبوعي">
      <div id="supervisorsTableContainer">
        <!-- سيتم إضافة جدول المشرفين هنا عبر JavaScript -->
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addEmployeeModalLabel">إضافة موظف جديد</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label for="employeeName" class="form-label">اسم الموظف</label>
            <input type="text" class="form-control" id="employeeName" placeholder="أدخل اسم الموظف">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
          <button type="button" class="btn btn-primary" onclick="saveNewEmployee()">حفظ</button>
        </div>
      </div>
    </div>
  </div>

  <div id="dropdownMenu" class="dropdown-menu shadow-sm" style="display: none;">
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
  <script>
    window.timetableConfig = <?= json_encode(array(
      'today' => $timetable_today,
      'latestMonth' => $timetable_latest_month,
      'canSave' => (bool) $CI->get_permission_enabled('employees_timetable.save'),
      'supervisors' => (bool) $can_show_supervisors_timetable,
    ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <script src="<?=base_url('public/js/employees_timetable.js')?>?v=roles-approval-4"></script>
  <script src="<?=base_url('public/js/script.js')?>"></script>
</div>
</div>

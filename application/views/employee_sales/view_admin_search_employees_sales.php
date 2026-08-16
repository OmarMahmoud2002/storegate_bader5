<title>مبيعات الموظفين</title>
<?php $this->view('view_header'); ?>
<?php $this->view('view_admin_sidebar'); ?>

<?php
// employee_search_page.php
$activeUpload = isset($active_employee_sales_upload) ? $active_employee_sales_upload : null;
$uploads = isset($employee_sales_uploads) ? $employee_sales_uploads : [];
$activeUploadId = isset($active_employee_sales_upload_id) ? (int) $active_employee_sales_upload_id : 0;
$file_uploaded_name = $activeUpload ? $activeUpload->filename : 'غير محدد';
$search_error = isset($search_error) ? $search_error : ($this->session->flashdata('search_error') ? $this->session->flashdata('search_error') : null);
// $search_term_value يتم تمريره من المتحكم لإعادة ملء الحقل

$search_term_value = isset($search_term_value) ? $search_term_value : '';
?>

<?php
$individualsDate = $activeUpload ? $activeUpload->individuals_date : null;
$businessDate = $activeUpload ? $activeUpload->business_date : null;
$hasUploadedFile = isset($file_uploaded_name) && $file_uploaded_name !== 'غير محدد';
?>


<?php 
$CI =& get_instance();
$can_upload = $CI->get_permission_enabled('employees_sales.upload');
$can_search = $CI->get_permission_enabled('employees_sales.search');
?>

<div class="app-content admin-ui-page admin-ui-employee-sales employee-sales-search-page">
    <div class="employee-sales-shell">
        <section class="employee-sales-card">
            <div class="employee-sales-heading">
                <div class="employee-sales-title">
                    <h1>البحث عن موظف</h1>
                    <p>قم بإدخال البيانات المطلوبة للوصول إلى السجلات المحدّثة</p>
                </div>
                <span class="employee-sales-icon" aria-hidden="true">
                    <i class="bi bi-person-vcard"></i>
                </span>
            </div>

            <?php if (isset($search_error) && $search_error): ?>
                <div class="employee-sales-alert employee-sales-alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span><?php echo htmlspecialchars($search_error); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('upload_success')): ?>
                <div class="employee-sales-alert employee-sales-alert-success">
                    <i class="bi bi-check-circle"></i>
                    <span><?php echo htmlspecialchars($this->session->flashdata('upload_success')); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('upload_error') && !isset($search_error)): ?>
                <div class="employee-sales-alert employee-sales-alert-warning">
                    <i class="bi bi-info-circle"></i>
                    <span><?php echo htmlspecialchars($this->session->flashdata('upload_error')); ?></span>
                </div>
            <?php endif; ?>

            <div class="employee-sales-body">
                <div class="employee-sales-meta">
                    <div class="employee-sales-dates">
                        <h2>نطاق التاريخ</h2>
                        <div class="employee-sales-date-grid">
                            <div class="employee-sales-date-chip">
                                <i class="bi bi-calendar2-week"></i>
                                <span><small>الأفراد حتى</small><?= htmlspecialchars($individualsDate ? $individualsDate : 'غير محدد') ?></span>
                            </div>
                            <div class="employee-sales-date-chip">
                                <i class="bi bi-calendar2-week"></i>
                                <span><small>الأعمال حتى</small><?= htmlspecialchars($businessDate ? $businessDate : 'غير محدد') ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="employee-sales-status">
                        <h2>أرشيف الملفات</h2>
                        <div class="employee-sales-status-list" aria-label="حالة البحث">
                            <span class="active"><?= count($uploads) ?> ملف محفوظ</span>
                            <span>كل رفع مستقل</span>
                        </div>
                    </div>
                </div>

                <aside class="employee-sales-files">
                    <div class="employee-sales-files-head">
                        <h2>المستندات الملحقة</h2>
                        <span>XLS, XLSX</span>
                    </div>

                    <div class="employee-sales-file-item">
                        <span class="employee-sales-file-action" aria-hidden="true">
                            <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                        </span>
                        <div>
                            <strong><?= htmlspecialchars($hasUploadedFile ? $file_uploaded_name : 'لا يوجد ملف مرفوع') ?></strong>
                            <p><?= htmlspecialchars($individualsDate ? 'الأفراد حتى ' . $individualsDate . ' | الأعمال حتى ' . $businessDate : 'ارفع ملفًا جديدًا لبدء البحث') ?></p>
                        </div>
                    </div>

                    <?php if ($can_upload): ?>
                        <a id="uploadFile" href="<?= site_url('admin/employees_sales_upload') ?>" class="employee-sales-upload-link">
                            <i class="bi bi-file-earmark-arrow-up"></i>
                            <span>رفع ملف جديد</span>
                        </a>
                    <?php endif; ?>
                </aside>
            </div>

            <?php if ($can_search): ?>
                <form action="<?= site_url('admin/process_employee_sales_search') ?>" method="post" class="employee-sales-search-form">
                    <?php echo form_hidden($this->security->get_csrf_token_name(), $this->security->get_csrf_hash()); ?>
                    <div class="employee-sales-history-selector">
                        <label for="employeeSalesUploadId">
                            <i class="bi bi-clock-history" aria-hidden="true"></i>
                            <span>البيانات السابقة</span>
                        </label>
                        <div class="employee-sales-history-select-wrap">
                            <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                            <select id="employeeSalesUploadId" name="upload_id" <?= empty($uploads) ? 'disabled' : 'required' ?>>
                                <?php if (empty($uploads)): ?>
                                    <option value="">لا توجد ملفات محفوظة</option>
                                <?php else: ?>
                                    <?php foreach ($uploads as $upload): ?>
                                        <?php
                                        $isAvailable = !isset($upload->is_available) || $upload->is_available;
                                        $optionLabel = $upload->filename
                                            . ' — الأفراد حتى ' . $upload->individuals_date
                                            . ' | الأعمال حتى ' . $upload->business_date;
                                        if (!$isAvailable) {
                                            $optionLabel .= ' — الملف غير متاح';
                                        }
                                        ?>
                                        <option value="<?= (int) $upload->id ?>"
                                            <?= (int) $upload->id === $activeUploadId ? 'selected' : '' ?>
                                            <?= !$isAvailable ? 'disabled' : '' ?>>
                                            <?= htmlspecialchars($optionLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <i class="bi bi-chevron-down employee-sales-history-chevron" aria-hidden="true"></i>
                        </div>
                        <p>اختر النسخة التي تريد تنفيذ البحث داخلها؛ لن يتم حذف أو استبدال أي ملف سابق.</p>
                    </div>
                    <label for="search_term" class="visually-hidden">أدخل رقم الهوية، الاسم، أو الرقم الوظيفي</label>
                    <div class="employee-sales-search-control">
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <input type="text" class="form-control"
                            id="search_term" name="search_term"
                            value="<?php echo htmlspecialchars(isset($search_term_value) ? $search_term_value : ''); ?>"
                            placeholder="أدخل رقم الهوية أو اسم الموظف..." required>
                        <button class="employee-sales-search-button" type="submit" aria-label="بحث" <?= !$hasUploadedFile ? 'disabled' : '' ?>>
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/sales.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<?php $this->load->view('view_footer'); ?>

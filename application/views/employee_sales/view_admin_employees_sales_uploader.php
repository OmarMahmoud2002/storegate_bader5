<title>مبيعات الموظفين</title>
<?php $this->view('view_header'); ?>
<?php $this->view('view_admin_sidebar'); ?>

<?php
$individualsDate = $this->session->userdata('individuals_date');
$businessDate = $this->session->userdata('business_date');
?>

<div class="app-content admin-ui-page admin-ui-employee-sales employee-sales-search-page employee-sales-upload-page">
    <div class="employee-sales-shell">
        <section class="employee-sales-card">
            <div class="employee-sales-heading">
                <div class="employee-sales-title">
                    <h1>رفع ملف بيانات الموظفين</h1>
                    <p>ارفع ملف xlsx أو xls بنفس هيكل الملف المثال لتحديث بيانات المبيعات</p>
                </div>
                <span class="employee-sales-icon" aria-hidden="true">
                    <i class="bi bi-file-earmark-arrow-up"></i>
                </span>
            </div>

            <?php if ($this->session->flashdata('upload_error')): ?>
                <div class="employee-sales-alert employee-sales-alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span><?= htmlspecialchars($this->session->flashdata('upload_error')) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('upload_success')): ?>
                <div class="employee-sales-alert employee-sales-alert-success">
                    <i class="bi bi-check-circle"></i>
                    <span><?= htmlspecialchars($this->session->flashdata('upload_success')) ?></span>
                </div>
            <?php endif; ?>

            <?= form_open_multipart(site_url('/admin/handle_employees_sales_upload'), ['class' => 'employee-sales-upload-form']) ?>
                <div class="employee-sales-date-fields">
                    <div class="employee-sales-field">
                        <label for="individuals_date">الأفراد حتى تاريخ</label>
                        <input type="date" name="individuals_date" id="individuals_date"
                            value="<?= htmlspecialchars($individualsDate ? $individualsDate : '') ?>" required>
                    </div>
                    <div class="employee-sales-field">
                        <label for="business_date">الأعمال حتى تاريخ</label>
                        <input type="date" name="business_date" id="business_date"
                            value="<?= htmlspecialchars($businessDate ? $businessDate : '') ?>" required>
                    </div>
                </div>

                <div class="employee-sales-dropzone" id="employeeSalesDropzone">
                    <input type="file" name="userfile" id="userfile" required
                        accept=".xlsx, .xls, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel">
                    <label for="userfile" class="employee-sales-dropzone-label">
                        <i class="bi bi-cloud-arrow-up"></i>
                        <strong id="employeeSalesFileLabel">اختر ملف xlsx أو xls</strong>
                        <span>أو اسحب الملف وأفلته هنا</span>
                    </label>
                </div>
                <p class="employee-sales-dropzone-hint">يجب أن يكون الملف بصيغة xlsx, xls ومشابهًا في الهيكل للملف المثال. شهر الملف يُحدد من «الأفراد حتى تاريخ»، وآخر ملف ترفعه للشهر يصبح النسخة المعتمدة للبحث فيه.</p>

                <button type="submit" class="employee-sales-submit-btn">
                    <i class="bi bi-upload"></i>
                    <span>رفع الملف</span>
                </button>
            <?= form_close() ?>

            <?php if (!empty($file_uploaded_name)): ?>
                <div class="employee-sales-current-file">
                    <span class="employee-sales-file-action" aria-hidden="true">
                        <i class="bi bi-file-earmark-spreadsheet-fill"></i>
                    </span>
                    <div class="employee-sales-current-file-info">
                        <strong>الملف المرفوع حالياً: <?= htmlspecialchars($file_uploaded_name) ?></strong>
                        <?php if ($individualsDate || $businessDate): ?>
                            <p>
                                <?php if ($individualsDate): ?>الأفراد حتى تاريخ: <?= htmlspecialchars($individualsDate) ?><?php endif; ?>
                                <?php if ($individualsDate && $businessDate): ?> | <?php endif; ?>
                                <?php if ($businessDate): ?>الأعمال حتى تاريخ: <?= htmlspecialchars($businessDate) ?><?php endif; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <a href="<?= site_url('employee/search') ?>" class="employee-sales-goto-search">
                        <i class="bi bi-search"></i>
                        <span>الانتقال إلى صفحة البحث</span>
                    </a>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script>
(function () {
    var fileInput = document.getElementById('userfile');
    var fileLabel = document.getElementById('employeeSalesFileLabel');
    var dropzone = document.getElementById('employeeSalesDropzone');
    if (!fileInput || !fileLabel || !dropzone) return;

    fileInput.addEventListener('change', function () {
        if (fileInput.files && fileInput.files.length > 0) {
            fileLabel.textContent = fileInput.files[0].name;
            dropzone.classList.add('has-file');
        } else {
            fileLabel.textContent = 'اختر ملف xlsx أو xls';
            dropzone.classList.remove('has-file');
        }
    });
})();
</script>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/sales.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<?php $this->load->view('view_footer'); ?>

<?php if ($can_upload_products): ?>
<div id="productUploadModal" class="serial-note-modal product-upload-modal hide" role="dialog" aria-modal="true" aria-labelledby="productUploadTitle" aria-describedby="productUploadDescription" dir="rtl">
  <div class="serial-note-modal-card">
    <div class="serial-note-modal-head">
      <div class="serial-note-modal-title">
        <span><i class="bi bi-file-earmark-excel" aria-hidden="true"></i></span>
        <div>
          <h2 id="productUploadTitle">إضافة منتجات</h2>
          <p id="productUploadDescription">ارفع ملف إكسيل لتحديث بيانات المخزون.</p>
        </div>
      </div>
      <button type="button" class="serial-note-modal-close" data-upload-close aria-label="إغلاق"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <form id="productUploadForm" class="serial-note-form" enctype="multipart/form-data" novalidate>
      <label for="productUploadFile" class="product-upload-file-label">
        <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i>
        <strong>اختر ملف إكسيل المنتجات</strong>
        <span>ملف XLSX أو XLS بحد أقصى 50 ميجابايت</span>
        <input type="file" id="productUploadFile" name="excel_file" accept=".xlsx,.xls">
      </label>
      <div id="productUploadProgress" class="product-upload-progress hide" role="status">
        <div class="product-upload-progress-heading"><span id="productUploadProgressText">جارٍ رفع الملف...</span></div>
        <div id="productUploadProgressBar" class="product-upload-progress-track" role="progressbar" aria-label="نسبة رفع الملف" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><span id="productUploadProgressFill"></span></div>
        <small>يرجى الانتظار حتى ينتهي تحديث المخزون</small>
      </div>
      <p id="productUploadMessage" class="product-upload-message hide" role="status" aria-live="polite"></p>
      <div class="serial-note-modal-actions">
        <button type="button" class="serial-note-cancel-button" data-upload-close>إلغاء</button>
        <button type="submit" id="productUploadSubmit" class="serial-note-save-button"><i class="bi bi-upload" aria-hidden="true"></i><span>رفع الملف</span></button>
        <button type="button" id="productUploadConfirm" class="serial-note-save-button hide"><i class="bi bi-check2" aria-hidden="true"></i><span>تأكيد متابعة الرفع</span></button>
      </div>
    </form>
  </div>
</div>
<div id="productUploadToast" class="product-upload-toast hide" role="status" aria-live="polite" dir="rtl"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><span>تم رفع الملف بنجاح</span></div>
<?php endif; ?>
<link rel="stylesheet" href="<?= base_url('public/css/product_upload_modal.css') ?>?v=2">
<script src="<?= base_url('public/js/product_upload_modal.js') ?>?v=3"></script>

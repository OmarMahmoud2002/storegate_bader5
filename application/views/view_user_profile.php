<title>الملف التعريفي</title>
<?php $this->view('view_header'); ?>

	<?php $this->view('view_user_sidebar'); ?>

    <div class="app-content">
      <div class="app-content-header">
        <h1 class="app-content-headerText">الملف التعريفي</h1>
      </div>
      <div class="profile-page-content">
        <div class="profile-card">
          <div class="inpt">
            <label>اسم الموظف</label>
            <input type="text" value="<?php echo safe_data($employee['user_fillname']); ?>" disabled readonly>
          </div>
          <div class="inpt">
            <label>البريد الالكتروني</label>
            <input type="text" value="<?php echo safe_data($employee['user_email']); ?>" disabled readonly>
          </div>
          <div class="inpt">
            <label>اسم الدخول</label>
            <input type="text" value="<?php echo safe_data($employee['user_name']); ?>" disabled readonly>
          </div>

          <div class="inpt">
            <label>الرقم السري</label>
            <div class="password-field password-field--masked">
              <input type="text" class="password-field__input" value="&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;&bull;" disabled readonly>
              <button type="button" class="password-field__btn password-toggle-edit" data-target="profilePasswordEditPanel">
                <i class="bi bi-pencil-square"></i>
                <span>تعديل كلمة السر</span>
              </button>
            </div>

            <?php echo form_open(base_url().MOD_VALUE.'user/change_password',array('class' => '')); ?>
              <div class="password-edit-panel" id="profilePasswordEditPanel" hidden>
                <div class="inpt">
                  <label>كلمة السر القديمة</label>
                  <input type="text" name="current_user_password" class="password-edit-current" placeholder="كلمة السر القديمة" autocomplete="off">
                </div>
                <div class="inpt">
                  <label>كلمة السر الجديدة</label>
                  <div class="password-field">
                    <input type="text" name="new_user_password" class="password-field__input password-edit-new" placeholder="كلمة السر الجديدة" autocomplete="new-password">
                    <button type="button" class="password-field__btn password-field__generate" title="توليد كلمة سر قوية"><i class="bi bi-arrow-repeat"></i></button>
                    <button type="button" class="password-field__btn password-field__copy" title="نسخ كلمة السر"><i class="bi bi-clipboard"></i></button>
                  </div>
                </div>
                <div class="inpt">
                  <label>تأكيد كلمة السر الجديدة</label>
                  <input type="text" name="confirm_user_password" class="password-edit-confirm" placeholder="تأكيد كلمة السر الجديدة" autocomplete="new-password">
                </div>
                <button type="submit" class="btn" name="changepasswordform">حفظ كلمة السر</button>
              </div>
            <?php echo form_close(); ?>
          </div>
        </div>
      </div>
    </div>
  </div>

<style>
.profile-page-content {
  padding: 8px 0;
}

.profile-card {
  max-width: 560px;
  padding: 24px;
  border: 1px solid var(--admin-ui-border, #e2e8f0);
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 10px 25px rgba(15, 23, 42, 0.06);
}

.profile-card .inpt {
  margin-bottom: 18px;
}

.profile-card .inpt label {
  display: block;
  margin-bottom: 8px;
  color: #2d3748;
  font-size: 14px;
  font-weight: 800;
}

.profile-card .inpt input[disabled] {
  width: 100%;
  min-height: 48px;
  padding: 10px 12px;
  border: 2px solid var(--admin-ui-border, #e2e8f0);
  border-radius: 12px;
  background: #f8fafc;
  color: #4a5568;
  font-size: 15px;
  font-weight: 600;
}
</style>

<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/password-field.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<?php $this->view('view_footer'); ?>

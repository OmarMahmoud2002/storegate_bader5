<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar'); ?>

<div class="app-content admin-ui-page admin-ui-profile">
  <div class="app-content-header">
    <div>
      <h1 class="app-content-headerText">الملف التعريفي</h1>
      <p class="admin-profile-header-note">بيانات حسابك وإعدادات الأمان</p>
    </div>
  </div>

  <div class="admin-profile-grid">
    <section class="admin-profile-card admin-profile-identity">
      <div class="admin-profile-card-head">
        <div class="admin-profile-card-icon"><i class="bi bi-person-badge"></i></div>
        <div>
          <h2>بيانات الحساب</h2>
          <p>البيانات الأساسية لحساب مدير النظام</p>
        </div>
      </div>

      <div class="admin-profile-summary">
        <div class="admin-profile-avatar"><i class="bi bi-person-fill"></i></div>
        <div>
          <strong><?php echo safe_data($admin['user_fillname']); ?></strong>
          <span>مدير النظام</span>
        </div>
      </div>

      <dl class="admin-profile-details">
        <div>
          <dt><i class="bi bi-person"></i> الاسم</dt>
          <dd><?php echo safe_data($admin['user_fillname']); ?></dd>
        </div>
        <div>
          <dt><i class="bi bi-envelope"></i> البريد الإلكتروني</dt>
          <dd><?php echo safe_data($admin['user_email']); ?></dd>
        </div>
        <div>
          <dt><i class="bi bi-at"></i> اسم الدخول</dt>
          <dd><?php echo safe_data($admin['user_name']); ?></dd>
        </div>
        <div>
          <dt><i class="bi bi-calendar3"></i> تاريخ إنشاء الحساب</dt>
          <dd><?php echo !empty($admin['user_create_date']) ? safe_data($admin['user_create_date']) : 'غير محدد'; ?></dd>
        </div>
      </dl>
    </section>

    <section class="admin-profile-card admin-profile-security">
      <div class="admin-profile-card-head">
        <div class="admin-profile-card-icon"><i class="bi bi-shield-lock"></i></div>
        <div>
          <h2>كلمة السر</h2>
          <p>غيّر كلمة السر الخاصة بحسابك فقط</p>
        </div>
      </div>

      <div class="admin-profile-password-status">
        <div>
          <i class="bi bi-check-circle-fill"></i>
          <span>كلمة السر مفعلة</span>
        </div>
        <button type="button" class="password-field__btn password-toggle-edit" data-target="adminPasswordEditPanel">
          <i class="bi bi-pencil-square"></i>
          <span>تغيير كلمة السر</span>
        </button>
      </div>

      <?php echo form_open(base_url().MOD_VALUE.'admin/change_admin_password', ['class' => 'admin-profile-password-form']); ?>
        <div class="password-edit-panel" id="adminPasswordEditPanel" hidden>
          <div class="inpt">
            <label for="adminCurrentPassword">كلمة السر الحالية</label>
            <input type="password" id="adminCurrentPassword" name="current_user_password" class="password-edit-current" placeholder="أدخل كلمة السر الحالية" autocomplete="current-password">
          </div>
          <div class="inpt">
            <label for="adminNewPassword">كلمة السر الجديدة</label>
            <div class="password-field">
              <input type="text" id="adminNewPassword" name="new_user_password" class="password-field__input password-edit-new" placeholder="أدخل كلمة السر الجديدة" autocomplete="new-password">
              <button type="button" class="password-field__btn password-field__generate" title="توليد كلمة سر قوية" aria-label="توليد كلمة سر قوية"><i class="bi bi-arrow-repeat"></i></button>
              <button type="button" class="password-field__btn password-field__copy" title="نسخ كلمة السر" aria-label="نسخ كلمة السر"><i class="bi bi-clipboard"></i></button>
            </div>
          </div>
          <div class="inpt">
            <label for="adminConfirmPassword">تأكيد كلمة السر الجديدة</label>
            <input type="text" id="adminConfirmPassword" name="confirm_user_password" class="password-edit-confirm" placeholder="أعد إدخال كلمة السر الجديدة" autocomplete="new-password">
          </div>
          <button type="submit" class="admin-profile-save-password">
            <i class="bi bi-check2-circle"></i>
            حفظ كلمة السر الجديدة
          </button>
        </div>
      <?php echo form_close(); ?>
    </section>
  </div>
</div>
</div>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/password-field.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<?php $this->load->view('view_footer'); ?>

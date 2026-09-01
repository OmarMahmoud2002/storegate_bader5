<?php
$page_title = 'الملف التعريفي';
$this->view('view_header');
$this->view('view_user_sidebar');
?>

<div class="app-content admin-ui-page admin-ui-profile admin-ui-user-profile">
    <div class="app-content-header user-page-heading">
        <div>
            <h1 class="app-content-headerText">الملف التعريفي</h1>
            <p>راجع بيانات حسابك وحدّث كلمة المرور بأمان.</p>
        </div>
    </div>

    <div class="admin-profile-grid">
        <section class="admin-profile-card">
            <div class="admin-profile-card-head">
                <span class="admin-profile-card-icon"><i class="bi bi-person-vcard"></i></span>
                <div>
                    <h2>بيانات الحساب</h2>
                    <p>البيانات الأساسية المسجلة للموظف</p>
                </div>
            </div>

            <div class="admin-profile-summary">
                <span class="admin-profile-avatar"><i class="bi bi-person"></i></span>
                <div>
                    <strong><?php echo safe_data($employee['user_fillname']); ?></strong>
                    <span>حساب موظف</span>
                </div>
            </div>

            <dl class="admin-profile-details">
                <div>
                    <dt><i class="bi bi-person-badge"></i> اسم الموظف</dt>
                    <dd><?php echo safe_data($employee['user_fillname']); ?></dd>
                </div>
                <div>
                    <dt><i class="bi bi-envelope"></i> البريد الإلكتروني</dt>
                    <dd dir="ltr"><?php echo safe_data($employee['user_email']); ?></dd>
                </div>
                <div>
                    <dt><i class="bi bi-at"></i> اسم الدخول</dt>
                    <dd dir="ltr"><?php echo safe_data($employee['user_name']); ?></dd>
                </div>
            </dl>
        </section>

        <section class="admin-profile-card admin-profile-security">
            <div class="admin-profile-card-head">
                <span class="admin-profile-card-icon"><i class="bi bi-shield-lock"></i></span>
                <div>
                    <h2>أمان الحساب</h2>
                    <p>استخدم كلمة مرور قوية لا تستخدمها في حساب آخر</p>
                </div>
            </div>

            <div class="admin-profile-password-status">
                <div><i class="bi bi-check-circle-fill"></i> كلمة المرور مفعّلة</div>
                <button type="button" class="password-field__btn password-toggle-edit" data-target="profilePasswordEditPanel">
                    <i class="bi bi-pencil-square"></i>
                    <span>تعديل كلمة السر</span>
                </button>
            </div>

            <?php echo form_open(base_url().MOD_VALUE.'user/change_password', array('class' => '')); ?>
                <div class="password-edit-panel" id="profilePasswordEditPanel" hidden>
                    <div class="inpt">
                        <label>كلمة السر القديمة</label>
                        <input type="password" name="current_user_password" class="password-edit-current" placeholder="كلمة السر القديمة" autocomplete="current-password">
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
                    <button type="submit" class="admin-profile-save-password" name="changepasswordform">
                        <i class="bi bi-check2-circle"></i>
                        حفظ كلمة السر
                    </button>
                </div>
            <?php echo form_close(); ?>
        </section>
    </div>
</div>
</div>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/password-field.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<?php $this->view('view_footer'); ?>

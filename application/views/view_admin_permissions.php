<?php $this->view('view_header'); ?>
<title>الصلاحيات</title>
<?php $this->view('view_admin_sidebar'); ?>

<?php 
$CI =& get_instance();
$can_edit = $CI->get_permission_enabled('roles.update');
$can_create = $CI->get_permission_enabled('roles.create');
$can_delete = $CI->get_permission_enabled('roles.delete');
?>

<?php
$pages = [];
foreach ($permission_groups as $group) {
    foreach ($group['pages'] as $page) {
        $pages[] = $page;
    }
}
?>

<div class="app-content admin-ui-page admin-ui-permissions permissions-simple-page" dir="rtl">
    <div class="app-content-header">
        <h1 class="app-content-headerText">الصلاحيات</h1>
        <div class="permissions-header-actions">
            <?php if ($can_create) :?>
                <button class="app-content-headerButton permissions-create-btn" onclick = "window.location.href = '<?php echo base_url().MOD_VALUE.'admin/permissions/create'; ?>'">انشاء صلاحية</button>
            <?php endif; ?>
        </div>
    </div>
    
    <form id="permissionsForm" class="permissions-simple-form" method="post">
        <?php if ($can_create && !isset($selected_role) && $segment == 'create') : ?>
        <section class="permissions-create-box">
            <div class="permissions-create-title">
                <i class="bi bi-shield-plus"></i>
                <div>
                    <h2>إنشاء صلاحية</h2>
                    <p>اكتب اسم الصلاحية واختار المسموح به من الجدول.</p>
                </div>
            </div>

            <div class="permissions-create-fields">
                <input name='action' type="hidden" value="create">

                <div class="permissions-field">
                    <label for="roleName">اسم الصلاحية</label>
                    <input id="roleName" name="role_name" type="text" placeholder="مثال: مسؤول مخزن" required>
                </div>

                <div class="permissions-field">
                    <label for="roleDescription">الوصف</label>
                    <input id="roleDescription" name="role_description" type="text" placeholder="اختياري">
                </div>

                <?php if ($can_create) :?>
                    <button type="submit" class="permissions-main-save">
                        <i class="bi bi-check2-circle"></i>
                        حفظ
                    </button>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ($can_edit && isset($selected_role) && $segment == 'update') :?>
        <section class="permissions-create-box">
            <div class="permissions-create-title">
                <i class="bi bi-shield-plus"></i>
                <div>
                    <h2>تعديل صلاحية <b><?php echo isset($selected_role) ? htmlspecialchars($selected_role['name'], ENT_QUOTES, 'UTF-8') : ''; ?></b></h2>
                </div>
            </div>

            <div class="permissions-create-fields">
                <input name='action' type="hidden" value="update">
                <input name='role_id' type='hidden' value = '<?php echo isset($selected_role) ? $selected_role['id'] : ''; ?>'>
                
                
                <div class="permissions-field">
                    <label for="roleName">اسم الصلاحية</label>
                    <input id="roleName" name="role_name" type="text" placeholder="مثال: مسؤول مخزن" value="<?php echo isset($selected_role) ? htmlspecialchars($selected_role['name'], ENT_QUOTES, 'UTF-8') : ''; ?>" required>
                </div>

                <div class="permissions-field">
                    <label for="roleDescription">الوصف</label>
                    <input id="roleDescription" name="role_description" type="text" placeholder="اختياري" value="<?php echo isset($selected_role) ? htmlspecialchars($selected_role['description'], ENT_QUOTES, 'UTF-8') : ''; ?>">
                </div>

                <?php if ($can_edit) :?>
                    <button type="submit" class="permissions-main-save">
                        <i class="bi bi-check2-circle"></i>
                        حفظ
                    </button>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>
        
        <?php if ($segment != 'update' && $segment != 'create') :?>
        <section class = 'permissions-roles-box'>
            <div class="permissions-roles-title">
                <i class="bi bi-shield-lock"></i>
                <div>
                    <h2>الصلاحيات الحالية</h2>
                    <p>اضغط على اسم الصلاحية لعرض وتعديل الصلاحيات الممنوحة لها.</p>
                </div>
            </div>

            <div class="permissions-role-list">
                <?php foreach ($roles as $role) { ?>
                    <?php if (isset($selected_role) && $role['id'] == $selected_role['id']) continue; ?>
                    <div class = 'permissions-role-card'>
                        <span>
                            <?php echo htmlspecialchars($role['name'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <div class = ' actions'>
                            <?php if ($can_edit) :?>
                            <button type="button" class="permissions-edit-btn" onclick="window.location.href='<?php echo base_url().MOD_VALUE.'admin/permissions/update?role_id='.urlencode($role['id']); ?>'">
                                <i class="bi bi-pencil-square"></i>
                                تعديل
                            </button>
                            <?php endif; ?>
                            <?php if ($can_delete) :?>
                            <button type="button" class="permissions-delete-btn" onclick="if(confirm('هل أنت متأكد أنك تريد حذف هذه الصلاحية؟')) { window.location.href='<?php echo base_url().MOD_VALUE.'admin/delete_permissions?role_id='.urlencode($role['id']); ?>'; }">
                                <i class="bi bi-trash"></i>
                                حذف
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                <?php } ?>
            </div>
        </section>
        <?php endif; ?>


        <?php if (($can_edit || $can_create) && ($segment == 'create' || $segment == 'update')) :?>
        <section class="permissions-table-box">
            <div class="permissions-table-head">
                <h2>جدول الصلاحيات</h2>
                <div class="permissions-table-actions">
                    <button type="button" id="selectAllPermissions">
                        <i class="bi bi-check2-square"></i>
                        تحديد الكل
                    </button>
                    <button type="button" id="clearPermissions">
                        <i class="bi bi-x-circle"></i>
                        تفريغ
                    </button>
                </div>
            </div>

            <div class="permissions-table-wrap">
                <table class="permissions-simple-table">
                    <thead>
                        <tr>
                            <th>اسم الصفحة</th>
                            <th>الصلاحيات المتاحة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page) { ?>
                            <tr>
                                <td class="permissions-page-name">
                                    <strong><?php echo htmlspecialchars($page['label'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                </td>
                                <td>
                                    <div class="permissions-check-list">
                                        <?php foreach ($page['permissions'] as $permission) { ?>
                                            <label class="permissions-check">
                                                <?php 
                                                $check = false;
                                                if (isset($permission['checked']) && $permission['checked']) {
                                                    $check = true;
                                                }

                                                if (isset($selected_role_permissions) && !empty($selected_role_permissions) && in_array($permission['key'], $selected_role_permissions)) {
                                                    $check = true;
                                                } else if (isset($selected_role_permissions) && !empty($selected_role_permissions) && !in_array($permission['key'], $selected_role_permissions)) {
                                                    $check = false;
                                                }
                                                
                                                ?>
                                                <input type="checkbox" name="permissions[]" value="<?php echo htmlspecialchars($permission['key'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $check ? 'checked' : ''; ?>>
                                                <span></span>
                                                <b><?php echo htmlspecialchars($permission['label'], ENT_QUOTES, 'UTF-8'); ?></b>
                                            </label>
                                        <?php } ?>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
        <?php endif; ?>
    </form>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('permissionsForm');
    var inputs = Array.prototype.slice.call(document.querySelectorAll('.permissions-check input'));

    document.getElementById('selectAllPermissions').addEventListener('click', function () {
        inputs.forEach(function (input) {
            input.checked = true;
        });
    });

    document.getElementById('clearPermissions').addEventListener('click', function () {
        inputs.forEach(function (input) {
            input.checked = false;
        });
    });

    /*
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        if (window.toastr) {
            toastr.success('تم تجهيز الشكل فقط، ولسه الحفظ الفعلي هيتربط مع اللوجيك بعدين.');
        }
    });*/
    
});
</script>

<?php $this->view('view_footer'); ?>

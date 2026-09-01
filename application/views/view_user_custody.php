<?php
$page_title = 'العهد';
$this->view('view_header');
$this->view('view_user_sidebar');

$custodies = isset($custodies) && is_array($custodies) ? $custodies : array();
$return_requests = isset($return_requests) && is_array($return_requests) ? $return_requests : array();
$status_labels = array(
    'pending' => 'قيد المراجعة',
    'reviewing' => 'قيد المراجعة',
    'approved' => 'تمت الموافقة',
    'rejected' => 'مرفوض',
    'sold' => 'أُغلق بعد تسجيل البيع',
);
$pending_count = 0;
foreach ($return_requests as $return_request) {
    if (in_array($return_request['status'], array('pending', 'reviewing'), true)) {
        $pending_count++;
    }
}
?>

<div class="app-content admin-ui-page admin-ui-user-custody">
    <div class="app-content-header user-page-heading">
        <div>
            <h1 class="app-content-headerText">العهد</h1>
            <p>كل الأجهزة والشرائح المسجلة على عهدتك. البيانات للعرض فقط ويمكنك إرسال طلب إرجاع للإدارة.</p>
        </div>
        <a class="app-content-headerButton user-custody-export" href="<?php echo base_url().MOD_VALUE; ?>user/export_custody">
            <i class="bi bi-file-earmark-excel"></i>
            تحميل Excel
        </a>
    </div>

    <div class="user-custody-summary" aria-label="ملخص العهد">
        <article>
            <span class="user-custody-summary__icon"><i class="bi bi-box-seam"></i></span>
            <div><span>إجمالي العهد</span><strong><?php echo number_format(count($custodies)); ?></strong></div>
        </article>
        <article>
            <span class="user-custody-summary__icon user-custody-summary__icon--pending"><i class="bi bi-hourglass-split"></i></span>
            <div><span>طلبات قيد المراجعة</span><strong><?php echo number_format($pending_count); ?></strong></div>
        </article>
    </div>

    <section class="user-custody-card" aria-labelledby="custody-list-title">
        <div class="user-custody-card__head">
            <div>
                <h2 id="custody-list-title">العهد الحالية</h2>
                <p>لا يتم إخراج أي عهدة من حسابك إلا بعد موافقة الإدارة أو تسجيل بيعها في ملف المبيعات.</p>
            </div>
            <span><?php echo number_format(count($custodies)); ?> عهدة</span>
        </div>

        <?php if (!empty($custodies)): ?>
            <div class="products-area-wrapper tableView user-custody-table">
                <div class="products-header">
                    <div class="product-cell">النوع</div>
                    <div class="product-cell">الوصف</div>
                    <div class="product-cell">كود المنتج</div>
                    <div class="product-cell">السيريال / الباركود</div>
                    <div class="product-cell">رقم الطلب</div>
                    <div class="product-cell">تاريخ الاستلام</div>
                    <div class="product-cell actions">الإجراءات</div>
                </div>
                <?php foreach ($custodies as $custody): ?>
                    <?php
                    $number = !empty($custody['serial_number']) ? $custody['serial_number'] : $custody['barcode'];
                    $latest_request = !empty($custody['request']) ? $custody['request'] : null;
                    $is_pending = $latest_request && in_array($latest_request['status'], array('pending', 'reviewing'), true);
                    $received_at = !empty($custody['date_created']) ? date('Y-m-d', strtotime($custody['date_created'])) : '-';
                    ?>
                    <div class="products-row">
                        <div class="product-cell">
                            <span class="cell-label">النوع</span>
                            <span class="custody-type-badge"><i class="bi <?php echo (int) $custody['type'] === 2 ? 'bi-sim' : 'bi-phone'; ?>"></i><?php echo (int) $custody['type'] === 2 ? 'شريحة' : 'جهاز'; ?></span>
                        </div>
                        <div class="product-cell custody-description">
                            <span class="cell-label">الوصف</span>
                            <strong><?php echo htmlspecialchars((string) $custody['item_description'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        </div>
                        <div class="product-cell">
                            <span class="cell-label">كود المنتج</span>
                            <span dir="ltr"><?php echo htmlspecialchars((string) $custody['item_code'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell">
                            <span class="cell-label">السيريال / الباركود</span>
                            <span class="custody-number" dir="ltr"><?php echo htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell">
                            <span class="cell-label">رقم الطلب</span>
                            <span dir="ltr"><?php echo htmlspecialchars(!empty($custody['order_number']) ? (string) $custody['order_number'] : '-', ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell">
                            <span class="cell-label">تاريخ الاستلام</span>
                            <span dir="ltr"><?php echo htmlspecialchars($received_at, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell actions">
                            <span class="cell-label">الإجراءات</span>
                            <?php if ($is_pending): ?>
                                <button type="button" class="custody-return-button is-pending" disabled><i class="bi bi-hourglass-split"></i> قيد المراجعة</button>
                            <?php else: ?>
                                <button
                                    type="button"
                                    class="custody-return-button js-open-return"
                                    data-custody-id="<?php echo (int) $custody['id']; ?>"
                                    data-description="<?php echo htmlspecialchars((string) $custody['item_description'], ENT_QUOTES, 'UTF-8'); ?>"
                                    data-number="<?php echo htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8'); ?>">
                                    <i class="bi bi-arrow-return-left"></i> إرجاع
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-admin-state user-custody-empty">
                <i class="bi bi-check2-circle"></i>
                <h3>لا توجد عهد مسجلة عليك حاليًا</h3>
                <p>ستظهر هنا أي أجهزة أو شرائح يتم تسليمها لك من الإدارة.</p>
            </div>
        <?php endif; ?>
    </section>

    <section class="user-custody-card user-return-history" id="return-requests" aria-labelledby="return-history-title">
        <div class="user-custody-card__head">
            <div>
                <h2 id="return-history-title">طلبات الإرجاع</h2>
                <p>تابع قرار الإدارة والرد على كل طلب سبق إرساله.</p>
            </div>
            <span><?php echo number_format(count($return_requests)); ?> طلب</span>
        </div>

        <?php if (!empty($return_requests)): ?>
            <div class="return-history-list">
                <?php foreach ($return_requests as $request): ?>
                    <?php
                    $request_number = !empty($request['serial_number']) ? $request['serial_number'] : $request['barcode'];
                    $status = isset($request['status']) ? $request['status'] : 'pending';
                    ?>
                    <article class="return-history-item">
                        <div class="return-history-item__identity">
                            <div>
                                <h3><?php echo htmlspecialchars((string) $request['item_description'], ENT_QUOTES, 'UTF-8'); ?></h3>
                                <p dir="ltr"><?php echo htmlspecialchars((string) $request_number, ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                            <span class="return-status return-status--<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($status_labels[$status]) ? $status_labels[$status] : $status, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <dl>
                            <div><dt>تاريخ الطلب</dt><dd dir="ltr"><?php echo htmlspecialchars((string) $request['requested_at'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                            <div><dt>ملاحظتك</dt><dd><?php echo htmlspecialchars(!empty($request['employee_note']) ? $request['employee_note'] : 'لا توجد', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                            <div><dt>رد الإدارة</dt><dd><?php echo htmlspecialchars(!empty($request['admin_note']) ? $request['admin_note'] : ($status === 'pending' || $status === 'reviewing' ? 'بانتظار المراجعة' : 'لا توجد ملاحظة'), ENT_QUOTES, 'UTF-8'); ?></dd></div>
                        </dl>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-admin-state user-custody-empty user-custody-empty--small">
                <i class="bi bi-inbox"></i>
                <p>لم ترسل أي طلب إرجاع حتى الآن.</p>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>

<div class="custody-return-modal" id="custodyReturnModal" aria-hidden="true">
    <div class="custody-return-modal__backdrop js-close-return"></div>
    <div class="custody-return-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="return-modal-title">
        <button type="button" class="custody-return-modal__close js-close-return" aria-label="إغلاق"><i class="bi bi-x-lg"></i></button>
        <span class="custody-return-modal__icon"><i class="bi bi-arrow-return-left"></i></span>
        <h2 id="return-modal-title">إرسال طلب إرجاع</h2>
        <p>سيصل الطلب إلى الإدارة للمراجعة، وستظل العهدة ظاهرة لديك حتى تتم الموافقة.</p>
        <div class="custody-return-modal__product">
            <strong id="returnProductDescription"></strong>
            <span id="returnProductNumber" dir="ltr"></span>
        </div>
        <?php echo form_open(base_url().MOD_VALUE.'user/request_product_return', array('id' => 'custodyReturnForm')); ?>
            <input type="hidden" name="custody_id" id="returnCustodyId" value="">
            <label for="returnEmployeeNote">ملاحظة للإدارة <small>(اختياري)</small></label>
            <textarea name="employee_note" id="returnEmployeeNote" maxlength="500" rows="3" placeholder="مثال: الجهاز جاهز للتسليم"></textarea>
            <div class="custody-return-modal__actions">
                <button type="button" class="secondary js-close-return">إلغاء</button>
                <button type="submit" class="primary"><i class="bi bi-send"></i> إرسال الطلب</button>
            </div>
        <?php echo form_close(); ?>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/home.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script>
(function () {
    var modal = document.getElementById('custodyReturnModal');
    var custodyInput = document.getElementById('returnCustodyId');
    var description = document.getElementById('returnProductDescription');
    var number = document.getElementById('returnProductNumber');

    function closeModal() {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('custody-modal-open');
    }

    document.querySelectorAll('.js-open-return').forEach(function (button) {
        button.addEventListener('click', function () {
            custodyInput.value = button.getAttribute('data-custody-id');
            description.textContent = button.getAttribute('data-description');
            number.textContent = button.getAttribute('data-number');
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('custody-modal-open');
            document.getElementById('returnEmployeeNote').focus();
        });
    });

    modal.querySelectorAll('.js-close-return').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            closeModal();
        }
    });
}());
</script>
<?php $this->view('view_footer'); ?>


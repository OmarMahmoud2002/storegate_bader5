<?php
$page_title = 'الصفحة الرئيسية';
$this->load->view('view_header');
$this->load->view('view_admin_sidebar');

$return_requests = isset($return_requests) && is_array($return_requests) ? $return_requests : array();
$return_request_counts = isset($return_request_counts) && is_array($return_request_counts)
    ? $return_request_counts
    : array('pending' => 0, 'approved' => 0, 'rejected' => 0, 'sold' => 0);
$return_request_employees = isset($return_request_employees) && is_array($return_request_employees) ? $return_request_employees : array();
$return_filters = isset($return_filters) && is_array($return_filters) ? $return_filters : array('employee_id' => 0, 'serial' => '');
$has_return_filters = !empty($return_filters['employee_id']) || $return_filters['serial'] !== '';
$status_labels = array(
    'pending' => 'جديد',
    'reviewing' => 'قيد المعالجة',
    'approved' => 'تمت الموافقة',
    'rejected' => 'مرفوض',
    'sold' => 'أُغلق بعد تسجيل البيع',
);
$home_tab_visibility = isset($home_tab_visibility) && is_array($home_tab_visibility) ? $home_tab_visibility : array();
$home_tabs = array(
    'returns' => array('label' => 'طلبات الإرجاع', 'icon' => 'bi-arrow-return-left', 'description' => ''),
    'low-stock' => array('label' => 'منتجات منخفضة الكمية', 'icon' => 'bi-box-seam', 'description' => 'سيتم إضافة بيانات المنتجات منخفضة الكمية هنا قريبًا.'),
    'over-five-days' => array('label' => 'منتجات تجاوزت 5 أيام', 'icon' => 'bi-calendar-x', 'description' => 'سيتم إضافة بيانات المنتجات التي تجاوزت خمسة أيام هنا قريبًا.'),
    'over-three-days' => array('label' => 'منتجات تجاوزت 3 أيام', 'icon' => 'bi-clock-history', 'description' => 'سيتم إضافة بيانات المنتجات التي تجاوزت ثلاثة أيام هنا قريبًا.'),
    'shortage' => array('label' => 'العجز', 'icon' => 'bi-exclamation-triangle', 'description' => 'سيتم إضافة بيانات العجز ومتابعتها هنا قريبًا.'),
    'missing-details' => array('label' => 'منتجات تحتاج إضافة تفاصيل', 'icon' => 'bi-card-checklist', 'description' => 'سيتم إضافة قائمة المنتجات التي تحتاج إلى استكمال بياناتها هنا قريبًا.'),
);
$visible_home_tabs = array_filter($home_tabs, function ($tab, $key) use ($home_tab_visibility) {
    return !empty($home_tab_visibility[$key]);
}, ARRAY_FILTER_USE_BOTH);
$first_visible_home_tab = empty($visible_home_tabs) ? '' : key($visible_home_tabs);
?>

<div class="app-content admin-ui-page admin-ui-home">
    <div class="app-content-header admin-home-heading">
        <div>
            <h1 class="app-content-headerText">الصفحة الرئيسية</h1>
            <!-- <p>مركز متابعة منظم للطلبات والتنبيهات التشغيلية. تمت إضافة طلبات الإرجاع حاليًا، ويمكن توسيع الأقسام لاحقًا دون ازدحام الصفحة.</p> -->
        </div>
    </div>

    <main class="admin-home-dashboard">
        <?php if (!empty($visible_home_tabs)): ?>
        <nav class="admin-home-tabs" aria-label="أقسام الصفحة الرئيسية">
            <div class="admin-home-tabs__list" role="tablist">
                <?php foreach ($visible_home_tabs as $tab_key => $tab): ?>
                    <?php $is_active_tab = $tab_key === $first_visible_home_tab; ?>
                    <button type="button" class="admin-home-tab<?php echo $is_active_tab ? ' is-active' : ''; ?>" id="home-tab-<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>" role="tab" aria-selected="<?php echo $is_active_tab ? 'true' : 'false'; ?>" aria-controls="home-panel-<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>" tabindex="<?php echo $is_active_tab ? '0' : '-1'; ?>" data-home-tab="<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="admin-home-tab__icon"><i class="bi <?php echo htmlspecialchars($tab['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                        <span class="admin-home-tab__label"><?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($tab_key === 'returns' && $return_request_counts['pending'] > 0): ?><span class="admin-home-tab__badge" aria-label="<?php echo number_format($return_request_counts['pending']); ?> طلب جديد"><?php echo number_format($return_request_counts['pending']); ?></span><?php endif; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </nav>
        <?php endif; ?>

        <?php if (!empty($home_tab_visibility['returns'])): ?>
        <section class="admin-home-tab-panel<?php echo $first_visible_home_tab === 'returns' ? ' is-active' : ''; ?>" id="home-panel-returns" role="tabpanel" aria-labelledby="home-tab-returns" data-home-panel="returns"<?php echo $first_visible_home_tab === 'returns' ? '' : ' hidden'; ?>>
        <section class="admin-home-summary" aria-label="ملخص طلبات الإرجاع">
            <article class="admin-home-summary__card admin-home-summary__card--pending"><span><i class="bi bi-hourglass-split"></i></span><div><small>بانتظار المراجعة</small><strong><?php echo number_format($return_request_counts['pending']); ?></strong></div></article>
            <article class="admin-home-summary__card admin-home-summary__card--approved"><span><i class="bi bi-check2-circle"></i></span><div><small>تمت الموافقة</small><strong><?php echo number_format($return_request_counts['approved']); ?></strong></div></article>
            <article class="admin-home-summary__card admin-home-summary__card--rejected"><span><i class="bi bi-x-circle"></i></span><div><small>مرفوضة</small><strong><?php echo number_format($return_request_counts['rejected']); ?></strong></div></article>
            <?php /* بطاقة "أُغلقت بعد البيع" معطّلة مؤقتًا بناءً على طلب الإدارة - يكفي إلغاء التعليق لإعادتها
            <article class="admin-home-summary__card admin-home-summary__card--sold"><span><i class="bi bi-receipt"></i></span><div><small>أُغلقت بعد البيع</small><strong><?php echo number_format($return_request_counts['sold']); ?></strong></div></article>
            */ ?>
        </section>

        <div class="admin-home-widgets">
            <section class="admin-home-widget admin-return-widget" id="return-requests" aria-labelledby="return-requests-title">
                <header class="admin-home-widget__head">
                    <div class="admin-home-widget__title">
                        <span><i class="bi bi-arrow-return-left"></i></span>
                        <div><h2 id="return-requests-title">طلبات إرجاع العهد</h2><p>راجع بيانات العهدة والموظف .</p></div>
                    </div>
                    <?php if ($return_request_counts['pending'] > 0): ?><span class="admin-home-widget__counter"><?php echo number_format($return_request_counts['pending']); ?> طلب جديد</span><?php endif; ?>
                </header>

                <form class="admin-return-filters" method="get" action="<?php echo base_url().MOD_VALUE; ?>admin/index">
                    <div class="admin-return-filters__field">
                        <label for="filterEmployee"><i class="bi bi-person"></i> الموظف</label>
                        <select name="employee_id" id="filterEmployee">
                            <option value="">كل الموظفين</option>
                            <?php foreach ($return_request_employees as $employee_option): ?>
                                <option value="<?php echo (int) $employee_option['employee_id']; ?>" <?php echo (int) $return_filters['employee_id'] === (int) $employee_option['employee_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(!empty($employee_option['employee_name']) ? $employee_option['employee_name'] : 'موظف غير متاح', ENT_QUOTES, 'UTF-8'); ?><?php echo !empty($employee_option['employee_number']) ? ' - ' . htmlspecialchars((string) $employee_option['employee_number'], ENT_QUOTES, 'UTF-8') : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="admin-return-filters__field">
                        <label for="filterSerial"><i class="bi bi-upc-scan"></i> السيريال / الباركود</label>
                        <input type="search" name="serial" id="filterSerial" dir="ltr" maxlength="120" placeholder="اكتب السيريال أو الباركود" value="<?php echo htmlspecialchars((string) $return_filters['serial'], ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="admin-return-filters__actions">
                        <button type="submit" class="admin-return-filters__submit"><i class="bi bi-search"></i> بحث</button>
                        <?php if ($has_return_filters): ?>
                            <a class="admin-return-filters__reset" href="<?php echo base_url().MOD_VALUE; ?>admin/index#return-requests"><i class="bi bi-x-lg"></i> إلغاء</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (!empty($return_requests)): ?>
                    <div class="admin-return-list">
                        <?php foreach ($return_requests as $request): ?>
                            <?php
                            $status = isset($request['status']) ? $request['status'] : 'pending';
                            $number = !empty($request['serial_number']) ? $request['serial_number'] : $request['barcode'];
                            $is_pending = $status === 'pending';
                            ?>
                            <details class="admin-return-request <?php echo $is_pending ? 'is-pending' : 'is-resolved'; ?>">
                                <summary class="admin-return-request__summary">
                                    <span class="admin-return-request__avatar"><i class="bi bi-person"></i></span>
                                    <div class="admin-return-request__ident">
                                        <strong><?php echo htmlspecialchars(!empty($request['employee_name']) ? $request['employee_name'] : 'موظف غير متاح', ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <small><?php echo htmlspecialchars(!empty($request['item_description']) ? $request['item_description'] : $request['item_code'], ENT_QUOTES, 'UTF-8'); ?> <i class="bi bi-dot"></i> <b dir="ltr"><?php echo htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8'); ?></b></small>
                                    </div>
                                    <span class="return-status return-status--<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(isset($status_labels[$status]) ? $status_labels[$status] : $status, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <span class="admin-return-request__chevron" aria-hidden="true"><i class="bi bi-chevron-down"></i></span>
                                </summary>

                                <div class="admin-return-request__content">
                                    <div class="admin-return-request__body">
                                        <div class="admin-return-request__product">
                                            <span><i class="bi <?php echo (int) $request['custody_type'] === 2 ? 'bi-sim' : 'bi-phone'; ?>"></i></span>
                                            <div><h3><?php echo htmlspecialchars(!empty($request['item_description']) ? $request['item_description'] : $request['item_code'], ENT_QUOTES, 'UTF-8'); ?></h3><p><b dir="ltr"><?php echo htmlspecialchars((string) $request['item_code'], ENT_QUOTES, 'UTF-8'); ?></b> <i class="bi bi-dot"></i> <b dir="ltr"><?php echo htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8'); ?></b></p></div>
                                        </div>
                                        <dl class="admin-return-request__details">
                                            <div><dt><i class="bi bi-person-badge"></i> الرقم الوظيفي</dt><dd dir="ltr"><?php echo htmlspecialchars(!empty($request['employee_number']) ? $request['employee_number'] : '-', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                            <div><dt><i class="bi bi-receipt"></i> رقم الطلب</dt><dd dir="ltr"><?php echo htmlspecialchars(!empty($request['order_number']) ? $request['order_number'] : '-', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                            <div><dt><i class="bi bi-calendar3"></i> تاريخ استلام العهدة</dt><dd dir="ltr"><?php echo htmlspecialchars(!empty($request['custody_date']) ? $request['custody_date'] : '-', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                            <div><dt><i class="bi bi-clock-history"></i> تاريخ الطلب</dt><dd dir="ltr"><?php echo htmlspecialchars((string) $request['requested_at'], ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                            <div><dt><i class="bi bi-chat-left-text"></i> ملاحظة الموظف</dt><dd><?php echo htmlspecialchars(!empty($request['employee_note']) ? $request['employee_note'] : 'لا توجد ملاحظة', ENT_QUOTES, 'UTF-8'); ?></dd></div>
                                        </dl>
                                    </div>

                                    <?php if ($is_pending): ?>
                                        <?php echo form_open(base_url().MOD_VALUE.'admin/review_return_request', array('class' => 'admin-return-request__actions js-review-form')); ?>
                                            <input type="hidden" name="request_id" value="<?php echo (int) $request['id']; ?>">
                                            <input type="hidden" name="employee_id" value="<?php echo (int) $return_filters['employee_id']; ?>">
                                            <input type="hidden" name="serial" value="<?php echo htmlspecialchars((string) $return_filters['serial'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <label><span>رد الإدارة <small>(اختياري)</small></span><input type="text" name="admin_note" maxlength="500" placeholder="ملاحظة للموظف - اتركها فارغة لإرسال الرد الافتراضي"></label>
                                            <div>
                                                <button type="button" class="return-review-button return-review-button--approve js-review-action" data-decision="approve" data-request-label="<?php echo htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-check2"></i> قبول وإرجاع</button>
                                                <button type="button" class="return-review-button return-review-button--reject js-review-action" data-decision="reject" data-request-label="<?php echo htmlspecialchars((string) $number, ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-x-lg"></i> رفض</button>
                                            </div>
                                        <?php echo form_close(); ?>
                                    <?php else: ?>
                                        <div class="admin-return-request__resolution"><i class="bi bi-chat-square-text"></i><div><span>رد الإدارة</span><strong><?php echo htmlspecialchars(!empty($request['admin_note']) ? $request['admin_note'] : 'لا توجد ملاحظة', ENT_QUOTES, 'UTF-8'); ?></strong><?php if (!empty($request['reviewer_name'])): ?><small>بواسطة <?php echo htmlspecialchars($request['reviewer_name'], ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?></div></div>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <?php if ($has_return_filters): ?>
                        <div class="empty-admin-state admin-return-empty"><i class="bi bi-search"></i><h3>لا توجد نتائج مطابقة للبحث</h3><p>جرّب تغيير الموظف أو السيريال، أو ألغِ البحث لعرض كل الطلبات.</p></div>
                    <?php else: ?>
                        <div class="empty-admin-state admin-return-empty"><i class="bi bi-inbox"></i><h3>لا توجد طلبات إرجاع حتى الآن</h3><p>ستظهر هنا الطلبات التي يرسلها الموظفون من صفحة العهد.</p></div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
        </section>
        <?php endif; ?>

        <?php foreach ($visible_home_tabs as $tab_key => $tab): ?>
            <?php if ($tab_key === 'returns') continue; ?>
            <?php $is_active_panel = $tab_key === $first_visible_home_tab; ?>
            <section class="admin-home-tab-panel<?php echo $is_active_panel ? ' is-active' : ''; ?>" id="home-panel-<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>" role="tabpanel" aria-labelledby="home-tab-<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>" data-home-panel="<?php echo htmlspecialchars($tab_key, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $is_active_panel ? '' : ' hidden'; ?>>
                <div class="admin-home-coming-soon">
                    <span class="admin-home-coming-soon__icon"><i class="bi <?php echo htmlspecialchars($tab['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                    <span class="admin-home-coming-soon__eyebrow"><?php echo htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <h2>جاري العمل على الصفحة</h2>
                    <p><?php echo htmlspecialchars($tab['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if (empty($visible_home_tabs)): ?>
            <section class="admin-home-no-tabs" aria-label="لا توجد أقسام متاحة">
                <span><i class="bi bi-shield-lock"></i></span>
                <h2>لا توجد أقسام متاحة</h2>
                <p>لم يتم منح هذا الدور صلاحية عرض أي تبويب في الصفحة الرئيسية.</p>
            </section>
        <?php endif; ?>
    </main>
</div>
</div>

<div class="return-review-modal" id="returnReviewModal" aria-hidden="true">
    <div class="return-review-modal__backdrop js-close-review"></div>
    <div class="return-review-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="review-modal-title">
        <span class="return-review-modal__icon"><i class="bi bi-question-lg"></i></span>
        <h2 id="review-modal-title">تأكيد القرار</h2>
        <p id="reviewModalMessage"></p>
        <div class="return-review-modal__actions"><button type="button" class="secondary js-close-review">تراجع</button><button type="button" class="primary" id="confirmReviewDecision">تأكيد</button></div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script>
(function () {
    var tabs = Array.prototype.slice.call(document.querySelectorAll('[data-home-tab]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('[data-home-panel]'));

    if (!tabs.length || !panels.length) return;

    function activateTab(tabName, moveFocus, updateHash) {
        var activeTab = null;

        tabs.forEach(function (tab) {
            var isActive = tab.getAttribute('data-home-tab') === tabName;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
            if (isActive) activeTab = tab;
        });

        panels.forEach(function (panel) {
            var isActive = panel.getAttribute('data-home-panel') === tabName;
            panel.classList.toggle('is-active', isActive);
            panel.hidden = !isActive;
        });

        if (moveFocus && activeTab) activeTab.focus();
        if (updateHash && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + tabName);
        }
    }

    tabs.forEach(function (tab, index) {
        tab.addEventListener('click', function () {
            activateTab(tab.getAttribute('data-home-tab'), false, true);
        });

        tab.addEventListener('keydown', function (event) {
            var nextIndex = null;
            if (event.key === 'ArrowLeft') nextIndex = (index + 1) % tabs.length;
            if (event.key === 'ArrowRight') nextIndex = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = tabs.length - 1;
            if (nextIndex === null) return;
            event.preventDefault();
            activateTab(tabs[nextIndex].getAttribute('data-home-tab'), true, true);
        });
    });

    function activateFromHash() {
        var tabName = window.location.hash.replace('#', '');
        var matchingTab = tabs.some(function (tab) { return tab.getAttribute('data-home-tab') === tabName; });
        activateTab(matchingTab ? tabName : tabs[0].getAttribute('data-home-tab'), false, false);
    }

    activateFromHash();
    window.addEventListener('hashchange', activateFromHash);
}());

(function () {
    var modal = document.getElementById('returnReviewModal');
    var message = document.getElementById('reviewModalMessage');
    var confirmButton = document.getElementById('confirmReviewDecision');
    var activeForm = null;
    var activeDecision = '';

    function closeModal() {
        modal.classList.remove('is-open', 'is-approve', 'is-reject');
        modal.setAttribute('aria-hidden', 'true');
        activeForm = null;
        activeDecision = '';
    }

    document.querySelectorAll('.js-review-action').forEach(function (button) {
        button.addEventListener('click', function () {
            activeForm = button.closest('form');
            activeDecision = button.getAttribute('data-decision');
            var number = button.getAttribute('data-request-label');
            modal.classList.add('is-open', activeDecision === 'approve' ? 'is-approve' : 'is-reject');
            modal.setAttribute('aria-hidden', 'false');
            message.textContent = activeDecision === 'approve'
                ? 'سيتم إخراج العهدة ' + number + ' من الموظف وإعادتها إلى المخزون. هل تريد المتابعة؟'
                : 'سيظل المنتج في عهدة الموظف وسيصله رد الرفض. هل تريد المتابعة؟';
            confirmButton.textContent = activeDecision === 'approve' ? 'نعم، قبول وإرجاع' : 'نعم، رفض الطلب';
        });
    });
    document.querySelectorAll('.js-close-review').forEach(function (button) { button.addEventListener('click', closeModal); });
    confirmButton.addEventListener('click', function () {
        if (!activeForm || !activeDecision) return;
        var decisionInput = document.createElement('input');
        decisionInput.type = 'hidden';
        decisionInput.name = 'decision';
        decisionInput.value = activeDecision;
        activeForm.appendChild(decisionInput);
        confirmButton.disabled = true;
        activeForm.submit();
    });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });
}());
</script>
<?php $this->load->view('view_footer'); ?>

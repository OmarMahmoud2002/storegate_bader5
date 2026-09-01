<?php
$page_title = 'الأجهزة المباعة';
$this->view('view_header');
$this->view('view_user_sidebar');

$sold_devices = isset($sold_devices) && is_array($sold_devices) ? $sold_devices : array();
$sold_devices_total = 0;
foreach ($sold_devices as $sold_device) {
    $sold_devices_total += (float) $sold_device['insert_excel_sales_amount'];
}

$filter_query = http_build_query(array(
    'from_date' => $fromdate,
    'to_date' => $todate,
));
?>

<div class="app-content admin-ui-page admin-ui-user-sales">
    <div class="app-content-header user-page-heading">
        <div>
            <h1 class="app-content-headerText">الأجهزة المباعة</h1>
            <!-- <p>يعرض الجدول مبيعاتك المسجلة من ملفات التقفيلة والمبيعات المرفوعة بواسطة الإدارة.</p> -->
        </div>
    </div>

    <div class="user-sales-summary" aria-label="ملخص المبيعات المحددة">
        <div class="user-sales-summary__card">
            <span class="user-sales-summary__icon"><i class="bi bi-phone"></i></span>
            <div>
                <span>عدد الأجهزة</span>
                <strong><?php echo number_format(count($sold_devices)); ?></strong>
            </div>
        </div>
        <div class="user-sales-summary__card">
            <span class="user-sales-summary__icon user-sales-summary__icon--amount"><i class="bi bi-cash-stack"></i></span>
            <div>
                <span>إجمالي مبلغ المبيعات</span>
                <strong dir="ltr"><?php echo number_format($sold_devices_total, 2); ?></strong>
            </div>
        </div>
    </div>

    <form class="user-sales-filter" method="get" action="<?php echo base_url().MOD_VALUE; ?>user/products">
        <div class="user-sales-filter__title">
            <i class="bi bi-funnel"></i>
            <div>
                <strong>فلترة النتائج</strong>
                <span>الفترة الافتراضية من أول الشهر الحالي حتى اليوم</span>
            </div>
        </div>
        <div class="user-sales-filter__fields">
            <label>
                <span><i class="bi bi-calendar3"></i> من تاريخ</span>
                <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromdate, ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>
            <label>
                <span><i class="bi bi-calendar-check"></i> إلى تاريخ</span>
                <input type="date" name="to_date" value="<?php echo htmlspecialchars($todate, ENT_QUOTES, 'UTF-8'); ?>" required>
            </label>
        </div>
        <div class="user-sales-filter__actions">
            <button type="submit" class="user-sales-button user-sales-button--primary">
                <i class="bi bi-search"></i>
                عرض النتائج
            </button>
            <a class="user-sales-button user-sales-button--secondary" href="<?php echo base_url().MOD_VALUE; ?>user/products">
                <i class="bi bi-arrow-counterclockwise"></i>
                الشهر الحالي
            </a>
            <a class="user-sales-button user-sales-button--export" href="<?php echo base_url().MOD_VALUE; ?>user/export_products?<?php echo htmlspecialchars($filter_query, ENT_QUOTES, 'UTF-8'); ?>">
                <i class="bi bi-file-earmark-excel"></i>
                تحميل Excel
            </a>
        </div>
    </form>

    <section class="user-sales-table-card" aria-labelledby="sold-devices-table-title">
        <div class="user-sales-table-card__heading">
            <div>
                <h2 id="sold-devices-table-title">سجل الأجهزة المباعة</h2>
                <p>من <?php echo htmlspecialchars($fromdate, ENT_QUOTES, 'UTF-8'); ?> إلى <?php echo htmlspecialchars($todate, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <span><?php echo number_format(count($sold_devices)); ?> عملية بيع</span>
        </div>

        <?php if (!empty($sold_devices)): ?>
            <div class="products-area-wrapper tableView user-sales-table">
                <div class="products-header">
                    <div class="product-cell sold-date">التاريخ</div>
                    <div class="product-cell sold-order">رقم الطلب</div>
                    <div class="product-cell sold-type">النوع</div>
                    <div class="product-cell sold-description">الوصف</div>
                    <div class="product-cell sold-serial">PRODUCT SERIAL NUMBER</div>
                    <div class="product-cell sold-payment">مبلغ المبيعات</div>
                </div>

                <?php foreach ($sold_devices as $sold_device): ?>
                    <?php
                    $display_order_number = !empty($sold_device['insert_excel_new_ordern'])
                        ? $sold_device['insert_excel_new_ordern']
                        : $sold_device['insert_excel_ordern'];
                    $sales_type = !empty($sold_device['insert_excel_sales_type']) ? $sold_device['insert_excel_sales_type'] : '-';
                    $description = !empty($sold_device['insert_excel_description']) ? $sold_device['insert_excel_description'] : '-';
                    $serial_number = !empty($sold_device['insert_excel_product_serial_number']) ? $sold_device['insert_excel_product_serial_number'] : '-';
                    $sold_date_timestamp = !empty($sold_device['insert_excel_date']) ? strtotime($sold_device['insert_excel_date']) : false;
                    $sold_date = $sold_date_timestamp ? date('Y-m-d', $sold_date_timestamp) : '-';
                    ?>
                    <div class="products-row">
                        <div class="product-cell sold-date">
                            <span class="cell-label">التاريخ</span>
                            <span dir="ltr"><?php echo htmlspecialchars($sold_date, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell sold-order">
                            <span class="cell-label">رقم الطلب</span>
                            <span dir="ltr"><?php echo htmlspecialchars((string) $display_order_number, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell sold-type">
                            <span class="cell-label">النوع</span>
                            <span class="user-sales-type-badge"><?php echo htmlspecialchars($sales_type, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell sold-description">
                            <span class="cell-label">الوصف</span>
                            <span><?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell sold-serial">
                            <span class="cell-label">PRODUCT SERIAL NUMBER</span>
                            <span dir="ltr"><?php echo htmlspecialchars($serial_number, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                        <div class="product-cell sold-payment">
                            <span class="cell-label">مبلغ المبيعات</span>
                            <span class="user-sales-amount" dir="ltr"><?php echo number_format((float) $sold_device['insert_excel_sales_amount'], 2); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <nav class="user-sales-pagination" id="userSalesPagination" aria-label="صفحات الأجهزة المباعة">
                <button type="button" id="userSalesPrev"><i class="bi bi-chevron-right"></i> السابق</button>
                <span id="userSalesPageInfo"></span>
                <button type="button" id="userSalesNext">التالي <i class="bi bi-chevron-left"></i></button>
            </nav>
        <?php else: ?>
            <div class="empty-admin-state user-sales-empty">
                <i class="bi bi-box-seam"></i>
                <h3>لا توجد أجهزة مباعة في الفترة المحددة</h3>
                <p>غيّر نطاق التاريخ أو انتظر رفع أحدث ملف مبيعات من الإدارة.</p>
            </div>
        <?php endif; ?>
    </section>
</div>
</div>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/home.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script>
(function () {
    var rows = Array.prototype.slice.call(document.querySelectorAll('.user-sales-table .products-row'));
    var pagination = document.getElementById('userSalesPagination');
    var previousButton = document.getElementById('userSalesPrev');
    var nextButton = document.getElementById('userSalesNext');
    var pageInfo = document.getElementById('userSalesPageInfo');
    var pageSize = 20;
    var currentPage = 1;
    var totalPages = Math.max(1, Math.ceil(rows.length / pageSize));

    if (!rows.length || !pagination) {
        return;
    }

    function renderPage() {
        var firstRow = (currentPage - 1) * pageSize;
        var lastRow = firstRow + pageSize;

        rows.forEach(function (row, index) {
            row.style.display = index >= firstRow && index < lastRow ? '' : 'none';
        });

        pagination.style.display = totalPages > 1 ? 'flex' : 'none';
        previousButton.disabled = currentPage === 1;
        nextButton.disabled = currentPage === totalPages;
        pageInfo.textContent = 'صفحة ' + currentPage + ' من ' + totalPages;
    }

    previousButton.addEventListener('click', function () {
        if (currentPage > 1) {
            currentPage--;
            renderPage();
        }
    });

    nextButton.addEventListener('click', function () {
        if (currentPage < totalPages) {
            currentPage++;
            renderPage();
        }
    });

    renderPage();
}());
</script>
<?php $this->view('view_footer'); ?>

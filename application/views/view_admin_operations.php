<title>العمليات</title>
<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar');?>

<div class="app-content admin-ui-page admin-ui-product-operations">
    <div class="operations-shell">
        <header class="operations-hero">
            <div class="operations-title">
                <span class="operations-title-icon" aria-hidden="true">
                    <i class="bi bi-clock-history"></i>
                </span>
                <div>
                    <h1>تفاصيل المنتج</h1>
                    <p>ابحث بالرقم التسلسلي .</p>
                </div>
            </div>
            <form class="input-section operations-search-form" method="get">
                <label for="serial-number">أدخل الرقم التسلسلي</label>
                <div class="operations-search-control">
                    <i class="bi bi-upc-scan" aria-hidden="true"></i>
                    <input type="text" id="serial-number" name="serial" value="<?=htmlspecialchars($serial ? $serial : '')?>" placeholder="أدخل الرقم التسلسلي">
                    <button type="submit">
                        <i class="bi bi-search"></i>
                        <span>بحث</span>
                    </button>
                </div>
            </form>
        </header>

        <section class="top-container operations-panel">
            <div class="operations-section-heading">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                <h2>تفاصيل البند</h2>
            </div>
            <div class="top-header operations-info-grid">
                <div class="box-content operations-info-item">
                    <label>رقم الصنف</label>
                    <span class="iteme"><?=htmlspecialchars((string) ($info['code'] !== '' ? $info['code'] : (!empty($serial) ? 'غير متوفر في البيانات الحالية' : '')))?></span>
                </div>
                <div class="box-content operations-info-item">
                    <label>اسم المنتج</label>
                    <span class="iteme"><?=htmlspecialchars((string) ($info['name'] !== '' ? $info['name'] : (!empty($serial) ? 'غير متوفر في البيانات الحالية' : '')))?></span>
                </div>
                <div class="box-content operations-info-item">
                    <label>الرقم التسلسلي</label>
                    <span class="iteme"><?=htmlspecialchars(strtoupper($serial ? $serial : ''))?></span>
                </div>
                <?php if (!empty($serial)) { ?>
                    <div class="box-content operations-info-item">
                        <label>الحالة</label>
                        <span class="iteme">
                            <?php if (!empty($last_sale)) { ?>
                                <span class="operations-status-badge operations-status-sold">مباع</span>
                            <?php } else { ?>
                                <span class="operations-status-badge operations-status-instock">غير مسجل كمباع</span>
                            <?php } ?>
                        </span>
                    </div>
                    <div class="box-content operations-info-item">
                        <label>حالة المخزون الحالية</label>
                        <span class="iteme">
                            <?php if ($stock_state === 'in') { ?>
                                <span class="operations-status-badge operations-status-stock-in">موجود في المخزون</span>
                            <?php } elseif ($stock_state === 'out') { ?>
                                <span class="operations-status-badge operations-status-stock-out">خرج من المخزون</span>
                            <?php } else { ?>
                                <span class="operations-status-badge operations-status-instock">غير متاح</span>
                            <?php } ?>
                        </span>
                    </div>
                    <div class="box-content operations-info-item">
                        <label>نوع المخزون (Subinventory Code)</label>
                        <span class="iteme"><?=htmlspecialchars(!empty($info['subinventory_code']) ? (string) $info['subinventory_code'] : 'غير متاح')?></span>
                    </div>
                <?php } ?>
            </div>
        </section>

        <?php if (!empty($last_sale)) { ?>
            <section class="sale-details operations-panel operations-sale-panel">
                <div class="operations-section-heading">
                    <i class="bi bi-cart-check" aria-hidden="true"></i>
                    <h2>بيانات البيع </h2>
                </div>
                <div class="top-header operations-info-grid">
                    <div class="box-content operations-info-item">
                        <label>رقم الطلب</label>
                        <span class="iteme"><?=htmlspecialchars((string) ($last_sale['order_number'] !== '' ? $last_sale['order_number'] : 'غير محدد'))?></span>
                    </div>
                    <div class="box-content operations-info-item">
                        <label>اسم الموظف</label>
                        <span class="iteme"><?=htmlspecialchars((string) $last_sale['employee_name'])?></span>
                    </div>
                    <div class="box-content operations-info-item">
                        <label>تاريخ البيع</label>
                        <?php $last_sale_time = strtotime((string) $last_sale['date']); ?>
                        <span class="iteme"><?=htmlspecialchars($last_sale_time ? date('d-m-Y', $last_sale_time) : (string) $last_sale['date'])?></span>
                    </div>
                    <div class="box-content operations-info-item">
                        <label>نوع المبيعات</label>
                        <span class="iteme"><?=htmlspecialchars((string) ($last_sale['sales_type'] !== '' ? $last_sale['sales_type'] : 'غير محدد'))?></span>
                    </div>
                </div>
                <?php if (count($sales) > 1) { ?>
                    <p class="operations-sale-note">يوجد <?=count($sales)?> سجلات بيع لهذا الرقم التسلسلي، جميعها موضحة في سجل العمليات بالأسفل.</p>
                <?php } ?>
            </section>
        <?php } ?>

        <section class="transaction-details operations-panel">
            <div class="operations-section-heading">
                <i class="bi bi-list-check" aria-hidden="true"></i>
                <h2>تفاصيل العملية</h2>
            </div>
            <div class="table-container operations-table-wrap">
                <table class="operations-table">
                    <thead>
                        <tr>
                            <th>التاريخ</th>
                            <th>طلب المبيعات / ملف المخزون</th>
                            <th>نوع العملية</th>
                            <th class="operation-movement-heading">العدد</th>
                            <th>تم بواسطة / البائع</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($timeline as $entry) {
                            $movement_value = $entry['movement'];
                            $movement_class = '';

                            if ($movement_value === '1') {
                                $movement_class = 'operation-movement-in';
                            } elseif ($movement_value === '-1') {
                                $movement_class = 'operation-movement-out';
                            }

                            // سجلات التقفيلة تحتوي على تاريخ فقط بدون وقت
                            $entry_time = strtotime((string) $entry['date']);
                            $entry_date_format = $entry['source'] === 'sale' ? 'd-m-Y' : 'd-m-Y h:i:s A';
                            $entry_date = $entry_time ? date($entry_date_format, $entry_time) : (string) $entry['date'];

                            $operation_label = (string) $entry['operation'];
                            if ($entry['source'] === 'sale' && $entry['sales_type'] !== '') {
                                $operation_label .= ' - ' . $entry['sales_type'];
                            }
                            if ($entry['source'] === 'sale' && (float) $entry['sales_amount'] > 0) {
                                $operation_label .= ' - مبلغ البيع: ' . number_format((float) $entry['sales_amount'], 2);
                            }

                            $sales_order = trim((string) $entry['sales_order']);
                            if ($entry['source'] === 'sale' && $sales_order === '') {
                                $sales_order = 'غير محدد';
                            }
                        ?>
                            <tr class="<?=$entry['source'] === 'sale' ? 'operations-row-sale' : ($entry['source'] === 'stock' ? 'operations-row-stock' : '')?>">
                                <td><?=htmlspecialchars($entry_date)?></td>
                                <td><?=htmlspecialchars($sales_order)?></td>
                                <td><?=htmlspecialchars($operation_label)?></td>
                                <td class="operation-movement-cell">
                                    <?php if ($movement_value !== '') { ?>
                                        <span class="operation-movement-badge <?=$movement_class?>"><?=$movement_value?></span>
                                    <?php } ?>
                                </td>
                                <td><?=htmlspecialchars((string) $entry['user_name'])?></td>
                            </tr>
                        <?php } ?>
                        <?php if (empty($timeline)) { ?>
                            <tr class="operations-empty-row">
                                <td colspan="5">
                                    <i class="bi bi-inbox"></i>
                                    <span>لا توجد عمليات لعرضها حالياً</span>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>

<?php $this->load->view('view_footer'); ?>

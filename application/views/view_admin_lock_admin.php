<title>التقفيلة</title>
<?php


$this->view('view_header'); ?>
<link rel="stylesheet" href="<?=base_url('/public/css/lock_admin.css')?>?a=<?php $date=date_create();echo date_timestamp_get($date);?>">


 <?php
$CI =& get_instance();
$CI->load->model('Model_admin');
$CI->load->model('Model_lock_extras');
$CI->load->helper('lock_admin');
$size = 17;
$can_edit_lock_extras = $CI->get_permission_enabled('lock_admin.update');
$can_download_lock = $CI->get_permission_enabled('lock_admin.download');
?>

	<?php $this->view('view_admin_sidebar'); ?>

    <div class="app-content admin-ui-page admin-ui-lock-admin">
        <div class="app-content-header">
            <h1 class="app-content-headerText">التقفيلة</h1>
        </div>
      <div class="lock-c">
        <div class="app-content-actions">
          <input class="search-bar" placeholder="الموظف" type="text">
          
          <form id="search-form" method="get" action="" style = 'margin-block-end: 0px;'>
              <div class="date-range">
                <span>من</span>
                <input type="date" name = 'date_start' id="startDate">
                <span>إلى</span>
                <input type="date" name = 'date_end' id="endDate">
                <button type= 'submit' class = 'updateDate'><i class = 'fa fa-refresh'></i></button>
              </div>
              
          </form>

          <div class="app-content-actions-wrapper">
              <button class="btn-upload" onclick="window.location.href='<?php echo base_url().MOD_VALUE.'admin/upload-form'; ?>'"><i class="fa fa-upload" style="margin-left: 10px;"></i>
      رفع مبيعات تواصل</button>
          </div>
        </div>
        <div class="salesReport">
    <?php
    //echo date('Y-m-d');
    // تعيين التواريخ بناءً على القيم المرسلة من النموذج
    $date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-m-d');
    $date_end = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-d');

    // استدعاء بيانات الموظفين مرة واحدة
    $employees = $this->Model_admin->getemployees();

    // تحديد عدد الموظفين المطلوب عرضهم
    $number_of_employees_to_display = $size;
    $employees_to_display = array();
    
    // قم بإنشاء مصفوفات لتخزين البيانات
    $twasel_sales = [];
    $electronic_sales = [];
    $jowy_sales = [];
    $quickplus_sales = [];
    $network_amounts = [];
    $cash_amounts = [];
    $settlements = [];
    $total_sales = [];

    // حساب البيانات لكل موظف
    foreach ($employees as $row) {
        if(count($employees_to_display) == $size)
        break;
        $user_id = $row['user_id'];

        if ($date_start && $date_end) {

        
            // استدعاء البيانات لمبيعات تواصل
            $excel_data = $this->Excel_import_model->selectbyid($user_id, $date_start, $date_end);
            $settlement_data = $this->Model_admin->get_settlement($user_id, $date_start, $date_end);

            $twasel_sales[$user_id] = 0; // تعيين القيمة الابتدائية للصفر
            $electronic_sales[$user_id] = 0;
            $jowy_sales[$user_id] = 0;
            $quickplus_sales[$user_id] = 0;
            foreach ($excel_data as $excel) {
                $twasel_sales[$user_id] += (float)($excel['insert_excel_twasel']);
                $electronic_sales[$user_id] += (float)($excel['insert_excel_electronic']);
                $jowy_sales[$user_id] += (float)($excel['insert_excel_jowy']);
                $quickplus_sales[$user_id] += (float)($excel['insert_excel_quickplus']);
            }
            
            $settlements[$user_id] = 0;
            
            foreach ($settlement_data as $settlement) {
                $settlements[$user_id] += (float)($settlement['settlement_amount']);
            }

            // حساب الإجمالي لكل موظف
            $total_sales[$user_id] = $twasel_sales[$user_id] + $electronic_sales[$user_id]+ $jowy_sales[$user_id]+ $quickplus_sales[$user_id];
            if(count($employees_to_display) < $size && $total_sales[$user_id] > 0)
            {
                array_push($employees_to_display,$row);
            
                // استدعاء بيانات الشبكة والنقدي
                $userlock = $this->Model_admin->getlock_by_userid($user_id, $date_start, $date_end);
                $network_amounts[$user_id] = isset($userlock['user_lock_span']) ? (float) $userlock['user_lock_span'] : 0;
                $cash_amounts[$user_id] = isset($userlock['user_lock_cash']) ? (float) $userlock['user_lock_cash'] : 0;
            }
        } else {
            // إذا لم يكن هناك تواريخ محددة، استخدم القيم الافتراضية (يمكن تعديلها حسب الحاجة)
            $twasel_sales[$user_id] = 0;
            $electronic_sales[$user_id] = 0;
            $jowy_sales[$user_id] = 0;
            $quickplus_sales[$user_id] = 0;
            $network_amounts[$user_id] = 0;
            $cash_amounts[$user_id] = 0;
            $total_sales[$user_id] = 0;
        }
    }

    // حساب الإجمالي العام
    $total_twasel = array_sum($twasel_sales);
    $total_electronic = array_sum($electronic_sales);
    $total_jowy = array_sum($jowy_sales);
    $total_quickplus = array_sum($quickplus_sales);
    $grand_total_sales = array_sum($total_sales);
    $total_network = array_sum($network_amounts);
    $total_cash = array_sum($cash_amounts);

    // المبالغ اليدوية (تمارا / المحفظة / رصيد إضافي) لكل الموظفين المعروضين في استعلام واحد
    $display_user_ids = array();
    foreach ($employees_to_display as $row) {
        $display_user_ids[] = (int) $row['user_id'];
    }
    $extras = $this->Model_lock_extras->get_totals_map($display_user_ids, $date_start, $date_end);
    foreach ($display_user_ids as $display_user_id) {
        if (!isset($extras[$display_user_id])) {
            $extras[$display_user_id] = $this->Model_lock_extras->empty_amounts();
        }
    }

    $extra_rows = array(
        'tamara' => 'تمارا',
        'wallet' => 'المحفظة',
        'extra_balance' => 'رصيد إضافي',
    );
    $extra_totals = array();
    foreach ($extra_rows as $extra_key => $extra_label) {
        $extra_totals[$extra_key] = 0;
        foreach ($display_user_ids as $display_user_id) {
            $extra_totals[$extra_key] += $extras[$display_user_id][$extra_key];
        }
    }
    ?>

    <div class="title-box">
        <p id="dateRangeText" style="margin: 8px 0 0; opacity: 0.9; font-size: 16px;">
            <?php 
                if(isset($_GET['date_start'])):
                    echo 'من تاريخ '. $date_start .' الي'. $date_end;
                endif;
            ?>
        </p>
    </div>

    <?php
     if(count($employees_to_display) > 0){
    ?>
    <table id = 'salesTable'>
        <thead>
            <tr>
                <th  rowspan="2" colspan="2" class = 'category'>الصنف</th>
                <!-- Days -->
                <?php $index = 1;?>
                <?php foreach ($employees_to_display as $row): ?>
                    
                    <th>
                        <?php echo htmlspecialchars($row['user_fillname']); ?>
                        <i class="fa fa-search search-icon" data-col = "<?php echo $index; ?>" data-uid="<?php echo (int) $row['user_id']; ?>" data-name="<?php echo htmlspecialchars($row['user_fillname']); ?>"></i>
                    </th>
                    <?php $index++; ?>
                <?php endforeach; ?>
                <th  rowspan="2">إجمالي المبيعات</th>
            </tr>
            <tr >
              
                <?php foreach ($employees_to_display as $row): ?>
                    <!--<th ><?php //echo htmlspecialchars($row['user_employee_Id']); ?></th>-->
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="white-space: nowrap;" colspan="2" class = 'category'>مبيعات تواصل</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <td class = '<?php echo $twasel_sales[$row['user_id']] > 0 ? 'clickable' : 'zero-value'; ?>'><?php echo number_format(isset($twasel_sales[$row['user_id']]) ? $twasel_sales[$row['user_id']] : 0, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $total_twasel > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_twasel, 2); ?></td>
            </tr>
            <tr>
                <td style="white-space: nowrap;" colspan="2" class = 'category'>شحن إلكتروني</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <td class = '<?php echo $electronic_sales[$row['user_id']] > 0 ? 'clickable' : 'zero-value'; ?>'><?php echo number_format(isset($electronic_sales[$row['user_id']]) ? $electronic_sales[$row['user_id']] : 0, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $total_electronic > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_electronic, 2); ?></td>
            </tr>
            <tr>
                <td style="white-space: nowrap;" colspan="2" class = 'category'>مبيعات جوي</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <td class = '<?php echo $jowy_sales[$row['user_id']] > 0 ? 'clickable' : 'zero-value'; ?>'><?php echo number_format(isset($jowy_sales[$row['user_id']]) ? $jowy_sales[$row['user_id']] : 0, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $total_jowy > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_jowy, 2); ?></td>
            </tr>
            <tr>
                <td style="white-space: nowrap;" colspan="2" class = 'category'>مبيعات كويك بلس</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <td class = '<?php echo $quickplus_sales[$row['user_id']] > 0 ? 'clickable' : 'zero-value'; ?>'><?php echo number_format(isset($quickplus_sales[$row['user_id']]) ? $quickplus_sales[$row['user_id']] : 0, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $total_quickplus > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_quickplus, 2); ?></td>
            </tr>
            <tr class="total-row summary-row no-hover">
                <td colspan="2" class = 'category'>الإجمالي</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <td class = '<?php echo $total_sales[$row['user_id']] > 0 ? 'clickable' : 'zero-value'; ?>'><?php echo number_format(isset($total_sales[$row['user_id']]) ? $total_sales[$row['user_id']] : 0, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $grand_total_sales > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($grand_total_sales, 2); ?></td>
            </tr>
            <tr>
                <td colspan="2" class = 'category'>مبلغ الشبكة</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <td class = '<?php echo $network_amounts[$row['user_id']] > 0 ? 'clickable' : 'zero-value'; ?>'><?php echo number_format(isset($network_amounts[$row['user_id']]) ? $network_amounts[$row['user_id']] : 0, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $total_network > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_network, 2); ?></td>
            </tr>
            <tr>
                <td colspan="2" class = 'category'>النقدي</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <td class = '<?php echo $cash_amounts[$row['user_id']] > 0 ? 'clickable' : 'zero-value'; ?>'><?php echo number_format(isset($cash_amounts[$row['user_id']]) ? $cash_amounts[$row['user_id']] : 0, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $total_cash > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_cash, 2); ?></td>
            </tr>
            <tr class="total-row payment-row no-hover">
                <td colspan="2" class = 'category'>إجمالي المدفوع</td>
                <?php foreach ($employees_to_display as $row): ?>
                    <?php
                    $user_id = $row['user_id'];
                    $cash_amount = isset($cash_amounts[$user_id]) ? $cash_amounts[$user_id] : 0;
                    $network_amount = isset($network_amounts[$user_id]) ? $network_amounts[$user_id] : 0;
                    $total_payment = $cash_amount + $network_amount;
                    ?>
                    <td class = '<?php echo $total_payment > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_payment, 2); ?></td>
                <?php endforeach; ?>
                <td class = '<?php echo $total_cash + $total_network > 0 ? 'clickable' : 'zero-value'?>'><?php echo number_format($total_cash + $total_network, 2); ?></td>
            </tr>
            <?php foreach ($extra_rows as $extra_key => $extra_label): ?>
            <tr class="extra-row" data-extra-key="<?php echo $extra_key; ?>">
              <td colspan="2" class = 'category'><?php echo $extra_label; ?></td>
              <?php foreach ($employees_to_display as $row): ?>
                <?php $extra_amount = $extras[(int) $row['user_id']][$extra_key]; ?>
                <td class = '<?php echo $extra_amount > 0 ? 'clickable' : 'zero-value'; ?>'><?=number_format($extra_amount, 2)?></td>
              <?php endforeach; ?>
              <td class = '<?php echo $extra_totals[$extra_key] > 0 ? 'clickable' : 'zero-value'; ?>'><?=number_format($extra_totals[$extra_key], 2)?></td>
            </tr>
            <?php endforeach; ?>
            <tr class = 'difference-row no-hover'>
                <td colspan = "2" class = 'category'>الفروقات</td>
                <?php
                $total_differences = 0;
                foreach ($employees_to_display as $row):
                    $user_id = $row['user_id'];
                    $employee_extras = $extras[(int) $user_id];

                    $difference = lock_admin_difference(array(
                        'cash' => isset($cash_amounts[$user_id]) ? $cash_amounts[$user_id] : 0,
                        'network' => isset($network_amounts[$user_id]) ? $network_amounts[$user_id] : 0,
                        'tamara' => $employee_extras['tamara'],
                        'wallet' => $employee_extras['wallet'],
                        'extra_balance' => $employee_extras['extra_balance'],
                        'total_sales' => isset($total_sales[$user_id]) ? $total_sales[$user_id] : 0,
                        'settlements' => isset($settlements[$user_id]) ? $settlements[$user_id] : 0,
                    ));
                    $total_differences += $difference;

                    $class = lock_admin_difference_class($difference);
                    ?>
                    <td class="<?php echo 'total ' . $class; ?>" style = 'direction:ltr'><?php echo number_format($difference, 2); ?></td>
                <?php endforeach; ?>
                <td class="<?php echo 'total ' . lock_admin_difference_class($total_differences); ?>" style = 'direction:ltr'>
                    <?php echo number_format($total_differences, 2); ?>
                </td>
            </tr>
        </tbody>
    </table>
    <?php
      }
      else{
        echo '<h4>حسب الفترة المحددة توجد بيانات صفرية </h4>';
      }
    ?>
</div>
</div>
</div>        
</div>

<div class="popup-overlay" id="detailsPopup">
    <div class="details-popup-container">
      <div class="details-popup-header">
        <div class="close-btn" id="detailsPopupClose"></div>
        <h3 class="details-popup-title" id="employeeName">صلاح</h3>
      </div>
      <div class="details-popup-content">
        <div class="stats-container">
          <div class="stat-box success-stat-box">
            <div class="stat-title success-stat-title">العمليات الناجحة</div>
            <div class="stat-value">عدد العمليات: 8</div>
            <div class="stat-value">إجمالي المبلغ: 523.50</div>
          </div>
          <div class="stat-box failed-stat-box">
            <div class="stat-title failed-stat-title">العمليات غير الناجحة</div>
            <div class="stat-value">عدد العمليات: 1</div>
            <div class="stat-value">إجمالي المبلغ: 100.00</div>
          </div>
        </div>
        
        <table class="details-table">
          <thead>
            <tr>
              <th>التاريخ</th>
              <th>السعر</th>
              <th>الحالة</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Jul 01, 2025</td>
              <td>66.00</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Feb 20, 2025</td>
              <td>60.00</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Feb 15, 2025</td>
              <td>57.50</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Feb 15, 2025</td>
              <td>80.00</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Feb 15, 2025</td>
              <td>80.00</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Feb 15, 2025</td>
              <td>80.00</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Feb 15, 2025</td>
              <td>80.00</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Feb 15, 2025</td>
              <td>80.00</td>
              <td class="success-status">ناجحة</td>
            </tr>
            <tr>
              <td>Jul 1, 2024</td>
              <td>100.00</td>
              <td class="failed-status">فاشلة</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
</div>

<!-- النافذة المنبثقة الأصلية (البحث) -->
<div class="popup-overlay" id="popupOverlay"
     data-date-start="<?php echo htmlspecialchars($date_start); ?>"
     data-date-end="<?php echo htmlspecialchars($date_end); ?>"
     data-can-edit="<?php echo $can_edit_lock_extras ? '1' : '0'; ?>"
     data-save-url="<?php echo base_url().MOD_VALUE.'admin/lock-admin-save-extra'; ?>">
    <div class="search-popup-container">
        <div class="search-popup-header">
            <div class="search-popup-title" id="popupTitle">تفاصيل الموظف</div>
            <div class="search-popup-header-actions">
                <?php if ($can_download_lock): ?>
                <a class="popup-export-btn" id="popupExportBtn" href="#">
                    <i class="fa fa-file-excel"></i>
                    <span>تحميل إكسيل</span>
                </a>
                <?php endif; ?>
                <div class="search-popup-close" id="popupClose">&times;</div>
            </div>
        </div>
        <div class="search-popup-content">
            <div class="popup-save-status" id="popupSaveStatus"></div>
            <table class="mini-table" id="popupTable">
                <thead>
                <tr>
                    <th class="category">الصنف</th>
                    <th>القيمة</th>
                </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
            <?php if ($can_edit_lock_extras): ?>
            <p class="popup-edit-hint">
                القيمة الجديدة تستبدل إجمالي الفترة المحددة ولا تُضاف إلى القيمة السابقة.
            </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    $(function() {
        $('#date_range').daterangepicker({
            
            locale: {
                format: 'YYYY-MM-DD'
            }
        });
    });
</script>
		
<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>

 
   
<?php $this->view('view_footer'); ?>

 <script>
    $(function() {
        
        // إرسال النموذج تلقائيًا عند اختيار تاريخ
        $('#date_range').on('apply.daterangepicker', function(ev, picker) {
            // الحصول على التواريخ المختارة
            var startDate = picker.startDate.format('YYYY-MM-DD');
            var endDate = picker.endDate.format('YYYY-MM-DD');

            // تعيين قيم التواريخ في النموذج
            $('<input>').attr({
                type: 'hidden',
                name: 'date_start',
                value: startDate
            }).appendTo('#search-form');
            $('<input>').attr({
                type: 'hidden',
                name: 'date_end',
                value: endDate
            }).appendTo('#search-form');

            // إرسال النموذج
            $('#search-form').get(0).submit(); // استخدم .get(0) للحصول على العنصر DOM الفعلي
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
      // تعيين التاريخ الحالي كقيمة افتراضية
      /*const today = new Date().toISOString().split('T')[0];
      document.getElementById('startDate').value = today;
      document.getElementById('endDate').value = today;
      updateDates();*/
      
      function openPopup(popup) {
        document.body.classList.add('lock-admin-popup-open');
        popup.classList.add('active');
        const content = popup.querySelector('.search-popup-content, .details-popup-content');
        if (content) content.scrollTop = 0;
      }

      function closePopup(popup) {
        popup.classList.remove('active');
        if (!document.querySelector('.popup-overlay.active')) {
          document.body.classList.remove('lock-admin-popup-open');
        }
      }

      // إغلاق النوافذ المنبثقة
      function setupPopupClose(popupId, closeBtnId) {
        const popup = document.getElementById(popupId);
        const closeBtn = document.getElementById(closeBtnId);
        
        // إغلاق عند النقر على الزر
        closeBtn.addEventListener('click', function(e) {
          e.stopPropagation();
          closePopup(popup);
        });
        
        // إغلاق عند النقر خارج النافذة
        popup.addEventListener('click', function(e) {
          if (e.target === this) {
            closePopup(this);
          }
        });
        
        // إغلاق عند الضغط على زر ESC
        document.addEventListener('keydown', function(e) {
          if (e.key === 'Escape' && popup.classList.contains('active')) {
            closePopup(popup);
          }
        });
      }
      
      // إعداد إغلاق النوافذ
      setupPopupClose('popupOverlay', 'popupClose');
      setupPopupClose('detailsPopup', 'detailsPopupClose');
      
      const popupOverlay = document.getElementById('popupOverlay');
      const salesTable = document.getElementById('salesTable');
      const exportBtn = document.getElementById('popupExportBtn');
      const saveStatus = document.getElementById('popupSaveStatus');
      const canEditExtras = popupOverlay && popupOverlay.getAttribute('data-can-edit') === '1';
      const saveUrl = popupOverlay ? popupOverlay.getAttribute('data-save-url') : '';
      const dateStart = popupOverlay ? popupOverlay.getAttribute('data-date-start') : '';
      const dateEnd = popupOverlay ? popupOverlay.getAttribute('data-date-end') : '';

      // العمود المفتوح حاليًا في النافذة المنبثقة
      let activeColumn = null;

      function formatAmount(value) {
        return Number(value).toLocaleString('en-US', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function parseAmount(text) {
        const value = parseFloat(String(text).replace(/,/g, '').trim());
        return isNaN(value) ? 0 : value;
      }

      function showStatus(message, isError) {
        if (!saveStatus) {
          return;
        }
        saveStatus.textContent = message;
        saveStatus.className = 'popup-save-status is-visible' + (isError ? ' is-error' : ' is-success');
        if (!isError) {
          setTimeout(function() {
            saveStatus.className = 'popup-save-status';
          }, 2500);
        }
      }

      // إعادة حساب خانة الإجمالي (آخر عمود) لصف واحد بعد تعديل مبلغ
      function refreshRowTotal(row) {
        const lastIndex = row.cells.length - 1;
        let total = 0;
        for (let i = 1; i < lastIndex; i++) {
          total += parseAmount(row.cells[i].textContent);
        }
        setCellAmount(row.cells[lastIndex], total);
      }

      function setCellAmount(cell, value) {
        cell.textContent = formatAmount(value);
        if (!cell.classList.contains('total')) {
          cell.classList.toggle('clickable', value > 0);
          cell.classList.toggle('zero-value', !(value > 0));
        }
      }

      function setDifferenceCell(cell, value) {
        cell.textContent = formatAmount(value);
        cell.classList.remove('negative', 'positive', 'zero');
        cell.classList.add(value < 0 ? 'negative' : (value > 0 ? 'positive' : 'zero'));
      }

      // تحديث صف الفروقات لموظف واحد ثم إعادة جمع الإجمالي العام
      function applyDifference(colIndex, difference) {
        const differenceRow = salesTable.querySelector('tbody tr.difference-row');
        if (!differenceRow) {
          return;
        }

        setDifferenceCell(differenceRow.cells[colIndex], difference);

        const lastIndex = differenceRow.cells.length - 1;
        let total = 0;
        for (let i = 1; i < lastIndex; i++) {
          total += parseAmount(differenceRow.cells[i].textContent);
        }
        setDifferenceCell(differenceRow.cells[lastIndex], total);
      }

      function buildValueCell(row, colIndex) {
        const sourceCell = row.cells[colIndex];
        const isTotal = sourceCell.classList.contains('total');
        const isZero = sourceCell.classList.contains('zero-value');

        let cellClass = isTotal ? 'total' : (isZero ? 'zero-value' : '');
        if (isTotal) {
          if (sourceCell.classList.contains('zero')) {
            cellClass += ' zero';
          } else if (sourceCell.classList.contains('positive')) {
            cellClass += ' positive';
          }
        }

        const valueCell = document.createElement('td');
        valueCell.className = cellClass;

        const valueText = document.createElement('span');
        valueText.className = 'extra-value-text';
        valueText.textContent = sourceCell.textContent.trim();
        valueCell.appendChild(valueText);

        return valueCell;
      }

      // زر التعديل لصفوف تمارا / المحفظة / الرصيد الإضافي
      function attachEditControl(valueCell, extraKey, colIndex) {
        const editBtn = document.createElement('button');
        editBtn.type = 'button';
        editBtn.className = 'extra-edit-btn';
        editBtn.title = 'تعديل المبلغ';
        editBtn.innerHTML = '<i class="fa fa-pen"></i>';
        valueCell.appendChild(editBtn);

        editBtn.addEventListener('click', function() {
          if (valueCell.querySelector('.extra-edit-form')) {
            return;
          }

          const currentValue = parseAmount(valueCell.querySelector('.extra-value-text').textContent);

          const form = document.createElement('div');
          form.className = 'extra-edit-form';
          form.innerHTML =
            '<input type="number" step="0.01" class="extra-input" value="' + currentValue + '">' +
            '<button type="button" class="extra-save-btn" title="حفظ"><i class="fa fa-check"></i></button>' +
            '<button type="button" class="extra-cancel-btn" title="إلغاء"><i class="fa fa-xmark"></i></button>';

          valueCell.classList.add('is-editing');
          valueCell.appendChild(form);

          const input = form.querySelector('.extra-input');
          input.focus();
          input.select();

          function closeForm() {
            valueCell.classList.remove('is-editing');
            form.remove();
          }

          form.querySelector('.extra-cancel-btn').addEventListener('click', closeForm);
          form.querySelector('.extra-save-btn').addEventListener('click', function() {
            saveExtra(extraKey, colIndex, input.value, valueCell, form, closeForm);
          });
          input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
              e.preventDefault();
              saveExtra(extraKey, colIndex, input.value, valueCell, form, closeForm);
            } else if (e.key === 'Escape') {
              e.stopPropagation();
              closeForm();
            }
          });
        });
      }

      function saveExtra(extraKey, colIndex, rawValue, valueCell, form, closeForm) {
        const amount = parseFloat(rawValue);
        if (isNaN(amount)) {
          showStatus('من فضلك أدخل مبلغًا صحيحًا', true);
          return;
        }

        const saveBtn = form.querySelector('.extra-save-btn');
        saveBtn.disabled = true;

        const payload = new FormData();
        payload.append('user_id', activeColumn.userId);
        payload.append('date', dateEnd);
        payload.append('date_start', dateStart);
        payload.append('date_end', dateEnd);
        payload.append('field', extraKey);
        payload.append('amount', amount);

        fetch(saveUrl, {
          method: 'POST',
          body: payload,
          credentials: 'same-origin'
        })
          .then(response => response.json())
          .then(function(result) {
            if (!result || result.status !== 'ok') {
              showStatus(result && result.message ? result.message : 'تعذر حفظ المبلغ', true);
              saveBtn.disabled = false;
              return;
            }

            const sourceRow = salesTable.querySelector('tbody tr.extra-row[data-extra-key="' + extraKey + '"]');
            if (sourceRow) {
              setCellAmount(sourceRow.cells[colIndex], result.amount);
              refreshRowTotal(sourceRow);
            }

            valueCell.querySelector('.extra-value-text').textContent = formatAmount(result.amount);
            valueCell.classList.toggle('zero-value', !(result.amount > 0));

            applyDifference(colIndex, result.difference);

            const popupDifferenceCell = document.querySelector('#popupTable tr.difference-row td:last-child');
            if (popupDifferenceCell) {
              setDifferenceCell(popupDifferenceCell, result.difference);
              popupDifferenceCell.classList.add('total');
            }

            closeForm();
            showStatus('تم حفظ المبلغ بنجاح', false);
          })
          .catch(function() {
            showStatus('تعذر الاتصال بالخادم', true);
            saveBtn.disabled = false;
          });
      }

      // إضافة أيقونات البحث للعناوين
      const searchIcons = document.querySelectorAll('.search-icon');

      searchIcons.forEach(icon => {
        icon.addEventListener('click', function(e) {
          e.stopPropagation();

          const colIndex = parseInt(this.getAttribute('data-col'));
          const employeeName = this.getAttribute('data-name');
          const employeeId = this.getAttribute('data-uid');
          const allRows = Array.from(salesTable.querySelectorAll('tbody tr'));

          activeColumn = { index: colIndex, userId: employeeId, name: employeeName };

         // تعبئة الجدول في النافذة المنبثقة
         const popupTable = document.getElementById('popupTable').querySelector('tbody');
          popupTable.innerHTML = '';

          allRows.forEach(row => {
            const isSummary = row.classList.contains('summary-row');
            const isPayment = row.classList.contains('payment-row');
            const isNoHover = row.classList.contains('no-hover');
            const isDifference = row.classList.contains('difference-row');
            const extraKey = row.getAttribute('data-extra-key');

            let rowClass = '';
            if (isSummary) rowClass = 'summary-row';
            if (isPayment) rowClass = 'payment-row';
            if (isDifference) rowClass += ' difference-row';
            if (isNoHover) rowClass += ' no-hover';

            const newRow = document.createElement('tr');
            newRow.className = rowClass.trim();

            const labelCell = document.createElement('td');
            labelCell.className = 'category';
            labelCell.textContent = row.cells[0].textContent;
            newRow.appendChild(labelCell);

            const valueCell = buildValueCell(row, colIndex);
            if (extraKey && canEditExtras) {
              newRow.classList.add('extra-row');
              attachEditControl(valueCell, extraKey, colIndex);
            }
            newRow.appendChild(valueCell);

            popupTable.appendChild(newRow);
          });

          if (exportBtn) {
            exportBtn.href = '<?php echo base_url().MOD_VALUE.'admin/lock-admin-export'; ?>'
              + '?user_id=' + encodeURIComponent(employeeId)
              + '&date_start=' + encodeURIComponent(dateStart)
              + '&date_end=' + encodeURIComponent(dateEnd);
          }
          if (saveStatus) {
            saveStatus.className = 'popup-save-status';
          }

          document.getElementById('popupTitle').textContent = employeeName;
          openPopup(popupOverlay);
        });
      });

      // إضافة حدث النقر على الخلايا الرقمية
      const clickableCells = document.querySelectorAll('.clickable');

      clickableCells.forEach(cell => {
        // صفوف المبالغ اليدوية تُحرَّر من النافذة المنبثقة ولا تفتح نافذة التفاصيل
        if (cell.parentElement && cell.parentElement.classList.contains('extra-row')) {
          return;
        }
        cell.addEventListener('click', function() {
          const employeeName = this.getAttribute('data-name') || 'الموظف';
          document.getElementById('employeeName').textContent = employeeName;
          openPopup(document.getElementById('detailsPopup'));
        });
      });
    });

    function updateDates() {
      const startDate = document.getElementById('startDate').value;
      const endDate = document.getElementById('endDate').value;
      
      document.getElementById('fromDate').textContent = startDate;
      document.getElementById('toDate').textContent = endDate;
    }
  </script>

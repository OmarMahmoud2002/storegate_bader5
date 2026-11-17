<title>البحث عن منتج</title>
<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar') ?>

<?php
$CI =& get_instance();

$can_add_to_cart = $CI->get_permission_enabled('product_search.cart.add');
$can_remove_from_cart = $CI->get_permission_enabled('product_search.cart.remove');
$can_add_custody = $CI->get_permission_enabled('product_search.custody.create');
$can_add_delivery = $CI->get_permission_enabled('product_search.delivery.create');
$can_return_custody = $CI->get_permission_enabled('product_search.custody.return');
$can_return_delivery = $CI->get_permission_enabled('product_search.delivery.return');
$can_upload_products = $CI->get_permission_enabled('products.import');
$can_add_serial_note = $CI->get_permission_enabled('product_search.notes.create');

$stock_upload_time = !empty($stock_excel_last_uploaded_at) ? strtotime($stock_excel_last_uploaded_at) : false;
$stock_upload_age_seconds = $stock_upload_time !== false ? max(0, time() - $stock_upload_time) : 0;
$stock_upload_age_hours = (int) floor($stock_upload_age_seconds / 3600);
$stock_upload_age_minutes = (int) floor(($stock_upload_age_seconds % 3600) / 60);
$stock_upload_is_stale = $stock_upload_time !== false && $stock_upload_age_seconds > (12 * 60 * 60);

if ($stock_upload_time !== false) {
  if ($stock_upload_age_hours === 0) {
    $stock_upload_age_text = $stock_upload_age_minutes === 0
      ? 'منذ أقل من دقيقة'
      : 'منذ ' . $stock_upload_age_minutes . ' دقيقة';
  } else {
    $stock_upload_age_text = 'منذ ' . $stock_upload_age_hours . ' ساعة';
    if ($stock_upload_age_minutes > 0) {
      $stock_upload_age_text .= ' و' . $stock_upload_age_minutes . ' دقيقة';
    }
  }
}
?>

<style>
  .serialAdd {
    margin-right: 15px;
    margin-bottom: 3px;
    color: black;
    padding: 5px;
    border-radius: 5px;
    cursor: pointer;
  }

  .serialAdd:hover {
    background-color: rgba(230, 230, 230, 0.5);
  }

  .remCustody {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 12px;
    background: var(--admin-ui-gradient);
    color: #fff;
    font-weight: 800;
    font-size: 14px;
    box-shadow: 0 10px 20px rgba(102, 126, 234, 0.2);
    cursor: pointer;
    transition: transform 0.18s ease, box-shadow 0.18s ease, filter 0.18s ease;
  }

  .remCustody:hover {
    transform: translateY(-1px);
    box-shadow: 0 14px 26px rgba(102, 126, 234, 0.26);
    filter: brightness(1.03);
  }

  .prod_form .prod_infos .cnts .prod_info td.product-cell-unavailable {
    background: inherit !important;
    color: #e53935 !important;
    font-weight: 800;
  }

  .prod_form .prod_infos .cnts .prod_info td.product-cell-subinventory-strong {
    background: #d9f7e5 !important;
    color: #157347 !important;
    font-weight: 800;
  }

  .prod_form .prod_infos .cnts .prod_info td.product-cell-subinventory-soft {
    background: #eef9f1 !important;
    color: #157347 !important;
    font-weight: 800;
  }

  .stock-upload-status {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    margin-top: 10px;
    color: #667085;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.7;
  }

  .stock-upload-status.is-stale {
    color: #dc2626;
  }

  .stock-upload-status i {
    font-size: 16px;
  }

  .stock-available-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-width: 72px;
    padding: 7px 13px;
    border: 1px solid rgba(102, 126, 234, 0.24);
    border-radius: 12px;
    background: rgba(102, 126, 234, 0.09);
    color: #4f46b8;
    font: inherit;
    font-weight: 900;
    cursor: pointer;
  }

  .stock-available-button:hover,
  .stock-available-button:focus-visible {
    background: var(--admin-ui-gradient);
    color: #fff;
    outline: none;
    box-shadow: 0 10px 22px rgba(102, 126, 234, 0.24);
  }

  .stock-breakdown-popup {
    position: fixed;
    inset: 0;
    z-index: 10050;
    display: grid;
    place-items: center;
    padding: 20px;
    background: rgba(21, 30, 50, 0.48);
    backdrop-filter: blur(4px);
  }

  .stock-breakdown-popup.hide {
    display: none;
  }

  .stock-breakdown-card {
    width: min(480px, 100%);
    overflow: hidden;
    border: 1px solid rgba(102, 126, 234, 0.16);
    border-radius: 18px;
    background: #fff;
    box-shadow: 0 28px 70px rgba(31, 42, 68, 0.22);
  }

  .stock-breakdown-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 22px;
    background: linear-gradient(135deg, #f4f6ff 0%, #f8f4ff 100%);
    border-bottom: 1px solid #e8eaf3;
  }

  .stock-breakdown-title {
    display: flex;
    align-items: center;
    gap: 11px;
  }

  .stock-breakdown-title i {
    color: #6b55c8;
    font-size: 23px;
  }

  .stock-breakdown-title h2 {
    margin: 0;
    color: #23314d;
    font-size: 20px;
    font-weight: 900;
  }

  .stock-breakdown-close {
    width: 38px;
    height: 38px;
    border: 0;
    border-radius: 11px;
    background: #fff;
    color: #667085;
    cursor: pointer;
  }

  .stock-breakdown-body {
    padding: 22px;
  }

  .stock-breakdown-list {
    display: grid;
    gap: 11px;
  }

  .stock-breakdown-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 14px 16px;
    border: 1px solid #e6e9f2;
    border-radius: 13px;
    background: #f8f9fc;
  }

  .stock-breakdown-row span {
    color: #344054;
    font-weight: 800;
  }

  .stock-breakdown-row strong {
    min-width: 42px;
    padding: 5px 10px;
    border-radius: 999px;
    background: var(--admin-ui-gradient);
    color: #fff;
    text-align: center;
  }

  .stock-breakdown-total {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px dashed #d0d5dd;
    color: #1d2939;
    font-weight: 900;
  }

  .top_part #startOverBtn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
  }

  .top_part #startOverBtn i {
    font-size: 14px;
  }
</style>

<div class='app-content admin-ui-page admin-ui-product-search'>
  <div class='app-content-header'>
    <h1 class='app-content-headerText'>البحث عن منتج</h1>
    <?php if ($can_add_serial_note): ?>
      <button type="button" id="openSerialNoteModal" class="serial-note-header-button">
        <i class="bi bi-chat-left-text" aria-hidden="true"></i>
        <span>إضافة ملاحظة</span>
      </button>
    <?php endif; ?>
  </div>
  <div class="prod_form">
    <form class='formTag product-search-card'>
      <?php if ($can_upload_products): ?>
        <a href="<?= base_url('admin/products/add') ?>" id="openProductUploadModal" class="product-upload-shortcut"
          title="رفع ملف إكسيل المنتجات" aria-haspopup="dialog" aria-controls="productUploadModal">
          <i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i>
          <span>إضافة منتجات</span>
        </a>
      <?php endif; ?>
      <div class="search-card-intro">
        <span class="search-card-icon" aria-hidden="true"><i class="bi bi-upc-scan"></i></span>
        <h1>مطابقة رقم التحقق</h1>
        <p>يرجى إدخال الرقم التسلسلي أو رقم الصنف أو الباركود للتحقق من عملية المطابقة.</p>
        <?php if ($stock_upload_time !== false): ?>
          <div class="stock-upload-status<?= $stock_upload_is_stale ? ' is-stale' : '' ?>" title="التوقيت حسب المملكة العربية السعودية">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <span>
              آخر رفع لملف المخزون:
              <?= date('d/m/Y', $stock_upload_time) ?>
              <?= date('h:i', $stock_upload_time) ?>
              <?= date('A', $stock_upload_time) === 'AM' ? 'ص' : 'م' ?>
              (<?= $stock_upload_age_text ?>)
            </span>
          </div>
        <?php else: ?>
          <div class="stock-upload-status">
            <i class="bi bi-clock-history" aria-hidden="true"></i>
            <span>لم يتم تسجيل وقت رفع ملف المخزون بعد</span>
          </div>
        <?php endif; ?>
      </div>
      <div class='top_part'>
        <button type='button' id='startOverBtn'><i class="bi bi-arrow-clockwise" aria-hidden="true"></i><span>البحث من جديد</span></button>
        <?php $count = count($cart); ?>
        <div class="cart-menu">
          <div class="cart" title="سلة المنتجات" aria-label="سلة المنتجات">
            <i class="bi bi-basket2" aria-hidden="true"></i>
            <?php
            if ($count > 0) {
              echo '<p>' . $count . '</p>';
            }
            ?>
          </div>
          <div class="cart-popup">
            <?php if ($count > 0) { ?>
              <?php foreach ($cart as $cart_item) { ?>
                <div class='cart-item' id='<?= $cart_item->id ?>' data-item-id='<?= $cart_item->item_id ?>'
                  data-serial_control='<?= $cart_item->serial_control ?>'>
                  <?php
                  $item = $this->Store->get_store_item(['id' => $cart_item->item_id]);
                  $shelf_id = $this->Store->get_item_shelf($item['item_code'], $item['serial_number'], $item['barcode']);

                  if ($shelf_id) {
                    $shelfObj = $this->Shelves->get_one_shelf_by_id($shelf_id);
                    $shelf = $shelfObj->shelf_number;
                  } else {
                    $shelf = 'عام';
                  }
                  ?>

                  <input type="checkbox" onchange="toggleItemSelection(this)">
                  <a href="/public/img/products/default_product_image.png" class="img" data-fancybox="cart-gallery"
                    data-caption="Product 1">
                    <img src="/public/img/products/default_product_image.png" />
                  </a>
                  <div class="item-details">
                    <div class="l">
                      <p class='itemname'>
                        <span>اسم:</span>
                        <?php
                        $exploded_name = explode(' ', $item['item_description']);
                        $chosen_words = array_splice($exploded_name, 0, 6);
                        $item_name = implode(' ', $chosen_words);
                        ?>
                        <span title='<?= $item['item_description'] ?>'><?= $item_name ?></span>
                      </p>
                      <p class='item_number'
                        data-number='<?= $item['serial_control'] == 'yes' ? $item['serial_number'] : $item['barcode'] ?>'>
                        <span>الرقم:</span>
                        <span><?= $item['serial_control'] == 'yes' ? $item['serial_number'] : $item['barcode'] ?></span>
                      </p>
                    </div>
                    <div class="l">
                      <p class='itemshelf'>
                        <span>الرف:</span>
                        <span><?= $shelf ?></span>
                      </p>
                      <div class="cart-verify">
                        <div class="image-container">
                          <svg class="toggle-input-image" clip-rule="evenodd" fill-rule="evenodd" stroke-linejoin="round"
                            stroke-miterlimit="2" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path
                              d="m22 5c0-.478-.379-1-1-1h-18c-.62 0-1 .519-1 1v14c0 .621.52 1 1 1h18c.478 0 1-.379 1-1zm-18.5.5h17v13h-17zm5.5 2c0-.276-.224-.5-.5-.5s-.5.224-.5.5v9c0 .276.224.5.5.5s.5-.224.5-.5zm8 0c0-.276-.224-.5-.5-.5s-.5.224-.5.5v9c0 .276.224.5.5.5s.5-.224.5-.5zm2 0c0-.276-.224-.5-.5-.5s-.5.224-.5.5v9c0 .276.224.5.5.5s.5-.224.5-.5zm-13.25-.5c-.414 0-.75.336-.75.75v8.5c0 .414.336.75.75.75s.75-.336.75-.75v-8.5c0-.414-.336-.75-.75-.75zm5.5 0c-.414 0-.75.336-.75.75v8.5c0 .414.336.75.75.75s.75-.336.75-.75v-8.5c0-.414-.336-.75-.75-.75zm3-.013c-.414 0-.75.336-.75.75v8.5c0 .414.336.75.75.75s.75-.336.75-.75v-8.5c0-.414-.336-.75-.75-.75z"
                              fill-rule="nonzero" />
                          </svg>
                          <input type="text" class="toggle-input-field" placeholder="ادخل رقم السريال">
                          <span class="verification-result correct">✔</span>
                          <span class="verification-result incorrect">✖</span>
                        </div>
                      </div>
                    </div>
                  </div>
                  <div class="options-item">
                    <button type='button' class='acc-btn'>✔</button>
                    <?php if ($can_remove_from_cart): ?>
                      <button type='button' class='rem-btn'>✖</button>
                    <?php endif; ?>
                  </div>
                </div>

              <?php } ?>
              <div class="select-all-container">
                <input type="checkbox" id="selectAllCheckbox" onchange="toggleAllSelections(this)">
                <label for="selectAllCheckbox">تحديد الكل</label>
              </div>
            <?php } else { ?>
              <div class="cart-empty-state">لا توجد منتجات</div>
            <?php } ?>
          </div>
        </div>
      </div>
      <div class="ff conts">
        <div class="inpts">
          <div class="inpt" id="serialNumber">
            <div style='display: flex; align-items: center; gap: 10px;'>
              <input type="text" id="serialNumberInput" placeholder="ادخل الرقم التسلسلي أو رقم الصنف أو الباركود">

              <?php if ($can_add_to_cart): ?>
                <i class="fa fa-plus serialAdd hide" onclick="register_item_to_cart()"></i>
              <?php endif; ?>
            </div>

            <p id="serialNumberError" class="hide">يرجى إدخال رقم السريال أو رقم الصنف أو الباركود</p>
            <p id="serialNumberHistory" class='hide'><a>اضغط هنا للمزيد من التفاصيل</a></p>
            <div id="serialNotesSearchResult" class="serial-notes-search-result hide" aria-live="polite">
              <div class="serial-notes-result-head">
                <i class="bi bi-exclamation-circle" aria-hidden="true"></i>
                <div>
                  <strong>السيريال غير موجود في المخزون</strong>
                  <span>لكن توجد ملاحظات مسجلة عليه</span>
                </div>
              </div>
              <div id="serialNotesSearchList" class="serial-notes-list"></div>
            </div>
          </div>
          <div class="select_type hide">
            <label>حدد ماتريد البحث عنه</label>
          </div>
          <div class="inpt hide" id="verification">
            <input type="text" id="verificationInput" placeholder="تحقق من رقم السريال"><br>
          </div>
        </div>
        <div class="checkResult">
        </div>
        <div class="btns">
          <button type="button" id="nextBtn">التالي</button>
          <button type="button" id="checkBtn" class="hide">تحقق</button>
          <?php if ($can_add_custody || $can_add_delivery): ?>
            <button type="button" id="deliveryBtn" class="hide">تسليم الجهاز</button>
          <?php endif; ?>
        </div>
      </div>
    </form>
    <div class="prod_infos hide">
      <div class="product-details-card">
        <div class="product-details-head">
          <span class="product-details-icon" aria-hidden="true"><i class="bi bi-box-seam"></i></span>
          <div>
            <h1>تفاصيل المنتج</h1>
            <p>بيانات المنتج المرتبطة بنتيجة البحث الحالية</p>
          </div>
        </div>
        <div class="cnts">
          <table class="prod_info">
            <tbody>
              <tr>
                <td>اسم المنتج</td>
                <td id='product_name'></td>
              </tr>
              <tr>
                <td>الايتم كود</td>
                <td id='item_code'></td>
              </tr>
              <tr>
                <td>رقم الرف</td>
                <td id='item_shelf'></td>
              </tr>
              <tr>
                <td>الرقم التسلسلي</td>
                <td id='serial_number'></td>
              </tr>
              <tr>
                <td>المخزون</td>
                <td id='subinventory_code'></td>
              </tr>
              <tr>
                <td>المتوفر</td>
                <td>
                  <button type="button" id="total_quantity" class="stock-available-button sort-button" aria-haspopup="dialog" aria-controls="stockBreakdownPopup" title="عرض توزيع المتوفر حسب المخزون">
                    <span>0</span>
                    <i class="bi bi-chevron-down" aria-hidden="true"></i>
                  </button>
                </td>
              </tr>
              <tr>
                <td>رقم الباركود</td>
                <td id='item_barcode'></td>
              </tr>
              <tr>
                <td>رقم الملصق</td>
                <td id='poster_number'></td>
              </tr>
              <tr id="serialNotesRow" class="hide">
                <td>ملاحظات السيريال</td>
                <td><div id="serialNotesDetailsList" class="serial-notes-list serial-notes-list-compact"></div></td>
              </tr>
            </tbody>
          </table>
          <div class="product-image-frame">
            <button type="button" id="productImagePrev" class="product-image-nav product-image-nav-prev hide" aria-label="الصورة السابقة">
              <i class="bi bi-chevron-right"></i>
            </button>
            <a id='product_image' class="product-image-preview" href="" data-fancybox="product-gallery" data-caption>
              <img src="" alt="صورة المنتج">
            </a>
            <button type="button" id="productImageNext" class="product-image-nav product-image-nav-next hide" aria-label="الصورة التالية">
              <i class="bi bi-chevron-left"></i>
            </button>
            <span id="productImageCounter" class="product-image-counter hide"></span>
          </div>
          <div style='display: none' id='gallery_images'>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php if ($can_add_serial_note): ?>
  <div id="serialNoteModal" class="serial-note-modal hide" role="dialog" aria-modal="true" aria-labelledby="serialNoteModalTitle">
    <div class="serial-note-modal-card">
      <div class="serial-note-modal-head">
        <div class="serial-note-modal-title">
          <span><i class="bi bi-chat-left-text" aria-hidden="true"></i></span>
          <div>
            <h2 id="serialNoteModalTitle">إضافة ملاحظة على سيريال</h2>
            <p>يمكن تسجيل الملاحظة حتى لو لم يكن السيريال موجودًا في المخزون.</p>
          </div>
        </div>
        <button type="button" id="closeSerialNoteModal" class="serial-note-modal-close" aria-label="إغلاق">
          <i class="bi bi-x-lg" aria-hidden="true"></i>
        </button>
      </div>
      <form id="serialNoteForm" class="serial-note-form" novalidate>
        <div class="serial-note-field">
          <label for="serialNoteNumber">رقم السيريال</label>
          <div class="serial-note-input-wrap">
            <i class="bi bi-upc-scan" aria-hidden="true"></i>
            <input type="text" id="serialNoteNumber" name="serial" maxlength="190" autocomplete="off"
              placeholder="اكتب رقم السيريال">
          </div>
          <div id="serialNoteExistence" class="serial-note-existence is-idle" aria-live="polite">
            <i class="bi bi-info-circle" aria-hidden="true"></i>
            <span>اكتب السيريال لمعرفة هل هو موجود أم لا</span>
          </div>
        </div>
        <div class="serial-note-field">
          <label for="serialNoteText">الملاحظة</label>
          <textarea id="serialNoteText" name="note" maxlength="2000" rows="5"
            placeholder="اكتب الملاحظة هنا..."></textarea>
          <small><span id="serialNoteCharacters">0</span> / 2000</small>
        </div>
        <p id="serialNoteFormMessage" class="serial-note-form-message hide" aria-live="assertive"></p>
        <div class="serial-note-modal-actions">
          <button type="button" id="cancelSerialNoteModal" class="serial-note-cancel-button">إلغاء</button>
          <button type="submit" id="saveSerialNote" class="serial-note-save-button">
            <i class="bi bi-check2-circle" aria-hidden="true"></i>
            <span>حفظ الملاحظة</span>
          </button>
        </div>
      </form>
    </div>
  </div>
<?php endif; ?>
<div id="stockBreakdownPopup" class="stock-breakdown-popup hide" role="dialog" aria-modal="true" aria-labelledby="stockBreakdownTitle">
  <div class="stock-breakdown-card">
    <div class="stock-breakdown-head">
      <div class="stock-breakdown-title">
        <i class="bi bi-boxes" aria-hidden="true"></i>
        <h2 id="stockBreakdownTitle">توزيع المتوفر حسب المخزون</h2>
      </div>
      <button type="button" class="stock-breakdown-close" aria-label="إغلاق">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
      </button>
    </div>
    <div class="stock-breakdown-body">
      <div class="stock-breakdown-list">
        <div class="stock-breakdown-row"><span>23012</span><strong data-stock-code="23012">0</strong></div>
        <div class="stock-breakdown-row"><span>SAgent_STK</span><strong data-stock-code="SAgent_STK">0</strong></div>
        <div class="stock-breakdown-row"><span>RESV_23012</span><strong data-stock-code="RESV_23012">0</strong></div>
      </div>
      <div class="stock-breakdown-total">
        <span>الإجمالي</span>
        <strong id="stockBreakdownTotal">0</strong>
      </div>
    </div>
  </div>
</div>
<?php if ($can_add_custody || $can_add_delivery): ?>
  <div class="delivery_popup hide">
    <div class="popup_content">
      <div class="close">
        <svg xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 384 512"><!--!Font Awesome Free 6.5.2 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2024 Fonticons, Inc.-->
          <path
            d="M342.6 150.6c12.5-12.5 12.5-32.8 0-45.3s-32.8-12.5-45.3 0L192 210.7 86.6 105.4c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L146.7 256 41.4 361.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0L192 301.3 297.4 406.6c12.5 12.5 32.8 12.5 45.3 0s12.5-32.8 0-45.3L237.3 256 342.6 150.6z">
          </path>
        </svg>
      </div>
      <h2 id="delivery_popup_title">الرجاء اختيار نوع التسليم</h2>
      <div class="btns" data-title="تسليم الجهاز">
        <?php if ($can_add_custody): ?>
          <button class="delivery_btn" data-type=".popup_employee" data-title="تسليم لموظف">تسليم لموظف</button>
        <?php endif; ?>
        <?php if ($can_add_delivery): ?>
          <button class="delivery_btn" data-type=".popup_delivery" data-title="توصيل الجهاز">توصيل </button>
        <?php endif; ?>
      </div>
      <div class="delivery_box popup_delivery hide">
        <div class='content'>
          <div class="inpt">
            <label>ادخل رقم الطلب:</label>
            <input type="text" id='delivery_order_number'>
          </div>
          <button onclick='product_delivery_confirm(event)'>توصيل</button>
        </div>

        <div class="done hide">
          <div class="sa">
            <div class="sa-success">
              <div class="sa-success-tip"></div>
              <div class="sa-success-long"></div>
              <div class="sa-success-placeholder"></div>
              <div class="sa-success-fix"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="delivery_box popup_employee hide">
        <div class="content">
          <div class="inpt">
            <label>اختر موظف:</label>
            <select id="selectElement">

              <option value="">&nbsp;</option>
              <?php foreach ($employees as $employee) { ?>
                <option value="<?= $employee['user_id'] ?>"><?= $employee['user_fillname'] ?></option>
              <?php } ?>
            </select>
            <script>


              $(document).ready(() => {
                $('#selectElement').select2()
              })
            </script>
            <style>
              .select2-container--open {
                z-index: 99999;
                /* A very high value */
              }


              .select2 {
                width: 100% !important;
                font-size: 20px;
              }

              .select2-selection {
                padding: 5px;
                height: auto !important;
              }
            </style>
          </div>
          <div class="inpt">
            <label>ادخل رقم الطلب:</label>
            <input id="empoo" type="text">
          </div>
          <p id="popEmplError" class="hide">الرجاء ملئ موظف و رقم الطلب</p>
          <button id="popup_employee_btn">تسليم</button>
        </div>
        <div class="done hide">
          <div class="sa">
            <div class="sa-success">
              <div class="sa-success-tip"></div>
              <div class="sa-success-long"></div>
              <div class="sa-success-placeholder"></div>
              <div class="sa-success-fix"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>
<div class="return_popup hide" role="dialog" aria-modal="true" aria-labelledby="return_popup_title">
  <div class="popup_content">
    <h2 id="return_popup_title">تأكيد الإرجاع</h2>
    <p id="return_popup_message">هل تريد إرجاع المنتج إلي المخزون؟</p>
    <div class="btns">
      <button type="button" class="return-confirm">تأكيد الإرجاع</button>
      <button type="button" class="return-cancel">إلغاء</button>
    </div>
  </div>
</div>
</div>



<div id="deleteSerialNoteModal" class="serial-note-modal serial-note-delete-modal hide" role="alertdialog" aria-modal="true" aria-labelledby="deleteSerialNoteTitle" aria-describedby="deleteSerialNoteDescription" dir="rtl">
  <div class="serial-note-modal-card">
    <div class="serial-note-modal-head">
      <div class="serial-note-modal-title">
        <span><i class="bi bi-trash" aria-hidden="true"></i></span>
        <div>
          <h2 id="deleteSerialNoteTitle">تأكيد حذف الملاحظة</h2>
          <p id="deleteSerialNoteDescription">هل تريد حذف هذه الملاحظة؟ لا يمكن التراجع عن الحذف.</p>
        </div>
      </div>
      <button type="button" class="serial-note-modal-close" data-delete-note-cancel aria-label="إغلاق"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>
    <div class="serial-note-form">
      <p id="deleteSerialNotePreview" class="serial-note-delete-preview"></p>
      <div class="serial-note-modal-actions">
        <button type="button" id="cancelDeleteSerialNote" class="serial-note-cancel-button" data-delete-note-cancel>إلغاء</button>
        <button type="button" id="confirmDeleteSerialNote" class="serial-note-save-button"><i class="bi bi-trash" aria-hidden="true"></i><span>حذف الملاحظة</span></button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/@fancyapps/ui@6.1/dist/fancybox/fancybox.umd.js"></script>

<script
  src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date = date_create();
     echo date_timestamp_get($date); ?>"></script>
<script>
  window.appBaseUrl = "<?php echo base_url() . MOD_VALUE; ?>";
  window.canDeleteSerialNote = <?= json_encode((bool) $CI->get_permission_enabled('product_search.notes.delete')) ?>;
</script>
<script
  src="<?php echo base_url(); ?>public/js/search_product.js?a=<?php $date = date_create();
     echo date_timestamp_get($date); ?>"></script>
<?php if ($can_upload_products): ?>
<?php $this->load->view('products/upload_modal', array('can_upload_products' => $can_upload_products)); ?>
<?php endif; ?>
<?php $this->load->view('view_footer') ?>

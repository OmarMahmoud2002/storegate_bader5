<title>المنتجات منخفضة الكمية</title>
<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar');?>

<div class="app-content admin-ui-page admin-ui-low">
  <div class="app-content-header">
    <h1 class="app-content-headerText">المنتجات منخفضة الكمية</h1>
  </div>
  <div class="app-content-actions">
    <input id='Search' onkeyup='search()' class="search-bar" placeholder="ابحث باسم المنتج أو رقم الصنف أو التصنيف" type="text">
    <a class="app-content-headerButton low-export-btn" href="<?= base_url().MOD_VALUE.'admin/products/export_low' ?>">
      <i class="bi bi-file-earmark-excel"></i>
      <span>تنزيل Excel</span>
    </a>
  </div>
    
    <div class="products-area-wrapper tableView">
        <div class="products-header">
          <div class="product-cell image">المنتج</div>
          <div class="product-cell item-code">رقم الصنف</div>
          <div class="product-cell category">تصنيف المنتج</div>
          <div class="product-cell quantity">العدد المتبقي</div>
          <div class="product-cell last_updated">آخر تحديث</div>
          <div class="product-cell status">الحالة</div>
        </div>
        <?php if ($low_products) { ?>
        <?php foreach ($low_products as $product) { ?>
            <div class='products-row'>
                <div class='product-cell image'>
                    <img src='<?= base_url("public/img/products/default_product_image.png") ?>' alt='product'>
                    <a href='<?= base_url().MOD_VALUE."admin/products/edit?item_code=".urlencode($product['item_code']) ?>'><?= $product['item_description'] ?></a>
                </div>

                <div class='product-cell item-code'><span class="cell-label">رقم الصنف</span><?= htmlspecialchars($product['item_code'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class='product-cell category'><span class="cell-label">تصنيف المنتج</span><?= $product['item_category'] ?></div>
                <div class='product-cell quantity'><span class="cell-label">العدد المتبقي</span><?= (int) $product['total_quantity'] ?></div>
                <div class='product-cell last_updated'><span class="cell-label">آخر تحديث</span><span title="<?= htmlspecialchars(quantity_update_note($product), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(quantity_update_date($product['quantity_changed_at']), ENT_QUOTES, 'UTF-8') ?></span></div>
                <div class="product-cell status">
                  <span class="cell-label">الحالة</span>
                  <span class="low-stock-badge"><i class="bi bi-exclamation-triangle"></i> الكمية منخفضة</span>
                </div>
            </div>
        <?php } ?>
        <?php } else { ?>
          <div class="empty-admin-state">
            <i class="bi bi-inbox"></i>
            <div>لا توجد منتجات منخفضة الكمية</div>
          </div>
        <?php } ?>
    </div>
</div>
</div>


<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>

<?php $this->load->view('view_footer'); ?>

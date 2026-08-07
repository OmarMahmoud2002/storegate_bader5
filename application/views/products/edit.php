<?php $this->load->view('view_header'); ?>
<?php $this->load->view('view_admin_sidebar'); ?>

<?php 
$CI =& get_instance();
$update_basic_permission = $CI->get_permission_enabled('products.update.basic');
$update_extra_permission = $CI->get_permission_enabled('products.update.extra');
?>

<link rel='stylesheet' href='<?= base_url('/public/css/edit_product.css') ?>'>

<div class = 'app-content admin-ui-page admin-ui-product-form'>
    <div class="hero-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <div class="product-icon me-3">
                            <i class="bi bi-box-seam"></i>
                        </div>
                        <div>
                            <h1 class="hero-title mb-1">تفاصيل المنتج</h1>

                            <p class="hero-subtitle mb-0"><?= htmlspecialchars((string) ($product['item_description'] ?? '')) ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?= base_url('admin/dashboard') ?>" class="position-relative btn btn-outline-light btn-lg">
                        <i class="bi bi-arrow-right me-2"></i>
                        العودة للمخزون
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container my-5">
        <!-- Product Summary Cards -->
        <div class="row mb-5">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="summary-card card-primary">
                    <div class="card-icon">
                        <i class="bi bi-tag"></i>
                    </div>
                    <div class="card-content">
                        <h6>كود المنتج</h6>
                        <p><?= htmlspecialchars((string) ($product['item_code'] ?? '')) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="summary-card card-success">
                    <div class="card-icon">
                        <i class="bi bi-grid-3x3-gap"></i>
                    </div>
                    <div class="card-content">
                        <h6>الفئة</h6>
                        <p><?= htmlspecialchars((string) ($product['item_category'] ?? '')) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="summary-card card-info">
                    <div class="card-icon">
                        <i class="bi bi-list-ol"></i>
                    </div>
                    <div class="card-content">
                        <h6>عدد السيريالات</h6>
                        <p><?= count($serials) ?></p>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="summary-card card-warning">
                    <div class="card-icon">
                        <i class="bi bi-shield-check"></i>
                    </div>
                    <div class="card-content">
                        <h6>نوع التحكم</h6>
                        <p>
                            <?php if ($hasSerial && $hasNoSerial): ?>
                                مختلط
                            <?php elseif ($hasSerial): ?>
                                سيريال
                            <?php elseif ($hasNoSerial): ?>
                                باركود
                            <?php else: ?>
                                غير محدد
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Section -->
        <div class="modern-tabs-container">
            <ul class="nav nav-pills modern-tabs justify-content-center mb-4" id="productTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-basic" data-bs-toggle="pill" data-bs-target="#basic" type="button" role="tab">
                        <i class="bi bi-info-circle me-2"></i>
                        بيانات المنتج
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-shelf" data-bs-toggle="pill" data-bs-target="#shelf" type="button" role="tab">
                        <i class="bi bi-gear me-2"></i>
                        الرف
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-extra" data-bs-toggle="pill" data-bs-target="#extra" type="button" role="tab">
                        <i class="bi bi-gear me-2"></i>
                        بيانات إضافية
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-serials" data-bs-toggle="pill" data-bs-target="#serials" type="button" role="tab">
                        <i class="bi bi-list-ul me-2"></i>
                        السيريالات
                    </button>
                </li>
            </ul>

            <div class="tab-content modern-tab-content" id="productTabsContent">
                <!-- التاب الأول: بيانات المنتج -->
                <div class="tab-pane fade show active" id="basic" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header card-primary">
                            <h5>
                                <i class="bi bi-box me-2"></i>
                                معلومات المنتج الأساسية

                                <div class="card-icon mx-2" style="width: 40px; height:40px;font-size: 1.1rem;">
                                    <span><?php echo $totalQuantity; ?></span>
                                </div>
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="<?= base_url('admin/products/update_basic_product_data')?>" method="post" class="m-2">
                                <?php
                                $description = (string) ($product['item_description'] ?? '');
                                $shortName = explode(',', $description)[0];
                                ?>
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="bi bi-tag-fill"></i>
                                        اسم المنتج
                                    </div>
                                    <div class="info-value w-100 <?= $text_danger ?>"><?= htmlspecialchars($shortName) ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="bi bi-hash"></i>
                                        الايتم كود
                                    </div>
                                    <div class="info-value w-100">
                                        <input type="text" class="form-control" value="<?= htmlspecialchars((string) ($product['item_code'] ?? '')) ?>" disabled>
                                    </div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="bi bi-file-text"></i>
                                        وصف المنتج
                                    </div>
                                    <div class="info-value w-100">
                                        <input type="hidden" name="item_id" value="<?= $product['id'] ?>">
                                        <input type="hidden" name="item_code" value="<?= $product['item_code'] ?>">
                                        <textarea class="bg-primary bg-opacity-10 form-control" name="item_description"><?= htmlspecialchars($description) ?></textarea>
                                    </div>
                                </div>
                                
                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="bi bi-collection"></i>
                                        الفئة
                                    </div>
                                    <div class="info-value w-100">
                                        <input type="text" class="bg-primary bg-opacity-10 form-control" name="item_category" value="<?= htmlspecialchars((string) ($product['item_category'] ?? '')) ?>">
                                    </div>
                                </div>
                                

                                <div class="info-row">
                                    <div class="info-label">
                                        <i class="bi bi-upc-scan"></i>
                                        رقم الباركود
                                    </div>
                                    <div class="info-value w-100">
                                        <input type="text" class="bg-primary bg-opacity-10 form-control" name="barcode" value="<?= htmlspecialchars((string) ($product['barcode'] ?? '')) ?>">
                                    </div>
                                </div>

                                <?php if ($update_basic_permission): ?>
                                    <div class="info-row">
                                        <div class="text-center">
                                            <button type="submit" class="btn btn-info text-light fs-5 fw-bold">حفظ</button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- التاب الثاني: بيانات الرف -->
                <div class="tab-pane fade show" id="shelf" role="tabpanel">
                    <form action="" class="col-md-6 m-2">
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="R200">
                            <button class="btn btn-info text-light fs-4 fw-bold">+</button>
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="R25">
                            <button class="btn btn-info text-light fs-4 fw-bold">+</button>
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="R16">
                            <button class="btn btn-info text-light fs-4 fw-bold">+</button>
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="R17">
                            <button class="btn btn-info text-light fs-4 fw-bold">+</button>
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="20">
                            <button class="btn btn-info text-light fs-4 fw-bold">+</button>
                        </div>
                        <div class="input-group mb-3">
                            <input type="text" class="form-control" value="C910">
                            <button class="btn btn-info text-light fs-4 fw-bold">+</button>
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-info text-light fs-4 fw-bold">حفظ</button>
                        </div>
                        
                    </form>
                </div>

                <!-- التاب الثالث: بيانات إضافية -->
                <div class="tab-pane fade" id="extra" role="tabpanel">
                    <form action="<?= base_url().MOD_VALUE.'admin/products/update_extra_product_data' ?>" enctype="multipart/form-data" class="col-md-6 m-2" method="post" >
                        <div class="mb-3">
                            <input type="hidden" name="item_id" value="<?= $product['id'] ?>">
                            <input type="hidden" name="item_code" value="<?= $product['item_code'] ?>">
                            <label class="form-label fw-bold">صور المنتج</label>

                            <div class="product-images-uploader">
                                <div class="product-gallery" id="productGallery">
                                    <div class="product-gallery-main">
                                        <img id="galleryMainImg" class="d-block" alt="صورة المنتج" style="display:none">
                                        <button type="button" class="carousel-remove-btn" id="galleryRemoveBtn" title="حذف الصورة" style="display:none">
                                            <i class="fa fa-close"></i>
                                        </button>
                                        <span class="carousel-new-badge" id="galleryNewBadge" style="display:none">جديدة</span>
                                        <button type="button" class="gallery-nav-btn gallery-nav-prev" id="galleryPrevBtn" title="السابق">
                                            <i class="bi bi-chevron-right"></i>
                                        </button>
                                        <button type="button" class="gallery-nav-btn gallery-nav-next" id="galleryNextBtn" title="التالي">
                                            <i class="bi bi-chevron-left"></i>
                                        </button>
                                        <div class="carousel-empty-state" id="galleryEmptyState">
                                            <i class="bi bi-images"></i>
                                            <p>لا توجد صور مضافة بعد</p>
                                        </div>
                                    </div>
                                    <div class="product-gallery-thumbs" id="galleryThumbs"></div>
                                </div>

                                <label for="product_images" class="btn-add-images">
                                    <i class="bi bi-cloud-arrow-up me-2"></i>
                                    إضافة صور
                                </label>
                                <input type="file" class="d-none" name="product_images[]" id="product_images" multiple accept="image/*">
                            </div>

                            <div class="gallery-lightbox" id="galleryLightbox">
                                <button type="button" class="gallery-lightbox-close" id="galleryLightboxClose" title="إغلاق">
                                    <i class="fa fa-close"></i>
                                </button>
                                <button type="button" class="gallery-lightbox-nav gallery-lightbox-prev" id="galleryLightboxPrev" title="السابق">
                                    <i class="bi bi-chevron-right"></i>
                                </button>
                                <img id="galleryLightboxImg" src="" alt="صورة المنتج">
                                <button type="button" class="gallery-lightbox-nav gallery-lightbox-next" id="galleryLightboxNext" title="التالي">
                                    <i class="bi bi-chevron-left"></i>
                                </button>
                            </div>

                            <input type = 'hidden' name = 'removed_images' id = 'removed_images' value="">
                            <script>
                                (function () {
                                    const gallery = document.getElementById('productGallery');
                                    const mainImg = document.getElementById('galleryMainImg');
                                    const removeBtn = document.getElementById('galleryRemoveBtn');
                                    const newBadge = document.getElementById('galleryNewBadge');
                                    const emptyState = document.getElementById('galleryEmptyState');
                                    const thumbsEl = document.getElementById('galleryThumbs');
                                    const prevBtn = document.getElementById('galleryPrevBtn');
                                    const nextBtn = document.getElementById('galleryNextBtn');
                                    const fileInput = document.getElementById('product_images');
                                    const removedImagesInput = document.getElementById('removed_images');

                                    const lightbox = document.getElementById('galleryLightbox');
                                    const lightboxImg = document.getElementById('galleryLightboxImg');
                                    const lightboxClose = document.getElementById('galleryLightboxClose');
                                    const lightboxPrev = document.getElementById('galleryLightboxPrev');
                                    const lightboxNext = document.getElementById('galleryLightboxNext');

                                    const existingImages = <?= json_encode(array_map(function ($img) {
                                        return [
                                            'id' => $img['id'],
                                            'src' => base_url('public/img/products/' . rawurlencode((string) ($img['filename'] ?? ''))),
                                        ];
                                    }, $images), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

                                    let slides = existingImages.map(img => ({ kind: 'existing', imageId: img.id, src: img.src }));
                                    let newFiles = [];
                                    let removedImages = [];
                                    let activeIndex = slides.length > 0 ? 0 : -1;

                                    function syncFileInput() {
                                        const dt = new DataTransfer();
                                        newFiles.forEach(file => dt.items.add(file));
                                        fileInput.files = dt.files;
                                    }

                                    function render() {
                                        gallery.classList.toggle('has-multiple', slides.length > 1);
                                        gallery.classList.toggle('is-empty', slides.length === 0);
                                        lightbox.classList.toggle('has-multiple', slides.length > 1);

                                        if (slides.length === 0) {
                                            mainImg.style.display = 'none';
                                            removeBtn.style.display = 'none';
                                            newBadge.style.display = 'none';
                                            emptyState.style.display = 'flex';
                                            closeLightbox();
                                        } else {
                                            if (activeIndex < 0 || activeIndex >= slides.length) activeIndex = 0;
                                            const active = slides[activeIndex];
                                            mainImg.src = active.src;
                                            mainImg.style.display = 'block';
                                            removeBtn.style.display = 'flex';
                                            newBadge.style.display = active.kind === 'new' ? 'inline-block' : 'none';
                                            emptyState.style.display = 'none';
                                            if (lightbox.classList.contains('open')) {
                                                lightboxImg.src = active.src;
                                            }
                                        }

                                        thumbsEl.innerHTML = '';
                                        slides.forEach((slide, index) => {
                                            const thumb = document.createElement('button');
                                            thumb.type = 'button';
                                            thumb.className = 'gallery-thumb' + (index === activeIndex ? ' active' : '');

                                            const img = document.createElement('img');
                                            img.src = slide.src;
                                            img.alt = 'صورة مصغرة';
                                            thumb.appendChild(img);

                                            thumb.addEventListener('click', function () {
                                                activeIndex = index;
                                                render();
                                            });

                                            thumbsEl.appendChild(thumb);
                                        });
                                    }

                                    function goTo(index) {
                                        if (slides.length === 0) return;
                                        activeIndex = (index + slides.length) % slides.length;
                                        render();
                                    }

                                    function openLightbox() {
                                        if (slides.length === 0) return;
                                        lightboxImg.src = slides[activeIndex].src;
                                        lightbox.classList.add('open');
                                    }

                                    function closeLightbox() {
                                        lightbox.classList.remove('open');
                                    }

                                    prevBtn.addEventListener('click', function (event) {
                                        event.stopPropagation();
                                        goTo(activeIndex - 1);
                                    });

                                    nextBtn.addEventListener('click', function (event) {
                                        event.stopPropagation();
                                        goTo(activeIndex + 1);
                                    });

                                    mainImg.addEventListener('click', openLightbox);

                                    lightboxClose.addEventListener('click', closeLightbox);

                                    lightbox.addEventListener('click', function (event) {
                                        if (event.target === lightbox) closeLightbox();
                                    });

                                    lightboxPrev.addEventListener('click', function (event) {
                                        event.stopPropagation();
                                        goTo(activeIndex - 1);
                                    });

                                    lightboxNext.addEventListener('click', function (event) {
                                        event.stopPropagation();
                                        goTo(activeIndex + 1);
                                    });

                                    document.addEventListener('keydown', function (event) {
                                        if (!lightbox.classList.contains('open')) return;
                                        if (event.key === 'Escape') closeLightbox();
                                        if (event.key === 'ArrowLeft') goTo(activeIndex + 1);
                                        if (event.key === 'ArrowRight') goTo(activeIndex - 1);
                                    });

                                    removeBtn.addEventListener('click', function () {
                                        if (activeIndex < 0 || activeIndex >= slides.length) return;
                                        const slide = slides[activeIndex];

                                        if (slide.kind === 'existing') {
                                            removedImages.push(slide.imageId);
                                            removedImagesInput.value = removedImages.join(',');
                                        } else {
                                            const fileIdx = newFiles.indexOf(slide.file);
                                            if (fileIdx > -1) newFiles.splice(fileIdx, 1);
                                            syncFileInput();
                                        }

                                        slides.splice(activeIndex, 1);
                                        if (activeIndex >= slides.length) activeIndex = slides.length - 1;
                                        render();
                                    });

                                    fileInput.addEventListener('change', function () {
                                        const chosen = Array.from(fileInput.files).filter(f => f.type.startsWith('image/'));
                                        if (chosen.length === 0) return;

                                        const firstNewIndex = slides.length;

                                        chosen.forEach(file => {
                                            newFiles.push(file);
                                            const slide = { kind: 'new', file: file, src: '' };
                                            slides.push(slide);

                                            const reader = new FileReader();
                                            reader.onload = function (e) {
                                                slide.src = e.target.result;
                                                render();
                                            };
                                            reader.readAsDataURL(file);
                                        });

                                        activeIndex = firstNewIndex;
                                        syncFileInput();
                                        render();
                                    });

                                    render();
                                })();
                            </script>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">السعر</label>
                            <input type="number" class="form-control" name="price" value="<?= $product['price'] != null ? $product['price'] : '' ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">رقم التنبيه بأنخفاض الكمية</label>
                            <input type="number" class="form-control" name="low_quantity" value="<?= $product['low_quantity'] != null ? $product['low_quantity'] : '' ?>">
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="serial_control" id="inlineRadio1" value="yes" <?= $hasSerial ? 'checked' : ''; ?> >
                                <label class="form-check-label" for="inlineRadio1">متعدد السيريالات</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="serial_control" id="inlineRadio2" value="no" <?= $hasNoSerial ? 'checked' : ''; ?> >
                                <label class="form-check-label" for="inlineRadio2">باركود موحد</label>
                            </div>
                        </div>
                        
                        <?php if ($update_extra_permission): ?>
                            <div class="text-center">
                                <button type="submit" class="btn btn-info text-light fs-5 fw-bold">حفظ</button>
                            </div>
                        <?php endif; ?>
                        
                    </form>
                </div>


                <!-- التاب الرابع: السيريالات -->
                <div class="tab-pane fade" id="serials" role="tabpanel">
                    <div class="content-card">
                        <div class="card-header">
                            <h5><i class="bi bi-list-ul me-2"></i>قائمة السيريالات</h5>
                            <?php if ($serials): ?>
                                <span class="serial-count"><span id="visibleSerialCount"><?= count($serials) ?></span> سيريال</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($serials): ?>
                                <div class="serial-inventory-filters" role="group" aria-label="فلترة السيريالات حسب المخزون">
                                    <button type="button" class="serial-inventory-filter sort-button active" data-serial-filter="all" aria-pressed="true">
                                        <span><i class="bi bi-grid-3x3-gap"></i> الكل</span>
                                        <strong><?= count($serials) ?></strong>
                                    </button>
                                    <?php foreach (array('23012', 'SAgent_STK', 'RESV_23012') as $inventoryCode): ?>
                                        <button type="button" class="serial-inventory-filter sort-button" data-serial-filter="<?= htmlspecialchars($inventoryCode, ENT_QUOTES, 'UTF-8') ?>" aria-pressed="false">
                                            <span><i class="bi bi-box-seam"></i> <?= htmlspecialchars($inventoryCode) ?></span>
                                            <strong><?= isset($serial_breakdown[$inventoryCode]) ? (int) $serial_breakdown[$inventoryCode] : 0 ?></strong>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                                <div class="serials-grid">
                                    <?php foreach ($serials as $index => $serialItem): ?>
                                        <?php
                                        $serialValue = isset($serialItem['serial_number']) ? $serialItem['serial_number'] : '';
                                        $inventoryCode = isset($serialItem['subinventory_code']) ? $serialItem['subinventory_code'] : '';
                                        $inventoryTone = $inventoryCode === '23012' ? 'main' : ($inventoryCode === 'SAgent_STK' ? 'agent' : 'reserved');
                                        ?>
                                        <div class="serial-item" data-subinventory="<?= htmlspecialchars($inventoryCode, ENT_QUOTES, 'UTF-8') ?>">
                                            <div class="serial-number"><?= $index + 1 ?></div>
                                            <div class="serial-details">
                                                <div class="serial-value"><?= htmlspecialchars((string) $serialValue) ?></div>
                                                <small class="serial-subinventory" data-tone="<?= $inventoryTone ?>">
                                                    <i class="bi bi-box-seam" aria-hidden="true"></i>
                                                    <?= htmlspecialchars((string) $inventoryCode) ?>
                                                </small>
                                            </div>
                                            <div class="serial-icon">
                                                <i class="bi bi-check-circle"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div id="serialFilterEmpty" class="empty-state serial-filter-empty" hidden>
                                    <div class="empty-icon"><i class="bi bi-funnel"></i></div>
                                    <h6>لا توجد سيريالات في هذا المخزون</h6>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="bi bi-inbox"></i>
                                    </div>
                                    <h6>لا توجد سيريالات</h6>
                                    <p class="text-muted">لا توجد سيريالات مسجلة لهذا المنتج</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</div>
</div>


<!-- JS -->
<script src="https://code.jquery.com/jquery-3.6.0.js" crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>

<script src="<?php echo base_url(); ?>public/js/script.js"></script>
<script src="<?php echo base_url(); ?>public/js/edit.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<?php $this->load->view('view_footer'); ?>

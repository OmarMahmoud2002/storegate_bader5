# دليل نشر التعديلات الحالية على cPanel

## 1) الخلاصة المهمة

- نسخة الهوست المفترضة في هذا الدليل هي آخر نسخة مرفوعة إلى GitHub: `origin/main` عند commit `8151326` واسمه `edit search`.
- يوجد commit محلي بعده لم يُرفع: `44fbede` واسمه `fix product serch`، ويعدل صفحة وJavaScript البحث عن المنتجات. هذا الدليل يشمله.
- الدليل يشمل كذلك كل الملفات المعدلة وغير المتتبعة الموجودة محليًا بتاريخ إعداد الدليل.
- المطلوب للنشر هو **39 ملفًا** محددًا أدناه، بالإضافة إلى مجلد `vendor/` كاملًا.
- لا ترفع `backup.sql` ولا تستورده كاملًا؛ يحتوي على `DROP TABLE` وبيانات محلية وقد يمسح بيانات الهوست. استخدم SQL المحدد في هذا الدليل فقط.
- لا يكفي رفع `composer.json` و`composer.lock`: لعدم وجود Terminal/SSH على الهوست يجب رفع `vendor/`، وفي داخله `vendor/autoload.php`.
- نفّذ SQL قبل تشغيل الكود الجديد. بعض الـModels تحاول إنشاء الجداول أو الأعمدة تلقائيًا، والأفضل إنشاؤها من phpMyAdmin أولًا وبترميز صحيح.

## 2) ما الذي تغيّر فعليًا؟

### أ) ترقية PHP 8.3 ومكتبة Excel

- تم ضبط Composer على PHP `^8.2` وإضافة `phpoffice/phpspreadsheet` بالإصدار المقفول `5.9.0`.
- تم تفعيل Composer autoload من `application/config/config.php`.
- تم استبدال استدعاءات PHPExcel القديمة الفعلية بـPhpSpreadsheet في الاستيراد، قراءة مبيعات الموظفين، وتصدير تقارير Excel.
- تم تغيير المنطقة الزمنية من `America/New_York` إلى `Asia/Riyadh`.
- تم تعديل `secure_data()` ليتعامل مع `null` بأمان في PHP 8.3.
- ملفات PHPExcel القديمة داخل `application/libraries/` لم تُحذف. اتركها على الهوست في أول نشر؛ الكود الجديد لا يعتمد عليها، ووجودها لا يضر.

### ب) مبيعات الموظفين وملفات Excel

- قراءة ديناميكية لشيت مبيعات الموظفين بدل الاعتماد الصلب على مواقع أعمدة محددة.
- تحميل شيت `staff sales` المطلوب فقط عندما يمكن ذلك، مع cache للملف لتحسين الأداء.
- التحقق من بنية ملف Excel قبل اعتماده وحذف الملف المرفوع إذا كان غير صالح.
- أسماء تخزين فريدة للملفات، مع تحديث سجل الفترة الصحيحة في `tbl_employees_sales`.
- دعم مسميات وظيفية إضافية، وتحسين البحث والتجميع والتصدير إلى XLSX.
- تحسين واجهة رفع ملف المبيعات.

### ج) المخزون والمنتجات والبحث

- البحث الموحد بالسيريال أو جزء منه أو كود المنتج أو الباركود.
- احتساب المخزون من المخازن الثلاثة: `23012` و`SAgent_STK` و`RESV_23012` مع عرض التفصيل.
- فصل سلة كل مدير بواسطة `tbl_cart.user_id`.
- حفظ آخر وقت نجاح لرفع ملف المخزون في `tbl_settings`.
- إضافة سجل دائم للسيريالات في `tbl_product_serial_history` حتى لا تضيع هوية المنتج بعد خروجه من المخزون الحالي.
- حفظ اسم وكود المنتج داخل سجل العمليات وعرض timeline يدمج عمليات المخزون مع المبيعات.
- تحسين تعديل المنتج والصور والرفوف، وتسجيل إزالة المنتج من الرف.
- تعديل كبير في واجهتي البحث وتعديل المنتج وملفات CSS/JS المرتبطة بهما.

### د) الموظفون وكلمات السر والجرد

- صفحة ملف تعريفي للموظف وتغيير كلمة السر.
- أدوات توليد ونسخ كلمة السر في صفحات الموظفين والمديرين.
- صفحة جرد مخزون جديدة، لكنها حاليًا صفحة «تحت التطوير» فقط.
- صلاحية جديدة باسم `stocktaking.view` يجب إضافتها لجدول `permissions` ثم منحها للأدوار المطلوبة.

## 3) نسخة احتياطية قبل أي شيء

من cPanel:

1. صدّر قاعدة بيانات الهوست الحالية من phpMyAdmin بصيغة SQL.
2. من File Manager اضغط مجلد الموقع الحالي إلى ZIP ونزّله، أو أنشئ نسخة باسم مثل `public_html_before_php83` إن كانت المساحة تسمح.
3. سجّل إصدار PHP الحالي، واحتفظ بإمكانية الرجوع إليه.
4. يفضّل تنفيذ النشر في وقت قليل الاستخدام أو أثناء وضع صيانة، لأن ملفات التطبيق و`vendor` مترابطة.

## 4) SQL المطلوب في phpMyAdmin

اختر **قاعدة بيانات الموقع الصحيحة** أولًا، ثم افتح تبويب **SQL**.

### 4.1 فحص قبل التنفيذ

نفّذ هذا الاستعلام أولًا. المفروض ألا يعرض الأعمدة/الجدول الجديدة إذا كانت قاعدة الهوست فعلًا على نسخة `8151326`:

```sql
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'tbl_cart' AND COLUMN_NAME = 'user_id')
    OR (TABLE_NAME = 'tbl_operations' AND COLUMN_NAME IN ('item_code', 'item_description'))
  );

SELECT TABLE_NAME
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'tbl_product_serial_history';

SELECT id, field, value
FROM tbl_settings
WHERE field = 'stock_excel_last_uploaded_at';

SELECT id, `key`, label
FROM permissions
WHERE `key` = 'stocktaking.view';
```

إذا ظهر عنصر موجود بالفعل، لا تعِد أمر إنشائه الخاص به. سكربت `ALTER TABLE` التالي مصمم للتنفيذ مرة واحدة على نسخة الهوست المحددة.

### 4.2 سكربت التحديث

```sql
SET NAMES utf8mb4;

-- 1) فصل سلة كل مدير عن الآخرين.
ALTER TABLE `tbl_cart`
  ADD COLUMN `user_id` INT(11) NOT NULL DEFAULT 0 AFTER `item_id`;

-- 2) الاحتفاظ بهوية المنتج داخل سجل العمليات.
ALTER TABLE `tbl_operations`
  ADD COLUMN `item_code` VARCHAR(120)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    NOT NULL DEFAULT '' AFTER `serial`,
  ADD COLUMN `item_description` VARCHAR(255)
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
    NOT NULL DEFAULT '' AFTER `item_code`;

-- 3) سجل دائم لهوية السيريالات حتى بعد حذفها من المخزون الحالي.
CREATE TABLE `tbl_product_serial_history` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `serial_number` VARCHAR(191) NOT NULL,
  `barcode` VARCHAR(191) NOT NULL DEFAULT '',
  `item_code` VARCHAR(120) NOT NULL DEFAULT '',
  `item_description` VARCHAR(255) NOT NULL DEFAULT '',
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `serial_number_unique` (`serial_number`),
  KEY `item_code_index` (`item_code`)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- 4) ملء سجل السيريالات من المخزون الموجود على الهوست الآن.
INSERT INTO `tbl_product_serial_history`
  (`serial_number`, `barcode`, `item_code`, `item_description`, `updated_at`)
SELECT
  UPPER(TRIM(`serial_number`)),
  COALESCE(`barcode`, ''),
  COALESCE(`item_code`, ''),
  COALESCE(`item_description`, ''),
  NOW()
FROM `current_store_items`
WHERE NULLIF(TRIM(`serial_number`), '') IS NOT NULL
ON DUPLICATE KEY UPDATE
  `barcode` = VALUES(`barcode`),
  `item_code` = VALUES(`item_code`),
  `item_description` = VALUES(`item_description`),
  `updated_at` = VALUES(`updated_at`);

-- 5) محاولة استكمال بيانات العمليات القديمة من السيريالات الحالية.
UPDATE `tbl_operations` AS o
INNER JOIN `tbl_product_serial_history` AS h
  ON TRIM(LEADING 'S' FROM UPPER(TRIM(h.`serial_number`)))
   = TRIM(LEADING 'S' FROM UPPER(TRIM(o.`serial`)))
SET
  o.`item_code` = h.`item_code`,
  o.`item_description` = h.`item_description`
WHERE NULLIF(TRIM(o.`serial`), '') IS NOT NULL
  AND (TRIM(o.`item_code`) = '' OR TRIM(o.`item_description`) = '');

-- 6) إعداد عرض تاريخ آخر رفع ناجح لملف المخزون.
INSERT INTO `tbl_settings` (`field`, `value`)
SELECT 'stock_excel_last_uploaded_at', ''
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `tbl_settings`
  WHERE `field` = 'stock_excel_last_uploaded_at'
);

-- 7) تسجيل صلاحية صفحة الجرد في كتالوج الصلاحيات.
INSERT INTO `permissions`
  (`key`, `label`, `type`, `page_label`, `route`, `section_name`, `section_icon`)
SELECT
  'stocktaking.view',
  'عرض الجرد',
  'view',
  'جرد المخزون',
  'admin/stocktaking',
  'الشحنات والتقفيلة والنماذج',
  'bi-truck'
FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `permissions`
  WHERE `key` = 'stocktaking.view'
);
```

لا تستورد `backup.sql`: الفرق الظاهر فيه لا يشمل كل تغييرات الـschema التي يطلبها الكود، كما أن استيراده كاملًا قد يستبدل بيانات الهوست ببيانات النسخة المحلية.

### 4.3 فحص SQL بعد التنفيذ

```sql
SHOW COLUMNS FROM `tbl_cart` LIKE 'user_id';
SHOW COLUMNS FROM `tbl_operations` LIKE 'item_code';
SHOW COLUMNS FROM `tbl_operations` LIKE 'item_description';
SHOW TABLES LIKE 'tbl_product_serial_history';

SELECT COUNT(*) AS archived_serials
FROM `tbl_product_serial_history`;

SELECT id, field, value
FROM `tbl_settings`
WHERE field = 'stock_excel_last_uploaded_at';

SELECT id, `key`, label
FROM `permissions`
WHERE `key` = 'stocktaking.view';
```

بعد تشغيل الموقع، افتح صفحة إدارة الصلاحيات وعدّل الدور المطلوب وفعّل **عرض الجرد**. إدخال الصلاحية في جدول `permissions` لا يمنحها تلقائيًا لكل الأدوار.

## 5) الملفات التي يجب رفعها

كل المسارات التالية نسبةً إلى جذر المشروع على الهوست، وهو نفس المجلد الذي يحتوي على `index.php`.

### 5.1 Composer وPHP 8.3 — مطلوبة أولًا

```text
composer.json
composer.lock                         [ملف جديد]
vendor/                               [المجلد كاملًا، وهو غير متتبع في Git]
application/config/config.php         [ارفعه في نهاية رفع الملفات وقبل تبديل PHP مباشرة]
application/helpers/site_helper.php
```

### 5.2 ترحيل Excel ومبيعات الموظفين

```text
application/controllers/Excel_import.php
application/controllers/Upload.php
application/controllers/admin/Other.php
application/helpers/employees_sales_helper.php
application/models/Model_employees_sales.php
application/services/EmployeeSalesService.php
application/views/employee_sales/view_admin_employee_sales_details.php
application/views/employee_sales/view_admin_employees_sales_uploader.php
```

ملاحظة: `application/controllers/admin/Other.php` يحتوي أيضًا على صفحة الجرد وتغييرات أخرى، فلا يمكن رفع «جزء Excel» منه وحده.

### 5.3 المخزون والبحث والمنتجات والعمليات والرفوف

```text
application/controllers/admin/Products.php
application/controllers/admin/Shelves.php
application/models/Excel_import_model.php
application/models/Model_operations.php
application/models/Model_settings.php
application/models/Model_store_items.php
application/views/products/edit.php
application/views/products/search.php
application/views/view_admin_dashboard.php
application/views/view_admin_operations.php
application/views/view_admin_shelves_products.php
public/css/edit_product.css
public/js/edit.js
public/js/search_product.js
public/js/shelf.js
```

الملفان `application/views/products/search.php` و`public/js/search_product.js` يشملان الـcommit المحلي `44fbede` غير المرفوع، لذلك لا تنسهما حتى لو كنت تقارن مع آخر commit محلي فقط.

### 5.4 الموظفون وكلمات السر والجرد والواجهة العامة

```text
application/controllers/User.php
application/views/view_admin_admins.php
application/views/view_admin_employee.php
application/views/view_admin_employees.php
application/views/view_admin_sidebar.php
application/views/view_header.php
application/views/view_user_sidebar.php
application/views/view_admin_stocktaking.php    [ملف جديد]
application/views/view_user_profile.php         [ملف جديد]
public/css/admin-ui-pattern.css
public/css/password-field.css                    [ملف جديد]
public/js/password-field.js                      [ملف جديد]
```

## 6) عناصر لا تُرفع إلى الهوست

```text
.gitignore
backup.sql
دليل_ترقية_PHP.md
دليل_نشر_التعديلات_على_cPanel.md
AI_Integration_Report.md
EMPLOYEES_SALES_DYNAMIC_EXCEL_IMPLEMENTATION_REPORT.md
EMPLOYEES_SALES_DYNAMIC_EXCEL_REFACTOR_PLAN.md
EMPLOYEES_SALES_PERFORMANCE_ANALYSIS.md
EMPLOYEES_SALES_PERFORMANCE_IMPLEMENTATION_REPORT.md
تحليل_صفحة_مبيعات_الموظفين.md
preview.png
public/employees_sales/*.xlsx
public/img/products/6a68*.jpg
```

السبب:

- ملفات Markdown و`preview.png` توثيق/معاينة محلية وليست جزءًا من تشغيل الموقع.
- ملفات Excel الموجودة محليًا ملفات رفع وتجربة؛ رفعها وحدها لا ينقل سجلاتها أو مسارها من قاعدة البيانات المحلية إلى قاعدة الهوست.
- صور المنتجات الجديدة ملفات بيانات محلية، ولا توجد ضمن التغييرات الحالية أوامر SQL تنقل سجلاتها في `tbl_products_images` إلى الهوست. لا ترفعها إلا إذا قررت نقل نفس بيانات المنتجات يدويًا أيضًا.
- لا ترفع `application/config/database.php` من جهازك، فهو غير متغير أصلًا وقد يحتوي بيانات اتصال مختلفة عن الهوست.

### بدل نقل 39 ملفًا واحدًا واحدًا

لا تحتاج Terminal على الهوست لكي ترفعها دفعة واحدة. أنشئ محليًا مجلد staging بنفس بنية المشروع، وانسخ إليه **المسارات الـ39 المذكورة فقط** مع مجلد `vendor/`، ثم اضغط محتويات الـstaging إلى ملف مثل `storegate-php83-deploy.zip`.

من cPanel File Manager:

1. ارفع ZIP واحدًا إلى جذر الموقع.
2. نفّذ SQL أولًا وضع الموقع في الصيانة.
3. استخدم **Extract** واختر جذر الموقع نفسه، مع الموافقة على استبدال الملفات الموجودة.
4. تحقق أن المسارات خرجت مباشرة مثل `application/controllers/...` و`vendor/autoload.php`، وليست داخل مجلد إضافي مثل `storegate-php83-deploy/application/...`.
5. احذف ZIP من الهوست بعد نجاح الاختبار.

يمكن أيضًا عمل ZIP مستقل لـ`vendor/` وZIP ثانٍ للملفات الـ39؛ هذا أفضل إذا كان حد رفع الملفات في cPanel صغيرًا أو أردت رفع المكتبة أولًا ثم كود التطبيق.

## 7) التعامل مع `vendor/` بدون SSH

النسخة المحلية التي تم فحصها تحتوي `vendor/autoload.php`، ومتطلبات PHP 8.3 ناجحة. المجلد الحالي حجمه حوالي **10.2 MB** ويحتوي حوالي **1927 ملفًا**، ولذلك لا ترفعه ملفًا ملفًا.

الطريقة العملية:

1. اضغط مجلد `vendor` محليًا إلى ملف مثل `vendor-php83.zip` بحيث يكون داخل الملف مجلد اسمه `vendor`.
2. ارفع ZIP إلى جذر الموقع من cPanel File Manager.
3. استخدم **Extract** في جذر الموقع، وتأكد أن الناتج هو `vendor/autoload.php` وليس `vendor/vendor/autoload.php`.
4. احذف ZIP من الهوست بعد نجاح الاستخراج لتوفير المساحة.

المجلد المحلي الحالي يحتوي كذلك حزم تطوير. سيعمل عند رفعه، لكن للحصول على نسخة إنتاج أصغر يفضّل تجهيز نسخة منفصلة محليًا بواسطة:

```powershell
composer install --no-dev --prefer-dist --optimize-autoloader
composer check-platform-reqs --no-dev
```

نفّذ ذلك داخل **نسخة منفصلة من المشروع** حتى لا تحذف أدوات التطوير من نسخة عملك الأساسية. ارفع `vendor/` الناتج مع نفس `composer.lock` الموجود في هذا المشروع.

## 8) ترتيب النشر الصحيح على cPanel

1. خذ نسختي الملفات وقاعدة البيانات الاحتياطيتين.
2. نفّذ SQL في القسم 4 وتأكد من نتائج الفحص.
3. فعّل وضع صيانة إن أمكن.
4. ارفع `composer.json` و`composer.lock`.
5. ارفع ZIP الخاص بـ`vendor/` واستخرجه، ثم تأكد من وجود `vendor/autoload.php`.
6. ارفع ملفات CSS وJS الجديدة/المعدلة.
7. ارفع الـModels والـHelpers والـService.
8. ارفع الـViews الجديدة/المعدلة.
9. ارفع الـControllers الجديدة/المعدلة.
10. ارفع `application/config/config.php` أخيرًا؛ هذا الملف يفعّل `vendor/autoload.php` ويغير المنطقة الزمنية.
11. من cPanel اختر PHP 8.3 للموقع.
12. فعّل امتدادات PHP المطلوبة: `mysqli`, `pdo_mysql`, `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `iconv`, `libxml`, `mbstring`, `simplexml`, `xml`, `xmlreader`, `xmlwriter`, `zip`, و`zlib`. ويوصى كذلك بـ`curl`, `intl`, و`openssl` لباقي وظائف الموقع.
13. أوقف وضع الصيانة وابدأ الاختبارات فورًا.

إذا كان الهوست قد تم تحويله بالفعل إلى PHP 8.3، نفّذ الخطوات تحت وضع صيانة وارفع `vendor` و`config.php` والملفات المعتمدة عليهما في نفس النافذة بأسرع ترتيب ممكن.

## 9) اختبار سريع بعد الرفع

اختبر بالترتيب:

- فتح الصفحة الرئيسية وتسجيل الدخول كمدير وكموظف.
- فتح البحث عن منتج والبحث بسيريال كامل، جزء سيريال، كود منتج، وباركود.
- إضافة منتج للسلة من مديرين مختلفين والتأكد أن السلتين منفصلتان.
- فتح تعديل منتج والتأكد من إجمالي المخازن الثلاثة والصور.
- فتح الرفوف، نقل منتج، وإزالة منتج من رف.
- فتح سجل العمليات لسيريال والتأكد من ظهور الاسم والكود والـtimeline.
- رفع ملف مخزون تجريبي والتأكد من ظهور وقت آخر رفع.
- رفع ملف مبيعات موظفين صالح، ثم البحث والتنزيل بصيغة XLSX.
- تجربة ملف Excel غير صالح والتأكد من ظهور رسالة خطأ وعدم اعتماده.
- فتح ملف الموظف وتغيير كلمة السر، وتجربة توليد ونسخ كلمة سر من لوحة الإدارة.
- منح `stocktaking.view` لدور تجريبي والتأكد من ظهور رابط صفحة الجرد.
- مراجعة `application/logs/` وواجهة **Errors** في cPanel بحثًا عن Fatal error أو أخطاء Composer/PhpSpreadsheet.

نظّف cache المتصفح أو استخدم نافذة خاصة إذا ظهرت واجهات CSS/JS قديمة.

## 10) الرجوع السريع عند وجود مشكلة

1. أعد إصدار PHP السابق من cPanel.
2. استرجع ملفات الموقع من ZIP الاحتياطي.
3. لا تحذف الجدول أو الأعمدة الجديدة عند الرجوع؛ التغييرات كلها إضافية والكود القديم سيتجاهلها، وتركها يحافظ على أي بيانات تكونت بعد النشر.
4. إذا كانت المشكلة في البيانات نفسها، استرجع قاعدة البيانات فقط من النسخة الاحتياطية التي أخذتها قبل التنفيذ.

## 11) نتائج الفحص المحلي وقت إعداد الدليل

- PHP المحلي: `8.3.28`.
- `composer.json` صالح.
- `composer check-platform-reqs --no-dev`: ناجح لكل المتطلبات.
- فحص syntax لجميع ملفات PHP المعدلة والجديدة: **29 ملفًا، 0 أخطاء**.
- هذه الفحوص لا تغني عن اختبار الهوست، لأن إصدار MariaDB وإعدادات PHP والصلاحيات وحدود الرفع والذاكرة قد تختلف.

# دليل ترقية PHP للمشروع

## الخلاصة السريعة

* **إصدار PHP الفعلي للموقع قبل الترقية:** `PHP 5.6`، وفق إعدادات cPanel السابقة. الإصدار المختار والمتاح حالياً على الاستضافة هو **PHP 8.3.31**.
* **الإصدار الذي يجب تثبيته محلياً للاختبار:** `PHP 8.3.31`، لمطابقة الاستضافة. لا تعتمد PHP 8.3.28 في الاختبار النهائي.
* المشروع مبني على **CodeIgniter 3.1.13**؛ ملف Composer يضع الحد الأدنى القديم `PHP >= 5.3.7`، وليس إصداراً مستهدفاً حديثاً.
* أحدث فرع PHP مستقر ومدعوم عالمياً وقت إعداد هذا الدليل (14 يوليو 2026) هو **PHP 8.5**، وآخر إصدار نقطي ظاهر في صفحة الإصدارات الرسمية هو **8.5.7** (4 يونيو 2026). لكن الإصدار المختار للمشروع لمطابقة الاستضافة هو **PHP 8.3.31**.

> مهم: بيئة التطوير المحلية وبيئة الاستضافة منفصلتان. فحص الاستضافة أكد أن PHP 8.3 هو الإصدار المستهدف وأن رقمه الكامل هو 8.3.31. لا تفترض أن نسخة الويب ونسخة CLI متطابقتان؛ تحقق من كلتيهما بـ `php -v` أو `phpinfo()`.

## نتيجة فحص المشروع: مانع رئيسي قبل PHP 8.5

يوجد في المشروع نسختان مضمّنتان من مكتبة **PHPExcel** القديمة تحت:

* `application/libraries/PHPExcel/`
* `application/libraries/1/PHPExcel/`

وهما مستخدمتان فعلياً في الاستيراد والتصدير من Excel، ومنها `Upload.php` و`Excel_import.php` و`admin/Products.php` و`admin/Other.php` و`EmployeeSalesService.php`.

فحص الصياغة بـ PHP 8.3.28 أظهر أخطاء قاتلة في PHPExcel مثل استخدام الوصول بالأقواس المعقوفة (`$value{0}`)، وهو تركيب أزيل من PHP 8.0. لذلك **لا ينبغي ترقية PHP وحدها إلى 8.5**: وظائف ملفات Excel ستنكسر، وربما لا تُحمَّل بعض الصفحات التي تستدعيها. أمّا ملفات المشروع خارج مكتبات الجداول المضمّنة فقد اجتازت فحص الصياغة بـ PHP 8.3.28 (102 ملفاً).

المكتبة البديلة الصحيحة هي `phpoffice/phpspreadsheet`. هي الامتداد الحديث لـ PHPExcel، والمشروع يحتوي بالفعل على استخدام namespaced محدود لها في `application/models/Model_admin.php`، لكن الترحيل غير مكتمل. الإصدار الحديث منها يتطلب PHP 8.2 أو أحدث، وبالتالي يناسب 8.5 بعد إتمام الترحيل.

## الهدف المقترح

الهدف العملي للمشروع هو **PHP 8.3.31** مع ترحيل PHPExcel إلى PhpSpreadsheet أولاً. أنشئ بيئة اختبار محلية على 8.3.31 أولاً، ثم حدّث الإنتاج بعد اكتمال الاختبارات. لا تستخدم PHP 5.6 أو PHP 8.3.28 كهدف نهائي.

### قيد الاستضافة الحالي

الدومين `storegate.org` يرث `PHP 5.6` من إعداد الحساب (**account default**)، ولا يوجد له حالياً إعداد مستقل. اختر من cPanel فرع `PHP 8.3`، وتأكد عبر `phpinfo()` بعد الحفظ أن الرقم الكامل هو `8.3.31`.

## ما ستحتاجه

1. نسخة احتياطية قابلة للاسترجاع من قاعدة البيانات ومن ملفات الرفع `public/`، مع نقطة Git/نسخة مجلدة من المشروع.
2. بيئة اختبار مستقلة (نسخة من قاعدة البيانات بعد إخفاء البيانات الحساسة إن أمكن)؛ لا تنفذ التحديث الأول على الإنتاج.
3. PHP 8.5 x64 المناسب لـ Windows/Laragon، وComposer (المتاح حالياً: 2.9.3).
4. امتدادات PHP التالية مفعّلة: `mysqli`, `pdo_mysql`, `mbstring`, `curl`, `gd`, `intl`, `zip`, `xml`, `xmlreader`, `xmlwriter`, `dom`, `fileinfo`, `openssl` و`zlib`.
5. عينات Excel حقيقية للاختبار: `.xlsx` و`.xls` إن كان النظام يقبله، ملف كبير، ملف عربي، وملف يحوي تواريخ وتنسيقات.
6. وقت لاختبار تسجيل الدخول والصلاحيات والرفع والاستيراد والتصدير والتقارير؛ هذه ليست ترقية خادم فقط بل ترقية توافق للمكتبات.

كل الامتدادات المذكورة أعلاه مفعلة في PHP 8.3 المحلي حالياً، لذا تكمن المخاطرة الأساسية في **توافق الكود والمكتبات** لا في نقص هذه الامتدادات.

## الخطة الصحيحة بالترتيب

> لأن الإنتاج الآن على PHP 5.6 وPhpSpreadsheet الحديث يتطلب PHP 8.2 أو أعلى، لا يمكن نشر التحويل إلى PhpSpreadsheet على الاستضافة القديمة وحده. جهّز الكود وPHP 8.3.31 في بيئة اختبار أولاً، ثم انشر التغييرين معاً.

### 1) التحضير والتوثيق

* خذ نسخة احتياطية لقاعدة البيانات وملفات الرفع واحتفظ بمسار PHP 8.3 كما هو للرجوع السريع.
* أنشئ فرع Git مخصصاً للترقية، ولا تعدّل `vendor/` يدوياً.
* شغّل على النسخة الحالية قائمة سيناريوهات الاختبار وسجل النتائج والصور عند الحاجة؛ ستكون هذه مرجع المقارنة بعد التحديث.
* راجع متغيرات البيئة وبيانات الاتصال في `application/config/database.php` ولا تضع كلمات مرور الإنتاج داخل Git أو في هذا الملف التوثيقي.

### 2) جهّز بيئة اختبار بـ PHP 8.5

لا تغيّر إنتاج 5.6 في هذه المرحلة. جهّز نسخة من الموقع وقاعدة البيانات في بيئة اختبار، وثبّت PHP 8.3.31 وComposer عليها. يمكن تنفيذ ذلك محلياً في Laragon، أو على نطاق تجريبي في استضافة تدعم الإصدار المستهدف.

تحقق أن البيئة الجديدة تعمل وأن الامتدادات اللازمة مفعلة:

```powershell
php -v
php --ini
php -m
```

### 3) ترحيل PHPExcel إلى PhpSpreadsheet

نفّذ الأمر التالي من جذر المشروع **في فرع الاختبار وتحت PHP 8.3.31 فقط**:

```powershell
composer require phpoffice/phpspreadsheet
```

ثم فعّل autoloader الخاص بـ Composer في CodeIgniter 3 بتغيير الإعداد في `application/config/config.php` إلى مسار فعلي (بدلاً من `FALSE`):

```php
$config['composer_autoload'] = FCPATH . 'vendor/autoload.php';
```

بدّل الاستدعاءات القديمة تدريجياً، مع إضافة imports أعلى الملفات التي تستخدم المكتبة:

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
```

أمثلة التحويل المباشر:

| PHPExcel القديم | PhpSpreadsheet الجديد |
| --- | --- |
| `PHPExcel_IOFactory::load($path)` | `IOFactory::load($path)` |
| `new PHPExcel()` | `new Spreadsheet()` |
| `PHPExcel_Cell::columnIndexFromString($c)` | `Coordinate::columnIndexFromString($c)` |
| `PHPExcel_Cell::stringFromColumnIndex($i)` | `Coordinate::stringFromColumnIndex($i)` |
| `PHPExcel_Shared_Date::ExcelToPHP($v)` | `Date::excelToDateTimeObject($v)` (ثم نسّق النتيجة) |
| `PHPExcel_Style_Alignment::HORIZONTAL_CENTER` | `Alignment::HORIZONTAL_CENTER` |
| `PHPExcel_Style_Border::BORDER_THIN` | `Border::BORDER_THIN` |
| `PHPExcel_Style_Fill::FILL_SOLID` | `Fill::FILL_SOLID` |

لا يكفي استبدال الأسماء آلياً: راجع خصوصاً تحويل التاريخ، أساليب القراءة، وحفظ الملف والتنزيل. أزل تحميل `application/libraries/Excel.php` والنسخ المكررة من PHPExcel فقط **بعد** أن تنجح كل السيناريوهات، كي لا يظهر تعارض بين autoloaders.

### 4) تثبيت PHP 8.5 في Laragon كنسخة إضافية

1. أوقف Apache/Nginx من Laragon.
2. حمّل حزمة Windows x64 من مصدر PHP الرسمي، وفكها في مجلد جديد داخل `C:\laragon\bin\php\`، مثلاً `php-8.5.x-Win32-vs17-x64`. لا تستبدل مجلد 8.3 الحالي.
3. ابدأ من `php.ini-development` الخاص بالنسخة الجديدة وأنشئ `php.ini`. انقل فقط الإعدادات اللازمة من الإعداد القديم (مثل المنطقة الزمنية وحدود الرفع والامتدادات)، ولا تنسخ الملف القديم كاملاً بلا مراجعة.
4. من Laragon اختر النسخة الجديدة من: `Menu > PHP > Version`، ثم أعد تشغيل Laragon.
5. تحقّق من الطرفية ومن الموقع:

```powershell
php -v
php --ini
php -m
php -l index.php
```

وينبغي أن يظهر `PHP 8.5.x` وأن تكون امتدادات قاعدة البيانات و`zip` و`mbstring` و`xml` مفعلة.

### 5) اختبار كامل قبل الإطلاق

نفّذ فحصاً نحوياً لكل كود المشروع (مع الاعتماديات المثبتة) ثم اختبر يدوياً:

```powershell
Get-ChildItem application -Recurse -Filter *.php -File |
  ForEach-Object { php -l $_.FullName }
composer check-platform-reqs
```

اختبر على بيئة الاختبار:

* تسجيل الدخول والخروج، الجلسة، الصلاحيات، والتنبيهات.
* عمليات قاعدة البيانات: إضافة وتعديل وحذف المنتجات والموظفين والمبيعات.
* رفع الصور والملفات، مع التحقق من أحجام الرفع وامتداد الملف.
* كل مسار استيراد Excel وكل مسار تصدير Excel، بما في ذلك التواريخ والبيانات العربية والملفات الكبيرة.
* سجل الأخطاء في `application/logs/` وسجل خادم الويب؛ عالج تحذيرات `Deprecated` قبل الإنتاج.

### 6) الإطلاق والرجوع السريع

* خذ نسخة احتياطية جديدة قبل لحظة الإطلاق.
* انشر كود PhpSpreadsheet و`composer.lock` و`vendor/` عبر `composer install --no-dev --optimize-autoloader` في بيئة الإنتاج، وليس `composer update`.
* تأكد أولاً أن الاستضافة تعمل على PHP 8.3.31 لهذا الدومين، ثم بدّل نسخة PHP، أعد تشغيل الخادم، وأعد اختبار أهم المسارات فوراً.
* إذا ظهر خلل، أعد Laragon/الخادم إلى PHP 8.3 السابق وأعد الكود/قاعدة البيانات من نقطة الرجوع. احتفظ بالنسخة السابقة حتى انتهاء فترة المراقبة.

## تحسينات موصى بها بالتزامن

* لا ترحّل CodeIgniter 3 إلى CodeIgniter 4 داخل نفس تغيير PHP؛ ذلك مشروع مستقل لأن البنية وNamespaces وآلية التوجيه تختلف. حدّث CI3 فقط ضمن خط 3.1.13 إن ظهر patch أمني متوافق، ثم خطّط لترحيل CI4 منفصلاً.
* غيّر تخزين كلمات المرور الذي يستخدم `md5()` في `application/models/Model_admin.php` إلى `password_hash()` و`password_verify()` ضمن خطة انتقال تدريجية؛ هذه أولوية أمنية مستقلة عن إصدار PHP.
* اضبط بيئة الإنتاج في `index.php` عبر `CI_ENV=production` وتأكد أن عرض الأخطاء مغلق في الإنتاج.
* أضف اختبارات آلية للوظائف الحرجة ولملفات Excel، ثم اجعل `php -l` و`composer check-platform-reqs` جزءاً من خط النشر.

## دليل التنفيذ الفعلي: ماذا أغيّر وماذا أرفع؟

هذه العملية تنقل الموقع من PHP 5.6 إلى PHP 8.3.31 بأمان. لا تنفّذ خطوة الاستضافة قبل إتمام واختبار خطوات الكود المحلية.

### المرحلة A — جهّز نقطة رجوع

1. من cPanel > **Backup** صدّر قاعدة البيانات بصيغة SQL، واحفظها خارج الاستضافة.
2. من **File Manager** نزّل نسخة مضغوطة من مجلد الموقع الفعلي (غالباً `public_html` أو المجلد المربوط بالدومين)، بما فيه `application/` و`system/` و`public/` وملفات الرفع.
3. لا تحذف أي ملف من الاستضافة في هذه المرحلة، ولا تغيّر PHP من 5.6 الآن.
4. أنشئ فرعاً مستقلاً محلياً، مثلاً:

```powershell
git switch -c upgrade/php-8-4
```

### المرحلة B — عدّل المشروع محلياً على PHP 8.3.31

1. شغّل نسخة اختبار محلية بــ PHP 8.3.31، ثم نفّذ:

```powershell
composer require phpoffice/phpspreadsheet
composer check-platform-reqs
```

2. في `composer.json` غيّر شرط PHP من:

```json
"php": ">=5.3.7"
```

إلى:

```json
"php": "^8.2"
```

ثم شغّل `composer update --lock` إذا لم يحدّث Composer ملف القفل تلقائياً. احتفظ بملفي `composer.json` و`composer.lock` الناتجين.

3. في `application/config/config.php` بدّل:

```php
$config['composer_autoload'] = FALSE;
```

إلى:

```php
$config['composer_autoload'] = FCPATH . 'vendor/autoload.php';
```

4. أزل تحميل مكتبة CodeIgniter القديمة من هذه الملفات (السطر `$this->load->library('excel');`) وأضف imports الخاصة بـ PhpSpreadsheet:

| الملف الذي يجب تعديله | التغيير المطلوب |
| --- | --- |
| `application/controllers/Upload.php` | إزالة تحميل `excel` واستخدام `IOFactory::load()` |
| `application/controllers/Excel_import.php` | إزالة تحميل `excel` وتحويل التاريخ إلى `Date::excelToDateTimeObject()` وتحويل `PHPExcel_Settings`/PCLZip إلى اعتماد امتداد `zip` |
| `application/controllers/admin/Products.php` | إزالة تحميل `excel` واستخدام `IOFactory::load()` |
| `application/controllers/admin/Other.php` | إزالة تحميل `excel` وتحويل القراءة والتصدير والأساليب إلى `Spreadsheet`, `IOFactory`, `Coordinate`, `Alignment`, `Border`, `Fill` |
| `application/services/EmployeeSalesService.php` | استخدام `IOFactory` و`Coordinate` بدلاً من `PHPExcel_IOFactory` و`PHPExcel_Cell` |

تضاف أعلى الملف، حسب ما يستخدمه، الاستدعاءات التالية:

```php
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
```

5. طبّق جدول الاستبدال الموجود سابقاً في هذا الدليل على **كل** نتائج البحث عن `PHPExcel`. لا تكتفِ بملف واحد: توجد استدعاءات في الملفات الخمسة أعلاه، ومنها إنشاء ملف Excel للتقرير في `admin/Other.php`.

6. لا تحذف المجلدات القديمة بعدُ. بعد أن لا يعيد الأمر التالي أي استدعاء فعلي لـ `PHPExcel` خارج النسخ القديمة، تصبح جاهزاً لحذفها في إصدار لاحق:

```powershell
rg -n "PHPExcel" application --glob '*.php'
```

### المرحلة C — اختبر قبل الرفع

1. شغّل الموقع محلياً على PHP 8.3.31، وتحقق من الصفحات الرئيسية وتسجيل الدخول وعمليات قاعدة البيانات.
2. اختبر كل عملية Excel: الاستيراد، قراءة الأعمدة المدمجة، التواريخ، الملفات العربية، والتصدير والتنزيل.
3. شغّل:

```powershell
Get-ChildItem application -Recurse -Filter *.php -File |
  Where-Object { $_.FullName -notmatch '\\PHPExcel\\' } |
  ForEach-Object { php -l $_.FullName }
composer check-platform-reqs
```

لا تنتقل إلى المرحلة التالية ما دام سجل الأخطاء يعرض `PHPExcel` أو `Deprecated` أو أخطاء في Excel.

### المرحلة D — الملفات التي تُرفع إلى الاستضافة

من نسخة الاختبار الناجحة، ارفع **فقط** العناصر التالية إلى جذر الموقع نفسه على الاستضافة، مع المحافظة على بنية المجلدات:

```text
application/config/config.php
application/controllers/Upload.php
application/controllers/Excel_import.php
application/controllers/admin/Products.php
application/controllers/admin/Other.php
application/services/EmployeeSalesService.php
composer.json
composer.lock
vendor/                 (إن لم يتوفر SSH/Composer على الاستضافة)
```

قد تظهر ملفات إضافية في قائمة Git إذا عدلتها أثناء الترحيل؛ ارفعها أيضاً. لا ترفع ملفاتك المحلية الحساسة، ولا تستبدل `application/config/database.php` بملف محلي إن كانت بياناته مختلفة عن بيانات الاستضافة.

**أفضل طريقة للـ `vendor/`:** إن توفر Terminal/SSH وComposer على الاستضافة، ارفع فقط `composer.json` و`composer.lock` ثم نفّذ داخل جذر الموقع:

```bash
composer install --no-dev --optimize-autoloader
```

إن لم يتوفر Composer/SSH، ارفع مجلد `vendor/` كاملاً من نفس نسخة الاختبار التي تم فيها تنفيذ `composer install` أو `composer require`. يجب أن يحتوي على `vendor/autoload.php`.

لا ترفع ولا تحذف هذه العناصر أثناء أول نشر:

```text
application/libraries/PHPExcel/
application/libraries/1/PHPExcel/
application/libraries/PHPExcel.php
application/libraries/Excel.php
application/libraries/IOFactory.php
```

اتركها مؤقتاً كملفات غير مستخدمة، ثم احذفها فقط بعد نجاح الموقع على 8.3.31 والتأكد من عدم وجود استدعاء لها. لا تحتاج إلى رفع `system/` أو `public/` أو قاعدة البيانات ما لم يكن أحدها قد تغيّر بالفعل ضمن عمل الترحيل.

### المرحلة E — تبديل PHP في cPanel

1. فعّل صفحة صيانة أو نفّذ التبديل في وقت قليل الاستخدام.
2. من **Account PHP Settings** اختر `PHP 8.3`. تأكد بعد الحفظ عبر `phpinfo()` أن الرقم الكامل هو `PHP 8.3.31`. لأن `storegate.org` يرث إعداد الحساب وليس له إعداد مستقل، سيأخذ الدومين الإصدار الجديد.
3. بعد اختيار 8.3، راجع قائمة الامتدادات لهذه النسخة وفعّل: `mysqli`, `pdo_mysql`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `zip`, `xml`, `xmlreader`, `xmlwriter`, `dom`, `simplexml` و`zlib`.
4. احفظ التغييرات، ثم افتح الموقع فوراً واختبر تسجيل الدخول واستيراد Excel وتصدير Excel.
5. راجع `Errors` في cPanel و`application/logs/`. إذا ظهرت مشكلة لا يمكن حلها فوراً، أعد اختيار PHP 5.6 وأعد الملفات من النسخة الاحتياطية.

### بعد نجاح الإطلاق

بعد عدة أيام من نجاح التشغيل، احذف نسخ PHPExcel القديمة المذكورة أعلاه، بشرط أن تكون نتيجة البحث عن `PHPExcel` خالية من الاستدعاءات. احتفظ بالنسخة الاحتياطية وملفات Composer المقفلة لتكون أي إعادة نشر قابلة للتكرار.

## مراجع رسمية

* [صفحة الإصدارات والتنزيلات في PHP](https://www.php.net/releases/)
* [دورات دعم إصدارات PHP](https://www.php.net/supported-versions.php)
* [دليل الانتقال إلى PHP 8.5](https://www.php.net/manual/en/migration85.php)
* [PhpSpreadsheet: بديل PHPExcel ومتطلبات PHP](https://github.com/PHPOffice/PhpSpreadsheet)
* [متطلبات حزمة PhpSpreadsheet في Packagist](https://packagist.org/packages/phpoffice/phpspreadsheet)

# Uncommitted Changes Guide

هذا الملف يقسم التعديلات غير المعمولة لها commit حسب الفيتشر، ويضع أوامر staging تساعدك ترفع المطلوب فقط بدون إدخال تعديلات صفحة مبيعات الموظفين.

## لا تستخدم هذا الأمر

```bash
git add .
```

لأن `public/css/admin-ui-pattern.css` يحتوي تعديلات مختلطة بين فيتشر المنتجات منخفضة الكمية وفيتشر مبيعات الموظفين، وملف Excel جديد داخل `public/employees_sales`.

## استبعاد فيتشر مبيعات الموظفين

لا تضف هذه الملفات إذا كنت لا تريد رفع فيتشر صفحة مبيعات الموظفين:

```bash
application/controllers/admin/Other.php
application/models/Model_employees_sales.php
application/views/employee_sales/view_admin_employee_sales_details.php
application/views/employee_sales/view_admin_employees_sales_uploader.php
application/views/employee_sales/view_admin_search_employees_sales.php
public/employees_sales/20260807_164400_69b56680_TGT_JUL_2026.xlsx
```

وفي ملف `public/css/admin-ui-pattern.css` لا تضف أي hunks تحتوي على:

```text
employee-sales-
employee-sales-history
employee-sales-date-chip
employee-sales-search-button:disabled
employee-sales-history-panel
employee-sales-history-note
```

## رفع تعديل المنتجات منخفضة الكمية فقط

هذا يشمل:

- إضافة رقم الصنف بجانب عمود المنتج.
- إضافة العدد المتبقي والحالة.
- إضافة زر تنزيل Excel.
- إضافة endpoint تصدير Excel.
- تنسيق أعمدة صفحة المنتجات منخفضة الكمية فقط.

اكتب الأوامر التالية:

```bash
git add application/controllers/admin/Products.php
git add application/views/products/low.php
git add -p public/css/admin-ui-pattern.css
```

عند أمر `git add -p public/css/admin-ui-pattern.css`:

- اختر `y` فقط للـ hunk الذي يحتوي على `admin-ui-low`.
- اختر `n` لأي hunk يحتوي على `employee-sales`.

بعدها راجع الملفات التي ستدخل في commit:

```bash
git diff --cached --name-only
git diff --cached --stat
```

المفروض تظهر تقريباً:

```text
application/controllers/admin/Products.php
application/views/products/low.php
public/css/admin-ui-pattern.css
```

ثم اعمل commit:

```bash
git commit -m "Add item code to low stock products table"
```

## رفع تعديل رقم الصنف فقط بدون زر Excel

لو تريد رفع التعديل الأخير فقط، وهو رقم الصنف بجانب المنتج، استخدم staging جزئي:

```bash
git add -p application/views/products/low.php
git add -p public/css/admin-ui-pattern.css
```

في `application/views/products/low.php`:

- اختر `y` للـ hunks التي تضيف `item-code` و `رقم الصنف`.
- اختر `n` للـ hunks التي تضيف `low-export-btn` إذا كنت لا تريد زر Excel.

في `public/css/admin-ui-pattern.css`:

- اختر `y` فقط للسطر/الجزء الخاص بـ `.product-cell.item-code`.
- اختر `n` لتعديلات `employee-sales`.
- اختر `n` لتعديلات `low-export-btn`, `quantity`, `status` لو لا تريدها.

إذا كان Git عرض hunk كبير وفيه أكثر من تعديل، اضغط:

```text
s
```

لتقسيمه إلى أجزاء أصغر، ثم اختار `y` أو `n` لكل جزء.

راجع ما تم staged:

```bash
git diff --cached
```

ثم اعمل commit:

```bash
git commit -m "Show item code in low stock products table"
```

## رفع تعديلات بحث المنتجات

هذه التعديلات في:

```text
application/views/products/search.php
```

الأوامر:

```bash
git add application/views/products/search.php
git diff --cached --name-only
git commit -m "Improve product search actions text"
```

## رفع تصحيح تسمية كويك بلس

هذه التعديلات في:

```text
application/models/Excel_import_model.php
application/views/view_admin_employee.php
application/views/view_admin_lock_admin.php
```

الأوامر:

```bash
git add application/models/Excel_import_model.php
git add application/views/view_admin_employee.php
git add application/views/view_admin_lock_admin.php
git diff --cached --name-only
git commit -m "Fix Quick Plus Arabic label"
```

## رفع تعديل رسالة الرف

هذا التعديل في:

```text
public/js/shelf.js
```

الأوامر:

```bash
git add public/js/shelf.js
git diff --cached --name-only
git commit -m "Update shelf save confirmation message"
```

## تنظيف أي staging بالخطأ

لو ضفت ملفات بالغلط في staging، استخدم:

```bash
git restore --staged .
```

ثم أعد إضافة الملفات المطلوبة فقط بالأوامر الموجودة بالأعلى.

## مراجعة نهائية قبل commit

قبل أي commit شغل:

```bash
git status --short
git diff --cached --name-only
git diff --cached --stat
```

لو ظهر أي ملف من ملفات مبيعات الموظفين في `git diff --cached --name-only`، أخرجه من staging:

```bash
git restore --staged application/controllers/admin/Other.php
git restore --staged application/models/Model_employees_sales.php
git restore --staged application/views/employee_sales/view_admin_employee_sales_details.php
git restore --staged application/views/employee_sales/view_admin_employees_sales_uploader.php
git restore --staged application/views/employee_sales/view_admin_search_employees_sales.php
git restore --staged public/employees_sales/20260807_164400_69b56680_TGT_JUL_2026.xlsx
```

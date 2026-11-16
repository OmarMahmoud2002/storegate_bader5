<?php
$monthNames = [1 => 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
?>
<label for="sales_month" class="form-label">البيانات السابقة بالشهر:</label>
<select id="sales_month" name="sales_month" class="form-control" required aria-describedby="sales_month_hint">
    <option value="">اختر الشهر</option>
    <?php foreach (isset($sales_months) ? $sales_months : [] as $salesMonth): ?>
        <?php $monthValue = $salesMonth->sales_month; ?>
        <option value="<?= htmlspecialchars($monthValue) ?>" <?= isset($selected_sales_month) && $selected_sales_month === $monthValue ? 'selected' : '' ?>>
            <?= htmlspecialchars($monthNames[(int) substr($monthValue, 5, 2)] . ' ' . substr($monthValue, 0, 4)) ?>
        </option>
    <?php endforeach; ?>
</select>
<!-- <small id="sales_month_hint" class="d-block mt-1">البحث في آخر ملف مرفوع للشهر المختار.</small> -->

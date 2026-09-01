<?php
$page_title = 'التقفيلة';
$this->view('view_header');
$this->view('view_user_sidebar');
?>

<div class="app-content admin-ui-page admin-ui-user-lock">
    <div class="app-content-header user-page-heading">
        <div>
            <h1 class="app-content-headerText">التقفيلة اليومية</h1>
            <p>أدخل مبالغ اليوم وراجع الإجمالي قبل التأكيد.</p>
        </div>
    </div>

    <section class="user-lock-card">
        <div class="user-lock-card__intro">
            <i class="bi bi-calculator"></i>
            <div>
                <h2>تفاصيل التحصيل</h2>
                <p>يُسمح بإرسال تقفيلة واحدة فقط لليوم.</p>
            </div>
        </div>

        <?php echo form_open(base_url().MOD_VALUE.'user/sendlock', array('class' => 'user-lock-form')); ?>
            <div class="user-lock-fields">
                <div class="user-lock-field">
                    <div class="user-lock-field__heading">
                        <label for="user_lock_cash"><i class="bi bi-cash"></i> المبلغ كاش (نقدي)</label>
                    </div>
                    <input placeholder="0.00" type="text" inputmode="decimal" class="number" name="user_lock_cash" id="user_lock_cash" min="0" autocomplete="off">
                </div>
                <div class="user-lock-field">
                    <div class="user-lock-field__heading">
                        <label for="user_lock_span"><i class="bi bi-credit-card"></i> المبلغ بطاقة (سبان)</label>
                        <button type="button" class="card-amount-trigger" data-bs-toggle="modal" data-bs-target="#userCardAmountModal" aria-label="إضافة مبالغ البطاقة بالتفصيل" title="إضافة مبالغ البطاقة بالتفصيل">
                            <i class="bi bi-credit-card-2-front"></i>
                        </button>
                    </div>
                    <input placeholder="0.00" type="text" inputmode="decimal" class="number" name="user_lock_span" id="user_lock_span" min="0" autocomplete="off">
                </div>
            </div>

            <div class="user-lock-total">
                <span>الإجمالي</span>
                <output id="total" dir="ltr">0.00</output>
            </div>

            <button type="submit" class="user-lock-submit" name="form_lock">
                <i class="bi bi-check2-circle"></i>
                تأكيد التقفيلة
            </button>
        <?php echo form_close(); ?>
    </section>
</div>
</div>

<div class="modal fade card-amount-modal" id="userCardAmountModal" tabindex="-1" aria-labelledby="userCardAmountModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div class="card-amount-modal__title">
                    <span><i class="bi bi-credit-card-2-front"></i></span>
                    <div>
                        <h5 class="modal-title" id="userCardAmountModalLabel">تفاصيل مبالغ البطاقة</h5>
                        <p>أضف كل عملية منفصلة أو حدّد عدد مرات تكرارها.</p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body">
                <div class="card-amount-entry">
                    <label for="userCardAmountInput">
                        <span>المبلغ</span>
                        <input type="number" id="userCardAmountInput" class="form-control" placeholder="0.00" min="0" step="0.01" inputmode="decimal">
                    </label>
                    <label for="userCardRepetitionInput" class="card-amount-entry__repeat">
                        <span>التكرار</span>
                        <input type="number" id="userCardRepetitionInput" class="form-control" placeholder="1" min="1" step="1" inputmode="numeric">
                    </label>
                    <button type="button" class="card-amount-add" id="userCardAmountAdd">
                        <i class="bi bi-plus-lg"></i>
                        إضافة
                    </button>
                </div>

                <div class="card-amount-list">
                    <div class="table-responsive">
                        <table class="table text-center">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المبلغ</th>
                                    <th>حذف</th>
                                </tr>
                            </thead>
                            <tbody id="userCardAmountList"></tbody>
                        </table>
                    </div>
                </div>

                <div class="card-amount-summary">
                    <div>
                        <span>عدد المبالغ</span>
                        <strong id="userCardAmountCount">0</strong>
                    </div>
                    <div>
                        <span>إجمالي المبالغ</span>
                        <strong id="userCardAmountTotal" dir="ltr">0.00</strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer card-amount-actions">
                <button type="button" class="card-amount-cancel" data-bs-dismiss="modal">إغلاق</button>
                <button type="button" class="card-amount-approve" id="userCardAmountApprove">
                    <i class="bi bi-check2-circle"></i>
                    اعتماد المبلغ
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/script.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/home.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script src="<?php echo base_url(); ?>public/js/user.js?a=<?php $date=date_create();echo date_timestamp_get($date);?>"></script>
<script>
(function () {
    var cashInput = document.getElementById('user_lock_cash');
    var cardInput = document.getElementById('user_lock_span');
    var totalOutput = document.getElementById('total');
    var cardAmountModalElement = document.getElementById('userCardAmountModal');
    var cardAmountInput = document.getElementById('userCardAmountInput');
    var repetitionInput = document.getElementById('userCardRepetitionInput');
    var amountList = document.getElementById('userCardAmountList');
    var amountCount = document.getElementById('userCardAmountCount');
    var amountTotal = document.getElementById('userCardAmountTotal');
    var cardAmounts = [];

    function normalizeAmountInput(input) {
        input.value = input.value.replace(/[^0-9.]/g, '').replace(/(\..*)\./g, '$1');
    }

    function updateTotal() {
        var cash = parseFloat(cashInput.value) || 0;
        var card = parseFloat(cardInput.value) || 0;
        totalOutput.textContent = (cash + card).toFixed(2);
    }

    function renderCardAmounts() {
        var total = 0;
        amountList.innerHTML = '';

        if (cardAmounts.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyRow.className = 'card-amount-empty';
            emptyCell.colSpan = 3;
            emptyCell.textContent = 'لم تتم إضافة مبالغ بعد';
            emptyRow.appendChild(emptyCell);
            amountList.appendChild(emptyRow);
        }

        cardAmounts.slice().reverse().forEach(function (amount, reverseIndex) {
            var originalIndex = cardAmounts.length - 1 - reverseIndex;
            var row = document.createElement('tr');
            var indexCell = document.createElement('td');
            var amountCell = document.createElement('td');
            var actionCell = document.createElement('td');
            var removeButton = document.createElement('button');

            total += amount;
            indexCell.textContent = String(originalIndex + 1);
            amountCell.textContent = amount.toFixed(2);
            amountCell.setAttribute('dir', 'ltr');
            removeButton.type = 'button';
            removeButton.className = 'card-amount-remove';
            removeButton.setAttribute('aria-label', 'حذف المبلغ');
            removeButton.innerHTML = '<i class="bi bi-trash3"></i>';
            removeButton.addEventListener('click', function () {
                cardAmounts.splice(originalIndex, 1);
                renderCardAmounts();
            });

            actionCell.appendChild(removeButton);
            row.appendChild(indexCell);
            row.appendChild(amountCell);
            row.appendChild(actionCell);
            amountList.appendChild(row);
        });

        amountCount.textContent = String(cardAmounts.length);
        amountTotal.textContent = total.toFixed(2);
    }

    function addCardAmount() {
        var amount = parseFloat(cardAmountInput.value);
        var repetition = parseInt(repetitionInput.value || '1', 10);

        if (!Number.isFinite(amount) || amount <= 0) {
            cardAmountInput.focus();
            return;
        }

        if (!Number.isInteger(repetition) || repetition < 1) {
            repetition = 1;
        }

        for (var index = 0; index < repetition; index += 1) {
            cardAmounts.push(amount);
        }

        cardAmountInput.value = '';
        repetitionInput.value = '';
        renderCardAmounts();
        cardAmountInput.focus();
    }

    [cashInput, cardInput].forEach(function (input) {
        input.addEventListener('input', function () {
            normalizeAmountInput(input);
            updateTotal();
        });
    });

    document.getElementById('userCardAmountAdd').addEventListener('click', addCardAmount);
    [cardAmountInput, repetitionInput].forEach(function (input) {
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                addCardAmount();
            }
        });
    });

    cardAmountModalElement.addEventListener('show.bs.modal', function () {
        var currentCardAmount = parseFloat(cardInput.value) || 0;
        if (cardAmounts.length === 0 && currentCardAmount > 0) {
            cardAmounts.push(currentCardAmount);
            renderCardAmounts();
        }
    });

    cardAmountModalElement.addEventListener('shown.bs.modal', function () {
        cardAmountInput.focus();
    });

    document.getElementById('userCardAmountApprove').addEventListener('click', function () {
        cardInput.value = amountTotal.textContent;
        updateTotal();
        bootstrap.Modal.getOrCreateInstance(cardAmountModalElement).hide();
    });

    renderCardAmounts();
}());
</script>
<?php $this->view('view_footer'); ?>

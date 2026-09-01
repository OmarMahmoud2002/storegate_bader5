const moneyDenominations = [500, 200, 100, 50, 20, 10, 5, 1, 2, 0.5, 0.25];
const cashTable = document.getElementById("cashTable");

moneyDenominations.forEach((denomination) => {
  const row = document.createElement("tr");

  const numberCell = document.createElement("td");
  const denominationCell = document.createElement("td");
  const totalCell = document.createElement("td");

  numberCell.style.width = "35%";
  denominationCell.style.width = "31%";
  totalCell.style.width = "35%";
  numberCell.style.verticalAlign = "middle";
  denominationCell.style.verticalAlign = "middle";
  totalCell.style.verticalAlign = "middle";
  
[numberCell, denominationCell, totalCell].forEach((cell) => {
  cell.style.verticalAlign = "middle";
  cell.style.padding = "1px";
  cell.style.lineHeight = "2";
});


  const inputField = document.createElement("input");
  inputField.type = "number";
  inputField.classList.add("form-control", "w-100",  "text-center");
  inputField.value = 0;

  inputField.addEventListener("focus", function () {
    this.select();
  });

  inputField.addEventListener("wheel", function (e) {
    this.blur();
    e.preventDefault();
  });

  inputField.addEventListener("keydown", function (e) {
    if (e.key === "ArrowUp" || e.key === "ArrowDown") {
      e.preventDefault();
    }
  });

  inputField.addEventListener("input", function () {
    if (this.value === "") {
      this.value = 0;
    }
    updateRowTotal(row, denomination);
    updateTotalAmount();
  });

  numberCell.appendChild(inputField);
  denominationCell.innerText = `${denomination}`;
  totalCell.innerText = "0.00";

  row.appendChild(numberCell);
  row.appendChild(denominationCell);
  row.appendChild(totalCell);

  cashTable.appendChild(row);
});

function updateRowTotal(row, denomination) {
  const numberCell = row.children[0].children[0];
  const totalCell = row.children[2];
  const quantity = parseFloat(numberCell.value) || 0;
  const rowTotal = denomination * quantity;

  totalCell.innerText = rowTotal.toFixed(2);
}

function updateTotalAmount() {
  let total = 0;
  const totals = cashTable.querySelectorAll("tr td:nth-child(3)");
  totals.forEach((cell) => {
    total += parseFloat(cell.innerText) || 0;
  });
  document.getElementById("totalAmount").innerText = total.toFixed(2);
}

document
  .getElementById("openCalculatorPopup")
  .addEventListener("click", function () {
    const cashCalculatorModal = new bootstrap.Modal(
      document.getElementById("cashCalculatorModal")
    );
    cashCalculatorModal.show();

    document
      .getElementById("cashCalculatorModal")
      .addEventListener("shown.bs.modal", function () {
        document.getElementById("user_lock_cash").focus();
      });
  });

function submitCash() {
  const total = document.getElementById("totalAmount").innerText;
  document.getElementById("user_lock_cash").value = total;

  updateOverallTotal();

  const cashCalculatorModal = bootstrap.Modal.getInstance(
    document.getElementById("cashCalculatorModal")
  );
  cashCalculatorModal.hide();

  const exampleModal = bootstrap.Modal.getInstance(
    document.getElementById("exampleModal")
  );
  exampleModal.show();
  document.getElementById("exampleModal").focus();
}

function updateOverallTotal() {
  const cash = parseFloat(document.getElementById("user_lock_cash").value) || 0;
  const card = parseFloat(document.getElementById("user_lock_span").value) || 0;
  document.getElementById("totalAmountField").value = (cash + card).toFixed(2);
}

document
  .getElementById("user_lock_cash")
  .addEventListener("input", updateOverallTotal);
document
  .getElementById("user_lock_span")
  .addEventListener("input", updateOverallTotal);

const amountInput = document.getElementById("amountInput");
const repetitionInput = document.getElementById("repetitionInput");
const amountList = document.getElementById("amountList");
const amountCount = document.getElementById("amountCount");
const cardTotalAmount = document.getElementById("totalAmount1");
const amountModalElement = document.getElementById("amountModal");
const amounts = [];

function addAmount() {
  const value = parseFloat(amountInput.value);
  let repetition = parseInt(repetitionInput.value || "1", 10);

  if (!Number.isFinite(value) || value <= 0) {
    amountInput.focus();
    return;
  }

  if (!Number.isInteger(repetition) || repetition < 1) repetition = 1;

  for (let i = 0; i < repetition; i++) {
    amounts.push(value);
  }

  renderAmounts();
  amountInput.value = "";
  repetitionInput.value = "";
  amountInput.focus();
}

function removeAmount(index) {
  amounts.splice(index, 1);
  renderAmounts();
}

function renderAmounts() {
  amountList.innerHTML = "";
  let totalAmountValue = 0;

  if (amounts.length === 0) {
    const emptyRow = document.createElement("tr");
    const emptyCell = document.createElement("td");
    emptyRow.className = "card-amount-empty";
    emptyCell.colSpan = 3;
    emptyCell.textContent = "لم تتم إضافة مبالغ بعد";
    emptyRow.appendChild(emptyCell);
    amountList.appendChild(emptyRow);
  }

  [...amounts].reverse().forEach((amount, index) => {
    const originalIndex = amounts.length - 1 - index;
    totalAmountValue += amount;

    const row = document.createElement("tr");
    const indexCell = document.createElement("td");
    const amountCell = document.createElement("td");
    const actionCell = document.createElement("td");
    const removeButton = document.createElement("button");

    indexCell.textContent = String(originalIndex + 1);
    amountCell.textContent = amount.toFixed(2);
    amountCell.setAttribute("dir", "ltr");
    removeButton.type = "button";
    removeButton.className = "card-amount-remove";
    removeButton.setAttribute("aria-label", "حذف المبلغ");
    removeButton.innerHTML = '<i class="bi bi-trash3"></i>';
    removeButton.addEventListener("click", () => {
      removeAmount(originalIndex);
    });

    actionCell.appendChild(removeButton);
    row.appendChild(indexCell);
    row.appendChild(amountCell);
    row.appendChild(actionCell);
    amountList.appendChild(row);
  });

  amountCount.textContent = amounts.length;
  cardTotalAmount.textContent = totalAmountValue.toFixed(2);
}

// نفتح المودال بالكود زي حاسبة الكاش بالظبط.
// لو استخدمنا data-bs-toggle، الـ data-api بتاع Bootstrap بيقفل أي مودال مفتوح
// (SELECTOR .modal.show) قبل ما يفتح الجديد، فبيختفي مودال اضافة التقفيلة.
const openCardAmountPopup = document.getElementById("openCardAmountPopup");
if (openCardAmountPopup) {
  openCardAmountPopup.addEventListener("click", function () {
    bootstrap.Modal.getOrCreateInstance(amountModalElement).show();
  });
}

amountModalElement.addEventListener("show.bs.modal", function () {
  const currentAmount = parseFloat(document.getElementById("user_lock_span").value) || 0;
  if (amounts.length === 0 && currentAmount > 0) {
    amounts.push(currentAmount);
    renderAmounts();
  }
});

amountModalElement.addEventListener("shown.bs.modal", function () {
  // يفضل فوق مودال اضافة التقفيلة زي حاسبة الكاش:
  // نسيب الـ backdrop الجديد تحت المودال الأب علشان يفضل ظاهر
  const backdrops = document.querySelectorAll(".modal-backdrop");
  const currentBackdrop = backdrops[backdrops.length - 1];
  if (currentBackdrop) currentBackdrop.classList.remove("card-amount-modal-backdrop");
  amountInput.focus();
});

amountModalElement.addEventListener("hidden.bs.modal", function () {
  const parentModal = document.getElementById("exampleModal");
  if (parentModal && parentModal.classList.contains("show")) {
    document.body.classList.add("modal-open");
  }
});

[amountInput, repetitionInput].forEach((input) => {
  input.addEventListener("keydown", function (e) {
    if (e.key === "Enter") {
      e.preventDefault();
      addAmount();
    }
  });
});

document.getElementById("Approval").addEventListener("click", function () {
  document.getElementById("user_lock_span").value = cardTotalAmount.textContent;
  updateOverallTotal();
  bootstrap.Modal.getOrCreateInstance(amountModalElement).hide();
});

renderAmounts();

// Review modal cash calculator
const reviewCashModalElement = document.getElementById("reviewCashCalculatorModal");
const reviewCashTable = document.getElementById("reviewCashTable");
const reviewCashTotal = document.getElementById("reviewCashTotal");
let activeReviewCashInput = null;
let activeReviewCashParent = null;

function updateReviewCashTotal() {
  let total = 0;
  reviewCashTable.querySelectorAll("tr").forEach((row) => {
    const quantity = parseFloat(row.querySelector("input").value) || 0;
    const denomination = parseFloat(row.dataset.denomination) || 0;
    const rowTotal = quantity * denomination;
    row.querySelector(".review-cash-row-total").textContent = rowTotal.toFixed(2);
    total += rowTotal;
  });
  reviewCashTotal.textContent = total.toFixed(2);
}

function resetReviewCashCalculator() {
  reviewCashTable.querySelectorAll("input").forEach((input) => {
    input.value = "0";
  });
  updateReviewCashTotal();
}

moneyDenominations.forEach((denomination) => {
  const row = document.createElement("tr");
  const quantityCell = document.createElement("td");
  const denominationCell = document.createElement("td");
  const totalCell = document.createElement("td");
  const quantityInput = document.createElement("input");

  row.dataset.denomination = String(denomination);
  quantityInput.type = "number";
  quantityInput.className = "form-control text-center";
  quantityInput.value = "0";
  quantityInput.min = "0";
  quantityInput.step = "1";
  quantityInput.inputMode = "numeric";
  quantityInput.addEventListener("focus", function () {
    this.select();
  });
  quantityInput.addEventListener("input", updateReviewCashTotal);
  quantityInput.addEventListener("wheel", function (event) {
    event.preventDefault();
    this.blur();
  });

  denominationCell.textContent = String(denomination);
  totalCell.textContent = "0.00";
  totalCell.className = "review-cash-row-total";
  quantityCell.appendChild(quantityInput);
  row.appendChild(quantityCell);
  row.appendChild(denominationCell);
  row.appendChild(totalCell);
  reviewCashTable.appendChild(row);
});

document.querySelectorAll(".lock-review-cash-trigger").forEach((button) => {
  button.addEventListener("click", function () {
    activeReviewCashParent = this.closest(".lock-review-modal");
    activeReviewCashInput = activeReviewCashParent.querySelector(".lock-review-cash-input");
    resetReviewCashCalculator();
    bootstrap.Modal.getOrCreateInstance(reviewCashModalElement).show();
  });
});

reviewCashModalElement.addEventListener("shown.bs.modal", function () {
  const backdrops = document.querySelectorAll(".modal-backdrop");
  const currentBackdrop = backdrops[backdrops.length - 1];
  if (currentBackdrop) currentBackdrop.classList.add("card-amount-modal-backdrop");
  const firstInput = reviewCashTable.querySelector("input");
  if (firstInput) firstInput.focus();
});

reviewCashModalElement.addEventListener("hidden.bs.modal", function () {
  if (activeReviewCashParent && activeReviewCashParent.classList.contains("show")) {
    document.body.classList.add("modal-open");
  }
});

document.getElementById("reviewCashApprove").addEventListener("click", function () {
  if (activeReviewCashInput) {
    activeReviewCashInput.value = reviewCashTotal.textContent;
    activeReviewCashInput.dispatchEvent(new Event("input", { bubbles: true }));
  }
  bootstrap.Modal.getOrCreateInstance(reviewCashModalElement).hide();
});

// Review modal card amount breakdown
const reviewAmountModalElement = document.getElementById("reviewAmountModal");
const reviewAmountInput = document.getElementById("reviewAmountInput");
const reviewRepetitionInput = document.getElementById("reviewRepetitionInput");
const reviewAmountList = document.getElementById("reviewAmountList");
const reviewAmountCount = document.getElementById("reviewAmountCount");
const reviewAmountTotal = document.getElementById("reviewAmountTotal");
const reviewAmounts = [];
let activeReviewCardInput = null;
let activeReviewCardParent = null;

function renderReviewAmounts() {
  reviewAmountList.innerHTML = "";
  const total = reviewAmounts.reduce((sum, amount) => sum + amount, 0);

  if (reviewAmounts.length === 0) {
    const emptyRow = document.createElement("tr");
    const emptyCell = document.createElement("td");
    emptyRow.className = "card-amount-empty";
    emptyCell.colSpan = 3;
    emptyCell.textContent = "لم تتم إضافة مبالغ بعد";
    emptyRow.appendChild(emptyCell);
    reviewAmountList.appendChild(emptyRow);
  }

  [...reviewAmounts].reverse().forEach((amount, index) => {
    const originalIndex = reviewAmounts.length - 1 - index;
    const row = document.createElement("tr");
    const indexCell = document.createElement("td");
    const amountCell = document.createElement("td");
    const actionCell = document.createElement("td");
    const removeButton = document.createElement("button");

    indexCell.textContent = String(originalIndex + 1);
    amountCell.textContent = amount.toFixed(2);
    amountCell.setAttribute("dir", "ltr");
    removeButton.type = "button";
    removeButton.className = "card-amount-remove";
    removeButton.setAttribute("aria-label", "حذف المبلغ");
    removeButton.innerHTML = '<i class="bi bi-trash3"></i>';
    removeButton.addEventListener("click", function () {
      reviewAmounts.splice(originalIndex, 1);
      renderReviewAmounts();
    });

    actionCell.appendChild(removeButton);
    row.appendChild(indexCell);
    row.appendChild(amountCell);
    row.appendChild(actionCell);
    reviewAmountList.appendChild(row);
  });

  reviewAmountCount.textContent = String(reviewAmounts.length);
  reviewAmountTotal.textContent = total.toFixed(2);
}

function addReviewAmount() {
  const value = parseFloat(reviewAmountInput.value);
  let repetition = parseInt(reviewRepetitionInput.value || "1", 10);

  if (!Number.isFinite(value) || value <= 0) {
    reviewAmountInput.focus();
    return;
  }
  if (!Number.isInteger(repetition) || repetition < 1) repetition = 1;

  for (let index = 0; index < repetition; index++) {
    reviewAmounts.push(value);
  }
  reviewAmountInput.value = "";
  reviewRepetitionInput.value = "";
  renderReviewAmounts();
  reviewAmountInput.focus();
}

document.querySelectorAll(".lock-review-card-trigger").forEach((button) => {
  button.addEventListener("click", function () {
    activeReviewCardParent = this.closest(".lock-review-modal");
    activeReviewCardInput = activeReviewCardParent.querySelector(".lock-review-card-input");
    reviewAmounts.splice(0, reviewAmounts.length);
    const currentAmount = parseFloat(activeReviewCardInput.value) || 0;
    if (currentAmount > 0) reviewAmounts.push(currentAmount);
    reviewAmountInput.value = "";
    reviewRepetitionInput.value = "";
    renderReviewAmounts();
    bootstrap.Modal.getOrCreateInstance(reviewAmountModalElement).show();
  });
});

document.getElementById("reviewAmountAdd").addEventListener("click", addReviewAmount);
[reviewAmountInput, reviewRepetitionInput].forEach((input) => {
  input.addEventListener("keydown", function (event) {
    if (event.key === "Enter") {
      event.preventDefault();
      addReviewAmount();
    }
  });
});

reviewAmountModalElement.addEventListener("shown.bs.modal", function () {
  const backdrops = document.querySelectorAll(".modal-backdrop");
  const currentBackdrop = backdrops[backdrops.length - 1];
  if (currentBackdrop) currentBackdrop.classList.add("card-amount-modal-backdrop");
  reviewAmountInput.focus();
});

reviewAmountModalElement.addEventListener("hidden.bs.modal", function () {
  if (activeReviewCardParent && activeReviewCardParent.classList.contains("show")) {
    document.body.classList.add("modal-open");
  }
});

document.getElementById("reviewAmountApprove").addEventListener("click", function () {
  if (activeReviewCardInput) {
    activeReviewCardInput.value = reviewAmountTotal.textContent;
    activeReviewCardInput.dispatchEvent(new Event("input", { bubbles: true }));
  }
  bootstrap.Modal.getOrCreateInstance(reviewAmountModalElement).hide();
});

renderReviewAmounts();
document.addEventListener("keydown", function (e) {
  const isArrowUp = e.key === "ArrowUp";
  const isArrowDown = e.key === "ArrowDown" || e.key === "Enter";

  if (isArrowUp || isArrowDown) {
    const active = document.activeElement;
    if (active.tagName === "INPUT") {
      const inputs = Array.from(document.querySelectorAll("#cashTable input"));
      const currentIndex = inputs.indexOf(active);
      if (currentIndex === -1) return;
      const nextIndex = isArrowDown ? currentIndex + 1 : currentIndex - 1;

      if (inputs[nextIndex]) {
        inputs[nextIndex].focus();
        e.preventDefault();
      }
    }
  }
});

$(document).ready(function () {
  $("#user_id").select2({
    placeholder: "اختر",
    width: "resolve",
    dir: "rtl",
    language: "ar",
    minimumInputLength: 0,
    minimumResultsForSearch: 0,
    dropdownParent: $("#exampleModal"),
  });
});

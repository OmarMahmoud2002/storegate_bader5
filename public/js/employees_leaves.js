document.addEventListener('DOMContentLoaded', function () {
  const yearFilter = document.getElementById('leave-year');
  const monthFilter = document.getElementById('leave-month');
  function filterMonth() {
    const latest = yearFilter.dataset.latestMonth;
    const max = yearFilter.value === latest.slice(0, 4) ? Number(latest.slice(5, 7)) : 12;
    document.getElementById('leave-month-value').value = yearFilter.value + '-' + String(Math.min(Number(monthFilter.value), max)).padStart(2, '0');
    yearFilter.form.requestSubmit();
  }
  yearFilter.addEventListener('change', filterMonth);
  monthFilter.addEventListener('change', filterMonth);

  const limitModal = document.getElementById('leave-limit-modal');
  document.body.appendChild(limitModal);
  const limits = JSON.parse(document.getElementById('leave-month-limits').textContent);
  const limitYear = document.getElementById('limit-year');
  const limitInputs = Array.from(limitModal.querySelectorAll('[data-limit-month]'));
  let displayedLimitYear = limitYear.value;
  const drafts = {};
  limitYear.addEventListener('change', function () {
    drafts[displayedLimitYear] = limitInputs.map(input => input.value);
    displayedLimitYear = limitYear.value;
    limitInputs.forEach((input, index) => {
      const key = displayedLimitYear + '-' + input.dataset.limitMonth.padStart(2, '0');
      input.value = drafts[displayedLimitYear]?.[index] ?? limits[key] ?? document.getElementById('leave-default-limit').dataset.count;
    });
  });
  if (document.getElementById('leave-limit-error')) bootstrap.Modal.getOrCreateInstance(limitModal).show();

  document.querySelectorAll('[data-leave-details]').forEach(cell => {
    // Bootstrap treats the native title as a separate popover heading.
    cell.removeAttribute('title');
    const popover = bootstrap.Popover.getOrCreateInstance(cell, {
      content: cell.dataset.leaveDetails, trigger: 'hover focus', placement: 'auto',
      container: 'body', customClass: 'leave-details-popover', html: false
    });
    cell.addEventListener('click', () => { cell.focus(); popover.show(); });
    cell.addEventListener('keydown', event => {
      if (event.key === 'Escape') popover.hide();
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); popover.show(); }
    });
  });
  const modal = document.getElementById('leave-modal');
  if (modal) {
    // Keep the dialog outside the scrolling application shell and its stacking context.
    document.body.appendChild(modal);
    modal.addEventListener('shown.bs.modal', function () {
      const nameInput = document.getElementById('leave-name');
      const target = document.getElementById('leave-capacity-warning') || document.getElementById('leave-error') || (nameInput.disabled ? modal.querySelector('[name="leave_type"]') : nameInput);
      if (target) target.focus();
    });
    if (document.getElementById('leave-error') || document.getElementById('leave-capacity-warning')) {
      bootstrap.Modal.getOrCreateInstance(modal).show();
    }
  }
  const form = document.getElementById('leave-form');
  if (form) {
    const nameField = document.getElementById('leave-name-field');
    const nameInput = document.getElementById('leave-name');
    const typeInputs = Array.from(form.querySelectorAll('[name="leave_type"]'));
    let selectedType = typeInputs.find(input => input.checked)?.value || '';
    const namesByType = {};
    if (selectedType) namesByType[selectedType] = nameInput.value;
    function showLeaveName(focus) {
      nameField.hidden = !selectedType;
      nameInput.disabled = !selectedType;
      if (!selectedType) return;
      nameInput.value = namesByType[selectedType] ?? (selectedType === 'annual' ? 'إجازة سنوية' : '');
      if (focus) nameInput.focus();
    }
    showLeaveName(false);
    typeInputs.forEach(input => input.addEventListener('change', function () {
      if (selectedType) namesByType[selectedType] = nameInput.value;
      selectedType = input.value;
      showLeaveName(true);
    }));
    // A changed request requires a fresh server capacity check before it can be confirmed.
    form.addEventListener('input', function () {
      const token = document.getElementById('leave-confirmation-token');
      if (token) {
        token.value = '';
        document.getElementById('leave-save').textContent = 'حفظ الإجازة';
      }
    });
    const all = document.getElementById('leave-all');
    const employees = document.getElementById('leave-employees');
    const start = document.getElementById('leave-start');
    const end = document.getElementById('leave-end');
    const checkboxes = Array.from(employees.querySelectorAll('input[type=checkbox]'));
    function updateSelection() {
      document.getElementById('leave-selected').textContent = 'المحدد: ' + checkboxes.filter(input => input.checked).length + ' موظف';
      if (checkboxes.length) checkboxes[0].setCustomValidity('');
    }
    all.addEventListener('change', function () {
      employees.hidden = all.checked;
      employees.disabled = all.checked;
      updateSelection();
    });
    checkboxes.forEach(input => input.addEventListener('change', updateSelection));
    updateSelection();
    document.getElementById('leave-search').addEventListener('input', function () {
      let visible = 0;
      const term = this.value.trim().toLocaleLowerCase('ar');
      employees.querySelectorAll('.leaves-employee').forEach(label => {
        label.hidden = !label.textContent.toLocaleLowerCase('ar').includes(term);
        if (!label.hidden) visible++;
      });
      document.getElementById('leave-no-results').hidden = visible > 0;
    });
    function updateDates() {
      end.min = start.value;
      end.setCustomValidity(end.value && start.value && end.value < start.value ? 'تاريخ النهاية يجب أن يكون بعد البداية أو في نفس اليوم.' : '');
      const days = Math.round((Date.parse(end.value) - Date.parse(start.value)) / 86400000) + 1;
      document.getElementById('leave-duration').textContent = Number.isFinite(days) && days > 0 ? 'مدة الإجازة: ' + days + ' يوم، شاملة البداية والنهاية.' : 'تشمل الإجازة يوم البداية ويوم النهاية.';
    }
    start.addEventListener('change', updateDates);
    end.addEventListener('change', updateDates);
    updateDates();
    form.addEventListener('submit', function (event) {
      if (!all.checked && !checkboxes.some(input => input.checked)) {
        event.preventDefault();
        if (checkboxes.length) {
          document.getElementById('leave-search').value = '';
          employees.querySelectorAll('.leaves-employee').forEach(label => { label.hidden = false; });
          checkboxes[0].setCustomValidity('اختر موظفًا واحدًا على الأقل.');
          checkboxes[0].reportValidity();
        } else alert('لا يوجد موظفون للاختيار.');
        return;
      }
      const button = document.getElementById('leave-save');
      button.disabled = true;
      button.textContent = 'جارٍ حفظ الإجازة…';
    });
  }
  const deleteModal = document.getElementById('leave-delete-modal');
  if (deleteModal) {
    document.body.appendChild(deleteModal);
    const confirmButton = document.getElementById('leave-delete-confirm');
    let pendingForm = null;
    deleteModal.addEventListener('shown.bs.modal', () => confirmButton.focus());
    confirmButton.addEventListener('click', function () {
      if (!pendingForm) return;
      confirmButton.disabled = true;
      confirmButton.textContent = 'جارٍ الحذف…';
      pendingForm.submit();
    });
    document.querySelectorAll('.leave-delete-form').forEach(form => {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        pendingForm = form;
        const input = form.querySelector('[data-leave-name]');
        document.getElementById('leave-delete-name').textContent = input ? input.dataset.leaveName : '';
        bootstrap.Modal.getOrCreateInstance(deleteModal).show();
      });
    });
  }
});

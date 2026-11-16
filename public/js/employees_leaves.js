document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('leave-modal');
  if (modal) {
    // Keep the dialog outside the scrolling application shell and its stacking context.
    document.body.appendChild(modal);
    modal.addEventListener('shown.bs.modal', function () {
      const target = document.getElementById('leave-error') || document.getElementById('leave-name');
      if (target) target.focus();
    });
    if (document.getElementById('leave-error')) {
      bootstrap.Modal.getOrCreateInstance(modal).show();
    }
  }
  const form = document.getElementById('leave-form');
  if (form) {
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

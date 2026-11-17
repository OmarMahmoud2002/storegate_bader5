(function () {
  'use strict';
  const modal = document.getElementById('productUploadModal');
  const opener = document.getElementById('openProductUploadModal');
  if (!modal || !opener) return;

  const form = document.getElementById('productUploadForm');
  const fileInput = document.getElementById('productUploadFile');
  const submit = document.getElementById('productUploadSubmit');
  const confirm = document.getElementById('productUploadConfirm');
  const message = document.getElementById('productUploadMessage');
  const progress = document.getElementById('productUploadProgress');
  const progressBar = document.getElementById('productUploadProgressBar');
  const progressText = document.getElementById('productUploadProgressText');
  const progressFill = document.getElementById('productUploadProgressFill');
  const toast = document.getElementById('productUploadToast');
  const closeButtons = modal.querySelectorAll('[data-upload-close]');
  let busy = false;
  let succeeded = false;
  let requiresConfirmation = false;
  let closeTimer;
  let toastTimer;
  let previousOverflow = '';
  let previousFocus;

  function showMessage(text, status) {
    message.textContent = text;
    message.className = 'product-upload-message is-' + status;
    message.classList.toggle('hide', !text);
  }

  function setBusy(value) {
    busy = value;
    modal.setAttribute('aria-busy', String(value));
    fileInput.disabled = value || succeeded;
    submit.disabled = value || succeeded;
    confirm.disabled = value || succeeded;
    closeButtons.forEach(function (button) { button.disabled = value; });
  }

  function closeModal() {
    if (busy) return;
    clearTimeout(closeTimer);
    modal.classList.add('hide');
    document.body.style.overflow = previousOverflow;
    if (previousFocus && previousFocus.isConnected) previousFocus.focus();
    if (succeeded) {
      clearTimeout(toastTimer);
      toast.classList.remove('hide');
      toastTimer = setTimeout(function () { toast.classList.add('hide'); }, 2000);
    }
  }

  opener.addEventListener('click', function (event) {
    event.preventDefault();
    if (!modal.classList.contains('hide')) return;
    form.reset();
    clearTimeout(toastTimer);
    toast.classList.add('hide');
    succeeded = false;
    requiresConfirmation = false;
    setBusy(false);
    showMessage('', 'error');
    progress.classList.add('hide');
    confirm.classList.add('hide');
    submit.classList.remove('hide');
    previousFocus = document.activeElement;
    previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    modal.classList.remove('hide');
    fileInput.focus();
  });

  closeButtons.forEach(function (button) { button.addEventListener('click', closeModal); });
  modal.addEventListener('click', function (event) { if (event.target === modal) closeModal(); });
  document.addEventListener('keydown', function (event) {
    if (modal.classList.contains('hide')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      event.stopImmediatePropagation();
      closeModal();
    } else if (event.key === 'Tab') {
      const controls = Array.from(modal.querySelectorAll('button, input')).filter(function (control) {
        return !control.disabled && !control.classList.contains('hide');
      });
      event.preventDefault();
      if (!controls.length) return;
      const index = controls.indexOf(document.activeElement);
      controls[(index + (event.shiftKey ? -1 : 1) + controls.length) % controls.length].focus();
    }
  }, true);

  fileInput.addEventListener('change', function () {
    requiresConfirmation = false;
    confirm.classList.add('hide');
    submit.classList.remove('hide');
    showMessage('', 'error');
  });

  function upload(confirmed) {
    if (busy || succeeded) return;
    const file = fileInput.files[0];
    if (!file) {
      showMessage('يرجى اختيار ملف إكسيل أولًا.', 'error');
      fileInput.focus();
      return;
    }
    if (!/\.(xlsx|xls)$/i.test(file.name) || file.size > 50 * 1024 * 1024) {
      showMessage('اختر ملف XLSX أو XLS بحجم لا يتجاوز 50 ميجابايت.', 'error');
      return;
    }
    const data = new FormData(form);
    if (confirmed && requiresConfirmation) data.append('confirm_old_file', '1');
    setBusy(true);
    showMessage('', 'error');
    progress.classList.remove('hide');
    progressBar.setAttribute('aria-valuenow', '0');
    progressFill.style.width = '0%';
    progress.classList.remove('is-processing');
    progressText.textContent = 'جارٍ رفع الملف...';

    $.ajax({
      url: opener.href,
      method: 'POST',
      data: data,
      processData: false,
      contentType: false,
      dataType: 'json',
      xhr: function () {
        const xhr = $.ajaxSettings.xhr();
        if (xhr.upload) xhr.upload.addEventListener('progress', function (event) {
          if (!event.lengthComputable) return;
          const percent = Math.round(event.loaded / event.total * 100);
          progressBar.setAttribute('aria-valuenow', String(percent));
          progressFill.style.width = percent + '%';
          progress.classList.toggle('is-processing', percent === 100);
          progressText.textContent = percent === 100 ? 'جارٍ معالجة الملف وتحديث المخزون...' : 'جارٍ رفع الملف...';
        });
        return xhr;
      }
    }).done(function (response) {
      requiresConfirmation = response.requires_confirmation === true;
      confirm.classList.toggle('hide', !requiresConfirmation);
      submit.classList.toggle('hide', requiresConfirmation);
      if (response.status === 'success') {
        succeeded = true;
        showMessage('تم رفع الملف بنجاح', 'success');
        const status = document.querySelector('.stock-upload-status');
        if (status && response.uploaded_at_label) {
          status.classList.remove('is-stale');
          status.querySelector('span').textContent = response.uploaded_at_label;
        }
        closeTimer = setTimeout(closeModal, 400);
      } else {
        showMessage(response.message || 'تعذر رفع الملف، يرجى المحاولة مرة أخرى.', requiresConfirmation ? 'warning' : 'error');
      }
    }).fail(function (xhr) {
      showMessage(xhr.responseJSON && xhr.responseJSON.message
        ? xhr.responseJSON.message
        : 'تعذر إكمال الرفع. تحقق من الاتصال وصلاحية الدخول، ثم حاول مرة أخرى.', 'error');
    }).always(function () {
      setBusy(false);
      progress.classList.add('hide');
      if (requiresConfirmation) confirm.focus();
    });
  }

  form.addEventListener('submit', function (event) { event.preventDefault(); upload(false); });
  confirm.addEventListener('click', function () { upload(true); });
})();

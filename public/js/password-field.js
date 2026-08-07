// Password field widget: generate, copy, and the old/new/confirm edit-panel toggle
function generateStrongPassword(length = 12) {
    const upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    const lower = 'abcdefghijkmnpqrstuvwxyz';
    const digits = '23456789';
    const all = upper + lower + digits;

    function randomChar(chars) {
        if (window.crypto && window.crypto.getRandomValues) {
            const buffer = new Uint32Array(1);
            window.crypto.getRandomValues(buffer);
            return chars[buffer[0] % chars.length];
        }
        return chars[Math.floor(Math.random() * chars.length)];
    }

    let password = [randomChar(upper), randomChar(lower), randomChar(digits)];
    for (let i = password.length; i < length; i++) {
        password.push(randomChar(all));
    }
    for (let i = password.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [password[i], password[j]] = [password[j], password[i]];
    }
    return password.join('');
}

function copyTextToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        return navigator.clipboard.writeText(text);
    }
    return new Promise((resolve, reject) => {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        try {
            document.execCommand('copy');
            resolve();
        } catch (err) {
            reject(err);
        } finally {
            document.body.removeChild(textarea);
        }
    });
}

function showPasswordFieldToast(type, message) {
    if (window.toastr && typeof toastr[type] === 'function') {
        toastr[type](message);
        return;
    }
    alert(message);
}

$(document).on('click', '.password-field__generate', function () {
    const container = $(this).closest('.password-field');
    const input = container.find('.password-field__input');
    const generated = generateStrongPassword();
    input.val(generated).trigger('focus');

    const panel = $(this).closest('.password-edit-panel');
    if (panel.length) {
        panel.find('.password-edit-confirm').val(generated);
    }
});

$(document).on('click', '.password-field__copy', function () {
    const btn = $(this);
    const input = btn.closest('.password-field').find('.password-field__input')[0];
    if (!input || !input.value) {
        return;
    }
    copyTextToClipboard(input.value).then(() => {
        const icon = btn.find('i');
        icon.removeClass('bi-clipboard').addClass('bi-check2');
        btn.addClass('is-copied');
        setTimeout(() => {
            icon.removeClass('bi-check2').addClass('bi-clipboard');
            btn.removeClass('is-copied');
        }, 1500);
    }).catch(() => {});
});

$(document).on('click', '.password-toggle-edit', function () {
    const targetId = $(this).data('target');
    const panel = document.getElementById(targetId);
    if (!panel) {
        return;
    }

    const isHidden = panel.hidden;
    panel.hidden = !isHidden;

    const label = $(this).find('span');
    const icon = $(this).find('i');

    if (isHidden) {
        label.text('إلغاء تعديل كلمة السر');
        icon.attr('class', 'bi bi-x-circle');
        const firstField = panel.querySelector('.password-edit-current');
        if (firstField) {
            firstField.focus();
        }
    } else {
        label.text('تعديل كلمة السر');
        icon.attr('class', 'bi bi-pencil-square');
        panel.querySelectorAll('input').forEach((input) => {
            input.value = '';
            delete input.dataset.autofilled;
        });
    }
});

$(document).on('submit', 'form', function (event) {
    const panel = $(this).find('.password-edit-panel').filter(function () {
        return !this.hidden;
    });

    if (!panel.length) {
        return;
    }

    const oldVal = panel.find('.password-edit-current').val().trim();
    const newVal = panel.find('.password-edit-new').val().trim();
    const confirmVal = panel.find('.password-edit-confirm').val().trim();
    const anyFilled = oldVal || newVal || confirmVal;

    if (!anyFilled) {
        return;
    }

    if (!oldVal || !newVal || !confirmVal) {
        event.preventDefault();
        showPasswordFieldToast('error', 'يجب إدخال كلمة السر القديمة والجديدة وتأكيدها');
        return;
    }

    if (newVal !== confirmVal) {
        event.preventDefault();
        showPasswordFieldToast('error', 'كلمة السر الجديدة وتأكيدها غير متطابقين');
    }
});

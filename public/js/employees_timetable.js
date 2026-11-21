/* global $, html2pdf */
'use strict';
const config = window.timetableConfig;
const days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
const labels = {am: 'AM', pm: 'PM', store_am: 'Store AM', store_pm: 'Store PM', note: 'ملاحظة', leave: 'إجازة'};
let employees = [], supervisors = [], cells = {}, bossCells = {}, leaves = [], overrides = {}, approvals = {};
let currentCell = null, busy = true, ready = false;
let year = Number(localStorage.getItem('selectedYear')) || Number(config.today.slice(0, 4));
let month = localStorage.getItem('selectedMonth') === null ? Number(config.today.slice(5, 7)) - 1 : Number(localStorage.getItem('selectedMonth'));
let week = localStorage.getItem('selectedWeek') || '0';
const escapeText = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
const dateKey = date => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
const monthKey = () => `${year}-${String(month + 1).padStart(2, '0')}`;
const weekCount = () => Math.ceil((new Date(year, month, 1).getDay() + new Date(year, month + 1, 0).getDate()) / 7);
const weekDates = index => Array.from({length: 7}, (_, i) => new Date(year, month, 1 - new Date(year, month, 1).getDay() + Number(index) * 7 + i));
const groups = () => config.supervisors ? ['employee', 'supervisor'] : ['employee'];
const weeks = () => week === 'all' ? Array.from({length: weekCount()}, (_, i) => i) : [Number(week)];
const activeDates = () => weeks().flatMap(weekDates).filter(date => date.getMonth() === month).map(dateKey);
const locked = (group, date) => Object.prototype.hasOwnProperty.call(approvals, `${group}_${date}`);
function valueFor(person, date, group) {
  if (locked(group, date)) return approvals[`${group}_${date}`][person.user_id] || {type: '', status: ''};
  const key = `${person.user_id}_${date}`;
  if (group === 'employee') {
    const matching = leaves.filter(leave => date >= leave.start_date && date <= leave.end_date &&
      (Number(leave.all_employees) === 1 || leave.employee_ids.map(Number).includes(Number(person.user_id))));
    if (matching.length) {
      const signature = matching.map(leave => Number(leave.id)).sort((a, b) => a - b).join(',');
      if (overrides[key]?.signature === signature) return overrides[key];
      return {type: 'leave', status: [...new Set(matching.map(leave => leave.name))].join(' / ')};
    }
  }
  return (group === 'employee' ? cells : bossCells)[key] || {type: '', status: ''};
}
function message(text, error = false) {
  const target = document.getElementById('timetableMessage');
  target.textContent = text; target.className = error ? 'alert alert-danger' : 'alert alert-success'; target.hidden = !text;
}
function saveSelection() {
  localStorage.setItem('selectedYear', year); localStorage.setItem('selectedMonth', month); localStorage.setItem('selectedWeek', week);
}
function filters() {
  const maxYear = Number(config.latestMonth.slice(0, 4));
  if (!Number.isInteger(year) || year < 2024 || year > maxYear) year = Number(config.today.slice(0, 4));
  const maxMonth = year === maxYear ? Number(config.latestMonth.slice(5, 7)) - 1 : 11;
  if (!Number.isInteger(month) || month < 0 || month > maxMonth) month = maxMonth;
  document.getElementById('yearSelect').innerHTML = Array.from({length: maxYear - 2024 + 1}, (_, i) => {
    const item = 2024 + i; return `<option value="${item}" ${item === year ? 'selected' : ''}>${item}</option>`;
  }).join('');
  document.getElementById('monthSelect').innerHTML = months.slice(0, maxMonth + 1).map((name, i) => `<option value="${i}" ${i === month ? 'selected' : ''}>${name}</option>`).join('');
  if (week !== 'all' && (!/^[0-5]$/.test(week) || Number(week) >= weekCount())) week = '0';
  saveSelection();
}
function header(dates, title) {
  return `<thead><tr><th class="employee-name-col">${title}</th>${dates.map(date => `<th class="${date.getMonth() !== month ? 'outside-month' : ''}"><span class="day-name">${days[date.getDay()]}</span><br><span class="date">${String(date.getDate()).padStart(2, '0')}/${String(date.getMonth() + 1).padStart(2, '0')}</span></th>`).join('')}</tr></thead>`;
}
function personRow(person, dates, group) {
  return `<tr><th class="employee-name-col">${escapeText(person.user_fillname)}${person.employee_type === 'consultant' ? '<small class="employee-role">استشاري</small>' : ''}</th>${dates.map(date => {
    if (date.getMonth() !== month) return '<td class="outside-month" aria-label="خارج الشهر">—</td>';
    const key = dateKey(date), value = valueFor(person, key, group);
    const editable = ready && config.canSave && !busy && !locked(group, key);
    const type = Object.prototype.hasOwnProperty.call(labels, value.type) ? value.type : (value.status ? 'note' : 'white');
    return `<td class="schedule-cell bg-${type} ${locked(group, key) ? 'cell-approved' : ''}" ${editable ? 'tabindex="0" role="button"' : ''} data-user="${person.user_id}" data-group="${group}" data-date="${key}" title="${escapeText(value.status)}">${escapeText(value.status)}</td>`;
  }).join('')}</tr>`;
}
function render() {
  document.getElementById('dropdownMenu').style.display = 'none'; currentCell = null;
  document.querySelectorAll('.week-btn').forEach(button => {
    button.hidden = button.dataset.week !== 'all' && Number(button.dataset.week) >= weekCount();
    const selected = button.dataset.week === week;
    button.classList.toggle('active', selected);
    button.setAttribute('aria-pressed', String(selected)); button.disabled = busy;
  });
  document.getElementById('yearSelect').disabled = busy; document.getElementById('monthSelect').disabled = busy;
  for (const group of groups()) {
    const container = document.getElementById(group === 'employee' ? 'scheduleTableContainer' : 'supervisorsTableContainer');
    if (!container) continue;
    container.classList.toggle('timetable-all-weeks', week === 'all');
    const people = group === 'employee' ? employees : supervisors;
    container.innerHTML = weeks().map(index => {
      const dates = weekDates(index);
      return `<section class="timetable-week"><h3>الأسبوع ${index + 1} — ${group === 'employee' ? 'الموظفون والاستشاريون' : 'المشرفون'}</h3><table class="table table-bordered timetable-grid">${header(dates, group === 'employee' ? 'الموظف' : 'المشرف')}<tbody>${people.length ? people.map(person => personRow(person, dates, group)).join('') : '<tr><td colspan="8">لا يوجد أشخاص ضمن هذا التصنيف</td></tr>'}</tbody></table></section>`;
    }).join('');
  }
  const count = activeDates().reduce((sum, date) => sum + groups().filter(group => locked(group, date)).length, 0);
  const total = activeDates().length * groups().length;
  const approve = document.getElementById('approveSchedule');
  if (approve) {
    const approved = count === total;
    const label = approved ? 'تعديل' : 'اعتمد الجدول';
    approve.disabled = busy || !ready;
    approve.dataset.action = approved ? 'edit' : 'approve';
    approve.dataset.tooltip = label;
    approve.setAttribute('aria-label', label);
    approve.classList.toggle('is-approved', approved);
    approve.innerHTML = `<i class="bi ${approved ? 'bi-pencil-square' : 'bi-check2-circle'}" aria-hidden="true"></i>`;
  }
  document.querySelectorAll('[data-export]').forEach(button => { button.disabled = busy || !ready; });
}
async function load() {
  busy = true; ready = false; render();
  try {
    const data = await Promise.all([$.getJSON('get_employees'), $.getJSON('get_supervisors'), $.getJSON('get_schedule'), $.getJSON('get_supervisors_schedule'), $.getJSON('employees_leaves/data'), $.getJSON('timetable_state', {month: monthKey()})]);
    if (!data[5].success) throw new Error(data[5].message);
    [employees, supervisors, cells, bossCells] = data;
    leaves = data[4].leaves; overrides = data[4].overrides || {}; approvals = data[5].approvals; ready = true;
  } catch (error) { message(error.message || 'تعذر تحميل الجدول. أعد تحميل الصفحة للمحاولة مرة أخرى.', true); }
  finally { busy = false; render(); }
}
async function post(url, data, success = '') {
  message(''); busy = true; render();
  try {
    const response = await $.ajax({url, method: 'POST', dataType: 'json', data});
    if (!response.success) throw new Error(response.message);
    message(success);
  } catch (error) { message(error.message || 'تعذر حفظ التغيير.', true); }
  await load();
}
function saveCell(cell, type, status) {
  if (busy || !ready || locked(cell.dataset.group, cell.dataset.date)) return;
  const key = `${cell.dataset.user}_${cell.dataset.date}`;
  return post('save_user_schedule', {scheduleData: JSON.stringify({[key]: {type, status}}), changed_key: key});
}
function openCell(cell) {
  if (!ready || busy || !config.canSave || locked(cell.dataset.group, cell.dataset.date)) return;
  const person = (cell.dataset.group === 'supervisor' ? supervisors : employees).find(row => Number(row.user_id) === Number(cell.dataset.user));
  if (!person) return;
  currentCell = cell;
  const options = person.employee_type === 'consultant' ? ['am', 'pm', 'store_am', 'store_pm', 'note'] : ['am', 'pm', 'note'];
  const menu = document.getElementById('dropdownMenu');
  menu.innerHTML = options.map(type => `<button type="button" class="dropdown-item bg-${type}" data-option="${type}">${labels[type]}</button>`).join('');
  menu.style.display = 'block'; const rect = cell.getBoundingClientRect();
  menu.style.left = `${Math.max(8, Math.min(rect.right - menu.offsetWidth, window.innerWidth - menu.offsetWidth - 8))}px`;
  menu.style.top = `${Math.max(8, Math.min(rect.bottom + 4, window.innerHeight - menu.offsetHeight - 8))}px`;
  menu.querySelector('button').focus();
}
function editNote(cell) {
  const input = document.createElement('textarea'); input.className = 'form-control timetable-note'; input.maxLength = 1000;
  input.setAttribute('aria-label', 'ملاحظة الدوام'); input.value = cell.textContent.trim(); cell.replaceChildren(input);
  let saved = false;
  function finish(cancel = false) {
    if (saved) return; saved = true;
    if (cancel) render(); else { const value = input.value.trim(); saveCell(cell, value ? 'note' : '', value); }
  }
  input.addEventListener('blur', () => finish());
  input.addEventListener('keydown', event => {
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); finish(true); }
    if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); finish(); }
  }); input.focus();
}
function changeApproval(action) {
  if (!busy && ready) return post('timetable_approval', {month: monthKey(), week, action}, action === 'approve' ? 'تم اعتماد وحفظ الفترة المعروضة وقفل تعديلها.' : 'تم فتح الفترة المعروضة للتعديل. اعتمدها مرة أخرى بعد الانتهاء.');
}

// Both export paths use measured A4 pages with repeated day headings.
const exportStyles = `
 @page{size:A4 landscape;margin:10mm}
 .export-page,.export-page *{box-sizing:border-box}
 .export-page{font-family:Tahoma,Arial,sans-serif;color:#18243b;direction:rtl;width:277mm;height:189mm;padding:2mm;overflow:hidden;background:white;break-after:page;page-break-after:always}
 .export-page:last-child{break-after:auto;page-break-after:auto}.export-page h2{font-size:18px;margin:0 0 6px}
 .export-meta{font-size:11px;margin-bottom:8px;display:flex;justify-content:space-between}
 .export-page table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:11px;margin:0 0 8px}
 .export-page th,.export-page td{border:1px solid #b7c2d1;padding:5px 3px;text-align:center;vertical-align:middle;white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.45;min-width:0;height:auto}
 .export-page thead th,.export-section{background:#e9edf7;font-weight:bold}.export-page .employee-name-col{width:19%}.export-page .employee-role{display:block;font-size:9px}
 .export-page .bg-am{background:#d8eef7!important}.export-page .bg-pm{background:#e4ddf7!important}.export-page .bg-store_am{background:#ffdfc2!important}.export-page .bg-store_pm{background:#f8c8d5!important}
 .export-page .bg-leave{background:#dfefce!important}.export-page .bg-note{background:#fff3cd!important}.export-page .outside-month{background:#f2f2f2;color:#999}
 @media print{body{-webkit-print-color-adjust:exact;print-color-adjust:exact}}
`;
function buildExport() {
  const root = document.createElement('div'); root.className = 'timetable-export';
  root.style.cssText = 'position:fixed;left:-20000px;top:0;width:277mm;direction:rtl;background:white';
  const style = document.createElement('style');
  style.textContent = exportStyles;
  root.append(style); document.body.append(root);
  let pageNumber = 0;
  for (const index of weeks()) {
    const dates = weekDates(index); let page, tbody;
    function newPage(group) {
      page = document.createElement('section'); page.className = 'export-page';
      const status = activeDates().every(date => groups().every(g => locked(g, date))) ? 'معتمد' : 'مسودة / معتمد جزئيًا';
      page.innerHTML = `<h2>جدول الدوام — ${months[month]} ${year} — الأسبوع ${index + 1}</h2><div class="export-meta"><span>الأيام خارج الشهر غير مشمولة</span><span>${status} · صفحة ${++pageNumber}</span></div><table>${header(dates, group === 'employee' ? 'الموظف' : 'المشرف')}<tbody></tbody></table>`;
      root.append(page); tbody = page.querySelector('tbody');
    }
    for (const group of groups()) {
      const people = group === 'employee' ? employees : supervisors;
      if (!page) newPage(group);
      const heading = document.createElement('tr'); heading.innerHTML = `<th colspan="8" class="export-section">${group === 'employee' ? 'الموظفون والاستشاريون' : 'المشرفون'}</th>`; tbody.append(heading);
      for (const person of people) {
        const template = document.createElement('template'); template.innerHTML = `<table><tbody>${personRow(person, dates, group)}</tbody></table>`;
        const row = template.content.querySelector('tr'); row.querySelectorAll('[tabindex]').forEach(cell => { cell.removeAttribute('tabindex'); cell.removeAttribute('role'); }); tbody.append(row);
        if (page.scrollHeight > page.clientHeight) {
          row.remove(); if (!heading.nextElementSibling && heading.parentNode) heading.remove();
          newPage(group); tbody.append(row);
          if (page.scrollHeight > page.clientHeight) row.style.fontSize = '8px';
        }
      }
    }
  }
  return root;
}
async function exportSchedule(mode) {
  if (busy || !ready) return;
  busy = true; render(); let root;
  try {
    if (document.fonts?.ready) await document.fonts.ready;
    root = buildExport();
    if (mode === 'pdf') {
      if (typeof html2pdf === 'undefined') throw new Error('تعذر تحميل أداة PDF. استخدم الطباعة ثم حفظ بتنسيق PDF أو أعد المحاولة.');
      let pdf;
      // Separate canvases avoid browser size limits for long monthly schedules.
      for (const page of root.querySelectorAll('.export-page')) {
        const worker = html2pdf().set({margin: 10, html2canvas: {scale: 2, backgroundColor: '#ffffff'}, jsPDF: {unit: 'mm', format: 'a4', orientation: 'landscape'}}).from(page).toPdf();
        const single = await worker.get('pdf');
        if (!pdf) pdf = single;
        else { const canvas = await worker.get('canvas'); pdf.addPage(); pdf.addImage(canvas.toDataURL('image/jpeg', 0.98), 'JPEG', 10, 10, 277, 189); }
      }
      pdf.save(`جدول_الدوام_${monthKey()}_${week === 'all' ? 'الشهر' : 'الأسبوع_' + (Number(week) + 1)}.pdf`);
    } else {
      const frame = document.createElement('iframe'); frame.style.cssText = 'position:fixed;width:0;height:0;border:0'; document.body.append(frame);
      const doc = frame.contentDocument;
      doc.open(); doc.write(`<!doctype html><html lang="ar" dir="rtl"><head><meta charset="utf-8"><title>جدول الدوام ${monthKey()}</title><style>body{margin:0}${exportStyles}</style></head><body>${Array.from(root.querySelectorAll('.export-page'), page => page.outerHTML).join('')}</body></html>`); doc.close();
      await doc.fonts.ready; frame.contentWindow.addEventListener('afterprint', () => frame.remove(), {once: true}); frame.contentWindow.focus(); frame.contentWindow.print();
    }
  } catch (error) { message(error.message || 'تعذر تجهيز الملف.', true); }
  finally { root?.remove(); busy = false; render(); }
}
document.addEventListener('DOMContentLoaded', () => {
  filters();
  document.getElementById('yearSelect').addEventListener('change', event => { year = Number(event.target.value); filters(); load(); });
  document.getElementById('monthSelect').addEventListener('change', event => { month = Number(event.target.value); filters(); load(); });
  document.querySelectorAll('.week-btn').forEach(button => button.addEventListener('click', () => { week = button.dataset.week; saveSelection(); render(); }));
  document.querySelectorAll('[data-export]').forEach(button => button.addEventListener('click', () => exportSchedule(button.dataset.export)));
  document.getElementById('approveSchedule')?.addEventListener('click', event => changeApproval(event.currentTarget.dataset.action));
  document.addEventListener('click', event => {
    const option = event.target.closest('[data-option]');
    if (option && currentCell) {
      const cell = currentCell, type = option.dataset.option; document.getElementById('dropdownMenu').style.display = 'none'; currentCell = null;
      if (type === 'note') editNote(cell); else saveCell(cell, type, labels[type] || ''); return;
    }
    const cell = event.target.closest('.schedule-cell');
    if (cell && !event.target.closest('textarea')) { openCell(cell); return; }
    document.getElementById('dropdownMenu').style.display = 'none'; currentCell = null;
  });
  document.addEventListener('keydown', event => {
    if ((event.key === 'Enter' || event.key === ' ') && event.target.matches('.schedule-cell[tabindex]')) { event.preventDefault(); openCell(event.target); }
    if (event.key === 'Escape') { document.getElementById('dropdownMenu').style.display = 'none'; currentCell = null; }
  });
  load();
});

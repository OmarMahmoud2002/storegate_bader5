const days = ["الأحد", "الاثنين", "الثلاثاء", "الأربعاء", "الخميس", "الجمعة", "السبت"];
let employees = [];
let supervisors = []; // قائمة المشرفين
let selectedMonth = parseInt(localStorage.getItem('selectedMonth')) || 0;
let selectedYear = parseInt(localStorage.getItem('selectedYear')) || new Date().getFullYear();
let selectedWeek = localStorage.getItem('selectedWeek') || '0';
let currentCell = null;
let scheduleData = {};
let employeeLeaves = [];
let employeeLeaveOverrides = {};

function matchingEmployeeLeaves(id, dateStr) {
  return employeeLeaves.filter(leave => dateStr >= leave.start_date && dateStr <= leave.end_date &&
    (Number(leave.all_employees) === 1 || leave.employee_ids.map(Number).includes(Number(id))));
}

function leaveSignature(matching) {
  return matching.map(leave => Number(leave.id)).sort((a, b) => a - b).join(',');
}

function getEmployeeCell(id, dateStr) {
  const matching = matchingEmployeeLeaves(id, dateStr);
  if (matching.length) {
    const override = employeeLeaveOverrides[`${id}_${dateStr}`];
    if (override && override.signature === leaveSignature(matching)) return override;
    return {type: 'leave', status: 'إجازة — ' + [...new Set(matching.map(leave => leave.name))].join(' / '), managedLeave: true};
  }
  return scheduleData[`${id}_${dateStr}`] || {};
}

function escapeScheduleText(value) {
  return String(value || '').replace(/[&<>"']/g, char => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char]));
}

function scheduleDate(date) {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}
let supervisorsData = {}; // بيانات المشرفين

function get_employees() {
  return $.get('get_employees', function(data) {
    employees = [];
    for (let emp of data) {
      employees.push({name: emp.user_fillname, id: emp.user_id});
    }
  }, 'json');
}

function get_supervisors() {
  return $.get('get_supervisors', function(data) {
    supervisors = [];
    for (let supervisor of data) {
      supervisors.push({name : supervisor.user_fillname, id: supervisor.user_id});
    }
  }, 'json');
}

function parseScheduleResponse(data) {
  if (!data) return {};
  if (typeof data === 'string') {
    try {
      return JSON.parse(data);
    } catch (e) {
      return {};
    }
  }
  return data;
}

function get_schedule() {
  return $.get('get_schedule', function(data) {
    scheduleData = parseScheduleResponse(data);
  }, 'json');
}

function get_supervisors_schedule() {
  return $.get('get_supervisors_schedule', function(data) {
    supervisorsData = parseScheduleResponse(data);
  }, 'json');
}

function loadTimetableData() {
  return $.when(
    get_employees(),
    get_supervisors(),
    get_schedule(),
    getEmployeeLeaves(),
    get_supervisors_schedule()
  );
}

function getEmployeeLeaves() {
  return $.get('employees_leaves/data', data => {
    employeeLeaves = data.leaves;
    employeeLeaveOverrides = data.overrides || {};
  }, 'json');
}
// إضافة متغير للتحكم في عرض جميع الأسابيع
let showAllWeeks = false;

// إضافة متغير للزر
let downloadBtn;

// دالة لحفظ البيانات في localStorage
function saveScheduleData(key, entry) {
  const payload = key ? { [key]: entry } : scheduleData;
  function restoreSavedData(message) {
    alert(message);
    $.when(get_schedule(), getEmployeeLeaves()).done(renderTable);
  }
  $.post('save_user_schedule', { scheduleData: JSON.stringify(payload), changed_key: key || '' }, function(response) {
    response = typeof response === 'string' ? JSON.parse(response) : response;
    if (response.success == false) {
      restoreSavedData(response.message);
      return;
    }
  }).fail(() => restoreSavedData('تعذر حفظ الحالة، حاول مرة أخرى.'));
}

// دالة لحفظ بيانات المشرفين في localStorage
function saveSupervisorsData() {
  $.post('save_user_schedule', { scheduleData: JSON.stringify(supervisorsData) }, function(response) {
    response = typeof response === 'string' ? JSON.parse(response) : response;
    if (response.success == false) {
      alert(response.message);
      return;
    }
  });
}

// دالة لاسترجاع بيانات المشرفين من localStorage
function loadSupervisorsData() {
  const savedData = localStorage.getItem('supervisorsData');
  if (savedData) {
    supervisorsData = JSON.parse(savedData);
  }
}

const getWeekDates = (month, weekIndex) => {
  const year = selectedYear;
  const firstDay = new Date(year, month, 1);
  const firstSundayOffset = (7 - firstDay.getDay()) % 7;
  const startDate = new Date(year, month, 1 + firstSundayOffset + (weekIndex * 7));

  const dates = [];
  for (let i = 0; i < 7; i++) {
    const day = new Date(startDate);
    day.setDate(startDate.getDate() + i);
    dates.push(day);
  }
  return dates;
};

function formatDate(date) {
  return String(date.getDate()).padStart(2, '0');
}

function normalizeScheduleType(cellData) {
  if (!cellData) return '';

  const type = (cellData.type || '').trim().toLowerCase();
  if (['am', 'pm', 'leave', 'note'].includes(type)) {
    return type;
  }

  const status = (cellData.status || '').trim().toLowerCase();
  if (status === 'am') return 'am';
  if (status === 'pm') return 'pm';
  if (['أجازه', 'إجازة', 'اجازه', 'اجازة'].includes(status)) return 'leave';
  if (status) return 'note';

  return '';
}

function getScheduleCellClass(cellData) {
  if (cellData && cellData.managedLeave) return ' bg-leave managed-leave';
  switch (normalizeScheduleType(cellData)) {
    case 'am': return ' bg-am';
    case 'pm': return ' bg-pm';
    case 'leave': return ' bg-leave';
    case 'note': return ' bg-note';
    default: return ' bg-white';
  }
}

// دالة للتحقق من وجود الأسبوع الخامس في الشهر
function checkFifthWeekExists(month) {
  const year = selectedYear;
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  
  // حساب تاريخ بداية الأسبوع الأول
  const firstSundayOffset = (7 - firstDay.getDay()) % 7;
  const firstWeekStart = new Date(year, month, 1 + firstSundayOffset);
  
  // حساب تاريخ بداية الأسبوع الخامس
  const fifthWeekStart = new Date(firstWeekStart);
  fifthWeekStart.setDate(firstWeekStart.getDate() + (4 * 7));
  
  // إذا كان تاريخ بداية الأسبوع الخامس قبل أو في نفس يوم آخر يوم في الشهر
  // فهذا يعني أن الشهر يحتاج أسبوع خامس
  return fifthWeekStart <= lastDay;
}

// تعديل دالة renderTable مع الإبقاء على معالجة المنسدلة كـ employee في عرض الكل
const renderTable = () => {
  const container = document.getElementById("scheduleTableContainer");
  const supervisorsContainer = document.getElementById("supervisorsTableContainer");
  
  // تنظيف الحاويات فقط إذا كانت موجودة لمنع توقف الكود بسبب الـ PHP
  if (container) container.innerHTML = '';
  if (supervisorsContainer) supervisorsContainer.innerHTML = '';

  // التحقق من وجود الأسبوع الخامس في الشهر
  const hasFifthWeek = checkFifthWeekExists(selectedMonth);
  const fifthWeekBtn = document.querySelector('.week-btn[data-week="4"]');
  if (fifthWeekBtn) {
    if (!hasFifthWeek) {
      fifthWeekBtn.style.display = 'none';
      if (selectedWeek === '4') {
        selectedWeek = '0';
        document.querySelector('.week-btn[data-week="0"]').classList.add('active');
        document.querySelectorAll('.week-btn').forEach(btn => {
          if (btn.dataset.week !== '0') btn.classList.remove('active');
        });
      } else {
         if (selectedWeek !== '4'){
             fifthWeekBtn.style.display = 'none';
         }
      }
    } else {
      fifthWeekBtn.style.display = 'inline-block';
    }
  }

  if (selectedWeek === 'all') {
    // --- عرض الكل ---
    if (container) {
      try {
        const weeksGridContainer = document.createElement('div');
        weeksGridContainer.className = 'weeks-grid';
        weeksGridContainer.style.display = 'flex';
        weeksGridContainer.style.flexWrap = 'wrap';
        weeksGridContainer.style.gap = '20px';
        weeksGridContainer.style.justifyContent = 'flex-start';
        
        const totalWeeks = hasFifthWeek ? 5 : 4;
        
        for (let week = 0; week < totalWeeks; week++) {
          const weekWrapper = document.createElement('div');
          weekWrapper.className = 'week-wrapper';
          weekWrapper.style.flex = '1';
          weekWrapper.style.minWidth = '300px';
          weekWrapper.style.maxWidth = 'calc(25% - 20px)';
          
          const weekContainer = document.createElement('div');
          weekContainer.className = 'week-container';
          
          const weekTitle = document.createElement('div');
          weekTitle.className = 'week-title';
          weekTitle.textContent = `الأسبوع ${week + 1}`;
          weekContainer.appendChild(weekTitle);

          const dates = getWeekDates(selectedMonth, week);

          // رندرة الموظفين
          try {
            let employeesHtml = `
              <table class="table table-bordered table-hover">
                <thead class="table-primary">
                  <tr>
                    <th class="align-middle text-center table-primary employee-name-col">الموظف</th>
                    ${dates.map(date => `
                      <th class="vertical-header" id="employee-day${week}-${date.getDay()}">
                        <span class="day-name">${days[date.getDay()]}</span>
                        <span class="date">${formatDate(date)}</span>
                      </th>
                    `).join('')}
                  </tr>
                </thead>
                <tbody>
                  ${employees.map(emp => `
                    <tr>
                      <td class="align-middle text-center table-primary fw-bold employee-name-col">${emp.name}</td>
                      ${dates.map(date => {
                        const dateStr = scheduleDate(date);
                        const cellData = getEmployeeCell(emp.id, dateStr);
                        let cellClass = 'align-middle text-center';
                        cellClass += getScheduleCellClass(cellData);
                        return `<td class="${cellClass}" style="cursor: pointer" data-date="${date.toISOString()}" data-employee="${escapeScheduleText(emp.name)}" data-employeeid="${emp.id}">${escapeScheduleText(cellData.status)}</td>`;
                      }).join('')}
                    </tr>
                  `).join('')}
                </tbody>
              </table>
            `;
            weekContainer.innerHTML += employeesHtml;

            // جدول توزيع الوقت
            let timeDistributionHtml = `
              <table class="table table-bordered table-hover mt-3" id="timeDistributionTable">
                <thead class="table-primary">
                  <tr>
                    <th class="align-middle text-center table-primary">توزيع الوقت</th>
                    ${dates.map(date => `
                      <th class="vertical-header" id="day${date.getDay() + 1}">
                        <span class="day-name">${days[date.getDay()]}</span>
                        <span class="date">${formatDate(date)}</span>
                      </th>
                    `).join('')}
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="align-middle text-center bg-am fw-bold">AM</td>
                    ${dates.map(date => {
                      const dateStr = scheduleDate(date);
                      let amCount = 0;
                      employees.forEach(emp => {
                        const key = `${emp.id}_${dateStr}`;
                        const cellData = getEmployeeCell(emp.id, dateStr);
                        if (normalizeScheduleType(cellData) === 'am') amCount++;
                      });
                      return `<td class="text-center time-am time-cell bg-am">${amCount}</td>`;
                    }).join('')}
                  </tr>
                  <tr>
                    <td class="align-middle text-center bg-pm fw-bold">PM</td>
                    ${dates.map(date => {
                      const dateStr = scheduleDate(date);
                      let pmCount = 0;
                      employees.forEach(emp => {
                        const key = `${emp.id}_${dateStr}`;
                        const cellData = getEmployeeCell(emp.id, dateStr);
                        if (normalizeScheduleType(cellData) === 'pm') pmCount++;
                      });
                      return `<td class="text-center time-pm time-cell bg-pm">${pmCount}</td>`;
                    }).join('')}
                  </tr>
                  <tr>
                    <td class="align-middle text-center bg-leave fw-bold">أجازه</td>
                    ${dates.map(date => {
                      const dateStr = scheduleDate(date);
                      let leaveCount = 0;
                      employees.forEach(emp => {
                        const key = `${emp.id}_${dateStr}`;
                        const cellData = getEmployeeCell(emp.id, dateStr);
                        if (normalizeScheduleType(cellData) === 'leave') leaveCount++;
                      });
                      return `<td class="text-center leave time-cell bg-leave">${leaveCount}</td>`;
                    }).join('')}
                  </tr>
                  <tr>
                    <td class="align-middle text-center bg-note fw-bold">ملاحظه</td>
                    ${dates.map(date => {
                      const dateStr = scheduleDate(date);
                      let noteCount = 0;
                      employees.forEach(emp => {
                        const key = `${emp.id}_${dateStr}`;
                        const cellData = getEmployeeCell(emp.id, dateStr);
                        if (normalizeScheduleType(cellData) === 'note') noteCount++;
                      });
                      return `<td class="text-center note time-cell bg-note">${noteCount}</td>`;
                    }).join('')}
                  </tr>
                </tbody>
              </table>
            `;
            weekContainer.innerHTML += timeDistributionHtml;
          } catch (empError) {
            console.error("خطأ في الموظفين (عرض الكل):", empError);
            weekContainer.innerHTML += `<div class="alert alert-danger mt-2">خطأ في تحميل جدول الموظفين.</div>`;
          }

          // رندرة المشرفين (فقط لو كانت مصفوفة المشرفين معرفة لتجنب الأخطاء)
          if (typeof supervisors !== 'undefined' && supervisors) {
            try {
              let supervisorsHtml = `
                <table class="table table-bordered table-hover mt-3">
                  <thead class="table-primary">
                    <tr>
                      <th class="align-middle text-center table-primary">المشرف</th>
                      ${dates.map(date => `
                        <th class="vertical-header" id="supervisor-day${week}-${date.getDay()}">
                          <span class="day-name">${days[date.getDay()]}</span>
                          <span class="date">${formatDate(date)}</span>
                        </th>
                      `).join('')}
                    </tr>
                  </thead>
                  <tbody>
                    ${supervisors.map(sup => `
                      <tr>
                        <td class="align-middle text-center table-primary fw-bold">${sup.name}</td>
                        ${dates.map(date => {
                          const dateStr = scheduleDate(date);
                          const cellData = supervisorsData[`${sup.id}_${dateStr}`] || {};
                          let cellClass = 'align-middle text-center';
                          cellClass += getScheduleCellClass(cellData);
                          return `<td class="${cellClass}" style="cursor: pointer" data-date="${date.toISOString()}" data-supervisor="${sup.name}" data-supervisorid="${sup.id}">${cellData.status || ''}</td>`;
                        }).join('')}
                      </tr>
                    `).join('')}
                  </tbody>
                </table>
              `;
              weekContainer.innerHTML += supervisorsHtml;
            } catch (supError) {
              console.error("خطأ في المشرفين (عرض الكل):", supError);
              weekContainer.innerHTML += `<div class="alert alert-danger mt-2">خطأ في تحميل جدول المشرفين.</div>`;
            }
          }
            
          weekWrapper.appendChild(weekContainer);
          weeksGridContainer.appendChild(weekWrapper);
        }
        container.appendChild(weeksGridContainer);
      } catch (globalAllError) {
        console.error("خطأ عام في عرض الكل:", globalAllError);
      }
    }

    if (downloadBtn) downloadBtn.style.display = 'inline-block';

  } else {
    // --- عرض أسبوع واحد فقط ---
    const dates = getWeekDates(selectedMonth, selectedWeek);
      
    // رندرة الموظفين وتوزيع الوقت
    if (container) {
      try {
        let employeesHtml = `
          <table class="table table-bordered table-hover">
            <thead class="table-primary">
              <tr>
                <th class="align-middle text-center table-primary employee-name-col">الموظف</th>
                ${dates.map(date => `
                  <th class="vertical-header" id="employee-day${selectedWeek}-${date.getDay()}">
                    <span class="day-name">${days[date.getDay()]}</span>
                    <span class="date">${formatDate(date)}</span>
                  </th>
                `).join('')}
              </tr>
            </thead>
            <tbody>
              ${employees.map(emp => `
                <tr>
                  <td class="align-middle text-center table-primary fw-bold employee-name-col">${emp.name}</td>
                  ${dates.map(date => {
                    const dateStr = scheduleDate(date);
                    const cellData = getEmployeeCell(emp.id, dateStr);
                    let cellClass = 'align-middle text-center';
                    cellClass += getScheduleCellClass(cellData);
                    return `<td class="${cellClass}" style="cursor: pointer" data-date="${date.toISOString()}" data-employee="${escapeScheduleText(emp.name)}" data-employeeid="${emp.id}">${escapeScheduleText(cellData.status)}</td>`;
                  }).join('')}
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
        container.innerHTML = employeesHtml;

        let timeDistributionHtml = `
          <table class="table table-bordered table-hover mt-3" id="timeDistributionTable">
            <thead class="table-primary">
              <tr>
                <th class="align-middle text-center table-primary">توزيع الوقت</th>
                ${dates.map(date => `
                  <th class="vertical-header" id="day${date.getDay() + 1}">
                    <span class="day-name">${days[date.getDay()]}</span>
                    <span class="date">${formatDate(date)}</span>
                  </th>
                `).join('')}
              </tr>
            </thead>
            <tbody>
              <tr>
                <td class="align-middle text-center bg-am fw-bold">AM</td>
                ${dates.map(date => {
                  const dateStr = scheduleDate(date);
                  let amCount = 0;
                  employees.forEach(emp => {
                    const key = `${emp.id}_${dateStr}`;
                    const cellData = getEmployeeCell(emp.id, dateStr);
                    if (normalizeScheduleType(cellData) === 'am') amCount++;
                  });
                  return `<td class="text-center time-am time-cell bg-am">${amCount}</td>`;
                }).join('')}
              </tr>
              <tr>
                <td class="align-middle text-center bg-pm fw-bold">PM</td>
                ${dates.map(date => {
                  const dateStr = scheduleDate(date);
                  let pmCount = 0;
                  employees.forEach(emp => {
                    const key = `${emp.id}_${dateStr}`;
                    const cellData = getEmployeeCell(emp.id, dateStr);
                    if (normalizeScheduleType(cellData) === 'pm') pmCount++;
                  });
                  return `<td class="text-center time-pm time-cell bg-pm">${pmCount}</td>`;
                }).join('')}
              </tr>
              <tr>
                <td class="align-middle text-center bg-leave fw-bold">أجازه</td>
                ${dates.map(date => {
                  const dateStr = scheduleDate(date);
                  let leaveCount = 0;
                  employees.forEach(emp => {
                    const key = `${emp.id}_${dateStr}`;
                    const cellData = getEmployeeCell(emp.id, dateStr);
                    if (normalizeScheduleType(cellData) === 'leave') leaveCount++;
                  });
                  return `<td class="text-center leave time-cell bg-leave">${leaveCount}</td>`;
                }).join('')}
              </tr>
              <tr>
                <td class="align-middle text-center bg-note fw-bold">ملاحظه</td>
                ${dates.map(date => {
                  const dateStr = scheduleDate(date);
                  let noteCount = 0;
                  employees.forEach(emp => {
                    const key = `${emp.id}_${dateStr}`;
                    const cellData = getEmployeeCell(emp.id, dateStr);
                    if (normalizeScheduleType(cellData) === 'note') noteCount++;
                  });
                  return `<td class="text-center note time-cell bg-note">${noteCount}</td>`;
                }).join('')}
              </tr>
            </tbody>
          </table>
        `;
        container.innerHTML += timeDistributionHtml;
      } catch (empSingleError) {
        console.error("خطأ في الموظفين (أسبوع واحد):", empSingleError);
      }
    }

    // رندرة المشرفين (فقط إذا كانت الحاوية موجودة في الـ HTML والمصفوفة معرّفة)
    if (supervisorsContainer && typeof supervisors !== 'undefined' && supervisors) {
      try {
        let supervisorsHtml = `
          <table class="table table-bordered table-hover">
            <thead class="table-primary">
              <tr>
                <th class="align-middle text-center table-primary">المشرف</th>
                ${dates.map(date => `
                  <th class="vertical-header" id="supervisor-day${selectedWeek}-${date.getDay()}">
                    <span class="day-name">${days[date.getDay()]}</span>
                    <span class="date">${formatDate(date)}</span>
                  </th>
                `).join('')}
              </tr>
            </thead>
            <tbody>
              ${supervisors.map(sup => `
                <tr>
                  <td class="align-middle text-center table-primary fw-bold">${sup.name}</td>
                  ${dates.map(date => {
                    const dateStr = scheduleDate(date);
                    const cellData = supervisorsData[`${sup.id}_${dateStr}`] || {};
                    let cellClass = 'align-middle text-center';
                    cellClass += getScheduleCellClass(cellData);
                    return `<td class="${cellClass}" style="cursor: pointer" data-date="${date.toISOString()}" data-supervisor="${sup.name}" data-supervisorid="${sup.id}">${cellData.status || ''}</td>`;
                  }).join('')}
                </tr>
              `).join('')}
            </tbody>
          </table>
        `;
        supervisorsContainer.innerHTML = supervisorsHtml;
      } catch (supSingleError) {
        console.error("خطأ في المشرفين (أسبوع واحد):", supSingleError);
      }
    }

    if (typeof updateTimeDistributionTable === "function") {
      try { updateTimeDistributionTable(); } catch(e) {}
    }
      
    if (downloadBtn) downloadBtn.style.display = 'none';
  }

  // إضافة مستمعي الأحداث للعناصر التي تمت رندرتها
  document.querySelectorAll("#scheduleTableContainer td[data-date]").forEach(cell => {
    cell.addEventListener("click", e => showDropdown(e, cell, 'employee'));
  });

  document.querySelectorAll("#supervisorsTableContainer td[data-date]").forEach(cell => {
    cell.addEventListener("click", e => showDropdown(e, cell, 'supervisor'));
  });

  // [المنطق الأصلي لك] هنا يتم التعامل مع خلايا "عرض الكل" كـ employee دائماً كما أردت
  if (selectedWeek === 'all') {
    document.querySelectorAll(".week-container td[data-date]").forEach(cell => {
      cell.addEventListener("click", e => showDropdown(e, cell, 'employee'));
    });
  }

  // إعداد خيارات التحميل والـ PDF بآلية حماية مرنة
  const monthSelect = document.getElementById('monthSelect');
  const monthName = monthSelect ? monthSelect.options[monthSelect.selectedIndex].text : 'الشرح الحالي';
  const weekText = selectedWeek === 'all' ? 'جميع الأسابيع' : `الأسبوع ${parseInt(selectedWeek) + 1}`;
    
  if (typeof downloadBtn !== 'undefined' && downloadBtn) {
    downloadBtn.onclick = () => {
      const tempContainer = document.createElement('div');
      tempContainer.style.padding = '20px';
      
      if (container) {
        const employeesTable = container.cloneNode(true);
        employeesTable.querySelectorAll('th.vertical-header').forEach(th => {
          const dayNameSpan = th.querySelector('.day-name');
          const dateSpan = th.querySelector('.date');
          if (dayNameSpan && dateSpan) {
            th.innerHTML = `<div class="d-flex flex-column align-items-center"><span class="pdf-date">${dateSpan.textContent}</span><span class="pdf-day-name">${dayNameSpan.textContent}</span></div>`;
          }
          const pdfDateSpan = th.querySelector('.pdf-date');
          if (pdfDateSpan) {
            const parentThId = th.id;
            const parts = parentThId.split('-');
            if (parts.length >= 3) {
              const dayIndex = parseInt(parts[parts.length - 1]);
              let weekIndex = 0;
              if (parentThId.startsWith('employee-day')) {
                weekIndex = parseInt(parts[0].replace('employee-day', ''));
              }
              if (!isNaN(dayIndex) && !isNaN(weekIndex)) {
                const dates = getWeekDates(selectedMonth, weekIndex);
                if (dates && dates[dayIndex]) pdfDateSpan.textContent = String(dates[dayIndex].getDate()).padStart(2, '0');
              }
            }
          }
        });
        tempContainer.appendChild(employeesTable);
      }
      
      if (supervisorsContainer && supervisorsContainer.innerHTML.trim() !== '') {
        const supervisorsTable = supervisorsContainer.cloneNode(true);
        supervisorsTable.querySelectorAll('th.vertical-header').forEach(th => {
          const dayNameSpan = th.querySelector('.day-name');
          const dateSpan = th.querySelector('.date');
          if (dayNameSpan && dateSpan) {
            th.innerHTML = `<div class="d-flex flex-column align-items-center"><span class="pdf-date">${dateSpan.textContent}</span><span class="pdf-day-name">${dayNameSpan.textContent}</span></div>`;
          }
          const pdfDateSpan = th.querySelector('.pdf-date');
          if (pdfDateSpan) {
            const parentThId = th.id;
            const parts = parentThId.split('-');
            if (parts.length >= 3) {
              const dayIndex = parseInt(parts[parts.length - 1]);
              let weekIndex = 0;
              if (parentThId.startsWith('supervisor-day')) {
                weekIndex = parseInt(parts[0].replace('supervisor-day', ''));
              }
              if (!isNaN(dayIndex) && !isNaN(weekIndex)) {
                const dates = getWeekDates(selectedMonth, weekIndex);
                if (dates && dates[dayIndex]) pdfDateSpan.textContent = String(dates[dayIndex].getDate()).padStart(2, '0');
              }
            }
          }
        });
        tempContainer.appendChild(supervisorsTable);
      }
      
      const timeDistributionTable = document.querySelector('.table-responsive:has(#timeDistributionTable)');
      if (timeDistributionTable && selectedWeek !== 'all') {
        const timeTable = timeDistributionTable.cloneNode(true);
        timeTable.querySelectorAll('th[id^="day"]').forEach(th => {
          const dayNameSpan = th.querySelector('.day-name');
          const dateSpan = th.querySelector('.date');
           if (dayNameSpan && dateSpan) {
            th.innerHTML = `<div class="d-flex flex-column align-items-center"><span class="pdf-date">${dateSpan.textContent}</span><span class="pdf-day-name">${dayNameSpan.textContent}</span></div>`;
          }
          const pdfDateSpan = th.querySelector('.pdf-date');
          if (pdfDateSpan) {
            const parentThId = th.id;
            const dayIndex = parseInt(parentThId.replace('day', '')) - 1;
            if (!isNaN(dayIndex)) {
              const dates = getWeekDates(selectedMonth, selectedWeek);
              if (dates && dates[dayIndex]) pdfDateSpan.textContent = String(dates[dayIndex].getDate()).padStart(2, '0');
            }
          }
        });
        tempContainer.appendChild(timeTable);
      }

      const opt = {
        margin: 1,
        filename: `جدول_الدوام_${monthName}_${weekText}.pdf`,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2 },
        jsPDF: { unit: 'cm', format: 'a4', orientation: 'landscape' }
      };

      if (typeof html2pdf !== 'undefined') {
        html2pdf().set(opt).from(tempContainer).save();
      }
    };
  }
};

function showDropdown(event, cell, type) {
  event.stopPropagation();
  currentCell = cell;
  currentCellType = type; // إضافة نوع الخلية (موظف أو مشرف)
  const dropdown = document.getElementById("dropdownMenu");
  const cellRect = cell.getBoundingClientRect();

  dropdown.style.display = "block";
  dropdown.style.position = "fixed";
  dropdown.style.zIndex = "1050";

  const margin = 8;
  const dropdownWidth = dropdown.offsetWidth || 160;
  const left = Math.max(
    margin,
    Math.min(cellRect.right - dropdownWidth, window.innerWidth - dropdownWidth - margin)
  );

  dropdown.style.top = `${cellRect.bottom + 4}px`;
  dropdown.style.left = `${left}px`;
}

function makeCellEditable(cell) {
  // إنشاء حقل إدخال
  const input = document.createElement('input');
  let isSaved = false;
  input.type = 'text';
  input.className = 'form-control form-control-sm text-center';
  input.style.width = '100%';
  input.style.height = '100%';
  input.style.padding = '0';
  input.style.margin = '0';
  input.style.border = '1px solid #dee2e6';
  input.style.borderRadius = '8px';
  input.style.fontSize = '14px';
  input.style.background = 'white';
  input.style.position = 'absolute';
  input.style.top = '0';
  input.style.left = '0';
  input.style.right = '0';
  input.style.bottom = '0';
  input.style.boxShadow = '0 0 0 2px rgba(0,123,255,0.25)';
  input.style.textAlign = 'center';
  input.dir = 'auto';
  input.value = cell.textContent.trim();
  input.placeholder = 'اكتب ملاحظه';
  
  // جعل الخلية relative لتحديد موقع حقل الإدخال
  cell.style.position = 'relative';
  
  function validateAndUpdate() {
    if (isSaved) return;
    isSaved = true;
    const value = input.value.trim();
    if (value) {
      updateCell(cell, value, 'note');
    } else {
      clearCell(cell);
    }
    input.remove();
    currentCell = null;
  }
  
  // إضافة مستمعي الأحداث
  input.addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      validateAndUpdate();
    }
  });
  
  input.addEventListener('blur', function() {
    validateAndUpdate();
  });
  
  // إعادة تعيين لون الإطار عند الكتابة
  input.addEventListener('input', function() {
    input.style.border = '1px solid #dee2e6';
    input.style.boxShadow = '0 0 0 2px rgba(0,123,255,0.25)';
  });
  
  // تفريغ الخلية وإضافة حقل الإدخال
  cell.textContent = '';
  cell.appendChild(input);
  input.focus();
}

function updateCell(cell, status, cellType) {
  const type = cell.dataset.supervisor ? 'supervisor' : 'employee';
  const name = cell.dataset.supervisor || cell.dataset.employee;
  const id = cell.dataset.supervisorid || cell.dataset.employeeid;
  const date = new Date(cell.dataset.date);
  const dateStr = scheduleDate(date);
  const key = `${id}_${dateStr}`;

  // تحديث البيانات
  if (type === 'supervisor') {
    supervisorsData[key] = { status, type: cellType };
    saveSupervisorsData();
  } else {
    const entry = { status, type: cellType };
    const matching = matchingEmployeeLeaves(id, dateStr);
    if (matching.length) {
      employeeLeaveOverrides[key] = { ...entry, signature: leaveSignature(matching) };
    } else {
      scheduleData[key] = entry;
    }
    saveScheduleData(key, entry);
  }

  // إعادة عرض الجداول مباشرة
  renderTable();
}

function clearCell(cell) {
  const type = cell.dataset.supervisor ? 'supervisor' : 'employee';
  const id = cell.dataset.supervisorid || cell.dataset.employeeid;
  const date = new Date(cell.dataset.date);
  const dateStr = scheduleDate(date);
  const key = `${id}_${dateStr}`;

  if (type === 'employee' && matchingEmployeeLeaves(id, dateStr).length) {
    updateCell(cell, '', '');
    return;
  }

  if (type === 'supervisor') {
    delete supervisorsData[key];
    saveSupervisorsData();
  } else {
    delete scheduleData[key];
    saveScheduleData();
  }

  renderTable();
}

function selectOption(type) {
  if (!currentCell) return;
  let status = "";
  let cellType = "";

  switch (type) {
    case "note":
      document.getElementById("dropdownMenu").style.display = "none";
      makeCellEditable(currentCell);
      return;
    case "am":
      status = "AM";
      cellType = "am";
      break;
    case "pm":
      status = "PM";
      cellType = "pm";
      break;
    case "leave":
      status = "أجازه";
      cellType = "leave";
      break;
    default:
      return;
  }

  updateCell(currentCell, status, cellType);

  document.getElementById("dropdownMenu").style.display = "none";
  currentCell = null;
}

function addEmployee() {
  // إظهار الـ modal
  const modal = new bootstrap.Modal(document.getElementById('addEmployeeModal'));
  modal.show();
  
  // تفريغ حقل الإدخال عند فتح الـ modal
  document.getElementById('employeeName').value = '';
  
  // إضافة مستمع حدث للـ Enter
  document.getElementById('employeeName').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
      saveNewEmployee();
    }
  });
}

function saveNewEmployee() {
  const nameInput = document.getElementById('employeeName');
  const name = nameInput.value.trim();
  
  if (name) {
    // إضافة الموظف وحفظ البيانات
    employees.push(name);
    localStorage.setItem('employees', JSON.stringify(employees));
    
    // إغلاق المودال وتنظيف جميع التأثيرات
    const modalElement = document.getElementById('addEmployeeModal');
    modalElement.style.display = 'none';
    modalElement.classList.remove('show');
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    const backdrop = document.querySelector('.modal-backdrop');
    if (backdrop) {
      backdrop.remove();
    }
    
    // تحديث الجدول وتفريغ حقل الإدخال
    renderTable();
    nameInput.value = '';
  }
}

// إضافة event listeners عند تحميل الصفحة
document.addEventListener('DOMContentLoaded', () => {
  // إنشاء زر التحميل
  downloadBtn = document.createElement('button');
  downloadBtn.className = 'btn btn-primary ms-2';
  downloadBtn.innerHTML = '<i class="fas fa-download"></i> تحميل PDF';
  
  // إضافة الزر بجانب زر إضافة موظف
  const addEmployeeBtn = document.querySelector('.btn-primary');
  addEmployeeBtn.parentNode.insertBefore(downloadBtn, addEmployeeBtn.nextSibling);

  document.addEventListener("click", () => {
    document.getElementById("dropdownMenu").style.display = "none";
    currentCell = null;
  });

  // إزالة الكلاس active من جميع الأزرار أولاً
  document.querySelectorAll(".week-btn").forEach(btn => {
    btn.classList.remove("active");
  });

  // تعيين القيم المحفوظة في القائمة المنسدلة والأزرار
  document.getElementById("monthSelect").value = selectedMonth;
  document.getElementById("yearSelect").value = selectedYear;
  
  // تفعيل الزر المحدد فقط
  const activeButton = document.querySelector(`.week-btn[data-week="${selectedWeek}"]`);
  if (activeButton) {
    activeButton.classList.add("active");
  }

  document.getElementById("monthSelect").addEventListener("change", (e) => {
    selectedMonth = parseInt(e.target.value);
    saveSelectedMonthAndWeek();
    // التحقق من وجود الأسبوع الخامس عند تغيير الشهر
    const hasFifthWeek = checkFifthWeekExists(selectedMonth);
    const fifthWeekBtn = document.querySelector('.week-btn[data-week="4"]');
    if (fifthWeekBtn) {
      if (!hasFifthWeek) {
        fifthWeekBtn.style.display = 'none';
        // إذا كان الأسبوع المحدد هو الخامس، نعود للأسبوع الأول
        if (selectedWeek === '4') {
          selectedWeek = '0';
          // إزالة الكلاس active من جميع الأزرار
          document.querySelectorAll(".week-btn").forEach(btn => btn.classList.remove("active"));
          // تفعيل زر الأسبوع الأول
          document.querySelector('.week-btn[data-week="0"]').classList.add("active");
        }
      } else {
        fifthWeekBtn.style.display = 'inline-block';
      }
    }
    renderTable();
  });

  document.getElementById("yearSelect").addEventListener("change", (e) => {
    selectedYear = parseInt(e.target.value);
    saveSelectedMonthAndWeek();
    renderTable();
  });

  document.querySelectorAll(".week-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      // إزالة الكلاس active من جميع الأزرار
      document.querySelectorAll(".week-btn").forEach(b => b.classList.remove("active"));
      // تفعيل الزر المحدد فقط
      btn.classList.add("active");
      selectedWeek = btn.dataset.week;
      saveSelectedMonthAndWeek();
      renderTable();
    });
  });

  // إضافة السنوات بشكل ديناميكي
  const yearSelect = document.getElementById('yearSelect');
  const currentYear = new Date().getFullYear();
  yearSelect.innerHTML = ''; // مسح الخيارات الموجودة
  
  for (let year = currentYear; year <= 2030; year++) {
    const option = document.createElement('option');
    option.value = year;
    option.textContent = year;
    option.selected = (year === selectedYear);
    yearSelect.appendChild(option);
  }

  // تحميل الجدول بعد وصول بيانات الموظفين والجداول من السيرفر
  loadTimetableData().always(() => {
    renderTable();
  });

});

// دالة لحفظ الشهر والأسبوع المحدد
function saveSelectedMonthAndWeek() {
  localStorage.setItem('selectedMonth', selectedMonth);
  localStorage.setItem('selectedYear', selectedYear);
  localStorage.setItem('selectedWeek', selectedWeek);
}

// دالة لتحديث جدول توزيع الوقت
function updateTimeDistributionTable() {
  const dates = getWeekDates(selectedMonth, selectedWeek);
  
  // التحقق من وجود جدول توزيع الوقت
  const timeDistributionTable = document.getElementById('timeDistributionTable');
  if (!timeDistributionTable) return;
  
  // تحديث عناوين الأيام في الجدول
  dates.forEach((date, dayIndex) => {
    const dayHeader = document.getElementById(`day${dayIndex + 1}`);
    if (dayHeader) {
      dayHeader.innerHTML = `
        <span class="day-name">${days[date.getDay()]}</span>
        <span class="date">${formatDate(date)}</span>
      `;
    }
  });

  // إعادة تعيين العدادات
  const amCells = document.querySelectorAll('.time-am');
  const pmCells = document.querySelectorAll('.time-pm');
  const leaveCells = document.querySelectorAll('.leave');
  const noteCells = document.querySelectorAll('.note');

  // التحقق من وجود الخلايا قبل تحديثها
  if (amCells.length === 0 || pmCells.length === 0 ||
      leaveCells.length === 0 || noteCells.length === 0) return;

  // إعادة تعيين الألوان والعدادات
  amCells.forEach(cell => {
    if (cell) {
      cell.textContent = '0';
      cell.className = 'text-center time-am bg-am';
    }
  });
  pmCells.forEach(cell => {
    if (cell) {
      cell.textContent = '0';
      cell.className = 'text-center time-pm bg-pm';
    }
  });
  leaveCells.forEach(cell => {
    if (cell) {
      cell.textContent = '0';
      cell.className = 'text-center leave bg-leave';
    }
  });
  noteCells.forEach(cell => {
    if (cell) {
      cell.textContent = '0';
      cell.className = 'text-center note bg-note';
    }
  });

  // حساب التوزيع لكل يوم
  dates.forEach((date, dayIndex) => {
    const dateStr = scheduleDate(date);
    let amCount = 0;
    let pmCount = 0;
    let leaveCount = 0;
    let noteCount = 0;

    employees.forEach(emp => {
      const key = `${emp.id}_${dateStr}`;
      const cellData = getEmployeeCell(emp.id, dateStr);
      
      switch(normalizeScheduleType(cellData)) {
        case 'am':
          amCount++;
          break;
        case 'pm':
          pmCount++;
          break;
        case 'leave':
          leaveCount++;
          break;
        case 'note':
          noteCount++;
          break;
      }
    });

    // تحديث الخلايا في جدول التوزيع مع التحقق من وجودها
    if (amCells[dayIndex]) amCells[dayIndex].textContent = amCount;
    if (pmCells[dayIndex]) pmCells[dayIndex].textContent = pmCount;
    if (leaveCells[dayIndex]) leaveCells[dayIndex].textContent = leaveCount;
    if (noteCells[dayIndex]) noteCells[dayIndex].textContent = noteCount;
  });
}

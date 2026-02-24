/**
 * datepicker.js — Custom date picker (polski)
 *
 * Wymaga w HTML:
 *   - #datepickerOverlay  (overlay)
 *   - #dpGrid             (siatka dni)
 *   - #dpMonthSelect      (select miesiąca)
 *   - #dpYearSelect       (select roku)
 *   - #dpConfirmBtn       (przycisk zatwierdzenia)
 *   - #date_of_birth      (hidden input — YYYY-MM-DD)
 *   - #date_of_birth_display (display input — DD.MM.YYYY)
 */

const MONTHS_PL = [
    'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
    'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'
];

const _dpToday = new Date();
_dpToday.setHours(0, 0, 0, 0);

const dpState = {
    viewYear: _dpToday.getFullYear(),
    viewMonth: _dpToday.getMonth(),
    selectedDate: new Date(_dpToday)
};

// Wypełnij pole datą dzisiejszą od razu
(function () {
    const d = _dpToday;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    const hidden = document.getElementById('date_of_birth');
    const display = document.getElementById('date_of_birth_display');
    if (hidden) hidden.value = `${yyyy}-${mm}-${dd}`;
    if (display) display.value = `${dd}.${mm}.${yyyy}`;
})();

function openDatePicker() {
    if (dpState.selectedDate) {
        dpState.viewYear = dpState.selectedDate.getFullYear();
        dpState.viewMonth = dpState.selectedDate.getMonth();
    }
    buildSelects();
    renderGrid();
    document.getElementById('datepickerOverlay').classList.add('open');
}

function closeDatePicker() {
    document.getElementById('datepickerOverlay').classList.remove('open');
}

function overlayClick(e) {
    if (e.target === document.getElementById('datepickerOverlay')) closeDatePicker();
}

function buildSelects() {
    const mSel = document.getElementById('dpMonthSelect');
    mSel.innerHTML = MONTHS_PL.map((m, i) =>
        `<option value="${i}" ${i === dpState.viewMonth ? 'selected' : ''}>${m}</option>`
    ).join('');

    const ySel = document.getElementById('dpYearSelect');
    const minYear = _dpToday.getFullYear() - 120;
    const maxYear = _dpToday.getFullYear();
    let yHtml = '';
    for (let y = maxYear; y >= minYear; y--) {
        yHtml += `<option value="${y}" ${y === dpState.viewYear ? 'selected' : ''}>${y}</option>`;
    }
    ySel.innerHTML = yHtml;
}

function dpMonthChanged() {
    dpState.viewMonth = parseInt(document.getElementById('dpMonthSelect').value);
    renderGrid();
}

function dpYearChanged() {
    dpState.viewYear = parseInt(document.getElementById('dpYearSelect').value);
    renderGrid();
}

function dpChangeMonth(delta) {
    dpState.viewMonth += delta;
    if (dpState.viewMonth > 11) { dpState.viewMonth = 0; dpState.viewYear++; }
    if (dpState.viewMonth < 0) { dpState.viewMonth = 11; dpState.viewYear--; }
    buildSelects();
    renderGrid();
}

function renderGrid() {
    const grid = document.getElementById('dpGrid');
    grid.innerHTML = '';

    const firstDay = new Date(dpState.viewYear, dpState.viewMonth, 1);
    let startDow = firstDay.getDay() - 1;
    if (startDow < 0) startDow = 6;

    const daysInMonth = new Date(dpState.viewYear, dpState.viewMonth + 1, 0).getDate();

    for (let i = 0; i < startDow; i++) {
        const empty = document.createElement('div');
        empty.className = 'datepicker-day empty';
        grid.appendChild(empty);
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(dpState.viewYear, dpState.viewMonth, d);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'datepicker-day';
        btn.textContent = d;

        if (date > _dpToday) {
            btn.classList.add('disabled');
        } else {
            if (date.toDateString() === _dpToday.toDateString()) btn.classList.add('today');
            if (dpState.selectedDate && date.toDateString() === dpState.selectedDate.toDateString()) {
                btn.classList.add('selected');
            }
            btn.addEventListener('click', () => selectDay(date));
        }
        grid.appendChild(btn);
    }
}

function selectDay(date) {
    dpState.selectedDate = date;
    document.getElementById('dpConfirmBtn').disabled = false;
    renderGrid();
}

function confirmDate() {
    if (!dpState.selectedDate) return;
    const d = dpState.selectedDate;
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    const dd = String(d.getDate()).padStart(2, '0');
    document.getElementById('date_of_birth').value = `${yyyy}-${mm}-${dd}`;
    document.getElementById('date_of_birth_display').value = `${dd}.${mm}.${yyyy}`;
    closeDatePicker();
}

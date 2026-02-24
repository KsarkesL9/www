/**
 * @file datepicker.js
 * @brief Custom date picker component with Polish month names.
 *
 * @details This file provides a date picker that opens as an
 *          overlay on the page. The user can pick a year,
 *          a month, and a day. Future dates are blocked.
 *          The selected date is saved in two HTML inputs:
 *          one hidden input in YYYY-MM-DD format for the server,
 *          and one visible display input in DD.MM.YYYY format.
 *
 * Required HTML elements:
 *   - #datepickerOverlay      (the overlay container element)
 *   - #dpGrid                 (the grid of day buttons)
 *   - #dpMonthSelect          (month drop-down select)
 *   - #dpYearSelect           (year drop-down select)
 *   - #dpConfirmBtn           (confirm / accept button)
 *   - #date_of_birth          (hidden input, stores YYYY-MM-DD)
 *   - #date_of_birth_display  (visible input, shows DD.MM.YYYY)
 */

/** @brief Array of Polish month names, index 0 = January, 11 = December. */
const MONTHS_PL = [
    'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
    'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'
];

/** @brief Today's date with time set to midnight. Used to block future dates. */
const _dpToday = new Date();
_dpToday.setHours(0, 0, 0, 0);

/**
 * @brief Internal state object for the date picker.
 *
 * @details Stores the year and month currently shown in the
 *          calendar view, and the date the user has chosen.
 *
 * @property {number} viewYear     The year shown in the calendar.
 * @property {number} viewMonth    The month (0-based) shown in the calendar.
 * @property {Date}   selectedDate The date the user has selected.
 */
const dpState = {
    viewYear: _dpToday.getFullYear(),
    viewMonth: _dpToday.getMonth(),
    selectedDate: new Date(_dpToday)
};

/**
 * @brief Sets today's date as the default value in the form inputs.
 *
 * @details This is a self-calling function (IIFE) that runs once
 *          immediately when the script loads. It formats today's
 *          date as YYYY-MM-DD for the hidden input (#date_of_birth)
 *          and as DD.MM.YYYY for the visible display input
 *          (#date_of_birth_display). If either element does not
 *          exist on the page, it is simply skipped.
 *
 * @returns {void}
 */
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

/**
 * @brief Opens the date picker overlay and shows the current selection.
 *
 * @details If a date is already selected, the function sets the
 *          calendar view to show that date's year and month.
 *          Then it rebuilds the month and year drop-down lists,
 *          redraws the day grid, and adds the CSS class 'open'
 *          to the overlay element (#datepickerOverlay) to make
 *          it visible on the page.
 *
 * @returns {void}
 *
 * @see closeDatePicker
 * @see buildSelects
 * @see renderGrid
 */
function openDatePicker() {
    if (dpState.selectedDate) {
        dpState.viewYear = dpState.selectedDate.getFullYear();
        dpState.viewMonth = dpState.selectedDate.getMonth();
    }
    buildSelects();
    renderGrid();
    document.getElementById('datepickerOverlay').classList.add('open');
}

/**
 * @brief Closes the date picker overlay.
 *
 * @details Removes the CSS class 'open' from the overlay element
 *          (#datepickerOverlay), which hides the date picker.
 *
 * @returns {void}
 *
 * @see openDatePicker
 */
function closeDatePicker() {
    document.getElementById('datepickerOverlay').classList.remove('open');
}

/**
 * @brief Closes the date picker when the user clicks on the background overlay.
 *
 * @details Checks if the click target is exactly the overlay element
 *          itself, not a child element inside it. If yes, calls
 *          closeDatePicker() to close the calendar. This lets the
 *          user close the picker by clicking outside the calendar box.
 *
 * @param {MouseEvent} e  The mouse click event from the browser.
 *
 * @returns {void}
 *
 * @see closeDatePicker
 */
function overlayClick(e) {
    if (e.target === document.getElementById('datepickerOverlay')) closeDatePicker();
}

/**
 * @brief Builds the month and year drop-down select elements.
 *
 * @details Fills the month select (#dpMonthSelect) with all 12 Polish
 *          month names and marks the current view month as selected.
 *          Fills the year select (#dpYearSelect) with years counting
 *          down from the current year to 120 years ago. Marks the
 *          current view year as selected.
 *
 * @returns {void}
 *
 * @see dpMonthChanged
 * @see dpYearChanged
 */
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

/**
 * @brief Updates the view month when the user picks a different month.
 *
 * @details Reads the selected value from the month drop-down
 *          (#dpMonthSelect), converts it to an integer, and saves
 *          it in dpState.viewMonth. Then redraws the day grid
 *          to show the days for the new month.
 *
 * @returns {void}
 *
 * @see renderGrid
 */
function dpMonthChanged() {
    dpState.viewMonth = parseInt(document.getElementById('dpMonthSelect').value);
    renderGrid();
}

/**
 * @brief Updates the view year when the user picks a different year.
 *
 * @details Reads the selected value from the year drop-down
 *          (#dpYearSelect), converts it to an integer, and saves
 *          it in dpState.viewYear. Then redraws the day grid
 *          to show the days for the new year.
 *
 * @returns {void}
 *
 * @see renderGrid
 */
function dpYearChanged() {
    dpState.viewYear = parseInt(document.getElementById('dpYearSelect').value);
    renderGrid();
}

/**
 * @brief Moves the calendar view forward or backward by one month.
 *
 * @details Adds the delta value to dpState.viewMonth. If the month
 *          goes past December (11), it resets to January (0) and
 *          increases the year by one. If the month goes before
 *          January (0), it resets to December (11) and decreases
 *          the year by one. Then rebuilds the select elements and
 *          redraws the day grid.
 *
 * @param {number} delta
 *        Use 1 to go to the next month, or -1 to go to the previous month.
 *
 * @returns {void}
 *
 * @see buildSelects
 * @see renderGrid
 */
function dpChangeMonth(delta) {
    dpState.viewMonth += delta;
    if (dpState.viewMonth > 11) { dpState.viewMonth = 0; dpState.viewYear++; }
    if (dpState.viewMonth < 0) { dpState.viewMonth = 11; dpState.viewYear--; }
    buildSelects();
    renderGrid();
}

/**
 * @brief Renders the grid of day buttons for the current view month and year.
 *
 * @details Clears the grid element (#dpGrid) and fills it with buttons.
 *          First adds empty placeholder cells for the days before the
 *          first day of the month so that Monday is always the first column.
 *          Then adds one button for each day in the month.
 *
 *          Buttons for future dates (after today) get the 'disabled' CSS
 *          class and have no click handler attached to them.
 *          Today's date gets the 'today' CSS class.
 *          The currently selected date gets the 'selected' CSS class.
 *          Clicking a valid (non-future) day button calls selectDay()
 *          with that date object.
 *
 * @returns {void}
 *
 * @see selectDay
 */
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

/**
 * @brief Saves the clicked date as the selected date and refreshes the grid.
 *
 * @details Stores the given Date object in dpState.selectedDate.
 *          Enables the confirm button (#dpConfirmBtn) so the user
 *          can click it to accept the selection. Then redraws
 *          the grid to show the new selection with the 'selected' class.
 *
 * @param {Date} date
 *        The Date object that the user clicked in the calendar grid.
 *
 * @returns {void}
 *
 * @see confirmDate
 * @see renderGrid
 */
function selectDay(date) {
    dpState.selectedDate = date;
    document.getElementById('dpConfirmBtn').disabled = false;
    renderGrid();
}

/**
 * @brief Confirms the selected date and writes it to the form inputs.
 *
 * @details Does nothing if no date is currently selected.
 *          Formats the selected date as YYYY-MM-DD and writes it
 *          to the hidden input (#date_of_birth) for form submission.
 *          Formats the same date as DD.MM.YYYY and writes it to
 *          the display input (#date_of_birth_display) for the user.
 *          Then closes the date picker overlay.
 *
 * @returns {void}
 *
 * @see selectDay
 * @see closeDatePicker
 */
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

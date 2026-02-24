/**
 * @file password.js
 * @brief Password strength indicator.
 *
 * @details This file provides one function that checks how
 *          strong a password is and updates a visual bar
 *          and a text label on the page to show the result.
 *
 * Functions:
 *   - checkPasswordStrength(pw, fillId?, labelId?)
 */

/**
 * @brief Checks how strong a password is and updates a visual bar and label.
 *
 * @details The function gives the password a score from 0 to 5.
 *          Each of the following rules adds 1 point to the score:
 *          - The password is at least 8 characters long.
 *          - The password is at least 12 characters long.
 *          - The password has at least one uppercase letter (A-Z).
 *          - The password has at least one digit (0-9).
 *          - The password has at least one special character
 *            (anything that is not a letter or digit).
 *
 *          The score is mapped to five strength levels:
 *          - Score 1: Very weak  (red,         20% bar width).
 *          - Score 2: Weak       (orange,       40% bar width).
 *          - Score 3: Medium     (yellow,       60% bar width).
 *          - Score 4: Strong     (light green,  80% bar width).
 *          - Score 5: Very strong (green,       100% bar width).
 *
 *          The function finds the bar fill element and the label
 *          element by their IDs. It sets the width and background
 *          color of the bar, and the text and color of the label.
 *          If an element is not found on the page, that part
 *          is simply skipped without an error.
 *
 * @param {string} pw
 *        The password string to check.
 * @param {string} [fillId='pwFill']
 *        The ID of the bar fill element that shows the strength visually.
 *        Default value is 'pwFill'.
 * @param {string} [labelId='pwLabel']
 *        The ID of the text label that shows the strength name.
 *        Default value is 'pwLabel'.
 *
 * @returns {void}
 */
function checkPasswordStrength(pw, fillId = 'pwFill', labelId = 'pwLabel') {
    let score = 0;
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    const levels = [
        { w: '20%', c: '#f87171', l: 'Bardzo słabe' },
        { w: '40%', c: '#fb923c', l: 'Słabe' },
        { w: '60%', c: '#facc15', l: 'Średnie' },
        { w: '80%', c: '#a3e635', l: 'Mocne' },
        { w: '100%', c: '#34d399', l: 'Bardzo mocne' },
    ];
    const lv = levels[Math.min(score, 4)];

    const fill = document.getElementById(fillId);
    if (fill) {
        fill.style.width = lv.w;
        fill.style.background = lv.c;
    }

    const label = document.getElementById(labelId);
    if (label) {
        label.textContent = lv.l;
        label.style.color = lv.c;
    }
}

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
 * @details The function evaluates the password using a restrictive model:
 *          - Very strong (score 4): length >= 12 and 4 different character types.
 *          - Strong      (score 3): length >= 10 and at least 3 character types.
 *          - Medium      (score 2): length >= 8 and at least 2 character types.
 *          - Weak        (score 1): length >= 6.
 *          - Very weak   (score 0): length < 6 or empty string.
 *
 *          Character types included: lowercase, uppercase, digits, symbols.
 *
 *          The score is mapped to five strength levels:
 *          - Score 0: Very weak  (red,         20% bar width).
 *          - Score 1: Weak       (orange,       40% bar width).
 *          - Score 2: Medium     (yellow,       60% bar width).
 *          - Score 3: Strong     (light green,  80% bar width).
 *          - Score 4: Very strong (green,       100% bar width).
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

    if (pw.length > 0) {
        let types = 0;
        if (/[a-z]/.test(pw)) types++;
        if (/[A-Z]/.test(pw)) types++;
        if (/[0-9]/.test(pw)) types++;
        if (/[^A-Za-z0-9]/.test(pw)) types++;

        if (pw.length >= 12 && types === 4) {
            score = 4;
        } else if (pw.length >= 10 && types >= 3) {
            score = 3;
        } else if (pw.length >= 8 && types >= 2) {
            score = 2;
        } else if (pw.length >= 6) {
            score = 1;
        } else {
            score = 0;
        }
    }

    const levels = [
        { w: '20%', c: '#f87171', l: 'Bardzo słabe' },
        { w: '40%', c: '#fb923c', l: 'Słabe' },
        { w: '60%', c: '#facc15', l: 'Średnie' },
        { w: '80%', c: '#a3e635', l: 'Mocne' },
        { w: '100%', c: '#34d399', l: 'Bardzo mocne' },
    ];
    const lv = levels[score];

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

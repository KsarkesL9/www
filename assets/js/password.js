/**
 * password.js — Wskaźnik siły hasła
 *
 * Funkcje:
 *   checkPasswordStrength(pw, fillId?, labelId?)
 */

/**
 * Oblicza siłę hasła i aktualizuje pasek wizualny
 * @param {string} pw             — wartość hasła
 * @param {string} [fillId='pwFill']   — id paska wypełnienia
 * @param {string} [labelId='pwLabel'] — id etykiety (może nie istnieć)
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

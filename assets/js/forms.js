/**
 * forms.js — Helpery formularzy
 *
 * Funkcje:
 *   togglePw(id)  — pokaż/ukryj hasło
 *   escHtml(str)  — escape HTML entities
 */

/**
 * Przełącza widoczność hasła w polu input
 * @param {string} id — id elementu input[type=password]
 */
function togglePw(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
}

/**
 * Escapuje znaki HTML, zapobiega XSS przy wstawianiu tekstu do DOM
 * @param {string} str
 * @returns {string}
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

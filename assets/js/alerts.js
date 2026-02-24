/**
 * alerts.js — Powiadomienia UI (alerty)
 *
 * Funkcje:
 *   showAlert(type, msg, elementId?)
 *   clearAlert(elementId?)
 */

/**
 * Wyświetla alert o podanym typie ('error', 'success', 'info')
 * @param {string} type    — klasa CSS alertu
 * @param {string} msg     — treść wiadomości
 * @param {string} [elementId='alert'] — id elementu DOM
 */
function showAlert(type, msg, elementId = 'alert') {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.className = 'alert alert-' + type + ' show';
    el.textContent = msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * Czyści alert
 * @param {string} [elementId='alert']
 */
function clearAlert(elementId = 'alert') {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.className = 'alert';
}

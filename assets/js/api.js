/**
 * api.js — Helper do komunikacji z API
 *
 * Funkcje:
 *   apiPost(url, data) — wysyła POST JSON i zwraca obiekt odpowiedzi
 */

/**
 * Wysyła żądanie POST z body JSON i zwraca sparsowaną odpowiedź
 * @param {string} url   — adres endpointu API
 * @param {Object} data  — dane do wysłania
 * @returns {Promise<Object>}
 */
async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return res.json();
}

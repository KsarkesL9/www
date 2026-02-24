/**
 * session.js — Timer sesji i wylogowanie
 *
 * Funkcje:
 *   initSession(expiresAt) — uruchamia odliczanie sesji
 *   logout()               — wysyła żądanie wylogowania
 */

/**
 * Inicjalizuje timer sesji — aktualizuje badge i przekierowuje po wygaśnięciu
 * @param {string|null} expiresAt — data wygaśnięcia sesji (ISO / MySQL format)
 */
function initSession(expiresAt) {
    const sessionExp = expiresAt
        ? new Date(expiresAt.replace(' ', 'T'))
        : new Date(Date.now() + 60 * 60 * 1000);

    function updateSession() {
        const diffMs = sessionExp - Date.now();
        const diffMin = Math.max(0, Math.floor(diffMs / 60000));
        const badge = document.getElementById('sessionBadge');
        if (!badge) return;

        badge.textContent = diffMin + 'm';
        badge.title = 'Sesja wygaśnie za ' + diffMin + ' min';

        if (diffMin <= 5) {
            badge.style.borderColor = 'var(--danger)';
            badge.style.color = 'var(--danger)';
            badge.style.background = 'rgba(248,113,113,0.15)';
        }
        if (diffMin <= 0) {
            window.location.href = '/pages/login.php?msg=session_expired';
        }
    }

    updateSession();
    setInterval(updateSession, 30000);
}

/**
 * Wylogowuje użytkownika — wywołuje API i przekierowuje na login
 */
async function logout() {
    try {
        const res = await fetch('/api/logout.php', { method: 'POST' });
        const data = await res.json();
        window.location.href = data.redirect || '/pages/login.php';
    } catch {
        window.location.href = '/pages/login.php';
    }
}

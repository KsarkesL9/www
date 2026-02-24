/**
 * @file session.js
 * @brief Session timer and logout helper functions.
 *
 * @details This file provides two functions:
 *          one that starts a countdown timer for the user session,
 *          and one that logs the user out by calling the logout API.
 *
 * Functions:
 *   - initSession(expiresAt)
 *   - logout()
 */

/**
 * @brief Starts a session countdown timer and shows it in a badge element.
 *
 * @details This function calculates how many minutes are left before
 *          the user session ends. It updates a DOM element with ID
 *          'sessionBadge' to show the remaining time in minutes.
 *          The update runs immediately and then repeats every 30 seconds.
 *
 *          When less than 5 minutes remain, the badge changes its
 *          colors to red to warn the user that the session is almost over.
 *
 *          When the time runs out (0 minutes left), the function
 *          redirects the browser to the login page with the query
 *          parameter 'msg=session_expired'.
 *
 *          If the expiresAt parameter is null or empty, the function
 *          uses one hour from the current time as a fallback value.
 *
 *          If the badge element does not exist on the page, the
 *          function updates nothing but still runs the timer check.
 *
 * @param {string|null} expiresAt
 *        The date and time when the session ends.
 *        Can be in ISO 8601 format or MySQL format, for example
 *        '2026-02-24 15:30:00'. Spaces are replaced with 'T'
 *        before parsing. Pass null to use one hour from now.
 *
 * @returns {void}
 *
 * @see logout
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
 * @brief Logs out the current user by calling the logout API endpoint.
 *
 * @details Sends a POST request to '/api/logout.php'. The server
 *          removes the session and returns a redirect URL in the
 *          JSON response. If the server gives a redirect URL,
 *          the browser goes to that URL. If the request fails
 *          for any network reason, the browser goes to the
 *          login page as a fallback.
 *          This function is async, so it returns a Promise.
 *
 * @returns {Promise<void>}
 *          A Promise that resolves after the browser has been
 *          redirected to the login page or the redirect URL.
 *
 * @see initSession
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

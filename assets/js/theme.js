/**
 * @file theme.js
 * @brief Light and dark theme toggle for the application.
 *
 * @details This file manages the visual theme of the page.
 *          It reads the saved theme from localStorage and
 *          applies it before the page fully loads to avoid
 *          a flash of the wrong theme. After the page loads,
 *          it creates a toggle button so the user can switch
 *          between light and dark mode.
 *          The chosen theme is saved in localStorage so it
 *          is remembered on the next visit.
 */

/**
 * @brief Applies the saved theme immediately before the DOM loads.
 *
 * @details This is a self-calling function (IIFE) that runs as
 *          soon as the script is parsed by the browser, before
 *          the DOM is ready. It reads the 'theme' key from
 *          localStorage. If the value is 'light', it sets the
 *          'data-theme' attribute on the root HTML element to
 *          'light'. This prevents a visible flash of the dark
 *          background when the user has chosen the light theme.
 *          If the value is not 'light', nothing is changed and
 *          the default dark theme is used.
 *
 * @returns {void}
 */
(function() {
    if (localStorage.getItem('theme') === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
    }
})();

/**
 * @brief Sets up the theme toggle button after the DOM is ready.
 *
 * @details This function runs when the 'DOMContentLoaded' event fires.
 *          It creates a button element with the CSS class 'theme-toggle'
 *          and appends it to the page body. The button shows a sun icon
 *          when the current theme is dark (so the user can switch to light)
 *          and a moon icon when the current theme is light (so the user
 *          can switch to dark). When the user clicks the button, it:
 *          - Removes or sets the 'data-theme' attribute on the root element.
 *          - Saves the new theme name ('light' or 'dark') in localStorage.
 *          - Updates the button icon to match the new theme.
 *
 * @returns {void}
 */
document.addEventListener('DOMContentLoaded', () => {
    // Inject button
    const btn = document.createElement('button');
    btn.className = 'theme-toggle';
    btn.setAttribute('aria-label', 'Przełącz motyw');
    document.body.appendChild(btn);

    const rootTheme = document.documentElement;
    const isLight = localStorage.getItem('theme') === 'light';

    const sunSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>';
    const moonSvg = '<svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" /></svg>';

    btn.innerHTML = isLight ? moonSvg : sunSvg;

    /**
     * @brief Handles a click on the theme toggle button.
     *
     * @details Reads the current theme from the root element's
     *          'data-theme' attribute. If the current theme is light,
     *          it removes the attribute (which activates the dark theme)
     *          and saves 'dark' to localStorage. If the current theme
     *          is dark, it sets the attribute to 'light' and saves
     *          'light' to localStorage. Updates the button icon
     *          to match the new theme.
     *
     * @returns {void}
     */
    btn.addEventListener('click', () => {
        const currentLight = rootTheme.getAttribute('data-theme') === 'light';
        if (currentLight) {
            rootTheme.removeAttribute('data-theme');
            localStorage.setItem('theme', 'dark');
            btn.innerHTML = sunSvg;
        } else {
            rootTheme.setAttribute('data-theme', 'light');
            localStorage.setItem('theme', 'light');
            btn.innerHTML = moonSvg;
        }
    });
});

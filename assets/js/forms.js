/**
 * @file forms.js
 * @brief Helper functions for HTML forms.
 *
 * @details This file has two small utility functions.
 *          One lets the user show or hide a password field.
 *          The other makes text safe to put inside HTML
 *          by replacing special characters with HTML entity codes.
 *
 * Functions:
 *   - togglePw(id)
 *   - escHtml(str)
 */

/**
 * @brief Toggles the visibility of a password input field.
 *
 * @details Finds an input element by its ID. If the input
 *          type is 'password', this function changes it to 'text'
 *          so the typed characters are visible on screen. If the
 *          type is already 'text', it changes back to 'password'
 *          to hide the characters again. If the element is not
 *          found on the page, the function does nothing.
 *
 * @param {string} id
 *        The ID of the password input HTML element.
 *
 * @returns {void}
 */
function togglePw(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
}

/**
 * @brief Replaces special HTML characters with safe HTML entity codes.
 *
 * @details Converts four characters that have special meaning in HTML:
 *          - '&' becomes '&amp;'
 *          - '<' becomes '&lt;'
 *          - '>' becomes '&gt;'
 *          - '"' becomes '&quot;'
 *          This is important for security. When you put user text
 *          directly into HTML without this step, a bad user could
 *          write HTML tags or scripts that run in the browser.
 *          This function stops that by turning those characters into
 *          safe text. This technique is called XSS prevention.
 *          The input value is always converted to a string first.
 *
 * @param {string} str
 *        The text string to make safe for HTML output.
 *
 * @returns {string}
 *          A new string with all special HTML characters replaced
 *          by their safe HTML entity codes.
 *
 * @example
 *   escHtml('<b>Hello</b>') // returns '&lt;b&gt;Hello&lt;/b&gt;'
 *   escHtml('"test"')       // returns '&quot;test&quot;'
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

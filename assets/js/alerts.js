/**
 * @file alerts.js
 * @brief UI alert helper functions.
 *
 * @details This file has two functions that show and hide
 *          alert messages on the page. Other scripts use
 *          these functions to tell the user about errors
 *          or success messages.
 *
 * Functions:
 *   - showAlert(type, msg, elementId?)
 *   - clearAlert(elementId?)
 */

/**
 * @brief Shows an alert message inside a DOM element.
 *
 * @details This function finds an HTML element by its ID.
 *          It sets the CSS class of that element to show
 *          the right alert style (error, success, or info).
 *          It puts the message text inside the element.
 *          Then it scrolls the page smoothly so the user
 *          can see the alert. If the element is not found
 *          on the page, the function does nothing and returns.
 *
 * @param {string} type
 *        The alert style. Possible values: 'error', 'success', 'info'.
 *        This value is added to the CSS class as 'alert-{type}'.
 * @param {string} msg
 *        The text message to display inside the alert element.
 * @param {string} [elementId='alert']
 *        The ID of the HTML element that will show the alert.
 *        If not given, the function uses the element with ID 'alert'.
 *
 * @returns {void}
 *
 * @see clearAlert
 */
function showAlert(type, msg, elementId = 'alert') {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.className = 'alert alert-' + type + ' show';
    el.textContent = msg;
    el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/**
 * @brief Hides an alert element by removing the 'show' CSS class.
 *
 * @details This function finds an HTML element by its ID
 *          and resets its CSS class back to just 'alert'.
 *          This hides the alert because the 'show' class
 *          is removed. If the element is not found on the
 *          page, the function does nothing and returns.
 *
 * @param {string} [elementId='alert']
 *        The ID of the HTML element to clear.
 *        If not given, the function uses the element with ID 'alert'.
 *
 * @returns {void}
 *
 * @see showAlert
 */
function clearAlert(elementId = 'alert') {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.className = 'alert';
}

/**
 * @file api.js
 * @brief Helper for sending JSON requests to the server API.
 *
 * @details This file provides one async function that
 *          sends a POST request with a JSON body to a server
 *          endpoint. All other scripts in this project use
 *          this function to talk to the PHP API.
 *
 * Functions:
 *   - apiPost(url, data)
 */

/**
 * @brief Sends a POST request with JSON data to a server endpoint.
 *
 * @details This function uses the browser Fetch API to send data.
 *          It converts the data object to a JSON string and puts
 *          it in the request body. It also sets the Content-Type
 *          header to 'application/json' so the server knows how
 *          to read the request. After the server responds, the
 *          function reads the response body and converts it from
 *          JSON to a JavaScript object.
 *          Because this is an async function, callers must use
 *          'await' or handle the returned Promise with '.then()'.
 *
 * @param {string} url
 *        The full URL path of the API endpoint.
 *        Example: '/api/login.php'.
 * @param {Object} data
 *        The data object to send in the request body.
 *        The function converts this to a JSON string automatically.
 *
 * @returns {Promise<Object>}
 *          A Promise that resolves with the server response
 *          parsed as a plain JavaScript object.
 *
 * @throws {TypeError} If the network request fails completely
 *         or if the response body is not valid JSON.
 */
async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return res.json();
}

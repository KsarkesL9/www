/**
 * @file messages.js
 * @brief Client-side logic for the messages page.
 *
 * @details This file handles all user interactions on the messages page:
 *          opening threads, sending replies, deleting messages,
 *          filtering the thread list, and composing new messages.
 *
 * Required global variables (set inline by PHP):
 *   - CURRENT_USER_ID  — the ID of the logged-in user (integer)
 *   - ACTIVE_THREAD_ID — the ID of the open thread, or 0 if none (integer)
 *
 * Required scripts loaded before this file:
 *   - alerts.js  — provides showAlert()
 *   - forms.js   — provides escHtml()
 *   - api.js     — provides apiPost()
 */

/**
 * @brief Scrolls the message list to the bottom when the page loads.
 *
 * @details This is a self-calling function (IIFE) that runs once when
 *          the script loads. It finds the element with ID 'messagesList'
 *          and sets its scroll position to the bottom so the newest
 *          messages are visible. If the element does not exist, nothing happens.
 *
 * @returns {void}
 */
(function scrollToBottom() {
    const list = document.getElementById('messagesList');
    if (list) list.scrollTop = list.scrollHeight;
})();

/**
 * @brief Opens a message thread by navigating to its page.
 *
 * @details Changes the browser location to the messages page
 *          with the thread ID added as a query parameter.
 *          The browser will load the new URL and show the thread.
 *
 * @param {number} threadId
 *        The numeric ID of the thread to open.
 *
 * @returns {void}
 */
function openThread(threadId) {
    window.location.href = '/pages/messages.php?thread=' + threadId;
}

/**
 * @brief Filters the thread list by showing only threads that match the query.
 *
 * @details Loops through all elements with the CSS class 'msg-thread-item'.
 *          For each item, it reads the 'data-search' attribute which
 *          contains the searchable text. If the query string (lowercased,
 *          trimmed) is found inside that text, the item stays visible.
 *          If not, the item is hidden. If the query is empty, all items
 *          are shown again.
 *
 * @param {string} query
 *        The search text entered by the user. Compared in lowercase.
 *
 * @returns {void}
 */
function filterThreads(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('.msg-thread-item').forEach(item => {
        const text = item.dataset.search || '';
        item.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

/**
 * @brief Handles keyboard events in the reply text area.
 *
 * @details Listens for the Enter key pressed together with
 *          Ctrl (Windows/Linux) or Cmd (Mac). When that key
 *          combination is detected, it prevents the default
 *          browser action (adding a new line) and calls
 *          sendReply() to submit the message.
 *
 * @param {KeyboardEvent} e
 *        The keyboard event fired by the browser.
 *
 * @returns {void}
 *
 * @see sendReply
 */
function replyKeydown(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        sendReply();
    }
}

/**
 * @brief Sends the text from the reply area as a new message in the active thread.
 *
 * @details Reads the text from the textarea with ID 'replyContent'.
 *          If the text is empty, the function returns without sending.
 *          Disables the send button while the request is in progress
 *          to prevent double-sending. Sends the message content and
 *          the active thread ID to the API endpoint '/api/messages/send.php'.
 *          On success, clears the textarea, adds the new message to the
 *          DOM by calling appendMessage(), and updates the sidebar
 *          preview by calling updateSidebarPreview(). On failure,
 *          shows a browser alert with the error message. Re-enables
 *          the send button in all cases after the request finishes.
 *
 * @returns {Promise<void>}
 *          A Promise that resolves when the API call is finished.
 *
 * @see appendMessage
 * @see updateSidebarPreview
 * @see apiPost
 */
async function sendReply() {
    const content = document.getElementById('replyContent').value.trim();
    if (!content) return;

    const btn = document.querySelector('.msg-send-btn');
    btn.disabled = true;

    try {
        const data = await apiPost('/api/messages/send.php', {
            thread_id: ACTIVE_THREAD_ID,
            content
        });

        if (data.success) {
            document.getElementById('replyContent').value = '';
            appendMessage(data.message);
            updateSidebarPreview(ACTIVE_THREAD_ID, content);
        } else {
            alert(data.message || 'Błąd wysyłania wiadomości.');
        }
    } catch {
        alert('Błąd połączenia z serwerem.');
    } finally {
        btn.disabled = false;
    }
}

/**
 * @brief Adds a new message bubble to the message list in the DOM.
 *
 * @details Finds the message list element (#messagesList) and removes
 *          the 'empty' placeholder element if it exists. Creates a new
 *          wrapper div with the 'mine' CSS class (for the current user's
 *          messages). Formats the message creation time as a localized
 *          date and time string. Builds the HTML for the message bubble,
 *          the time label, and the delete button. Appends the new bubble
 *          to the list and scrolls the list to the bottom.
 *          Message content is escaped with escHtml() to prevent XSS.
 *          Newlines in the content are replaced with '<br>' tags.
 *
 * @param {Object} msg
 *        The message data object returned by the server API.
 * @param {number} msg.message_id
 *        The unique ID of the new message.
 * @param {string} msg.content
 *        The text content of the message.
 * @param {string} msg.created_at
 *        The creation timestamp in MySQL datetime format.
 *
 * @returns {void}
 *
 * @see escHtml
 * @see deleteMessage
 */
function appendMessage(msg) {
    const list = document.getElementById('messagesList');
    const empty = list.querySelector('.msg-empty');
    if (empty) empty.remove();

    const wrap = document.createElement('div');
    wrap.className = 'msg-bubble-wrap mine';
    wrap.dataset.messageId = msg.message_id;

    const now = new Date(msg.created_at.replace(' ', 'T'));
    const timeStr = now.toLocaleDateString('pl-PL', { day: '2-digit', month: '2-digit', year: 'numeric' })
        + ' ' + now.toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit' });

    wrap.innerHTML = `
        <div class="msg-bubble">${escHtml(msg.content).replace(/\n/g, '<br>')}</div>
        <div class="msg-bubble-time">${timeStr}</div>
        <button class="msg-delete-btn" onclick="deleteMessage(${msg.message_id}, this)" title="Usuń wiadomość">Usuń</button>
    `;
    list.appendChild(wrap);
    list.scrollTop = list.scrollHeight;
}

/**
 * @brief Updates the thread preview text and time in the sidebar thread list.
 *
 * @details Finds the thread item in the sidebar by its 'data-thread' attribute.
 *          If found, it updates the preview text to the first 60 characters
 *          of the content (with '…' appended if longer). Removes the unread
 *          dot indicator and the 'unread' CSS class from the thread item.
 *          Updates the time label to the current time (HH:MM format).
 *
 * @param {number} threadId
 *        The ID of the thread whose preview should be updated.
 * @param {string} content
 *        The full text content of the new message. Only the first
 *        60 characters are used in the preview.
 *
 * @returns {void}
 */
function updateSidebarPreview(threadId, content) {
    const item = document.querySelector(`.msg-thread-item[data-thread="${threadId}"]`);
    if (!item) return;
    const preview = item.querySelector('.msg-thread-preview');
    if (preview) preview.textContent = content.substring(0, 60) + (content.length > 60 ? '…' : '');
    const dot = item.querySelector('.msg-unread-dot');
    if (dot) dot.remove();
    item.classList.remove('unread');
    const timeEl = item.querySelector('.msg-thread-time');
    const now = new Date();
    if (timeEl) timeEl.textContent = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
}

/**
 * @brief Deletes a message (marks it as deleted for all thread participants).
 *
 * @details Asks the user to confirm the deletion with a browser confirm dialog.
 *          If the user cancels, the function returns without doing anything.
 *          If confirmed, sends the message ID to '/api/messages/delete.php'.
 *          On success, finds the message bubble element in the DOM, adds
 *          the 'deleted' CSS class to it, replaces the bubble content with
 *          a 'deleted' notice text, and removes the delete button.
 *          On failure, shows a browser alert with the error message.
 *
 * @param {number} messageId
 *        The unique ID of the message to delete.
 * @param {HTMLElement} btn
 *        The delete button element that was clicked. Used to find
 *        the parent wrapper element and to remove itself after deletion.
 *
 * @returns {Promise<void>}
 *          A Promise that resolves when the API call is finished.
 *
 * @see apiPost
 */
async function deleteMessage(messageId, btn) {
    if (!confirm('Usunąć tę wiadomość? Będzie oznaczona jako usunięta dla wszystkich uczestników.')) return;

    try {
        const data = await apiPost('/api/messages/delete.php', { message_id: messageId });

        if (data.success) {
            const wrap = btn.closest('.msg-bubble-wrap');
            const bubble = wrap.querySelector('.msg-bubble');
            bubble.classList.add('deleted');
            bubble.innerHTML = 'Wiadomość została usunięta';
            btn.remove();
        } else {
            alert(data.message || 'Błąd usuwania wiadomości.');
        }
    } catch {
        alert('Błąd połączenia z serwerem.');
    }
}

/** @brief List of currently selected recipients for the compose form. */
let selectedRecipients = [];

/** @brief Timer ID used to delay the user search request (debounce). */
let searchTimeout = null;

/**
 * @brief Opens the compose new message modal overlay.
 *
 * @details Resets all compose form fields to empty values.
 *          Clears the list of selected recipients and re-renders
 *          the recipient tags. Hides the user search dropdown.
 *          Clears any alert messages in the compose form.
 *          Adds the 'open' CSS class to the overlay (#composeOverlay)
 *          to show the modal. After a short delay, moves the browser
 *          focus to the subject input field.
 *
 * @returns {void}
 *
 * @see closeCompose
 * @see renderRecipientTags
 */
function openCompose() {
    selectedRecipients = [];
    renderRecipientTags();
    document.getElementById('composeSubject').value = '';
    document.getElementById('composeContent').value = '';
    document.getElementById('recipientSearch').value = '';
    document.getElementById('userDropdown').classList.remove('show');
    document.getElementById('composeAlert').className = 'alert';
    document.getElementById('composeOverlay').classList.add('open');
    setTimeout(() => document.getElementById('composeSubject').focus(), 200);
}

/**
 * @brief Closes the compose new message modal overlay.
 *
 * @details Removes the 'open' CSS class from the compose overlay
 *          element (#composeOverlay), which hides the modal.
 *
 * @returns {void}
 *
 * @see openCompose
 */
function closeCompose() {
    document.getElementById('composeOverlay').classList.remove('open');
}

/**
 * @brief Closes the compose modal when the user clicks on the background overlay.
 *
 * @details Checks if the click target is exactly the compose overlay
 *          element itself, not a child element. If yes, calls closeCompose()
 *          to close the modal dialog.
 *
 * @param {MouseEvent} e
 *        The mouse click event from the browser.
 *
 * @returns {void}
 *
 * @see closeCompose
 */
function overlayClick(e) {
    if (e.target === document.getElementById('composeOverlay')) closeCompose();
}

/**
 * @brief Renders the list of selected recipients as tag elements in the compose form.
 *
 * @details Reads the selectedRecipients array and generates HTML for
 *          each recipient as a small tag div. Each tag shows the
 *          recipient's name (escaped with escHtml) and a remove button
 *          that calls removeRecipient() when clicked. Replaces the
 *          current content of the #recipientTags container element.
 *
 * @returns {void}
 *
 * @see removeRecipient
 * @see escHtml
 */
function renderRecipientTags() {
    const container = document.getElementById('recipientTags');
    container.innerHTML = selectedRecipients.map(r => `
        <div class="recipient-tag">
            ${escHtml(r.name)}
            <button onclick="removeRecipient(${r.user_id})" title="Usuń">&times;</button>
        </div>
    `).join('');
}

/**
 * @brief Removes a recipient from the selected recipients list.
 *
 * @details Filters the selectedRecipients array to remove the entry
 *          with the matching user ID. Then calls renderRecipientTags()
 *          to update the tag display in the compose form.
 *
 * @param {number} userId
 *        The user ID of the recipient to remove.
 *
 * @returns {void}
 *
 * @see renderRecipientTags
 */
function removeRecipient(userId) {
    selectedRecipients = selectedRecipients.filter(r => r.user_id !== userId);
    renderRecipientTags();
}

/**
 * @brief Adds a user to the selected recipients list for the compose form.
 *
 * @details Checks if the user is already in the selectedRecipients array.
 *          If not, adds an object with user_id and name to the array.
 *          Then calls renderRecipientTags() to update the tag display.
 *          Clears the recipient search input field and hides the
 *          user search dropdown.
 *
 * @param {number} userId
 *        The numeric user ID of the recipient to add.
 * @param {string} name
 *        The full name of the recipient to display as a tag.
 *
 * @returns {void}
 *
 * @see renderRecipientTags
 * @see removeRecipient
 */
function addRecipient(userId, name) {
    if (!selectedRecipients.find(r => r.user_id === userId)) {
        selectedRecipients.push({ user_id: userId, name });
    }
    renderRecipientTags();
    document.getElementById('recipientSearch').value = '';
    document.getElementById('userDropdown').classList.remove('show');
}

/**
 * @brief Searches for active users matching the query and shows results in a dropdown.
 *
 * @details Clears any existing search timeout to reset the debounce timer.
 *          If the query is less than 2 characters, hides the dropdown and returns.
 *          Otherwise, waits 280 milliseconds (debounce delay) before sending a
 *          GET request to '/api/messages/search_users.php' with the query.
 *          If the API returns no users, shows a 'no results' message in
 *          the dropdown. If users are found, filters out the current user
 *          and already-selected recipients, then builds and shows dropdown
 *          items. Clicking an item calls addRecipient(). If the request
 *          fails for any reason, the dropdown is hidden silently.
 *
 * @param {string} query
 *        The text typed by the user in the recipient search field.
 *        The request is only sent if the query is 2 or more characters.
 *
 * @returns {void}
 *
 * @see addRecipient
 * @see escHtml
 */
function searchUsers(query) {
    clearTimeout(searchTimeout);
    const dropdown = document.getElementById('userDropdown');
    if (query.trim().length < 2) { dropdown.classList.remove('show'); return; }

    searchTimeout = setTimeout(async () => {
        try {
            const res = await fetch('/api/messages/search_users.php?q=' + encodeURIComponent(query));
            const data = await res.json();

            if (!data.users || data.users.length === 0) {
                dropdown.innerHTML = '<div class="user-dropdown-item" style="color:var(--text-muted);">Brak wyników</div>';
                dropdown.classList.add('show');
                return;
            }

            dropdown.innerHTML = data.users
                .filter(u => u.user_id !== CURRENT_USER_ID && !selectedRecipients.find(r => r.user_id === u.user_id))
                .map(u => `
                    <div class="user-dropdown-item" onclick="addRecipient(${u.user_id}, ${JSON.stringify(u.full_name)})">
                        <span>${escHtml(u.full_name)}</span>
                        <small>${escHtml(u.role_name)}</small>
                    </div>
                `).join('');

            if (!dropdown.innerHTML.trim()) {
                dropdown.innerHTML = '<div class="user-dropdown-item" style="color:var(--text-muted);">Brak wyników</div>';
            }
            dropdown.classList.add('show');
        } catch {
            dropdown.classList.remove('show');
        }
    }, 280);
}

// Close the user search dropdown when clicking outside it
document.addEventListener('click', (e) => {
    if (!e.target.closest('.user-search-wrap')) {
        const dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('show');
    }
});

/**
 * @brief Sends a new message thread from the compose modal form.
 *
 * @details Reads the subject and content from the compose form inputs.
 *          Validates that at least one recipient has been selected and
 *          that the content is not empty. If validation fails, shows
 *          an error alert inside the compose modal and returns.
 *          If valid, disables the send button and shows a loading spinner.
 *          Sends the subject, content, and array of recipient IDs to
 *          '/api/messages/create_thread.php' using apiPost(). On success,
 *          closes the compose modal and redirects to the new thread page.
 *          On failure, shows an error alert in the compose modal and
 *          re-enables the send button.
 *
 * @returns {Promise<void>}
 *          A Promise that resolves when the API call is finished.
 *
 * @see closeCompose
 * @see showAlert
 * @see apiPost
 */
async function sendCompose() {
    const subject = document.getElementById('composeSubject').value.trim();
    const content = document.getElementById('composeContent').value.trim();

    if (selectedRecipients.length === 0) { showAlert('error', 'Dodaj co najmniej jednego odbiorcę.', 'composeAlert'); return; }
    if (!content) { showAlert('error', 'Wpisz treść wiadomości.', 'composeAlert'); return; }

    const btn = document.getElementById('composeSendBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Wysyłanie…';

    try {
        const data = await apiPost('/api/messages/create_thread.php', {
            subject,
            content,
            recipient_ids: selectedRecipients.map(r => r.user_id)
        });

        if (data.success) {
            closeCompose();
            window.location.href = '/pages/messages.php?thread=' + data.thread_id;
        } else {
            showAlert('error', data.message || 'Błąd wysyłania wiadomości.', 'composeAlert');
            btn.disabled = false;
            btn.textContent = 'Wyślij';
        }
    } catch {
        showAlert('error', 'Błąd połączenia z serwerem.', 'composeAlert');
        btn.disabled = false;
        btn.textContent = 'Wyślij';
    }
}

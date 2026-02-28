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

/** @brief State for recipient picker */
let pickerAllUsers = [];
let pickerSelectedIds = new Set();

/**
 * @brief Opens the compose new message modal overlay and loads recipients.
 *
 * @details Resets all compose form fields to empty values.
 *          Calls API to load allowed recipients.
 *          Initializes the dual-pane picker.
 *
 * @returns {void}
 */
async function openCompose() {
    document.getElementById('composeSubject').value = '';
    document.getElementById('composeContent').value = '';
    document.getElementById('composeAlert').className = 'alert';
    document.getElementById('pickerSearch').value = '';

    document.getElementById('composeOverlay').classList.add('open');
    setTimeout(() => document.getElementById('composeSubject').focus(), 200);

    const loadingWrap = document.getElementById('pickerLoading');
    const pickerWrap = document.getElementById('recipientPickerWrap');

    loadingWrap.style.display = 'block';
    loadingWrap.innerHTML = '<span class="spinner"></span> Ładowanie dostępnych odbiorców…';
    pickerWrap.style.display = 'none';

    try {
        const res = await fetch('/api/messages/get_recipients.php');
        const data = await res.json();

        if (!data.success) {
            loadingWrap.innerHTML = `<div style="color:var(--danger);">${escHtml(data.message)}</div>`;
            return;
        }

        pickerAllUsers = data.users || [];
        pickerSelectedIds.clear();

        // Populate roles dropdown
        const roles = new Set(pickerAllUsers.map(u => u.role_name));
        const select = document.getElementById('pickerRoleSelect');
        select.innerHTML = '<option value="ALL">Wszystkie grupy</option>' + Array.from(roles).map(r => `<option value="${escHtml(r)}">${escHtml(r)}</option>`).join('');

        loadingWrap.style.display = 'none';
        pickerWrap.style.display = 'flex';

        pickerRender();
    } catch {
        loadingWrap.innerHTML = '<div style="color:var(--danger);">Błąd pobierania odbiorców.</div>';
    }
}

/** @brief Re-renders both lists of the dual-pane picker */
function pickerRender() {
    pickerFilter();
    pickerRenderSelected();
}

/** @brief Filters and renders the left list (available users) */
function pickerFilter() {
    const role = document.getElementById('pickerRoleSelect').value;
    const search = document.getElementById('pickerSearch').value.toLowerCase();

    const available = pickerAllUsers.filter(u => !pickerSelectedIds.has(u.user_id))
        .filter(u => role === 'ALL' || u.role_name === role)
        .filter(u => u.full_name.toLowerCase().includes(search));

    const list = document.getElementById('pickerAvailableList');
    list.innerHTML = available.map(u => `
        <div class="picker-item" onclick="this.classList.toggle('active')" data-id="${u.user_id}">
            <span>${escHtml(u.full_name)}</span>
            <small>${escHtml(u.role_name)}</small>
        </div>
    `).join('');
}

/** @brief Filters and renders the right list (selected users) */
function pickerRenderSelected() {
    const selected = pickerAllUsers.filter(u => pickerSelectedIds.has(u.user_id));
    const list = document.getElementById('pickerSelectedList');
    list.innerHTML = selected.map(u => `
        <div class="picker-item" onclick="this.classList.toggle('active')" data-id="${u.user_id}">
            <span>${escHtml(u.full_name)}</span>
            <small>${escHtml(u.role_name)}</small>
        </div>
    `).join('');
}

/** @brief Moves active users from left list to right list */
function pickerAddSelected() {
    document.querySelectorAll('#pickerAvailableList .picker-item.active').forEach(el => {
        pickerSelectedIds.add(el.dataset.id);
    });
    pickerRender();
}

/** @brief Moves active users from right list back to left list */
function pickerRemoveSelected() {
    document.querySelectorAll('#pickerSelectedList .picker-item.active').forEach(el => {
        pickerSelectedIds.delete(el.dataset.id);
    });
    pickerRender();
}

/**
 * @brief Sends a new message thread from the compose modal form.
 *
 * @details Reads the subject and content from the compose form inputs.
 *          Gathers selected checkbox recipient IDs.
 *          Sends them to '/api/messages/create_thread.php' API.
 */
async function sendCompose() {
    const subject = document.getElementById('composeSubject').value.trim();
    const content = document.getElementById('composeContent').value.trim();

    const selectedIds = Array.from(pickerSelectedIds);

    if (selectedIds.length === 0) { showAlert('error', 'Dodaj co najmniej jednego odbiorcę.', 'composeAlert'); return; }
    if (!content) { showAlert('error', 'Wpisz treść wiadomości.', 'composeAlert'); return; }

    const btn = document.getElementById('composeSendBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Wysyłanie…';

    try {
        const data = await apiPost('/api/messages/create_thread.php', {
            subject,
            content,
            recipient_ids: selectedIds
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
/**
 * @brief Closes the compose new message modal overlay.
 */
function closeCompose() {
    document.getElementById('composeOverlay').classList.remove('open');
}

/**
 * @brief Closes the compose modal when the user clicks on the background overlay.
 */
function overlayClick(e) {
    if (e.target === document.getElementById('composeOverlay')) closeCompose();
}

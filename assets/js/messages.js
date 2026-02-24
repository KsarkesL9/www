/**
 * messages.js — Logika strony wiadomości
 *
 * Wymaga globalnych zmiennych (ustawianych inline z PHP):
 *   CURRENT_USER_ID  — id zalogowanego użytkownika
 *   ACTIVE_THREAD_ID — id aktywnego wątku (0 jeśli brak)
 *
 * Zależności (ładowane przed tym plikiem):
 *   alerts.js  — showAlert()
 *   forms.js   — escHtml()
 *   api.js     — apiPost()
 */

// ===== Scroll do dołu wiadomości =====
(function scrollToBottom() {
    const list = document.getElementById('messagesList');
    if (list) list.scrollTop = list.scrollHeight;
})();

// ===== Otwieranie wątku =====
function openThread(threadId) {
    window.location.href = '/pages/messages.php?thread=' + threadId;
}

// ===== Filtrowanie wątków =====
function filterThreads(query) {
    const q = query.toLowerCase().trim();
    document.querySelectorAll('.msg-thread-item').forEach(item => {
        const text = item.dataset.search || '';
        item.style.display = (!q || text.includes(q)) ? '' : 'none';
    });
}

// ===== Odpowiedź w wątku =====
function replyKeydown(e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
        e.preventDefault();
        sendReply();
    }
}

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

// ===== Usuwanie wiadomości =====
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

// ===== Compose modal =====
let selectedRecipients = [];
let searchTimeout = null;

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

function closeCompose() {
    document.getElementById('composeOverlay').classList.remove('open');
}

function overlayClick(e) {
    if (e.target === document.getElementById('composeOverlay')) closeCompose();
}

function renderRecipientTags() {
    const container = document.getElementById('recipientTags');
    container.innerHTML = selectedRecipients.map(r => `
        <div class="recipient-tag">
            ${escHtml(r.name)}
            <button onclick="removeRecipient(${r.user_id})" title="Usuń">&times;</button>
        </div>
    `).join('');
}

function removeRecipient(userId) {
    selectedRecipients = selectedRecipients.filter(r => r.user_id !== userId);
    renderRecipientTags();
}

function addRecipient(userId, name) {
    if (!selectedRecipients.find(r => r.user_id === userId)) {
        selectedRecipients.push({ user_id: userId, name });
    }
    renderRecipientTags();
    document.getElementById('recipientSearch').value = '';
    document.getElementById('userDropdown').classList.remove('show');
}

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

// Zamknij dropdown po kliknięciu poza nim
document.addEventListener('click', (e) => {
    if (!e.target.closest('.user-search-wrap')) {
        const dd = document.getElementById('userDropdown');
        if (dd) dd.classList.remove('show');
    }
});

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

/**
 * Диалоги и чаты — список, сообщения, отправка
 */

let currentChatId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadDialogs();

    document.getElementById('chatSearch')?.addEventListener('input', filterChats);
    document.getElementById('btnChatRefresh')?.addEventListener('click', () => {
        if (currentChatId) loadMessages(currentChatId);
    });

    document.getElementById('messageForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const input = document.getElementById('messageInput');
        const text = input.value.trim();
        if (!text || !currentChatId) return;

        input.value = '';
        await App.api(`/api/chats/${encodeURIComponent(currentChatId)}/send`, {
            method: 'POST',
            body: { message: text },
        });
        loadMessages(currentChatId);
    });

    document.getElementById('fileInput')?.addEventListener('change', async (e) => {
        if (!currentChatId || !e.target.files[0]) return;
        const formData = new FormData();
        formData.append('file', e.target.files[0]);

        const res = await fetch(`/api/chats/${encodeURIComponent(currentChatId)}/upload`, {
            method: 'POST',
            headers: { 'X-CSRF-Token': App.csrfToken },
            body: formData,
        });
        const data = await res.json();
        if (data.error) App.toast(data.error, 'danger');
        else App.toast('Файл отправлен', 'success');
        loadMessages(currentChatId);
        e.target.value = '';
    });
});

async function loadDialogs() {
    const data = await App.api('/api/chats?limit=100');
    const list = document.getElementById('chatList');

    const items = data.dialogs?.items;
    if (!items || data.error) {
        list.innerHTML = '<div class="text-center text-muted py-4">Ошибка загрузки или не авторизован</div>';
        if (data.error) App.toast(data.error, 'danger');
        return;
    }

    if (items.length === 0) {
        list.innerHTML = '<div class="text-center text-muted py-4">Нет диалогов</div>';
        return;
    }

    list.innerHTML = items.map(d => {
        const title = d.title || ('ID ' + d.id);
        const unread = d.unread_count || 0;
        const typeBadge = d.type ? `<span class="badge bg-secondary me-1">${d.type}</span>` : '';

        return `
            <div class="chat-item" data-id="${d.id}" data-title="${escapeAttr(title)}">
                <div class="chat-item-title">${escapeHtml(title)}</div>
                <div class="chat-item-preview">
                    ${unread > 0 ? '<span class="badge bg-primary">' + unread + '</span> ' : ''}
                    ${typeBadge}ID: ${d.id}
                </div>
            </div>
        `;
    }).join('');

    list.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', () => {
            list.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            currentChatId = item.dataset.id;
            document.getElementById('chatTitle').textContent = item.dataset.title;
            document.getElementById('chatEmpty').classList.add('d-none');
            document.getElementById('chatActive').classList.remove('d-none');
            loadMessages(currentChatId);
        });
    });
}

async function loadMessages(chatId) {
    const container = document.getElementById('chatMessages');
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm"></div></div>';

    const url = `/api/chats/${encodeURIComponent(chatId)}/messages?limit=50`;
    const data = await App.api(url);

    if (!data.history || data.error) {
        container.innerHTML = '<div class="text-center text-muted py-4">Ошибка загрузки сообщений</div>';
        if (data.error) App.toast(data.error, 'danger');
        return;
    }

    const messages = (data.history.messages || []).slice().reverse();

    if (messages.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-4">Нет сообщений</div>';
        return;
    }

    container.innerHTML = messages.map(m => {
        const isOut = m.out;
        const text = m.message || '[' + (m.media?._ || 'media') + ']';
        const date = m.date ? new Date(m.date * 1000).toLocaleString('ru') : '';
        return `
            <div class="message-bubble ${isOut ? 'message-out' : 'message-in'}">
                ${escapeHtml(text)}
                <div class="message-meta">${date} #${m.id}</div>
            </div>
        `;
    }).join('');

    container.scrollTop = container.scrollHeight;
}

function filterChats() {
    const q = document.getElementById('chatSearch').value.toLowerCase();
    document.querySelectorAll('.chat-item').forEach(item => {
        const title = (item.dataset.title || '').toLowerCase();
        const id = (item.dataset.id || '').toLowerCase();
        item.style.display = (title.includes(q) || id.includes(q)) ? '' : 'none';
    });
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function escapeAttr(str) {
    return escapeHtml(str).replace(/'/g, '&#39;');
}

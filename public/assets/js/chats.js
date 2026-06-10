/**
 * Диалоги и чаты — список, сообщения, отправка
 */

let currentChatId = null;
let currentAccessHash = 0;

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
        await App.api(`/api/chats/${currentChatId}/send`, {
            method: 'POST',
            body: { message: text },
        });
        loadMessages(currentChatId);
    });

    document.getElementById('fileInput')?.addEventListener('change', async (e) => {
        if (!currentChatId || !e.target.files[0]) return;
        const formData = new FormData();
        formData.append('file', e.target.files[0]);

        const res = await fetch(`/api/chats/${currentChatId}/upload`, {
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

    if (!data.dialogs || data.error) {
        list.innerHTML = '<div class="text-center text-muted py-4">Ошибка загрузки или не авторизован</div>';
        return;
    }

    const dialogs = data.dialogs.dialogs || [];
    const chats = data.dialogs.chats || [];
    const users = data.dialogs.users || [];

    // Собираем lookup-таблицы
    const chatMap = {};
    chats.forEach(c => chatMap[c.id] = c);
    const userMap = {};
    users.forEach(u => userMap[u.id] = u);

    if (dialogs.length === 0) {
        list.innerHTML = '<div class="text-center text-muted py-4">Нет диалогов</div>';
        return;
    }

    list.innerHTML = dialogs.map(d => {
        let title = 'Unknown';
        let chatId = '';
        let accessHash = 0;

        const peer = d.peer;
        if (peer._ === 'peerUser') {
            const u = userMap[peer.user_id] || {};
            title = (u.first_name || '') + ' ' + (u.last_name || '');
            if (u.username) title += ' (@' + u.username + ')';
            chatId = 'user_' + peer.user_id;
            accessHash = u.access_hash || 0;
        } else if (peer._ === 'peerChat') {
            const c = chatMap[peer.chat_id] || {};
            title = c.title || 'Chat ' + peer.chat_id;
            chatId = 'chat_' + peer.chat_id;
        } else if (peer._ === 'peerChannel') {
            const c = chatMap[peer.channel_id] || {};
            title = c.title || 'Channel ' + peer.channel_id;
            chatId = 'channel_' + peer.channel_id;
            accessHash = c.access_hash || 0;
        }

        const unread = d.unread_count || 0;
        return `
            <div class="chat-item" data-id="${chatId}" data-hash="${accessHash}" data-title="${title.replace(/"/g, '&quot;')}">
                <div class="chat-item-title">${title.trim()}</div>
                <div class="chat-item-preview">${unread > 0 ? '<span class="badge bg-primary">' + unread + '</span> ' : ''}ID: ${chatId}</div>
            </div>
        `;
    }).join('');

    // Клик по чату
    list.querySelectorAll('.chat-item').forEach(item => {
        item.addEventListener('click', () => {
            list.querySelectorAll('.chat-item').forEach(i => i.classList.remove('active'));
            item.classList.add('active');
            currentChatId = item.dataset.id;
            currentAccessHash = item.dataset.hash;
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

    const url = `/api/chats/${chatId}/messages?limit=50&access_hash=${currentAccessHash}`;
    const data = await App.api(url);

    if (!data.history || data.error) {
        container.innerHTML = '<div class="text-center text-muted py-4">Ошибка загрузки сообщений</div>';
        return;
    }

    const messages = (data.history.messages || []).reverse();

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
                ${text}
                <div class="message-meta">${date} #${m.id}</div>
            </div>
        `;
    }).join('');

    container.scrollTop = container.scrollHeight;
}

function filterChats() {
    const q = document.getElementById('chatSearch').value.toLowerCase();
    document.querySelectorAll('.chat-item').forEach(item => {
        const title = item.dataset.title.toLowerCase();
        item.style.display = title.includes(q) ? '' : 'none';
    });
}

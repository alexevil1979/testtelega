/**
 * API Логгер — полный MTProto exchange (parsed + raw)
 */

let logEntries = [];
let streamPos = 0;
let eventSource = null;

document.addEventListener('DOMContentLoaded', () => {
    loadHistory();
    startStream();

    document.getElementById('liveStream')?.addEventListener('change', (e) => {
        if (e.target.checked) startStream();
        else stopStream();
    });

    document.getElementById('filterCategory')?.addEventListener('change', () => {
        renderEntries();
        restartStream();
    });

    document.getElementById('filterMethod')?.addEventListener('input', renderEntries);

    document.getElementById('btnClearLogs')?.addEventListener('click', async () => {
        if (!confirm('Очистить все логи?')) return;
        await App.api('/api/logger/clear', { method: 'POST' });
        logEntries = [];
        renderEntries();
        App.toast('Логи очищены', 'success');
    });

    document.getElementById('detailClose')?.addEventListener('click', () => {
        document.getElementById('loggerDetail').classList.add('d-none');
    });
});

async function loadHistory() {
    const data = await App.api('/api/logger/list?limit=200');
    if (data.logs) {
        logEntries = data.logs.reverse();
        renderEntries();
    }
}

function startStream() {
    stopStream();
    const category = document.getElementById('filterCategory')?.value || '';
    const url = `/api/logger/stream?pos=${streamPos}` + (category ? `&category=${category}` : '');

    eventSource = new EventSource(url);

    eventSource.onmessage = (event) => {
        const entry = JSON.parse(event.data);

        if (entry.type === 'reconnect') {
            streamPos = entry.pos || 0;
            restartStream();
            return;
        }
        if (entry.type === 'ping') return;

        logEntries.push(entry);
        if (logEntries.length > 1000) logEntries.shift();
        renderEntries();
    };

    eventSource.onerror = () => {
        stopStream();
        setTimeout(() => {
            if (document.getElementById('liveStream')?.checked) startStream();
        }, 3000);
    };
}

function stopStream() {
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }
}

function restartStream() {
    stopStream();
    if (document.getElementById('liveStream')?.checked) startStream();
}

function renderEntries() {
    const container = document.getElementById('loggerEntries');
    const category = document.getElementById('filterCategory')?.value || '';
    const methodFilter = (document.getElementById('filterMethod')?.value || '').toLowerCase();

    let filtered = logEntries;
    if (category) filtered = filtered.filter(e => e.category === category);
    if (methodFilter) filtered = filtered.filter(e => (e.method || '').toLowerCase().includes(methodFilter));

    document.getElementById('logCount').textContent = filtered.length + ' записей';

    if (filtered.length === 0) {
        container.innerHTML = '<div class="text-center text-muted py-5">Нет записей</div>';
        return;
    }

    container.innerHTML = filtered.slice(-200).map((entry) => {
        const idx = logEntries.indexOf(entry);
        const payload = entry.payload_file ? '<span class="badge bg-info">FILE</span>' : '';
        return `
        <div class="log-entry ${entry.error ? 'error' : 'success'}" data-idx="${idx}">
            <span class="log-entry-time">${entry.created_at || ''}</span>
            <span class="log-entry-method">→ ${entry.method || '—'}</span>
            <span class="badge bg-secondary">${entry.category || ''}</span>
            <span class="log-entry-duration">${entry.duration_ms || 0}ms</span>
            ${entry.error ? '<span class="badge bg-danger">ERR</span>' : '<span class="badge bg-success">OK</span>'}
            ${payload}
        </div>`;
    }).join('');

    container.scrollTop = container.scrollHeight;

    container.querySelectorAll('.log-entry').forEach(el => {
        el.addEventListener('click', () => showDetail(logEntries[el.dataset.idx]));
    });
}

async function showDetail(entry) {
    if (!entry) return;

    const needsFull = entry.payload_file
        || !entry.request?.raw?.hex
        || (entry.response && !entry.response?.raw?.hex && !entry.response?.raw?.error);
    if (entry.id && needsFull) {
        const full = await App.api(`/api/logger/entry/${entry.id}`);
        if (full.entry) entry = full.entry;
    }

    const detail = document.getElementById('loggerDetail');
    detail.classList.remove('d-none');

    document.getElementById('detailMethod').textContent = entry.method || '—';
    document.getElementById('detailId').textContent = entry.id || '—';
    document.getElementById('detailDuration').textContent = (entry.duration_ms || 0) + ' ms';
    document.getElementById('detailCategory').textContent = entry.category || '—';
    document.getElementById('detailTime').textContent = entry.created_at || '—';
    document.getElementById('detailSession').textContent = entry.session_id || '—';
    document.getElementById('detailError').textContent = entry.error || '—';
    document.getElementById('detailPayload').textContent = entry.payload_file || '—';

    setJson('detailReqParsed', entry.request?.parsed ?? entry.params ?? {});
    setJson('detailReqRaw', formatRaw(entry.request?.raw));
    setJson('detailResParsed', entry.response?.parsed ?? entry.response ?? null);
    setJson('detailResRaw', formatRaw(entry.response?.raw));
}

function formatRaw(raw) {
    if (!raw) return { note: 'Нет данных (ошибка до ответа или старая запись)' };
    return raw;
}

function setJson(elementId, data) {
    const el = document.getElementById(elementId);
    if (!el) return;
    el.textContent = JSON.stringify(data, null, 2);
    if (typeof hljs !== 'undefined') hljs.highlightElement(el);
}

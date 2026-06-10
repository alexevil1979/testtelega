/**
 * API Логгер — realtime SSE + история
 */

let logEntries = [];
let streamPos = 0;
let eventSource = null;

document.addEventListener('DOMContentLoaded', () => {
    // Загрузка истории
    loadHistory();

    // Live stream
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

    container.innerHTML = filtered.slice(-200).map((entry, idx) => `
        <div class="log-entry ${entry.error ? 'error' : 'success'}" data-idx="${logEntries.indexOf(entry)}">
            <span class="log-entry-time">${entry.created_at || ''}</span>
            <span class="log-entry-method">${entry.method || '—'}</span>
            <span class="badge bg-secondary">${entry.category || ''}</span>
            <span class="log-entry-duration">${entry.duration_ms || 0}ms</span>
            ${entry.error ? '<span class="badge bg-danger">ERR</span>' : ''}
        </div>
    `).join('');

    container.scrollTop = container.scrollHeight;

    container.querySelectorAll('.log-entry').forEach(el => {
        el.addEventListener('click', () => showDetail(logEntries[el.dataset.idx]));
    });
}

function showDetail(entry) {
    if (!entry) return;

    const detail = document.getElementById('loggerDetail');
    detail.classList.remove('d-none');

    document.getElementById('detailMethod').textContent = entry.method || '—';
    document.getElementById('detailDuration').textContent = (entry.duration_ms || 0) + ' ms';
    document.getElementById('detailCategory').textContent = entry.category || '—';
    document.getElementById('detailTime').textContent = entry.created_at || '—';
    document.getElementById('detailError').textContent = entry.error || '—';

    const paramsEl = document.getElementById('detailParams');
    paramsEl.textContent = JSON.stringify(entry.params || {}, null, 2);
    hljs.highlightElement(paramsEl);

    const responseEl = document.getElementById('detailResponse');
    responseEl.textContent = JSON.stringify(entry.response || null, null, 2);
    hljs.highlightElement(responseEl);
}

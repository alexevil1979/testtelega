<!-- Дашборд: обзор состояния аккаунта и быстрые действия -->

<div class="row g-4">
    <!-- Статус аккаунта -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-person-circle"></i>
                <span>Аккаунт</span>
            </div>
            <div class="card-body" id="accountCard">
                <div class="text-center py-4" id="accountLoading">
                    <div class="spinner-border spinner-border-sm text-muted"></div>
                    <p class="mt-2 mb-0 text-muted">Загрузка...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Быстрая статистика -->
    <div class="col-lg-8">
        <div class="row g-3">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-primary"><i class="bi bi-chat-dots"></i></div>
                    <div>
                        <div class="stat-value" id="statDialogs">—</div>
                        <div class="stat-label">Диалогов</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-success"><i class="bi bi-people"></i></div>
                    <div>
                        <div class="stat-value" id="statContacts">—</div>
                        <div class="stat-label">Контактов</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-warning"><i class="bi bi-bug"></i></div>
                    <div>
                        <div class="stat-value" id="statApiCalls">—</div>
                        <div class="stat-label">API вызовов</div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon bg-info"><i class="bi bi-clock"></i></div>
                    <div>
                        <div class="stat-value" id="statUptime">—</div>
                        <div class="stat-label">Сессия</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Быстрые действия -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning"></i> Быстрые действия
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a href="/auth" class="btn btn-outline-primary btn-sm"><i class="bi bi-shield-lock"></i> Авторизация</a>
                    <a href="/chats" class="btn btn-outline-primary btn-sm"><i class="bi bi-chat-dots"></i> Диалоги</a>
                    <a href="/logger" class="btn btn-outline-warning btn-sm"><i class="bi bi-bug"></i> API Логгер</a>
                    <button class="btn btn-outline-info btn-sm" id="btnGetUpdates" <?= !$isLoggedIn ? 'disabled' : '' ?>>
                        <i class="bi bi-arrow-repeat"></i> Получить Updates
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="btnRpcCall" <?= !$isLoggedIn ? 'disabled' : '' ?>>
                        <i class="bi bi-terminal"></i> RPC Вызов
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Последние API-вызовы -->
<div class="row g-4 mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-activity"></i> Последние API-вызовы</span>
                <a href="/logger" class="btn btn-sm btn-outline-secondary">Все логи</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="recentLogsTable">
                        <thead>
                            <tr>
                                <th>Время</th>
                                <th>Метод</th>
                                <th>Категория</th>
                                <th>Длительность</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5" class="text-center text-muted py-4">Загрузка...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно RPC -->
<div class="modal fade" id="rpcModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-terminal"></i> RPC Вызов</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">MTProto метод</label>
                    <input type="text" class="form-control" id="rpcMethod" placeholder="messages.getDialogs">
                </div>
                <div class="mb-3">
                    <label class="form-label">Параметры (JSON)</label>
                    <textarea class="form-control font-monospace" id="rpcParams" rows="6">{}</textarea>
                </div>
                <div id="rpcResult" class="d-none">
                    <label class="form-label">Ответ</label>
                    <pre class="json-viewer"><code class="language-json" id="rpcResultCode"></code></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-primary" id="rpcExecute">
                    <i class="bi bi-play-fill"></i> Выполнить
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Карточка аккаунта — через API, без MadelineProto при рендере страницы
    loadAccountCard();

    async function loadAccountCard() {
        const card = document.getElementById('accountCard');
        const status = await App.api('/api/auth/status');
        if (!status.logged_in) {
            card.innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-shield-x display-4 text-muted"></i>
                    <p class="mt-3 text-muted">Не авторизован</p>
                    <a href="/auth" class="btn btn-primary btn-sm">
                        <i class="bi bi-box-arrow-in-right"></i> Войти
                    </a>
                </div>`;
            return;
        }
        const me = await App.api('/api/auth/me');
        const u = me.user || {};
        card.innerHTML = `
            <div class="account-info">
                <div class="account-avatar"><i class="bi bi-person-fill"></i></div>
                <div>
                    <h5>${(u.first_name || '') + ' ' + (u.last_name || '')}</h5>
                    ${u.username ? '<p class="text-muted mb-0">@' + u.username + '</p>' : ''}
                    <p class="text-muted mb-0">ID: ${u.id || 0}</p>
                    <p class="text-muted mb-0">Телефон: ${u.phone || '—'}</p>
                </div>
            </div>`;
        document.getElementById('btnGetUpdates')?.removeAttribute('disabled');
        document.getElementById('btnRpcCall')?.removeAttribute('disabled');
    }

    // Загрузка последних логов
    App.api('/api/logger/list?limit=10').then(data => {
        const tbody = document.querySelector('#recentLogsTable tbody');
        if (!data.logs || data.logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Нет записей</td></tr>';
            document.getElementById('statApiCalls').textContent = '0';
            return;
        }
        document.getElementById('statApiCalls').textContent = data.logs.length + '+';
        tbody.innerHTML = data.logs.map(log => `
            <tr>
                <td><small>${log.created_at}</small></td>
                <td><code>${log.method}</code></td>
                <td><span class="badge bg-secondary">${log.category}</span></td>
                <td>${log.duration_ms}ms</td>
                <td>${log.error ? '<span class="badge bg-danger">Error</span>' : '<span class="badge bg-success">OK</span>'}</td>
            </tr>
        `).join('');
    }).catch(() => {
        const tbody = document.querySelector('#recentLogsTable tbody');
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Нет записей</td></tr>';
    });

    // RPC модалка
    document.getElementById('btnRpcCall')?.addEventListener('click', () => {
        if (typeof bootstrap === 'undefined') { App.toast('Bootstrap не загружен', 'danger'); return; }
        new bootstrap.Modal(document.getElementById('rpcModal')).show();
    });
    document.getElementById('rpcExecute')?.addEventListener('click', async () => {
        const method = document.getElementById('rpcMethod').value;
        let params = {};
        try { params = JSON.parse(document.getElementById('rpcParams').value); } catch(e) {
            App.toast('Невалидный JSON параметров', 'danger'); return;
        }
        const result = await App.api('/api/rpc', { method: 'POST', body: { method, params } });
        const el = document.getElementById('rpcResult');
        el.classList.remove('d-none');
        const code = document.getElementById('rpcResultCode');
        code.textContent = JSON.stringify(result.result || result.error, null, 2);
        if (typeof hljs !== 'undefined') hljs.highlightElement(code);
    });

    // Updates
    document.getElementById('btnGetUpdates')?.addEventListener('click', async () => {
        const data = await App.api('/api/updates');
        App.toast('Updates получены: ' + (data.updates?.length || 0), 'success');
    });
});
</script>

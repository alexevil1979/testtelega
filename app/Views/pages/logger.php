<!-- API Логгер / Отладка MTProto -->

<div class="logger-layout">
    <!-- Панель фильтров -->
    <div class="logger-toolbar">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <select class="form-select form-select-sm" id="filterCategory" style="width:auto;">
                <option value="">Все категории</option>
                <option value="auth">auth</option>
                <option value="messages">messages</option>
                <option value="users">users</option>
                <option value="chats">chats</option>
                <option value="updates">updates</option>
                <option value="rpc">rpc</option>
                <option value="general">general</option>
            </select>
            <input type="text" class="form-control form-control-sm" id="filterMethod"
                   placeholder="Фильтр по методу..." style="width:200px;">
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="liveStream" checked>
                <label class="form-check-label" for="liveStream">Live</label>
            </div>
            <button class="btn btn-sm btn-outline-danger" id="btnClearLogs">
                <i class="bi bi-trash"></i> Очистить
            </button>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-download"></i> Экспорт
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="/api/logger/export?format=json" target="_blank">JSON</a></li>
                    <li><a class="dropdown-item" href="/api/logger/export?format=csv" target="_blank">CSV</a></li>
                </ul>
            </div>
            <span class="badge bg-secondary ms-auto" id="logCount">0 записей</span>
        </div>
    </div>

    <!-- Список логов -->
    <div class="logger-entries" id="loggerEntries">
        <div class="text-center text-muted py-5">
            <div class="spinner-border spinner-border-sm"></div>
            <p class="mt-2">Ожидание API-вызовов...</p>
        </div>
    </div>

    <!-- Детали выбранного лога -->
    <div class="logger-detail d-none" id="loggerDetail">
        <div class="logger-detail-header">
            <h6 id="detailMethod">—</h6>
            <button class="btn btn-sm btn-close" id="detailClose"></button>
        </div>
        <div class="logger-detail-body">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabParams">Параметры</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabResponse">Ответ</a></li>
                <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabMeta">Мета</a></li>
            </ul>
            <div class="tab-content p-3">
                <div class="tab-pane fade show active" id="tabParams">
                    <pre class="json-viewer"><code class="language-json" id="detailParams"></code></pre>
                </div>
                <div class="tab-pane fade" id="tabResponse">
                    <pre class="json-viewer"><code class="language-json" id="detailResponse"></code></pre>
                </div>
                <div class="tab-pane fade" id="tabMeta">
                    <table class="table table-sm">
                        <tr><td>Длительность</td><td id="detailDuration">—</td></tr>
                        <tr><td>Категория</td><td id="detailCategory">—</td></tr>
                        <tr><td>Время</td><td id="detailTime">—</td></tr>
                        <tr><td>Ошибка</td><td id="detailError">—</td></tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

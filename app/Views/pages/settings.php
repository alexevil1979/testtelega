<!-- Настройки -->

<div class="row g-4">
    <!-- Прокси MTProto -->
    <div class="col-12">
        <div class="card">
            <div class="card-header"><i class="bi bi-shield-shaded"></i> Прокси (MTProto / SOCKS5)</div>
            <div class="card-body">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="proxyEnabled"
                           <?= ($proxy['enabled'] ?? true) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="proxyEnabled">Использовать прокси всегда</label>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">proxy_url (MTProto / SOCKS5)</label>
                        <input type="text" class="form-control font-monospace" id="proxyUrl"
                               value="<?= htmlspecialchars($proxy['mtproto_url'] ?? 'socks5://127.0.0.1:1084') ?>"
                               placeholder="socks5://127.0.0.1:1084">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">http_api_proxy_url (HTTP API)</label>
                        <input type="text" class="form-control font-monospace" id="httpApiProxyUrl"
                               value="<?= htmlspecialchars($proxy['http_api_url'] ?? 'socks5://127.0.0.1:1084') ?>"
                               placeholder="socks5://127.0.0.1:1084">
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-primary btn-sm" id="btnSaveProxy">
                        <i class="bi bi-save"></i> Сохранить прокси
                    </button>
                    <small class="text-muted ms-2">После сохранения API переподключится через прокси</small>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-hdd-stack"></i> Сессии MadelineProto</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Размер</th>
                            <th>Изменён</th>
                            <th>Статус</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sessions)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Нет сессий</td></tr>
                        <?php else: ?>
                            <?php foreach ($sessions as $s): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($s['id']) ?></code></td>
                                    <td><?= number_format($s['size'] / 1024, 1) ?> KB</td>
                                    <td><small><?= htmlspecialchars($s['modified']) ?></small></td>
                                    <td>
                                        <?php if ($s['active']): ?>
                                            <span class="badge bg-success">Активная</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger btn-delete-session"
                                                data-id="<?= htmlspecialchars($s['id']) ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-sliders"></i> Обслуживание</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-warning" id="btnClearCache">
                        <i class="bi bi-arrow-clockwise"></i> Очистить кэш
                    </button>
                    <button class="btn btn-outline-danger" id="btnClearAllLogs">
                        <i class="bi bi-trash"></i> Очистить все логи MTProto
                    </button>
                </div>

                <hr>

                <h6>Информация о системе</h6>
                <table class="table table-sm">
                    <tr><td>PHP</td><td><?= PHP_VERSION ?></td></tr>
                    <tr><td>ОС</td><td><?= PHP_OS ?></td></tr>
                    <tr><td>Память</td><td><?= ini_get('memory_limit') ?></td></tr>
                    <tr><td>Upload max</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
                    <tr><td>Время</td><td><?= date('Y-m-d H:i:s T') ?></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('btnSaveProxy')?.addEventListener('click', async () => {
        await App.api('/api/settings', {
            method: 'POST',
            body: {
                proxy_enabled: document.getElementById('proxyEnabled').checked,
                proxy_url: document.getElementById('proxyUrl').value.trim(),
                http_api_proxy_url: document.getElementById('httpApiProxyUrl').value.trim(),
            }
        });
        App.toast('Прокси сохранён', 'success');
    });

    document.querySelectorAll('.btn-delete-session').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (!confirm('Удалить сессию «' + btn.dataset.id + '»?\n\nАккаунт Telegram на сервере будет отключён.')) return;
            const data = await App.api('/api/settings/sessions/delete', {
                method: 'POST',
                body: { session_id: btn.dataset.id }
            });
            if (data.status === 'ok') {
                App.toast('Сессия удалена', 'success');
                location.reload();
            } else {
                App.toast(data.error || 'Не удалось удалить сессию', 'danger');
            }
        });
    });

    document.getElementById('btnClearCache')?.addEventListener('click', async () => {
        await App.api('/api/settings/clear-cache', { method: 'POST' });
        App.toast('Кэш очищен', 'success');
    });

    document.getElementById('btnClearAllLogs')?.addEventListener('click', async () => {
        if (!confirm('Удалить все логи MTProto?')) return;
        await App.api('/api/logger/clear', { method: 'POST' });
        App.toast('Логи очищены', 'success');
    });
});
</script>

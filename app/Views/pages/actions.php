<!-- Действия: создание групп, каналов, управление участниками -->

<div class="row g-4">
    <!-- Создание группы -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-people-fill"></i> Создать группу</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Название</label>
                    <input type="text" class="form-control" id="groupTitle" placeholder="Моя группа">
                </div>
                <div class="mb-3">
                    <label class="form-label">User IDs участников (через запятую)</label>
                    <input type="text" class="form-control" id="groupUsers" placeholder="123456, 789012">
                </div>
                <button class="btn btn-primary" id="btnCreateGroup">
                    <i class="bi bi-plus-circle"></i> Создать
                </button>
            </div>
        </div>
    </div>

    <!-- Создание канала -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-broadcast"></i> Создать канал</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Название</label>
                    <input type="text" class="form-control" id="channelTitle" placeholder="Мой канал">
                </div>
                <div class="mb-3">
                    <label class="form-label">Описание</label>
                    <textarea class="form-control" id="channelAbout" rows="2"></textarea>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="channelMegagroup">
                    <label class="form-check-label" for="channelMegagroup">Супергруппа (megagroup)</label>
                </div>
                <button class="btn btn-primary" id="btnCreateChannel">
                    <i class="bi bi-plus-circle"></i> Создать
                </button>
            </div>
        </div>
    </div>

    <!-- Пригласить / Кикнуть -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-person-plus"></i> Управление участниками</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Channel ID</label>
                    <input type="number" class="form-control" id="manageChannelId">
                </div>
                <div class="mb-3">
                    <label class="form-label">Access Hash</label>
                    <input type="number" class="form-control" id="manageAccessHash">
                </div>
                <div class="mb-3">
                    <label class="form-label">User ID</label>
                    <input type="number" class="form-control" id="manageUserId">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success btn-sm" id="btnInvite">
                        <i class="bi bi-person-plus"></i> Пригласить
                    </button>
                    <button class="btn btn-danger btn-sm" id="btnKick">
                        <i class="bi bi-person-x"></i> Кикнуть
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Блокировка -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><i class="bi bi-slash-circle"></i> Блокировка</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">User ID</label>
                    <input type="number" class="form-control" id="blockUserId">
                </div>
                <div class="mb-3">
                    <label class="form-label">Access Hash</label>
                    <input type="number" class="form-control" id="blockAccessHash">
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-warning btn-sm" id="btnBlock">
                        <i class="bi bi-slash-circle"></i> Заблокировать
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" id="btnUnblock">
                        <i class="bi bi-check-circle"></i> Разблокировать
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Результат -->
<div class="card mt-4 d-none" id="actionResult">
    <div class="card-header"><i class="bi bi-check2-circle"></i> Результат</div>
    <div class="card-body">
        <pre class="json-viewer"><code class="language-json" id="actionResultCode"></code></pre>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const showResult = (data) => {
        document.getElementById('actionResult').classList.remove('d-none');
        const code = document.getElementById('actionResultCode');
        code.textContent = JSON.stringify(data, null, 2);
        hljs.highlightElement(code);
    };

    document.getElementById('btnCreateGroup')?.addEventListener('click', async () => {
        const users = document.getElementById('groupUsers').value.split(',').map(s => s.trim()).filter(Boolean);
        const data = await App.api('/api/actions/create-group', {
            method: 'POST',
            body: { title: document.getElementById('groupTitle').value, users }
        });
        showResult(data);
        App.toast('Группа создана', 'success');
    });

    document.getElementById('btnCreateChannel')?.addEventListener('click', async () => {
        const data = await App.api('/api/actions/create-channel', {
            method: 'POST',
            body: {
                title: document.getElementById('channelTitle').value,
                about: document.getElementById('channelAbout').value,
                megagroup: document.getElementById('channelMegagroup').checked,
                broadcast: !document.getElementById('channelMegagroup').checked,
            }
        });
        showResult(data);
        App.toast('Канал создан', 'success');
    });

    document.getElementById('btnInvite')?.addEventListener('click', async () => {
        const data = await App.api('/api/actions/invite', {
            method: 'POST',
            body: {
                channel_id: +document.getElementById('manageChannelId').value,
                access_hash: +document.getElementById('manageAccessHash').value,
                users: ['user_' + document.getElementById('manageUserId').value],
            }
        });
        showResult(data);
    });

    document.getElementById('btnKick')?.addEventListener('click', async () => {
        const data = await App.api('/api/actions/kick', {
            method: 'POST',
            body: {
                channel_id: +document.getElementById('manageChannelId').value,
                access_hash: +document.getElementById('manageAccessHash').value,
                user_id: +document.getElementById('manageUserId').value,
            }
        });
        showResult(data);
    });

    document.getElementById('btnBlock')?.addEventListener('click', async () => {
        const data = await App.api('/api/actions/block', {
            method: 'POST',
            body: {
                user_id: +document.getElementById('blockUserId').value,
                access_hash: +document.getElementById('blockAccessHash').value,
            }
        });
        showResult(data);
    });

    document.getElementById('btnUnblock')?.addEventListener('click', async () => {
        const data = await App.api('/api/actions/unblock', {
            method: 'POST',
            body: {
                user_id: +document.getElementById('blockUserId').value,
                access_hash: +document.getElementById('blockAccessHash').value,
            }
        });
        showResult(data);
    });
});
</script>

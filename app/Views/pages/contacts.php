<!-- Контакты и пользователи -->

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><i class="bi bi-search"></i> Поиск пользователей</div>
            <div class="card-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="userSearchInput" placeholder="Username или имя">
                    <button class="btn btn-primary" id="btnUserSearch"><i class="bi bi-search"></i></button>
                </div>
                <div id="searchResults"></div>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-people"></i> Контакты</span>
                <button class="btn btn-sm btn-outline-primary" id="btnRefreshContacts">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="contactsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Имя</th>
                                <th>Username</th>
                                <th>Телефон</th>
                                <th>Действия</th>
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

<!-- Модалка информации о пользователе -->
<div class="modal fade" id="userInfoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Информация о пользователе</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userInfoBody">
                <pre class="json-viewer"><code class="language-json"></code></pre>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadContacts();

    document.getElementById('btnRefreshContacts')?.addEventListener('click', loadContacts);
    document.getElementById('btnUserSearch')?.addEventListener('click', searchUsers);
    document.getElementById('userSearchInput')?.addEventListener('keydown', e => {
        if (e.key === 'Enter') searchUsers();
    });

    async function loadContacts() {
        const data = await App.api('/api/contacts');
        const tbody = document.querySelector('#contactsTable tbody');
        const users = data.contacts?.users || [];
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">Нет контактов</td></tr>';
            return;
        }
        tbody.innerHTML = users.map(u => `
            <tr>
                <td>${u.id}</td>
                <td>${u.first_name || ''} ${u.last_name || ''}</td>
                <td>${u.username ? '@' + u.username : '—'}</td>
                <td>${u.phone || '—'}</td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="showUserInfo(${u.id})">
                        <i class="bi bi-info-circle"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    async function searchUsers() {
        const q = document.getElementById('userSearchInput').value;
        if (!q) return;
        const data = await App.api('/api/users/search?q=' + encodeURIComponent(q));
        const el = document.getElementById('searchResults');
        const users = data.result?.users || [];
        if (users.length === 0) {
            el.innerHTML = '<p class="text-muted">Ничего не найдено</p>';
            return;
        }
        el.innerHTML = users.map(u => `
            <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                <div>
                    <strong>${u.first_name || ''} ${u.last_name || ''}</strong>
                    ${u.username ? '<small class="text-muted"> @' + u.username + '</small>' : ''}
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="showUserInfo(${u.id})">
                    <i class="bi bi-info-circle"></i>
                </button>
            </div>
        `).join('');
    }

    window.showUserInfo = async (id) => {
        const data = await App.api('/api/users/' + id);
        const modal = new bootstrap.Modal(document.getElementById('userInfoModal'));
        const code = document.querySelector('#userInfoBody code');
        code.textContent = JSON.stringify(data.user, null, 2);
        hljs.highlightElement(code);
        modal.show();
    };
});
</script>

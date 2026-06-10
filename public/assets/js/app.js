/**
 * TestTelega — основной JS-модуль
 * API-обёртка, toast, тема, sidebar, статус подключения
 */

const App = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

    /**
     * Универсальный API-запрос с CSRF.
     */
    async api(url, options = {}) {
        const opts = {
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
                ...(options.headers || {}),
            },
        };

        if (options.body) {
            opts.body = JSON.stringify(options.body);
        }

        try {
            const res = await fetch(url, opts);
            const data = await res.json();

            if (!res.ok) {
                this.toast(data.error || `Ошибка ${res.status}`, 'danger');
                return data;
            }

            return data;
        } catch (err) {
            this.toast('Ошибка сети: ' + err.message, 'danger');
            throw err;
        }
    },

    /**
     * Toast-уведомление.
     */
    toast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        const id = 'toast-' + Date.now();
        const bgClass = {
            success: 'bg-success',
            danger: 'bg-danger',
            warning: 'bg-warning',
            info: 'bg-info',
        }[type] || 'bg-info';

        const html = `
            <div id="${id}" class="toast align-items-center text-white ${bgClass} border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        `;
        container.insertAdjacentHTML('beforeend', html);
        const el = document.getElementById(id);
        const toast = new bootstrap.Toast(el, { delay: 4000 });
        toast.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    },

    /**
     * Подсветка JSON.
     */
    highlightJson(element) {
        if (typeof hljs !== 'undefined') {
            hljs.highlightElement(element);
        }
    },
};

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', () => {
    // Sidebar toggle (mobile)
    const sidebar = document.getElementById('sidebar');
    document.getElementById('sidebarToggle')?.addEventListener('click', () => {
        sidebar?.classList.add('open');
    });
    document.getElementById('sidebarClose')?.addEventListener('click', () => {
        sidebar?.classList.remove('open');
    });

    // Theme toggle
    const themeToggle = document.getElementById('themeToggle');
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-bs-theme', savedTheme);
    updateThemeIcon(savedTheme);

    themeToggle?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-bs-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('theme', next);
        updateThemeIcon(next);
    });

    function updateThemeIcon(theme) {
        const icon = themeToggle?.querySelector('i');
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
    }

    // Статус подключения
    checkConnectionStatus();
    setInterval(checkConnectionStatus, 30000);

    async function checkConnectionStatus() {
        const el = document.getElementById('connectionStatus');
        if (!el) return;

        try {
            const data = await App.api('/api/auth/status');
            const dot = el.querySelector('.bi-circle-fill');
            if (data.logged_in) {
                dot.className = 'bi bi-circle-fill text-success';
                el.lastChild.textContent = ' Подключён';
            } else {
                dot.className = 'bi bi-circle-fill text-warning';
                el.lastChild.textContent = ' Не авторизован';
            }
        } catch {
            const dot = el.querySelector('.bi-circle-fill');
            dot.className = 'bi bi-circle-fill text-danger';
            el.lastChild.textContent = ' Ошибка';
        }
    }
});

/**
 * Авторизация Telegram — 3-шаговый процесс
 */

document.addEventListener('DOMContentLoaded', () => {
    // Если уже авторизован — загрузить данные пользователя
    if (!document.getElementById('authSteps')?.classList.contains('d-none')) {
        checkOrphanSession();
    } else {
        loadAuthUser();
    }

    async function checkOrphanSession() {
        const status = await App.api('/api/auth/status');
        if (!status.madeline_logged_in || status.logged_in) {
            return;
        }

        const alert = document.getElementById('orphanSessionAlert');
        const sessionIdEl = document.getElementById('orphanSessionId');
        const sessionInput = document.getElementById('sessionIdInput');
        const sessionId = status.session_id || 'default';

        if (alert) {
            alert.classList.remove('d-none');
        }
        if (sessionIdEl) {
            sessionIdEl.textContent = sessionId;
        }
        if (sessionInput && !sessionInput.value) {
            sessionInput.value = sessionId;
        }
    }

    async function resetSession(sessionId) {
        const id = sessionId || document.getElementById('sessionIdInput')?.value.trim() || 'default';
        if (!confirm('Удалить сессию «' + id + '» и выйти из аккаунта Telegram на этом сервере?')) {
            return false;
        }

        const data = await App.api('/api/auth/reset-session', {
            method: 'POST',
            body: { session_id: id },
        });

        if (data.status === 'ok') {
            App.toast('Сессия сброшена. Можно войти под другим номером.', 'success');
            document.getElementById('orphanSessionAlert')?.classList.add('d-none');
            return true;
        }

        App.toast(data.error || 'Сессия не найдена', 'warning');
        return false;
    }

    async function loadAuthUser() {
        const el = document.getElementById('authUserInfo');
        if (!el) return;
        const me = await App.api('/api/auth/me');
        const u = me.user || {};
        el.innerHTML = `
            <div class="account-avatar mx-auto mb-3" style="width:80px;height:80px;font-size:2rem;">
                <i class="bi bi-person-check-fill text-success"></i>
            </div>
            <h4>${(u.first_name || '') + ' ' + (u.last_name || '')}</h4>
            ${u.username ? '<p class="text-muted">@' + u.username + '</p>' : ''}
            <p class="text-muted">ID: ${u.id || 0} | ${u.phone || ''}</p>`;
    }

    const stepPhone = document.getElementById('stepPhone');
    const stepCode = document.getElementById('stepCode');
    const step2fa = document.getElementById('step2fa');

    // Шаг 1: Отправка телефона
    document.getElementById('btnSendPhone')?.addEventListener('click', async () => {
        const phone = document.getElementById('phoneInput').value.trim();
        const sessionId = document.getElementById('sessionIdInput').value.trim();

        if (!phone) {
            App.toast('Введите номер телефона', 'warning');
            return;
        }

        const btn = document.getElementById('btnSendPhone');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Отправка...';

        const data = await App.api('/api/auth/phone', {
            method: 'POST',
            body: { phone, session_id: sessionId || 'default' },
        });

        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Отправить код';

        if (data.code === 'already_logged_in') {
            const reset = confirm(
                'Этот инстанс MadelineProto уже залогинен.\n\nСбросить сессию и войти под другим номером?'
            );
            if (reset) {
                const ok = await resetSession(sessionId || 'default');
                if (ok) {
                    App.toast('Повторите отправку кода', 'info');
                }
            }
            return;
        }

        if (data.status === 'code_required' || !data.error) {
            stepPhone.classList.add('d-none');
            stepCode.classList.remove('d-none');
            document.getElementById('codeInput').focus();
            App.toast('Код отправлен в Telegram', 'success');
        }
    });

    // Шаг 2: Ввод кода
    document.getElementById('btnSendCode')?.addEventListener('click', async () => {
        const code = document.getElementById('codeInput').value.trim();
        if (!code) {
            App.toast('Введите код', 'warning');
            return;
        }

        const btn = document.getElementById('btnSendCode');
        btn.disabled = true;

        const data = await App.api('/api/auth/code', {
            method: 'POST',
            body: { code },
        });

        btn.disabled = false;

        if (data.status === '2fa_required') {
            stepCode.classList.add('d-none');
            step2fa.classList.remove('d-none');
            document.getElementById('password2faInput').focus();
            App.toast('Требуется пароль 2FA', 'warning');
        } else if (data.status === 'ok') {
            App.toast('Авторизация успешна!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    });

    // Шаг 3: 2FA
    document.getElementById('btnSend2fa')?.addEventListener('click', async () => {
        const password = document.getElementById('password2faInput').value;
        if (!password) {
            App.toast('Введите пароль 2FA', 'warning');
            return;
        }

        const data = await App.api('/api/auth/2fa', {
            method: 'POST',
            body: { password },
        });

        if (data.status === 'ok') {
            App.toast('Авторизация успешна!', 'success');
            setTimeout(() => location.reload(), 1000);
        }
    });

    // Назад к телефону
    document.getElementById('btnBackPhone')?.addEventListener('click', () => {
        stepCode.classList.add('d-none');
        stepPhone.classList.remove('d-none');
    });

    // Выход
    document.getElementById('btnLogout')?.addEventListener('click', async () => {
        if (!confirm('Выйти из аккаунта Telegram?')) return;
        await App.api('/api/auth/logout', { method: 'POST' });
        App.toast('Вы вышли из аккаунта', 'success');
        setTimeout(() => location.reload(), 1000);
    });

    document.getElementById('btnResetSession')?.addEventListener('click', async () => {
        const ok = await resetSession(document.getElementById('sessionIdInput')?.value.trim());
        if (ok) {
            setTimeout(() => location.reload(), 800);
        }
    });
});

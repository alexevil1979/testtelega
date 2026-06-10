<!-- Авторизация Telegram -->

<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-shield-lock"></i> Авторизация Telegram
            </div>
            <div class="card-body">
                <div id="authLoggedIn" class="<?= $isLoggedIn ? '' : 'd-none' ?>">
                    <div class="text-center py-3" id="authUserInfo">
                        <div class="spinner-border spinner-border-sm"></div>
                    </div>
                    <button class="btn btn-danger mt-3 w-100" id="btnLogout">
                        <i class="bi bi-box-arrow-right"></i> Выйти
                    </button>
                </div>

                <div id="authSteps" class="<?= $isLoggedIn ? 'd-none' : '' ?>">
                    <!-- Форма авторизации (3 шага) -->
                        <!-- Шаг 1: Телефон -->
                        <div class="auth-step" id="stepPhone">
                            <div class="mb-3">
                                <label class="form-label">Номер телефона</label>
                                <input type="tel" class="form-control form-control-lg" id="phoneInput"
                                       placeholder="+79001234567" autocomplete="tel">
                                <div class="form-text">В международном формате с кодом страны</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ID сессии (опционально)</label>
                                <input type="text" class="form-control" id="sessionIdInput"
                                       placeholder="default" value="default">
                            </div>
                            <button class="btn btn-primary w-100" id="btnSendPhone">
                                <i class="bi bi-send"></i> Отправить код
                            </button>
                        </div>

                        <!-- Шаг 2: Код -->
                        <div class="auth-step d-none" id="stepCode">
                            <div class="text-center mb-4">
                                <i class="bi bi-envelope-check display-4 text-primary"></i>
                                <p class="mt-2">Код отправлен в Telegram</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Код подтверждения</label>
                                <input type="text" class="form-control form-control-lg text-center"
                                       id="codeInput" placeholder="12345" maxlength="6" autocomplete="one-time-code">
                            </div>
                            <button class="btn btn-primary w-100" id="btnSendCode">
                                <i class="bi bi-check-lg"></i> Подтвердить
                            </button>
                            <button class="btn btn-link w-100 mt-2" id="btnBackPhone">Назад</button>
                        </div>

                        <!-- Шаг 3: 2FA -->
                        <div class="auth-step d-none" id="step2fa">
                            <div class="text-center mb-4">
                                <i class="bi bi-key display-4 text-warning"></i>
                                <p class="mt-2">Требуется пароль двухфакторной аутентификации</p>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Пароль 2FA</label>
                                <input type="password" class="form-control form-control-lg"
                                       id="password2faInput" placeholder="Пароль" autocomplete="current-password">
                            </div>
                            <button class="btn btn-warning w-100" id="btnSend2fa">
                                <i class="bi bi-unlock"></i> Войти
                            </button>
                        </div>
                </div>
            </div>
        </div>

        <!-- Информация о сессии -->
        <div class="card mt-4">
            <div class="card-header"><i class="bi bi-info-circle"></i> Информация</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0 small text-muted">
                    <li><i class="bi bi-check2"></i> Сессии хранятся вне web-root (папка <code>sessions/</code>)</li>
                    <li><i class="bi bi-check2"></i> Все MTProto-вызовы логируются в API Логгер</li>
                    <li><i class="bi bi-check2"></i> Для работы нужны <code>API_ID</code> и <code>API_HASH</code> из my.telegram.org</li>
                </ul>
            </div>
        </div>
    </div>
</div>

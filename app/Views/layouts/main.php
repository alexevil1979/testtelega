<!DOCTYPE html>
<html lang="ru" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <title><?= htmlspecialchars($title ?? 'TestTelega') ?> — <?= htmlspecialchars($app['name']) ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Highlight.js для JSON -->
    <link href="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/styles/atom-one-dark.min.css" rel="stylesheet">
    <!-- Кастомные стили -->
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
    <!-- Боковое меню -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <i class="bi bi-telegram"></i>
                <span>TestTelega</span>
            </div>
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarClose">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <a href="/" class="nav-item <?= ($page ?? '') === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-speedometer2"></i> Дашборд
            </a>
            <a href="/auth" class="nav-item <?= ($page ?? '') === 'auth' ? 'active' : '' ?>">
                <i class="bi bi-shield-lock"></i> Авторизация
            </a>
            <a href="/chats" class="nav-item <?= ($page ?? '') === 'chats' ? 'active' : '' ?>">
                <i class="bi bi-chat-dots"></i> Диалоги
            </a>
            <a href="/contacts" class="nav-item <?= ($page ?? '') === 'contacts' ? 'active' : '' ?>">
                <i class="bi bi-people"></i> Контакты
            </a>
            <a href="/actions" class="nav-item <?= ($page ?? '') === 'actions' ? 'active' : '' ?>">
                <i class="bi bi-lightning"></i> Действия
            </a>
            <a href="/logger" class="nav-item <?= ($page ?? '') === 'logger' ? 'active' : '' ?>">
                <i class="bi bi-bug"></i> API Логгер
            </a>
            <a href="/settings" class="nav-item <?= ($page ?? '') === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i> Настройки
            </a>
        </nav>

        <div class="sidebar-footer">
            <button class="btn btn-sm btn-outline-secondary w-100" id="themeToggle">
                <i class="bi bi-moon-stars"></i> Тема
            </button>
        </div>
    </div>

    <!-- Основной контент -->
    <div class="main-content">
        <!-- Верхняя панель -->
        <header class="topbar">
            <button class="btn btn-sm btn-outline-secondary d-lg-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            <h1 class="page-title"><?= htmlspecialchars($title ?? '') ?></h1>
            <div class="topbar-actions">
                <span class="status-badge" id="connectionStatus">
                    <i class="bi bi-circle-fill text-secondary"></i> Проверка...
                </span>
            </div>
        </header>

        <!-- Контент страницы -->
        <main class="content-area">
            <?= $content ?>
        </main>
    </div>

    <!-- Toast-уведомления -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer"></div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/core.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/highlight.js@11.9.0/lib/languages/json.min.js"></script>
    <script src="/assets/js/app.js"></script>
    <?php if (($page ?? '') === 'logger'): ?>
    <script src="/assets/js/api-logger.js"></script>
    <?php endif; ?>
    <?php if (($page ?? '') === 'auth'): ?>
    <script src="/assets/js/auth.js"></script>
    <?php endif; ?>
    <?php if (($page ?? '') === 'chats'): ?>
    <script src="/assets/js/chats.js"></script>
    <?php endif; ?>
</body>
</html>

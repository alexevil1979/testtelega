<!-- Диалоги и чаты -->

<div class="chat-layout">
    <!-- Список диалогов -->
    <div class="chat-sidebar">
        <div class="chat-sidebar-header">
            <input type="text" class="form-control form-control-sm" id="chatSearch" placeholder="Поиск чатов...">
        </div>
        <div class="chat-list" id="chatList">
            <div class="text-center text-muted py-4">
                <div class="spinner-border spinner-border-sm"></div>
                <p class="mt-2 mb-0">Загрузка диалогов...</p>
            </div>
        </div>
    </div>

    <!-- Область сообщений -->
    <div class="chat-main">
        <div class="chat-main-empty" id="chatEmpty">
            <i class="bi bi-chat-square-text display-1 text-muted"></i>
            <p class="text-muted mt-3">Выберите чат для просмотра сообщений</p>
        </div>

        <div class="chat-main-active d-none" id="chatActive">
            <!-- Заголовок чата -->
            <div class="chat-header" id="chatHeader">
                <h5 id="chatTitle">—</h5>
                <div class="chat-header-actions">
                    <button class="btn btn-sm btn-outline-secondary" id="btnChatInfo" title="Информация">
                        <i class="bi bi-info-circle"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" id="btnChatRefresh" title="Обновить">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>

            <!-- Сообщения -->
            <div class="chat-messages" id="chatMessages"></div>

            <!-- Ввод сообщения -->
            <div class="chat-input">
                <form id="messageForm" class="d-flex gap-2">
                    <label class="btn btn-outline-secondary btn-sm mb-0" title="Прикрепить файл">
                        <i class="bi bi-paperclip"></i>
                        <input type="file" id="fileInput" class="d-none">
                    </label>
                    <input type="text" class="form-control" id="messageInput" placeholder="Сообщение..." autocomplete="off">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-send"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

# TestTelega — Telegram Web Client / Tester

Веб-приложение для подключения к живому Telegram-аккаунту, выполнения всех пользовательских действий и отладки MTProto-вызовов в реальном времени.

**Домен:** `testtelega.1tlt.ru`  
**Корень:** `/ssd/www/testtelega`

## Стек

- PHP 8.2+ / Apache 2 / MySQL 8
- [MadelineProto](https://github.com/danog/MadelineProto) (MTProto)
- Bootstrap 5 + Vanilla JS
- SSE для realtime-логов

## Возможности

| Раздел | Описание |
|--------|----------|
| **Авторизация** | Телефон → код → 2FA, управление сессиями |
| **Диалоги** | Список чатов, история, отправка/редактирование/удаление, файлы |
| **Контакты** | Поиск, добавление, информация о пользователях |
| **Действия** | Группы, каналы, invite/kick, block/unblock, updates |
| **API Логгер** | Realtime MTProto-логи, фильтры, экспорт JSON/CSV |
| **Настройки** | Сессии, кэш, системная информация |
| **RPC** | Универсальный вызов любого MTProto-метода |

---

## Установка на Ubuntu VPS

> **На этом сервере PHP:** `/usr/local/php82/bin/php` (кастомная сборка).  
> Полная инструкция: [docs/INSTALL_VPS_PHP82.md](docs/INSTALL_VPS_PHP82.md)

### Быстрый старт (кастомный PHP 8.2)

```bash
export PHP_BIN=/usr/local/php82/bin/php
export PROJECT=/ssd/www/testtelega

# Проверка модулей
bash $PROJECT/deploy/check-php82.sh

# Composer — только через кастомный PHP!
cd $PROJECT
$PHP_BIN $(command -v composer) install --no-dev --optimize-autoloader

# Apache FPM vhost
sudo cp deploy/apache-vhost-php82-fpm.conf /etc/apache2/sites-available/testtelega.conf
sudo a2enmod proxy proxy_fcgi rewrite headers
sudo a2ensite testtelega && sudo systemctl reload apache2
```

### 1. Системные пакеты (без apt-php, если используется /usr/local/php82)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server git unzip curl ca-certificates composer
# PHP уже установлен в /usr/local/php82 — apt php8.2 не нужен
```

### 2. MySQL

```bash
sudo mysql -e "CREATE DATABASE testtelega CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'testtelega'@'localhost' IDENTIFIED BY 'ВАШ_ПАРОЛЬ';"
sudo mysql -e "GRANT ALL PRIVILEGES ON testtelega.* TO 'testtelega'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
sudo mysql testtelega < /ssd/www/testtelega/database/schema.sql
```

### 3. Развёртывание проекта

```bash
sudo mkdir -p /ssd/www/testtelega
sudo chown $USER:www-data /ssd/www/testtelega

# Копирование файлов
cd /ssd/www/testtelega
/usr/local/php82/bin/php $(command -v composer) install --no-dev --optimize-autoloader

cp .env.example .env
nano .env   # Заполнить API_ID, API_HASH, DB_PASSWORD, APP_KEY
```

### 4. Telegram API credentials

1. Перейти на https://my.telegram.org/apps
2. Создать приложение
3. Скопировать `api_id` и `api_hash` в `.env`

### 5. Права на папки

```bash
cd /ssd/www/testtelega

# Сессии — только для www-data
chmod 750 sessions
chown www-data:www-data sessions

# Логи — запись для Apache
chmod 775 logs
chown www-data:www-data logs

# .env — только чтение для www-data
chmod 640 .env
chown www-data:www-data .env
```

### 6. Apache VirtualHost

```bash
sudo cp deploy/apache-vhost.conf /etc/apache2/sites-available/testtelega.conf
sudo a2enmod rewrite headers
sudo a2ensite testtelega
sudo a2dissite 000-default   # опционально
sudo systemctl reload apache2
```

### 7. SSL (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d testtelega.1tlt.ru
```

### 8. Проверка

```bash
# PHP-модули (кастомный PHP)
/usr/local/php82/bin/php -m | grep -E 'openssl|curl|pdo_mysql|gmp|mbstring|xml|zip|bcmath|pcntl'

# Права
ls -la sessions/ logs/

# Открыть в браузере
curl -I https://testtelega.1tlt.ru
```

---

## Docker (альтернатива)

```bash
cp .env.example .env
# Заполнить TELEGRAM_API_ID и TELEGRAM_API_HASH

docker-compose up -d
# Приложение: http://localhost:8080
```

---

## Структура проекта

```
/ssd/www/testtelega/
├── public/              # DocumentRoot Apache
│   ├── index.php        # Точка входа
│   ├── .htaccess        # Rewrite rules
│   └── assets/          # CSS, JS
├── app/
│   ├── Bootstrap.php    # Инициализация
│   ├── Router.php       # Маршрутизация
│   ├── Database.php     # PDO
│   ├── View.php         # Шаблоны
│   ├── Controllers/     # Контроллеры
│   ├── Services/        # TelegramService, MtProtoLogger
│   ├── Middleware/      # CSRF, RateLimit
│   └── Views/           # PHP-шаблоны
├── config/              # app.php, database.php, routes.php
├── database/            # schema.sql
├── sessions/            # MadelineProto сессии (вне web-root)
├── logs/                # MTProto логи
├── deploy/              # Apache vhost
├── .env                 # Настройки (не в git)
├── composer.json
├── Dockerfile
└── docker-compose.yml
```

---

## Безопасность

- Сессии MadelineProto хранятся в `sessions/` (вне `public/`)
- CSRF-токен на всех POST-запросах
- Rate limiting (60 req/min по IP)
- Чувствительные данные маскируются в логах
- Apache блокирует доступ к `app/`, `config/`, `sessions/`, `vendor/`

---

## API Endpoints

### Авторизация
- `POST /api/auth/phone` — отправить номер
- `POST /api/auth/code` — ввести код
- `POST /api/auth/2fa` — пароль 2FA
- `GET /api/auth/status` — статус
- `POST /api/auth/logout` — выход

### Чаты
- `GET /api/chats` — список диалогов
- `GET /api/chats/{id}/messages` — история
- `POST /api/chats/{id}/send` — отправить
- `POST /api/chats/{id}/upload` — файл

### Логгер
- `GET /api/logger/stream` — SSE realtime
- `GET /api/logger/list` — история
- `GET /api/logger/export?format=json|csv` — экспорт

### RPC (отладка)
- `POST /api/rpc` — `{"method": "messages.getDialogs", "params": {...}}`

---

## Лицензия

MIT

# Установка TestTelega на VPS с кастомным PHP

**Домен:** `testtelega.1tlt.ru`  
**Путь:** `/ssd/www/testtelega`  
**PHP:** `/usr/local/php82/bin/php` (кастомная сборка, не apt)

---

## 0. Переменные окружения (удобно для копирования)

```bash
export PHP_BIN=/usr/local/php82/bin/php
export PHP_INI=/usr/local/php82/etc/php.ini
export PROJECT=/ssd/www/testtelega
```

---

## 1. Проверка PHP

```bash
$PHP_BIN -v
$PHP_BIN --ini
$PHP_BIN -m
```

Обязательные модули для MadelineProto + Composer:

```
openssl, curl, mbstring, xml, zip, bcmath, pdo_mysql, pcntl, json, intl

> **gmp** — опционально (ускоряет криптографию). Без него MadelineProto работает через **bcmath**.
```

Проверка одной командой:

```bash
$PHP_BIN -m | grep -E 'openssl|curl|gmp|pcntl|mbstring|pdo_mysql|zip|xml|bcmath|intl'
```

---

## 2. Включение openssl и curl (если отсутствуют)

### Вариант A — расширения уже скомпилированы, нужно включить в php.ini

```bash
nano $PHP_INI
```

Найти и раскомментировать (убрать `;`):

```ini
extension=openssl
extension=curl
extension=mbstring
extension=xml
extension=zip
extension=gmp
extension=bcmath
extension=pdo_mysql
extension=mysqli
extension=intl
extension=pcntl
```

Если указаны `.so` файлы — путь обычно такой:

```ini
extension_dir = "/usr/local/php82/lib/php/extensions/no-debug-non-zts-20220829"
extension=openssl
extension=curl
```

> Номер папки `20220829` может отличаться — смотрите содержимое:
> `ls /usr/local/php82/lib/php/extensions/`

### Вариант B — расширений нет, нужна пересборка PHP

При `./configure` для PHP 8.2 должны быть:

```bash
--with-openssl --with-curl --with-zlib --enable-mbstring --with-gmp \
--enable-bcmath --with-pdo-mysql --enable-pcntl --with-intl
```

После пересборки:

```bash
sudo systemctl restart apache2
# если используется php-fpm:
sudo /usr/local/php82/sbin/php-fpm restart
```

---

## 3. Composer через кастомный PHP

Системный `composer` может вызывать **другой** `php`. Всегда запускайте так:

```bash
cd $PROJECT

# Установка зависимостей
$PHP_BIN $(command -v composer) install --no-dev --optimize-autoloader

# Если composer не найден — скачать и вызывать явно:
# curl -sS https://getcomposer.org/installer | $PHP_BIN
# $PHP_BIN composer.phar install --no-dev --optimize-autoloader
```

Проверка, какой PHP использует composer:

```bash
composer -V
head -1 $(command -v composer)   # shebang — часто /usr/bin/env php
$PHP_BIN -v                        # должен совпадать major/minor (8.2)
```

---

## 4. MySQL

```bash
sudo mysql <<'EOF'
CREATE DATABASE IF NOT EXISTS testtelega CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'testtelega'@'localhost' IDENTIFIED BY 'ВАШ_ПАРОЛЬ';
GRANT ALL PRIVILEGES ON testtelega.* TO 'testtelega'@'localhost';
FLUSH PRIVILEGES;
EOF

mysql -u testtelega -p testtelega < $PROJECT/database/schema.sql
```

---

## 5. Клонирование и настройка проекта

```bash
sudo mkdir -p /ssd/www
sudo chown $USER:www-data /ssd/www

cd /ssd/www
git clone https://github.com/alexevil1979/testtelega.git testtelega
cd $PROJECT

git pull origin main
$PHP_BIN $(command -v composer) install --no-dev --optimize-autoloader

cp .env.example .env
nano .env
```

`.env` — минимум:

```env
APP_URL=https://testtelega.1tlt.ru
APP_KEY=$(openssl rand -hex 16)
TELEGRAM_API_ID=...
TELEGRAM_API_HASH=...
DB_DATABASE=testtelega
DB_USERNAME=testtelega
DB_PASSWORD=...
```

---

## 6. Права

```bash
cd $PROJECT

sudo chown -R www-data:www-data sessions logs .env
sudo chmod 750 sessions
sudo chmod 775 logs
sudo chmod 640 .env
```

---

## 7. Apache + кастомный PHP 8.2

Системный `libapache2-mod-php8.2` из apt **не подходит**, если сайт должен работать на `/usr/local/php82`.

### Вариант A — PHP-FPM (рекомендуется)

1. Убедитесь, что запущен FPM от кастомного PHP:

```bash
# путь может отличаться на вашем сервере
ls /usr/local/php82/var/run/php-fpm.sock
# или
ls /usr/local/php82/var/run/www.sock
```

2. Включите модули Apache:

```bash
sudo a2enmod proxy proxy_fcgi rewrite headers
```

3. Используйте vhost из репозитория:

```bash
sudo cp $PROJECT/deploy/apache-vhost-php82-fpm.conf \
  /etc/apache2/sites-available/testtelega.conf
```

4. Отредактируйте путь к сокету FPM в конфиге, если он другой.

5. Активация:

```bash
sudo a2ensite testtelega
sudo apache2ctl configtest
sudo systemctl reload apache2
```

### Вариант B — mod_php от кастомной сборки

Если в Apache уже подключён `LoadModule php_module /usr/local/php82/.../libphp.so` — используйте:

```bash
sudo cp $PROJECT/deploy/apache-vhost.conf /etc/apache2/sites-available/testtelega.conf
```

Настройки `php_value` в vhost работают только с mod_php.

---

## 8. SSL

Сертификат уже выпущен, но certbot не смог установить в Apache?  
Ошибка: `Could not reverse map the HTTPS VirtualHost` — установите вручную:

```bash
cd $PROJECT
sudo bash deploy/ssl-fix.sh
```

Или вручную:

```bash
sudo cp $PROJECT/deploy/apache-vhost-php82-fpm-ssl.conf \
  /etc/apache2/sites-available/testtelega.conf
sudo a2enmod ssl proxy proxy_fcgi rewrite headers
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Первый выпуск сертификата (если ещё нет):

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot certonly --apache -d testtelega.1tlt.ru
sudo bash $PROJECT/deploy/ssl-fix.sh
```

---

## 8.1. Прокси SOCKS5 (обязательно для Telegram)

В `.env`:

```env
PROXY_ENABLED=true
PROXY_URL=socks5://127.0.0.1:1084
HTTP_API_PROXY_URL=socks5://127.0.0.1:1084
```

Убедитесь, что SOCKS5-прокси запущен на сервере:

```bash
ss -tlnp | grep 1084
```

Настройки можно менять в UI: **Настройки → Прокси**.

---

## 9. Проверка

```bash
# CLI
$PHP_BIN $PROJECT/public/index.php 2>&1 | head

# Модули
$PHP_BIN -m | grep -E 'openssl|curl|gmp|pcntl'

# Сайт
curl -I https://testtelega.1tlt.ru

# Логи Apache
sudo tail -f /var/log/apache2/testtelega-error.log
```

Создайте тестовый файл (удалите после проверки):

```bash
echo '<?php phpinfo();' | sudo tee $PROJECT/public/_phpinfo.php
# откройте https://testtelega.1tlt.ru/_phpinfo.php
# убедитесь: PHP Version 8.2.x, Loaded Configuration File = /usr/local/php82/etc/php.ini
sudo rm $PROJECT/public/_phpinfo.php
```

---

## 10. Обновление проекта

```bash
cd $PROJECT
git pull origin main
$PHP_BIN $(command -v composer) install --no-dev --optimize-autoloader
sudo systemctl reload apache2
```

---

## Быстрый чеклист (ваш сервер)

```bash
export PHP_BIN=/usr/local/php82/bin/php
export PROJECT=/ssd/www/testtelega

$PHP_BIN -m | grep -E 'openssl|curl'     # оба должны быть
cd $PROJECT && git pull origin main
$PHP_BIN $(command -v composer) install --no-dev --optimize-autoloader
```

---

## 11. MadelineProto IPC (авторизация Telegram)

При отправке номера телефона ошибка:

```
Could not connect to MadelineProto, please enable proc_open...
```

**Причины:**
1. IPC worker запускается через `proc_open` с PHP из `PATH`.
2. Если первым в PATH оказывается `/usr/bin/php` (apt) без `mbstring`, в логе будет:
   `MadelineProto requires the mbstring extension to run. Try running sudo apt-get install php8.2-mbstring`
3. Нужен **тот же** бинарник, что и FPM: `/usr/local/php82/bin/php`.

В botfabric это решалось явным `command=/usr/local/php82/bin/php ...` в Supervisor (CLI, без FPM).  
В TestTelega приложение принудительно задаёт PHP для IPC через `MadelineEnvironment` + `PHP_BIN` в `.env`.

**Исправление одной командой:**

```bash
cd $PROJECT
git pull origin main
sudo bash deploy/fix-madelineproto-ipc.sh
```

Скрипт:
- убирает `proc_open`, `popen` из `disable_functions` в `php.ini`;
- задаёт `open_basedir` с путями проекта, `/tmp`, `/usr/local/php82`;
- прописывает `env[PATH]=/usr/local/php82/bin:...` в PHP-FPM pool;
- перезапускает FPM.

В `.env` добавьте (если ещё нет):

```env
PHP_BIN=/usr/local/php82/bin/php
```

Если сессия «зависла» после неудачных попыток:

```bash
bash deploy/reset-session.sh default
```

Проверка лога:

```bash
tail -50 $PROJECT/logs/MadelineProto.log
```

Ошибки Apache `php8.2-fpm.sock` — vhost смотрит на apt-php, а не на кастомный FPM:

```bash
sudo bash deploy/fix-apache-php82.sh
```

---

## Частые ошибки

| Ошибка | Решение |
|--------|---------|
| `You must enable the openssl extension` | Включить `extension=openssl` в `/usr/local/php82/etc/php.ini` |
| `PHP curl extension enabled` (warning) | Включить `extension=curl` в том же php.ini |
| Composer тянет не тот PHP | Запускать `$PHP_BIN $(command -v composer) ...` |
| Сайт показывает другую версию PHP | Apache использует не php82 — настроить FPM vhost |
| 500 после деплоя | `tail /var/log/apache2/testtelega-error.log` |
| `Could not connect to MadelineProto` | `sudo bash deploy/fix-madelineproto-ipc.sh` |
| `requires the mbstring extension` в MadelineProto.log | IPC взял `/usr/bin/php` — `git pull`, `PHP_BIN` в `.env`, `fix-madelineproto-ipc.sh` |
| `php8.2-fpm.sock` в Apache error log | `sudo bash deploy/fix-apache-php82.sh` |

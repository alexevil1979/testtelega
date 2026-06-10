-- TestTelega — схема базы данных MySQL 8
-- Домен: testtelega.1tlt.ru

CREATE DATABASE IF NOT EXISTS testtelega
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE testtelega;

-- Логи MTProto-вызовов
CREATE TABLE IF NOT EXISTS mtproto_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    method VARCHAR(255) NOT NULL,
    params JSON,
    response JSON,
    duration_ms DECIMAL(10, 2) DEFAULT 0,
    error TEXT NULL,
    category VARCHAR(50) DEFAULT 'general',
    session_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_method (method),
    INDEX idx_category (category),
    INDEX idx_created_at (created_at),
    INDEX idx_session_id (session_id)
) ENGINE=InnoDB;

-- Настройки приложения (key-value)
CREATE TABLE IF NOT EXISTS app_settings (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` JSON NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Кэш чатов (опционально, для ускорения UI)
CREATE TABLE IF NOT EXISTS chat_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    peer_id VARCHAR(100) NOT NULL,
    peer_type ENUM('user', 'chat', 'channel') NOT NULL,
    title VARCHAR(500),
    data JSON,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_peer (peer_id, peer_type),
    INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB;

-- Пользователь приложения (для будущей мульти-авторизации)
CREATE TABLE IF NOT EXISTS app_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Начальные настройки
INSERT INTO app_settings (`key`, `value`) VALUES
    ('theme', '"dark"'),
    ('log_retention_days', '30'),
    ('auto_refresh_interval', '30')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

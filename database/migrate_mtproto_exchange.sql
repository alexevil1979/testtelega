-- Миграция: полный MTProto exchange log (parsed + raw)
USE testtelega;

-- Если колонка уже есть — пропустите ошибку
ALTER TABLE mtproto_logs
    ADD COLUMN exchange JSON NULL COMMENT 'request/response parsed+raw' AFTER response;

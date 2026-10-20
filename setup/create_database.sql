-- create_database.sql
-- Создание схемы базы данных для учета типов сетевых устройств и устройств.
-- Входные параметры: нет; скрипт создает базу switches и таблицы, если их нет.
-- Выход: база данных switches с таблицами dev_type и devices.
-- Пример вызова: mysql -u root -p < setup/create_database.sql
-- @author  Pavel Dik: paveldik@gmail.com
-- @version 1.0

CREATE DATABASE IF NOT EXISTS `switches`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `switches`;

CREATE TABLE IF NOT EXISTS `dev_type` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `devtype` VARCHAR(64) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dev_type_devtype` (`devtype`)
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `devices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL,
  `type_id` INT UNSIGNED NOT NULL,
  `devname` VARCHAR(255) NOT NULL,
  `blocked` TINYINT(1) NOT NULL DEFAULT 0,
  `comment` TEXT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_devices_ip_address` (`ip_address`),
  KEY `idx_devices_type_ip` (`type_id`, `ip_address`),
  CONSTRAINT `fk_devices_dev_type`
    FOREIGN KEY (`type_id`) REFERENCES `dev_type` (`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

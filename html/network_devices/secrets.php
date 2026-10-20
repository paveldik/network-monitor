<?php
/*
 * secrets.php
 *
 * Чтение общих параметров приложения из системного файла конфигурации,
 * расположенного вне каталога веб-приложения.
 *
 * Расположение: /html/network_devices/secrets.php
 * Входные параметры: нет; читает /etc/network-monitor/network-monitor.cfg.
 * Выход: функции network_monitor_config() и network_monitor_setting() возвращают
 * конфигурацию и отдельные значения.
 * Пример вызова: require_once __DIR__.'/secrets.php'; network_monitor_setting('DB_HOST');
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */

const NETWORK_MONITOR_CONFIG_PATH = '/etc/network-monitor/network-monitor.cfg';

function network_monitor_config()
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    if (!is_readable(NETWORK_MONITOR_CONFIG_PATH)) {
        throw new RuntimeException('Не удалось прочитать системный файл конфигурации приложения.');
    }

    $lines = file(NETWORK_MONITOR_CONFIG_PATH, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException('Не удалось прочитать системный файл конфигурации приложения.');
    }

    $parsedConfig = array();
    foreach ($lines as $lineNumber => $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (strpos($line, '=') === false) {
            throw new RuntimeException('Некорректная строка в системном файле конфигурации: '.($lineNumber + 1));
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        if (!preg_match('/^[A-Z][A-Z0-9_]*$/', $name)) {
            throw new RuntimeException('Некорректное имя параметра в системном файле конфигурации: '.($lineNumber + 1));
        }
        if (array_key_exists($name, $parsedConfig)) {
            throw new RuntimeException('Повторяется параметр в системном файле конфигурации: '.$name);
        }
        $parsedConfig[$name] = trim($value);
    }

    $config = $parsedConfig;
    return $config;
}

function network_monitor_setting($name)
{
    $config = network_monitor_config();
    if (!isset($config[$name]) || $config[$name] === '') {
        throw new RuntimeException('В системном файле конфигурации отсутствует обязательный параметр: '.$name);
    }
    return $config[$name];
}

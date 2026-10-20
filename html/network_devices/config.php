<?php
/*
 * Подключение к базе данных приложения и загрузка параметров из системного файла.
 *
 * Расположение: /html/network_devices/config.php
 * Входные параметры: нет; читает DB_* из /etc/network-monitor/network-monitor.cfg.
 * Выход: константы DB_* и открытое соединение MySQL в переменной $connect.
 * Пример вызова: require_once __DIR__.'/config.php';
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
require_once __DIR__.'/secrets.php';

define('DB_SERVER', network_monitor_setting('DB_HOST'));
define('DB_PORT', (int) network_monitor_setting('DB_PORT'));
define('DB_USERNAME', network_monitor_setting('DB_USER'));
define('DB_PASSWORD', network_monitor_setting('DB_PASSWORD'));
define('DB_DATABASE', network_monitor_setting('DB_NAME'));

$connect = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
if (!$connect) {
    die('Could not connect: '.mysqli_connect_error());
}
mysqli_query($connect, "SET NAMES utf8");
mysqli_query($connect, "SET CHARACTER SET 'utf8'");
mysqli_query($connect, "SET SESSION collation_connection = 'utf8mb4_general_ci'");
?>

<?php
/*
 * main.php
 *
 * Главное меню приложения со ссылками на управление коммутаторами и просмотр
 * журналов сетевых устройств.
 *
 * Расположение: /html/network_devices/main.php
 * Входные параметры: нет.
 * Выход: HTML-страница меню.
 * Пример вызова: /network_devices/main.php
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
include ('config.php');
include ('lib.php');
#include ('inc/cookies.php');
$self = 'main.php';
$pagename='Меню';
#date_default_timezone_set('Europe/Moscow');
htmlHeader($pagename);
echo '<div id="content">';
echo (!isset($user_role) || $user_role==0 || $user_role==1)?'- <a href="devicelist.php">Настройка коммутаторов</a><br>':'';
echo (!isset($user_role) || $user_role==0 || $user_role==1)?'- <a href="http://network.pmpmilk.ru/LogRead.php?n=50&t=50&tr=1">Чтение логов</a><br>':'';
#echo (!isset($user_role) || $user_role==0 || $user_role==2)?'- <a href="get_xls_form.php">Загрузка событий с флэшки</a><br>':'';
echo '</div>';

Footer();
?>

<?php
/*
 * LogReadDevInventory.php
 *
 * Опрос имен поддерживаемых сетевых устройств по SNMP и обновление файлов
 * инвентарных списков на основе текущих списков устройств.
 *
 * Расположение: /html/LogReadDevInventory.php
 * Входные параметры: нет; использует файлы LogRead/LogRead.<vendor>.list и
 * SNMP community из системной конфигурации приложения.
 * Выход: обновленные файлы списков устройств и диагностический вывод.
 * Пример вызова: php /var/www/html/LogReadDevInventory.php
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
require_once __DIR__.'/network_devices/secrets.php';
#
# требуется пакет apt install php-snmp
#
# Инвентаризируем имена устройств (читаем по snmp их hostname и сохраняем в файлах со списками устройств)
#
# Вызов: из консоли: wget http://network.pmpmilk.ru/LogReadDevInventory.php
#
#
$self='LogReadDevInventory.php';

#Загружаем списки устройств в массивы
foreach (array('LogRead/LogRead.cisco.list', 'LogRead/LogRead.mikrotik.list', 'LogRead/LogRead.hp.list', 'LogRead/LogRead.edgecore.list') as &$value) { // Перебираем файлы со списками устройств
  $element = explode(".", $value);
  $Vendor=$element[1];
  $CheckList=file($value); // $CheckList - массив из строк файла
  foreach ($CheckList as $line) { // Перебираем строки списка из файла
    $element = explode(":", $line); // Разбиваем строку на массив по разделителю ":"
    $DeviceList[]=array($Vendor, $element[0], $element[1]); // Создаем очередной элемент массива: array(Vendor, Ip, DeviceName)
  }
}


function Mikr_GetHostname($ip){
#
# https://bestmonitoringtools.com/mibdb/mibdb_search.php?mib=MIKROTIK-MIB
# тут есть таблица mib
#
  $timeout=50000; // в микросекундах (=50мс)
  if ($ip=='192.168.202.23') $portnum=$portnum+2;
  $mib='.1.3.6.1.2.1.31.1.1.1.18.'.$portnum;
  $answer=snmp2_get($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  if (stripos($answer,'STRING:') !== false){
    preg_match('#^STRING: "(.*)"$#iu',$answer,$test);
    $result=$test[1];
  }
  elseif ($answer=='""') $result='';
  else $result='!error!';
  return $result;
}
function Cisco_GetHostName($ip){
  $timeout=50000; // в микросекундах (=50мс)
  $mib='.1.3.6.1.2.1.1.5.0';
  $answer=snmp2_get($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  if (stripos($answer,'STRING:') !== false){
    preg_match('#^STRING: "(.*)"$#iu',$answer,$test);
    $result=str_replace('\"','',$test[1]);
    $result=str_replace('.pmpmilk.ru','',$result);
  }
  elseif ($answer=='""') $result='';
  else $result='!e!';
  return $result;
}

function Mikrotik_GetHostName($ip){
  $HostName=Cisco_GetHostName($ip);
  $result=preg_replace('/^(\d+)-[\w\-]*[-| ]+([\w_]+)$/i', '$1($2)', $HostName);
  if (strlen($result)>5) $result='Mikrot_'.$result; else $result='';
  return $result;
}


function CheckVendor($ip){ // Возвращает массив array(Vendor,DevName)
  global $DeviceList;
  $Vendor='';
  $DevName='';
  foreach ($DeviceList as $Device) { // Перебираем строки списка из файла
    if ($Device[1]==$ip) {
      $Vendor=$Device[0];
      preg_match('#(\S*)#iu',$Device[2],$DevName); // Обрезаем артефакты, типа концов строк ("\n")
      break;
    }
  }
  $DeviceInfo=array($Vendor, $DevName[1]);
  return $DeviceInfo;
}

function ReplaceFileIfDifferent($LastVendor) {
        if (md5_file('/tmp/LogReadDevInventory.'.$LastVendor.'.list') == md5_file('LogRead/LogRead.'.$LastVendor.'.list')) echo "files the same<br>\n";
#       exec('diff /tmp/LogReadDevInventory.'.$LastVendor.'.list LogRead.'.$LastVendor.'.list', $output, $retval);
#       if (strlen($output[0])=0) echo "files the same<br>\n";
        else {
            echo "files are different<br>\n";
            copy('LogRead/LogRead.'.$LastVendor.'.list', 'LogRead/LogRead.'.$LastVendor.'.list.old'); // делаем резервную копию
            copy('/tmp/LogReadDevInventory.'.$LastVendor.'.list','LogRead/LogRead.'.$LastVendor.'.list'); // заменяем старую
        }
        unlink('/tmp/LogReadDevInventory.'.$LastVendor.'.list'); // удаляем временный файл
}

# перебираем массив со списком устройств
$LastVendor='';
foreach ($DeviceList as $Device) { // Перебираем строки списка из файла
    $Vendor=$Device[0];
    $Ip=$Device[1];
    preg_match('#(\S*)#iu',$Device[2],$DevName); // Обрезаем артефакты, типа концов строк ("\n")

$HostName='';
if ($Vendor=='cisco') $HostName=Cisco_GetHostName($Ip);
if ($Vendor=='mikrotik') $HostName=Mikrotik_GetHostName($Ip);
if (strlen($HostName)<5) $HostName=$DevName[1]; // Не меняем имя, если новое слишком короткое
if ($LastVendor<>$Vendor){
    if (strlen($LastVendor)>0){
        fclose($fd);
        ReplaceFileIfDifferent($LastVendor);
    }
    $LastVendor=$Vendor;
    $fd = fopen('/tmp/LogReadDevInventory.'.$Vendor.'.list', 'w') or die("не удалось создать файл");
}
fwrite($fd, $Ip.':'.$HostName."\n");
echo "$Vendor, $Ip, $DevName[1] | $HostName <br>\n";
}
fclose($fd);
ReplaceFileIfDifferent($LastVendor);

?>

<?php
/*
 * LogReadDevInventorySQL.php
 *
 * Обновление инвентарных списков на основе JSON-списка устройств с SNMP-
 * опросом Cisco и MikroTik; по запросу возвращает обновленный список в JSON.
 *
 * Расположение: /html/LogReadDevInventorySQL.php
 * Входные GET/POST-параметры: devtype и vendor — ограничение типа/поставщика,
 * opt=getjson — запрос JSON, action=test — диагностический вывод.
 * Выход: обновленные файлы LogRead/LogRead.<vendor>.list; при непустом opt —
 * JSON со списком устройств.
 * Пример вызова: /LogReadDevInventorySQL.php?devtype=1&vendor=cisco&opt=getjson
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
require_once __DIR__.'/network_devices/secrets.php';
#
# Инвентаризируем имена устройств (читаем по snmp их hostname и сохраняем в файлах со списками устройств)
#
# Вызов: из консоли: wget http://network.pmpmilk.ru/LogReadDevInventory.php
#
#
$self='LogReadDevInventorySQL.php';
$verbose=0;
#include ('/var/www/html/LogRead/config.php');

function getvar($name){ // функциогнал api
 if (isset($_POST[$name])){
   global $$name;
   $$name=$_POST[$name];
 }elseif (isset($_GET[$name])){
   global $$name;
   $$name=$_GET[$name];
 }
}

function DevArayGenerator(){ // вариант загрузки списка через json
  $json = file_get_contents('http://network.pmpmilk.ru/network_devices/DevJSonList.php');
  $DeviceList=json_decode($json);
  return $DeviceList;
}

function DevAaaryGenerator3(){ // вариант загрузки списка из базы mysql
    $query = mysqli_query($connect,"SELECT d.ip_address, d.devname, dt.devtype, d.blocked
                                    FROM dev_type dt, devices d
                                    WHERE d.type_id = dt.id
                                    ORDER BY dt.devtype, d.ip_address"); //подгружаем список типов устройств

    while($res = mysqli_fetch_array($query)){ // перебираем устройства
        $Vendor=$res[devtype];
        $IP=$res[ip_address];
        $DeviceName=$res[devname];
        $DeviceList[]=array($Vendor, $element[0], $element[1]); // Создаем очередной элемент массива: array(Vendor, Ip, DeviceName)
    }
    return $DeviceList;
}

#Загружаем списки устройств в массивы
function DevAaaryGenerator2(){ // вариант загрузки списка из файлов
  foreach (array('LogRead/LogRead.cisco.list', 'LogRead/LogRead.mikrotik.list', 'LogRead/LogRead.hp.list', 'LogRead/LogRead.edgecore.list') as &$value) { // Перебираем файлы со списками устройств
    $element = explode(".", $value);
    $Vendor=$element[1]; // тип устройства между первой и второй точками
    $CheckList=file($value); // $CheckList - массив из строк файла
    foreach ($CheckList as $line) { // Перебираем строки списка из файла
      $element = explode(":", $line); // Разбиваем строку на массив по разделителю ":"
      $DeviceList[]=array($Vendor, $element[0], $element[1]); // Создаем очередной элемент массива: array(Vendor, Ip, DeviceName)
    }
  }
  return $DeviceList;
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
        if (md5_file('/tmp/LogReadDevInventory.'.$LastVendor.'.list') == md5_file('LogRead/LogRead.'.$LastVendor.'.list') && $verbose) echo "files the same<br>\n";
#       exec('diff /tmp/LogReadDevInventory.'.$LastVendor.'.list LogRead.'.$LastVendor.'.list', $output, $retval);
#       if (strlen($output[0])=0) echo "files the same<br>\n";
        else {
            if($verbose) echo "files are different<br>\n";
            copy('LogRead/LogRead.'.$LastVendor.'.list', 'LogRead/LogRead.'.$LastVendor.'.list.old'); // делаем резервную копию
            copy('/tmp/LogReadDevInventory.'.$LastVendor.'.list','LogRead/LogRead.'.$LastVendor.'.list'); // заменяем старую
        }
        unlink('/tmp/LogReadDevInventory.'.$LastVendor.'.list'); // удаляем временный файл
}

#-------------------------------------------------------------------------------------
$DeviceList=DevArayGenerator(); // загружаем из базы массив со списком устройств

# Инициализируем (читаем) переданные api-параметры
getvar('devtype');
getvar('vendor');
getvar('opt');
getvar('action');
if (isset($action) && $action=='test') $verbose=1;


if($verbose) print_r($DeviceList);
#exit();


# перебираем массив со списком устройств
$LastVendor='';
$k=0;
$VendorArray=array();
foreach ($DeviceList as $Device) { // Перебираем строки списка из файла
    $k++;
    $Vendor=$Device[0];
    if (isset($devtype) && $Vendor!==$vendor) continue;
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
    $DeviceList[$k-1][2]=$HostName;
    $VendorArray[]=array($Vendor, $Ip, $HostName);
    if($verbose) echo "$Vendor, $Ip, $DevName[1] | $HostName <br>\n";
}
fclose($fd);
ReplaceFileIfDifferent($LastVendor);
$json = json_encode($VendorArray);
if (isset($opt) && $opt='getjson') echo($json);

?>

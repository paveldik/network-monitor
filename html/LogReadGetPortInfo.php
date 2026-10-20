<?php
/*
 * LogReadGetPortInfo.php
 *
 * Получение SNMP-информации об интерфейсах и PoE-коммутатора и выдача
 * результатов в JSON.
 *
 * Расположение: /html/LogReadGetPortInfo.php
 * Входной GET/POST-параметр: ip — адрес коммутатора.
 * Выход: JSON-массив интерфейсов со статусами и описаниями; дополнительные
 * поля PoE возвращаются как нулевые заглушки.
 * Пример вызова: /LogReadGetPortInfo.php?ip=192.0.2.10
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
require_once __DIR__.'/network_devices/secrets.php';
#
# Инвентаризируем интерфейсы устройства (читаем по snmp информацию об интерфейсах и отправляем массивом по json)
#
# вызов: LogReadGetPortInfo.php?ip=192.168.202.110
#
#
$self='LogReadGetPortInfo.php';

# Set poe on|off: https://support.edge-core.com/hc/en-us/articles/360014536914-How-to-enable-disable-PoE-function-via-SNMP-
# -----------------------------------------------------


function getvar($name){ // функциогнал api
 if (isset($_POST[$name])){
   global $$name;
   $$name=$_POST[$name];
 }elseif (isset($_GET[$name])){
   global $$name;
   $$name=$_GET[$name];
 }
}

function Cisco_InerfacesInfo($ip){
# Возвращает массив: [OID-номер интерфейса][Имя интерфейса]
  $timeout=50000; // в микросекундах (=50мс)

# считываем имена интерфейсов
  $mib='.1.3.6.1.2.1.2.2.1.2';
  $int_names=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  if (!count($int_names)) return 'error';
  foreach ($int_names as &$line) {
    preg_match('#String:\s*"(.*)"#iu',$line,$test);
    $line=$test[1];
  }

# считываем MIB-номера интерфейсов
  $mib='.1.3.6.1.2.1.2.2.1.1';
  $int_numbers=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  foreach ($int_numbers as &$line) {
    preg_match('#Integer:\s*(.*)#iu',$line,$test);
    $line=$test[1];
  }
# Считываем статус линка на интерфейсе (1=Up)
  $mib='.1.3.6.1.2.1.2.2.1.8';
  $int_status=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  foreach ($int_status as &$line) {
    preg_match('#Integer:\s*(.*)#iu',$line,$test);
    $line=$test[1];
  }

# Считываем admin-статус линка на интерфейсе (1=Up)
  $mib='.1.3.6.1.2.1.2.2.1.7';
  $int_admstatus=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  foreach ($int_admstatus as &$line) {
    preg_match('#Integer:\s*(.*)#iu',$line,$test);
    $line=$test[1];
  }

# считываем описания интерфейсов
  $mib='1.3.6.1.2.1.31.1.1.1.18';
  $int_descr=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  if (!count($int_descr)) return 'error';
  foreach ($int_descr as &$line) {
    preg_match('#String:\s*"(.*)"#iu',$line,$test);
    $line=$test[1];
  }

# Считываем admin-статус линка на интерфейсе (1=Up)
#  $mib='.1.3.6.1.2.1.2.2.1.7';
#  $int_admstatus=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
#  foreach ($int_admstatus as &$line) {
#    preg_match('#Integer:\s*(.*)#iu',$line,$test);
#    $line=$test[1];
#  }



# Склеиваем все, что насобирали в один массив
  $k=0;
  foreach ($int_numbers as &$line) {
    if (substr($int_names[$k], 0, 2)!='Fa' && substr($int_names[$k], 0, 2)!='Gi'){ $k++; continue;}
    $arr[]=array($int_numbers[$k], $int_names[$k], $int_status[$k], $int_admstatus[$k], $int_descr[$k],'0','0','0','0');
    $k++;
  }

  return $arr;

#------ PoE -----

# Считываем admin PoE-статус интерфейса (1=Up) (в массиве только ethernet интерфейсы)
  $mib='1.3.6.1.4.1.9.9.402.1.2.1.1';
  $poe_admstatus=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  foreach ($poe_admstatus as &$line) {
    preg_match('#Integer:\s*(.*)#iu',$line,$test);
    $line=$test[1];
  }


# Считываем PoE-статус интерфейса (1=Up) (в массиве только ethernet интерфейсы)
  $mib='1.3.6.1.4.1.9.9.402.1.2.1.4';
  $poe_status=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  foreach ($poe_status as &$line) {
    preg_match('#Integer:\s*(.*)#iu',$line,$test);
    $line=$test[1];
  }

# Считываем текущую max мощность PoE на интерфейсе (0-NN mWt) (в массиве только ethernet интерфейсы)
  $mib='1.3.6.1.4.1.9.9.402.1.2.1.9';
  $poe_curmaxpower=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  foreach ($poe_curmaxpower as &$line) {
    preg_match('#Gauge32:\s*(.*)#iu',$line,$test);
    $line=$test[1];
  }

# Считываем установленную max мощность PoE на интерфейсе (0-NN mWt) (в массиве только ethernet интерфейсы)
  $mib='1.3.6.1.4.1.9.9.402.1.2.1.6';
  $poe_maxpower=snmp2_walk($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  foreach ($poe_maxpower as &$line) {
    preg_match('#Gauge32:\s*(.*)#iu',$line,$test);
    $line=$test[1];
  }

# Собираем всё в кучку
# [OID-номер интерфейса][Имя интерфейса][статус линка на интерфейсе][admin-статус линка на интерфейсе][interface description]
#  [admin-статус PoE][статус PoE][Текущий предел мощности на порту][Заданный предел мощности на порту]
#
  $k=0;
  foreach ($arr as &$line) {
    $arr1[]=array($arr[$k][0],$arr[$k][1],$arr[$k][2],$arr[$k][3],$arr[$k][4],$poe_admstatus[$k],$poe_status[$k],$poe_curmaxpower[$k],$poe_maxpower[$k]);
    $k++;
  }

#  return $int_numbers;
#  return $int_names;
#  return $int_admstatus;
#  return $int_descr;
#  return $poe_admstatus;
#  return $poe_status;
#  return $poe_curmaxpower;
#  return $poe_maxpower;
#  return $int_status;
  return $arr1;
}



#echo '1';
#echo '<pre>';
#print_r(Cisco_PortNameList('192.168.202.110'));
#echo '</pre>';
#exit();
getvar('ip');
echo json_encode(Cisco_InerfacesInfo($ip));

?>

<?php
/*
 * mikrot_port_renamer.php
 *
 * Чтение названий и описаний интерфейсов MikroTik по SNMP и вывод команд
 * транслитерации описаний портов для применения администратором.
 *
 * Расположение: /html/network_devices/mikrot_port_renamer.php
 * Входные GET/POST-параметры: devtype — тип устройства, ip — IP MikroTik.
 * Выход: HTML со списком интерфейсов и командами переименования; устройство
 * скриптом не изменяется.
 * Пример вызова: /network_devices/mikrot_port_renamer.php?devtype=2&ip=192.168.2.10
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
#
# Переименовыватель портов микротов с русского на транслит
#
include ('config.php');
include ('lib.php');
require_once __DIR__.'/secrets.php';
$self = 'devicelist.php';
$pagename='Переименование портов';
#date_default_timezone_set('Europe/Saratov');
$br="\n";
$ed=0;

function translit($value)
{
  $converter = array(
  'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
  'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
  'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
  'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
  'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
  'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
  'э' => 'e',    'ю' => 'yu',   'я' => 'ya',

  'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
  'Е' => 'E',    'Ё' => 'E',    'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
  'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
  'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
  'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'C',    'Ч' => 'Ch',
  'Ш' => 'Sh',   'Щ' => 'Sch',  'Ь' => '',     'Ы' => 'Y',    'Ъ' => '',
  'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
    );
  $value = strtr($value, $converter);
  return $value;
}

function TranslateMikrotHEX2Text($hexstring){
# Конвертируем байтовую HEX-кодировку из cp1251 - в utf-8
# Для этого сначала чистим байтовую кодировку от лишних символов (пробелов и переносов строк),
# после этого конвертируем полученную HEX-строку в строку, состоящую из соответствующих байтов.
# Затем байтовую строку конвертируем из cp1251 - в utf-8.
#
  return mb_convert_encoding(hex2bin(preg_replace('/[^a-zA-Z\d]/ui', '', $hexstring)), 'utf8', 'windows-1251');
}


function GetMikrotPortNames($ip){
#iso.3.6.1.2.1.2.2.1.2.1
  $timeout=50000;
  $mib='.1.3.6.1.2.1.2.2.1.2.';
  for ($k=1; $k<=50; $k++){
    $answer=snmp2_get($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib.$k, $timeout);
    if (stripos($answer,'br-') !== false) return $name;
    preg_match('#STRING: "(.*)"#iu',$answer,$test);
    if (strlen($test[1])==0) return $name;
    $name[$k]=$test[1];
  }
  return $name;
}

function Mikr_GetPortDescription($ip,$portnum){
#
# https://forum.mikrotik.com/viewtopic.php?t=162719
# как посмотреть таблицу mib у микротов из консоли
#
# https://bestmonitoringtools.com/mibdb/mibdb_search.php?mib=MIKROTIK-MIB
# тут есть таблица mib
#
  $timeout=50000; // в микросекундах (=50мс)
  if ($ip=='192.168.22.23') $portnum=$portnum+2;
  if ($ip=='192.168.22.10') $portnum=$portnum+1;
  $mib='.1.3.6.1.2.1.31.1.1.1.18.'.$portnum;
  $answer=str_replace("\n","",snmp2_get($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout));
#  return $answer;
  if (stripos($answer,'x-STRING:') !== false){
    preg_match('#STRING: (.*)#iu',$answer,$test);
#    return $answer."<br>".$test[1];
    $result=TranslateMikrotHEX2Text($test[1]);
  }
  elseif (stripos($answer,'STRING:') !== false){
    preg_match('#^STRING: "(.*)"$#iu',$answer,$test);
    $result=$test[1];
  }
  elseif ($answer='""') $result='';
  else $result='!error!';
  return $result;
}

function getvar($name){
 if (isset($_POST[$name])){
   global $$name;
   $$name=$_POST[$name];
 }elseif (isset($_GET[$name])){
   global $$name;
   $$name=$_GET[$name];
 }
}

getvar('devtype');
getvar('ip');



##############################################################

echo htmlHeader($pagename);

$portnames=GetMikrotPortNames($ip);
echo '<pre>';
echo "-----------\n";
echo "Список интерфейсов устройства с IP ".$ip.":\n";
print_r($portnames);
echo "-----------\n";
echo '</pre>';
#exit();
echo "</br>";
echo "Список дескрипторов интерфейсов (как есть):<br>&nbsp<br>";
for ($k=1; $k<=count($portnames); $k++){
  echo "port $k:".Mikr_GetPortDescription($ip,$k)."<br>";
}

echo "-------------<br>";
echo "<br> Ниже список команд для переименования дескрипторов интерфейсов из консоли микротика</br>";
echo "-------------<br>";
echo "/interface ethernet<br>";
for ($k=1; $k<=count($portnames); $k++){
$portnames[$k]=str_replace("sfpp","sfp-sfpplus",$portnames[$k]);
echo 'set [ find default-name='.$portnames[$k].' ] comment="'.translit(Mikr_GetPortDescription($ip,$k)).'"<br>';
}
echo "&nbsp<br>";

echo'</body></html>';

?>

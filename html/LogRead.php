<?php
/*
 * LogRead.php
 *
 * Отображение последних записей журналов сетевых устройств с фильтрацией,
 * сопоставлением адресов с инвентарными списками и переводом событий.
 *
 * Расположение: /html/LogRead.php
 * Входные GET-параметры: n — число записей (по умолчанию 50), t — интервал
 * автообновления в секундах (по умолчанию 5), tr — перевод (0 или 1),
 * site — площадка (Site1, msk или Site2), s — возврат к HTTP Referer.
 * Выход: HTML-страница с отформатированными записями syslog и автообновлением.
 * Пример вызова: /LogRead.php?n=50&t=5&tr=1&site=msk
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
require_once __DIR__.'/network_devices/secrets.php';
#
# Выводим "хвост" логов всех сетевых девайсов на один экран
#
# Вызов: http://network.pmpmilk.ru/LogRead.php?t=70&p=5
#
# n - кол-во строк лога на экране
# p - частота обновления экрана в секундах
#
$self='LogRead.php';
$translate=1;
$pornamecolor='#5d0';
$portdescrcolor='#0af';
$devnamecolor='#5d0';
$usernamecolor='#C09';
$alarmcolor='#e0202';
$sysmessagecolor='#a0a0a0';


function TranslateMikrotHEX2Text($hexstring){
# Конвертируем байтовую HEX-кодировку из cp1251 - в utf-8
# Для этого сначала чистим байтовую кодировку от лишних символов (пробелов и переносов строк),
# после этого конвертируем полученную HEX-строку в строку, состоящую из соответствующих байтов.
# Затем байтовую строку конвертируем из cp1251 - в utf-8.
#
  return mb_convert_encoding(hex2bin(preg_replace('/[^a-zA-Z\d]/ui', '', $hexstring)), 'utf8', 'windows-1251');
}


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


function Mikr_GetPortDescription($ip,$portnum){
#
# https://forum.mikrotik.com/viewtopic.php?t=162719
# как посмотреть таблицу mib у микротов из консоли
#
# https://bestmonitoringtools.com/mibdb/mibdb_search.php?mib=MIKROTIK-MIB
# тут есть таблица mib
#
  $timeout=50000; // в микросекундах (=50мс)
  # Вносим корректировку нумерации портов микротиков в зависимости от модели
  if ($ip=='192.168.12.11') $portnum=$portnum+2; // RB962UiGS-5HacT2HnT
  if ($ip=='192.168.12.10') $portnum=$portnum+1; // 951G-2HnD
  if ($ip=='192.168.12.18') $portnum=$portnum+1; // RB2011UiAS-2HnD
  if ($ip=='192.168.12.4') $portnum=$portnum+1; // CRS109-8G-1S-2HnD
  $mib='.1.3.6.1.2.1.31.1.1.1.18.'.$portnum;
  $answer=str_replace("\n","",snmp2_get($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout));
  if (stripos($answer,'x-STRING:') !== false){
    preg_match('#STRING: (.*)#iu',$answer,$test);
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
function Cisco_GetPortDescription($ip,$portnum){
# $portnum=10023 для интерфейса fa1/0/23 и $portnum=10102 для gi1/0/2
# https://forum.mikrotik.com/viewtopic.php?t=162719
# как посмотреть таблицу mib у микротов из консоли
#
# https://bestmonitoringtools.com/mibdb/mibdb_search.php?mib=MIKROTIK-MIB
# тут есть таблица mib
#
  if (strlen($portnum)<>5) return '!error!';
  $timeout=50000; // в микросекундах (=50мс)
  $mib='.1.3.6.1.2.1.31.1.1.1.18.'.$portnum;
  $answer=snmp2_get($ip, network_monitor_setting('SNMP_COMMUNITY'), $mib, $timeout);
  if (stripos($answer,'STRING:') !== false){
    preg_match('#^STRING: "(.*)"$#iu',$answer,$test);
    $result=str_replace('\"','',$test[1]);
  }
  elseif ($answer='""') $result='';
  else $result='!error!';
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


function htmlHeader($pagename){
  echo "<html lang='ru'><head><meta http-equiv='Content-Type' content='text/html; charset=utf-8'>".
        "<title>".$pagename."</title>".
        "<style>
            body {
                    background: #000; /* Цвет фона */
                    color: #fc0; /* Цвет текста */
                 }
           #log {
                    background: #000; /* Цвет фона */
                    color: #ed0; /* Цвет текста */
                    font-family: Sans-serif;
                    font-size: 12px;
                    line-height: 15px;
                    padding: 8px;
/*                  border: 2px solid #456;*/
               }
  </style>".
        "</head><body>";
}
//Проверяем переданные параметры
if($_SERVER["REQUEST_METHOD"] == "GET") {
    if (isset($_GET['n'])) $n=intval($_GET['n']);
    else $n=50; // кол-во строк лога
    if (isset($_GET['t'])) $t=intval($_GET['t']);
    else $t='5'; // период обновления
    if (isset($_GET['tr'])) $translate=intval($_GET['tr']);
    if (isset($translate)&&$translate!=0) $translate=1; // Управление переводом лога на русский язык
    if (isset($_GET['s'])) $s=$_GET['s'];
    if (isset($_GET['site'])) {
        $site=$_GET['site'];
        if ($site=='Site2') { $file='Site2.log'; $name='Площадка 2'; }
        elseif ($site=='msk') { $file='MSK.log'; $name='Москва'; }
        else { $file='Site1.log'; $name='Площадка 1'; }
    } else $file='Site1.log';
}
$header='refresh: '.$t; // параметры рефреша страницы
if (isset($s)) { // Если есть параметр $s, то рефрешим на url реферера
    $source=$_SERVER['HTTP_REFERER'];
    $header.='; URL='.$source;
}

//
// читаем лог, чистим от "мусора" и разбиваем на массив из трок
//
#$data=shell_exec('tail -n'.$n.' Site1.log | grep -ve "dhcp,info S1-02" | grep -ve "info S1-23.*@wlan2"');
#$data=shell_exec('tail -n500 Site1.log | grep -ve "dhcp,info S1-02" | grep -ve "info S1-23.*@wlan2" | grep -ve "for user: backuper" | tail -n'.$n);
#$data=shell_exec('tail -n500 '.$file.' | grep -ve "dhcp,info S1-02" | grep -ve "info S1-23.*@wlan2" | grep -ve "for user: backuper" | tail -n'.$n);
$data=shell_exec('tail -n600 '.$file.' | grep -ve "dhcp,info S1-02" | grep -ve "info S1-23.*@wlan2" | grep -ve "for user: backuper"'); // тут читаем лог из файла в переменную
$data = preg_replace('#.*wireless.*\x0a#', '', $data); // Удаляем строку, содержащюю слово wireless (используем \x0a в качестве символа конца строки, т.к. $ не работает)
$data = preg_replace('#.*dhcp .?.?assigned .*\x0a#', '', $data); // Удаляем строку, содержащюю текст "dhcp *assigned"
$data = preg_replace('#.*backuper .*\x0a#', '', $data); // Удаляем строку, содержащюю текст "user backuper logged in"
$data = preg_replace('#.*192\.168\.33\.16.*\x0a#', '', $data); // Удаляем строку, содержащюю ip адрес backup-сервера
$data2 = explode("\n", $data); // разбиваем лог на массив, состоящий из отдельных строк.
$numrows = count($data2); // кол-во элементов массива (строк) в итоговом логе
$nn=$n;
if ($nn>$numrows) $nn=$numrows;


$LinkText=($translate!=0)?'Показать оригинал':'Перевести на русский';
$LinkSite=(strlen($site)>0)?'&site='.$site:'';


header($header);
htmlHeader('LAN devices log');
echo '<table bgcolor="green" width="100%">';
echo '<tr><td width="240">';
echo $n.' из '.$numrows.' строк лога устройств';
echo '</td><td width="20"></td>';
echo '<td><input type="button" value="-" onclick="window.location=`'.$self.'?n='.(($n>10)?$n-10:$n).'&t='.$t.$LinkSite.'&tr='.$translate.'`;" />';
echo '&nbsp;&nbsp;10&nbsp;строк&nbsp;&nbsp;';
echo '<input type="button" value="+" onclick="window.location=`'.$self.'?n='.($n+10).'&t='.$t.$LinkSite.'&tr='.$translate.'`;" /></td>';
echo '<td width="50"></td>';
echo '<td><input type="button" value="'.$LinkText.'" onclick="window.location=`'.$self.'?n='.$n.'&t='.$t.$LinkSite.'&tr='.($translate==0?1:0).'`;" />';
echo '</td>';
echo '<td width="50%"></td></tr></table>';
//
#echo '<pre>';

// Замена лишних двоеточий (в часах и маках) на "&&"
// Однотипное упорядочивание элементов лога для всех устройств
for ($i = $numrows-$nn-1; $i <= $numrows-1; $i++) {
// Заменяем '<' и '>' на ascii-код
    $data2[$i]=str_replace('<','&#60;',$data2[$i]);
    $data2[$i]=str_replace('>','&#62;',$data2[$i]);
// разбиение строки лога на массив (новый вариант)
#Feb 14 07:42:14: 192.168.11.12: wireless,info: S1-23: : E2:F5:11:12:13:14@wlan2: disconnected, extensive data loss, signal strength -49
  preg_match('#^(.*\d\d:\d\d:\d\d):\s*(\d+\.[\d\.]+):\s*(.*)$#iu',$data2[$i],$test);
  $time=$test[1];
  preg_match('#^\s*([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\s*#u',$test[2],$ip);
  $ip=$test[2];
  $body=$test[3]; // Тут пока что лежит все, что после IP
  $Device='';
#  echo $test[1].'! '.$test[2].'!! '.$test[3].'!!! '.$test[1].'<br>';
  if (strlen($ip)==0) continue;
  $Device=CheckVendor($ip);
  if (strlen($Device[0])==0) { // Если ip нет в вписках, выводим лог как есть.
   $data2[$i]=$time.': '.$ip.': '.$body."!!Dev Not in List!!\n";
   continue;
  }
  // Тут формируем стандартную структуру лога, в зависимости от типа устройства (анализируем переменную $body, полученную в первой итерации)
  $vendor=$Device[0];
  $devname=$Device[1];
  $data2[$i]=$time.': '.$ip.': <font color="'.$devnamecolor.'">'.$devname.'</font>: '; // Заполняем левую часть строки лога

  if ($vendor=='cisco') {
#Feb 14 12:24:24: 192.168.12.101: sw_cisco_06: 2275:: Sw-Cisco_06: 6w1d: %LINEPROTO-5-UPDOWN: Line protocol on Interface FastEthernet0/20, changed state to up
#Feb 15 13:28:12: 192.168.12.102: sw_cisco_09: 172:: 2d04h: %LINEPROTO-5-UPDOWN: Line protocol on Interface FastEthernet0/4, changed state to down
#Feb 15 09:06:51: 192.168.12.103: sw_cisco_08: 110:: Sw-Cisco_08: 7w3d: %LINEPROTO-5-UPDOWN: Line protocol on Interface FastEthernet0/3, changed state to down

#Oct 23 13:59:49: 192.168.12.104: Sw-cisco_20(sklad_2fl): Power granted --%ILPOWER-5-POWER_GRANTED: Interface Fa1/0/9--
#Oct 23 13:59:51: 192.168.12.105: Sw-cisco_21(ulitsa): Logging to host 192.168.17.6 port 10514 started - reconnection --%SYS-6-LOGGINGHOST_STARTSTOP--
#Oct 23 13:59:10: 192.168.12.106: Sw-cisco_20(sklad_2fl): Interface FastEthernet1/0/9, changed state to administratively down --%LINK-5-CHANGED--

    preg_match('#^(.*):\s*:\s*(.*):\s*(%.*):\s*(.*)$#iu',$body,$test);
    $Cis_counter=$test[1];
    $Cis_hostnameAndUptime=$test[2];
    $Cis_mtype=$test[3];
    $Cis_body=$test[4];
    $Cis_body_R=$Cis_body.' --'.$Cis_mtype.'--';

    if ($Cis_mtype==''){
        preg_match('#^.*:\s*:\s*.*:\s*(.*)$#iu',$body,$test);
        $Cis_body_R=' | <font color="'.$sysmessagecolor.'">'.$test[1].'</font>';
    }
    if ($Cis_mtype=='%LINEPROTO-5-UPDOWN') {
        $PortDescr='';
        preg_match('#FastEthernet([0-9/]*)/([0-9]*)#iu',$Cis_body,$test);
        if (!isset($test[2])){
          preg_match('#GigabitEthernet([0-9/]*/)+([0-9]*), .*#iu',$Cis_body,$test);
          if (!isset($test[2])){
             preg_match('#face (.*), .*#iu',$Cis_body,$test);
              if (stripos($Cis_body,' to up') !== false){
                $Cis_body_R='Интерфейс '."'".'<font color="'.$portdescrcolor.'">'.$test[1]."</font>' включен";
              }
              else {
                $Cis_body_R='Интерфейс '."'".'<font color="'.$portdescrcolor.'">'.$test[1]."</font>' отключен";
              }
          }
          else {
            if (strlen($test[2])>0) $PortDescr=Cisco_GetPortDescription($ip,'100'.(strlen($test[2])>1?$test[2]:'0'.$test[2]));
            if (stripos($Cis_body,' to up') !== false){
              $Cis_body_R='Гигабитный порт <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'".' включен';
            }
            elseif (stripos($Cis_body,' to down') !== false){
              $Cis_body_R='Гигабитный порт <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'".' отключен';
            }
          }
        }
        else {
          if (strlen($test[2])>0) $PortDescr=Cisco_GetPortDescription($ip,'100'.(strlen($test[2])>1?$test[2]:'0'.$test[2]));
          if (stripos($Cis_body,' to up') !== false){
            $Cis_body_R='100Мбит порт <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'".' включен';
          }
          elseif (stripos($Cis_body,' to down') !== false){
            $Cis_body_R='100Мбит порт <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'".' отключен';
          }
        }


    }
    if ($Cis_mtype=='%LINK-3-UPDOWN') {
        $PortDescr='';
        preg_match('#FastEthernet([0-9/]*)/([0-9]*)#iu',$Cis_body,$test);
        if (!isset($test[2])){
          preg_match('#GigabitEthernet([0-9/]*/)+([0-9]*), .*#iu',$Cis_body,$test);
          if (strlen($test[2])>0) $PortDescr=Cisco_GetPortDescription($ip,'100'.(strlen($test[2])>1?$test[2]:'0'.$test[2]));
          if (stripos($Cis_body,' to up') !== false){
            $Cis_body_R='Появился линк на Гигабитном порту <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
          }
          elseif (stripos($Cis_body,' to down') !== false){
            $Cis_body_R='Упал линк на Гигабитном порту <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
          }
        }
        else {
          if (strlen($test[2])>0) $PortDescr=Cisco_GetPortDescription($ip,'100'.(strlen($test[2])>1?$test[2]:'0'.$test[2]));
          if (stripos($Cis_body,' to up') !== false){
            $Cis_body_R='Появился линк на 100Мбитном порту <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
          }
          elseif (stripos($Cis_body,' to down') !== false){
            $Cis_body_R='Упал линк на 100Мбитном порту <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
          }
        }
    }

#Oct 23 13:59:49: 192.168.12.101: Sw-cisco_20(sklad_2fl): Power granted --%ILPOWER-5-POWER_GRANTED: Interface Fa1/0/9--
    if (stripos($Cis_mtype,'5-POWER_GRANTED') !== false){
        preg_match('#face Fa\w*([0-9/]*)/([0-9]*)#iu',$Cis_mtype,$test);
        $PortDescr='';
        if (strlen($test[2])>0) $PortDescr=Cisco_GetPortDescription($ip,'100'.(strlen($test[2])>1?$test[2]:'0'.$test[2]));
        $Cis_body_R='Подано PoE-питание на 100Мбитный порт <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
    }

#Oct 23 15:08:38: 192.168.12.102: Sw_cisco_14(sklad2-01): 2442:: 3w3d: %ILPOWER-5-IEEE_DISCONNECT: Interface Fa1/0/1: PD removed
    if (stripos($Cis_mtype,'5-IEEE_DISCONNECT') !== false){
        preg_match('#face Fa\w*([0-9/]*)/([0-9]*)#iu',$Cis_mtype,$test);
        $PortDescr='';
        if (strlen($test[2])>0) $PortDescr=Cisco_GetPortDescription($ip,'100'.(strlen($test[2])>1?$test[2]:'0'.$test[2]));
        $Cis_body_R='Отключено PoE-питание на 100Мбитном порту <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
    }

#Oct 23 13:59:10: 192.168.12.101: Sw-cisco_20(sklad_2fl): Interface FastEthernet1/0/9, changed state to administratively down --%LINK-5-CHANGED--
    if ($Cis_mtype=='%LINK-5-CHANGED') {
        preg_match('#Fa\w*([0-9/]*)/([0-9]*)#iu',$Cis_body,$test);
        if (!isset($test[2])){
          preg_match('#face (.*), .*#iu',$Cis_body,$test);
          $Cis_body_R='Интерфейс '."'".'<font color="'.$portdescrcolor.'">'.$test[1]."</font>' принудительно отключен администратором";
        }
        else {
          $PortDescr='';
          if (strlen($test[2])>0) $PortDescr=Cisco_GetPortDescription($ip,'100'.(strlen($test[2])>1?$test[2]:'0'.$test[2]));
          if (stripos($Cis_body,' down') !== false){
            $Cis_body_R='100Мбитный порт <font color="'.$pornamecolor.'">'.$test[2].'</font>'." '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'".' принудительно отключен администратором';
          }
       }
    }

#%SYS-5-CONFIG_I: Configured from console by fedya on vty0 (192.168.2.111)
    if ($Cis_mtype=='%SYS-5-CONFIG_I' && stripos($Cis_body,'Configured from consol') !== false) {
        preg_match('#sole by ([a-z].*) on ([a-z].*) (\([0-9].*\))#iu',$Cis_body,$test);
        $test[1]='<font color="'.$usernamecolor.'">'.$test[1].'</font> ';
        $Cis_body_R='Конфигурация изменена пользователем '.$test[1].' с IP-адресом '.$test[3];
    }

#Oct 26 13:44:33: 192.168.12.107: Sw-cisco_20(sklad_2fl): 14:: Sw-cisco_20(sklad_2fl): *Mar 1 00:02:30.944: %SYS-5-RESTART: System restarted --
    if ($Cis_mtype=='%SYS-5-RESTART' && stripos($Cis_body,'System restarted') !== false) {
        $Cis_body_R='<font color="'.$alarmcolor.'">Произошла перезагрузка системы</font>';
    }

#Logging to host 192.168.12.6 Port 10514 started - CLI initiated --%SYS-6-LOGGINGHOST_STARTSTOP--
    if ($Cis_mtype=='%SYS-6-LOGGINGHOST_STARTSTOP' && stripos($Cis_body,'started') !== false) {
        preg_match('#host ([0-9].*) Port (\d+) #iu',$Cis_body,$test);
#        $Cis_body_R='  ----'.$Cis_body.'---'.$test[1].'---'.$test[2].'---';
#        $Cis_body_R=$test[1];

        $Cis_body_R='Активирована отправка системных логов на порт '.$test[2].' хоста '.$test[1];
    }

    if ($translate==1) $body=$Cis_body_R;
  } // end of Cisco part


  if ($vendor=='mikrotik') {
    $repeated=0;
# OID с алиасами интерфейсов: .1.3.6.1.2.1.31.1.1.1.18.1 - 28 (по номерам портов)
# имена интерфейсов:          .1.3.6.1.2.1.2.2.1.2.1 - 28

    if (stripos($body,'sage repeated') !== false){
      preg_match('#^(.*):.* (\d+) .*\[\s*(.*):\s*:\s*(.*)\]\s*$#iu',$body,$test);
      $Mikr_mtype=$test[1];
      $Mikr_hostname=$test[3];
      $Mikr_body=$test[4];
      $repeated=$test[2];
#    echo $test[3];
#    exit();

    }else{
      preg_match('#^(.*):\s*(.*):\s*:\s*(.*)$#iu',$body,$test);
      $Mikr_mtype=$test[1];
      $Mikr_hostname=$test[2];
      $Mikr_body=$test[3];
    }
    $Mikr_body_R=$Mikr_body.' --'.$Mikr_mtype.'--';
    if (stripos($Mikr_mtype,'interface') !== false) {
#Feb 15 08:12:39: 192.168.12.19: Mikrotik_19: interface,info: S1-19: : ether5 link up (speed 10M, full duplex)
        if (stripos($Mikr_body,' link ') !== false) {
          preg_match('#^(.*) link.*#iu',$Mikr_body,$test);
          $Mikr_port=$test[1];
          preg_match('#ether(\d\d?)#iu',$test[1],$Mikr_PortNum);
          $PortDescr='';
          if (strlen($Mikr_PortNum[1])>0) $PortDescr=Mikr_GetPortDescription($ip,$Mikr_PortNum[1]);
          if (stripos($Mikr_body,'link up') !== false){
            preg_match('#(\(.*\))#iu',$Mikr_body,$speed);
            $Mikr_body_R='Появился линк на порту <font color="'.$pornamecolor.'">'.$test[1]."</font> '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>' ".$speed[1];
          }
          elseif (stripos($Mikr_body,'link down') !== false){
            $Mikr_body_R='Упал линк на порту <font color="'.$pornamecolor.'">'.$test[1]."</font> '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
          }
        }
      }
    if (stripos($Mikr_mtype,'account') !== false && stripos($Mikr_mtype,'system') !== false){
        preg_match('#user ([a-z].*) logged ([a-z].*) from ([0-9\.]*) [a-z][a-z][a-z]* ([a-z].*)#iu',$Mikr_body,$test);
        $test[1]='<font color="'.$usernamecolor.'">'.$test[1].'</font> ';
        if ($test[2]=='in') {
          if ($test[4]=='romon network') $Mikr_body_R='Пользователь '.$test[1].' с IP-адресом '.$test[3].' вошел в '.$test[4];
          else $Mikr_body_R='Пользователь '.$test[1].' с IP-адресом '.$test[3].' авторизовался на устройстве через '.$test[4];
        }
        elseif ($test[2]=='out') {
          $Mikr_body_R='Пользователь '.$test[1].' с IP-адресом '.$test[3].' вышел из '.$test[4];
        }
        else {
#user pavel logged out by romon 64:D1:54:99:E3:88 via winbox --system,info,account--
            preg_match('#^user ([a-z].*) logged ([a-z].*) by romon ([\da-f:]*) via ([a-z].*)#iu',$Mikr_body,$test);
            $Mikr_body_R=$test[2];
            $test[1]='<font color="'.$usernamecolor.'">'.$test[1].'</font> ';
            if ($test[2]=='in') {
              $Mikr_body_R='Пользователь '.$test[1].' авторизовался через romon-сервер с mac-адресом '.$test[3].', используя '.$test[4];
            }
            elseif ($test[2]=='out') {
              $Mikr_body_R='Пользователь '.$test[1].', авторизованный через romon-сервер с mac-адресом '.$test[3].', вышел из системы';
            }
        }
    }
    if (stripos($Mikr_mtype,'l2tp') !== false){
#Feb 15 07:24:28: 192.168.12.1: Mikrotik_GW: l2tp,ppp,info: Site-GW: : <l2tp-site-user>: using encoding - MPPE128 stateless
#Feb 15 07:24:28: 192.168.12.1: Mikrotik_GW: l2tp,ppp,info,account: Site-GW: : site-user logged in, 192.168.48.6 from 217.18.90.100
#<85.26.88.88>: CCP lost compression got out of sync: disabling compression --l2tp,ppp,info--
# Рассинхронизация L2tp-протокола сжатия CCP с хостом 85.26.88.88.    - не сделано. Надо сделать
        preg_match('/^&#60;l2tp-(.*):\s*(.+)/iu',$Mikr_body,$test); // &#60; это знак "<", который мы ранее заменили на эту html-последовательность
        if (strlen($test[1])>0) {
          $Mikr_body=$test[2];
          $Mikr_body_R=$Mikr_body;
          if (stripos($Mikr_body,'sing encoding')) {
            preg_match('#^.* - (.*)#iu',$Mikr_body, $test);
            $Mikr_body_R='Организовано L2TP-подключение с использованием криптозащиты '.$test[1];
          }
          else {
            $Mikr_body_R.='!SKIP!'; // Ставим флаг пропуска строки при выводе на экран
          }
        }
        elseif (stripos($Mikr_body,'first L2TP') !== false) {
#first L2TP UDP packet received from 234.123.90.36
          preg_match('#^.*from ([0-9\.]*)#iu',$Mikr_body, $test);
          $Mikr_body_R='Получен запрос на подключение по протоколу L2TP c IP-адреса '.$test[1];
        }
        elseif (stripos($Mikr_body,'logged') !== false) {
          preg_match('#^([a-z].*) logged ([a-z].*), ([0-9]+\.[0-9\.]+)?.*from ([0-9\.]*)#iu',$Mikr_body, $test);
          if ($test[2]=='in') {
            $test[1]='<font color="'.$usernamecolor.'">'.$test[1].'</font> ';
            $Mikr_body_R='L2TP-Пользователь '.$test[1].' с IP-адреса '.$test[4].' авторизовался на устройстве. Ему выдан внутренний IP '.$test[3];
          }
          elseif ($test[2]=='out') {
            $Mikr_body_R='Пользователь '.$test[1].' с IP-адресом '.$test[4].' завершил L2TP-сессию';
          }
        }
    }
#ISAKMP-SA dying 88.147.88.91[500]-88.147.89.96[500] spi:5a62d58bf4cdb97a:baba81720d72f9f7 --ipsec,info--
    if (stripos($Mikr_mtype,'ipsec') !== false){
      preg_match('#^ISAKMP-SA\s*(\S+)\s*([\d\.]+).*-([\d\.]+).*#iu',$Mikr_body,$test);
      if ($test[1]=='dying') {
      $Mikr_body_R=$test[1].'--'.$test[2].'--'.$test[3].'--';
      $Mikr_body_R='Запрос на установку IPSEC-соединения от хоста '.$test[3];
      }
      elseif ($test[1]=='established') {
      $Mikr_body_R=$test[1].'--'.$test[2].'--'.$test[3].'--';
      $Mikr_body_R='IPSEC-соединение c хостом '.$test[3].' успешно установлено';
      }
      else {
        $Mikr_body_R.='!SKIP!'; // Ставим флаг пропуска строки при выводе на экран
      }
    }
#ether3 detected poe-out status: on --poe-out,info--
    if (stripos($Mikr_mtype,'poe-out') !== false){
        preg_match('#^(.*) detect.*tus:\s*(\w*)#iu',$Mikr_body,$test);
        $Mikr_body_R=$test[2];
        preg_match('#ether(\d\d?)#iu',$test[1],$Mikr_PortNum);
        $PortDescr='';
        if (strlen($Mikr_PortNum[1])>0) $PortDescr=Mikr_GetPortDescription($ip,$Mikr_PortNum[1]);
        if ($test[2]=='on') {
          $Mikr_body_R='Подано PoE-питание на порт <font color="'.$pornamecolor.'">'.$test[1]."</font> '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
        }elseif ($test[2]=='off' || $test[2]=='disabled') {
          $Mikr_body_R='Отключено PoE-питание на порту <font color="'.$pornamecolor.'">'.$test[1]."</font> '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'";
        }elseif ($test[2]=='wait_for_load') {
          $Mikr_body_R='Порт <font color="'.$pornamecolor.'">'.$test[1]."</font> '".'<font color="'.$portdescrcolor.'">'.$PortDescr."</font>'".' в режиме ожидания PoE-нагрузки';
        }

    }
#Mikrotik_05(sklad): device changed by fedya --system,info--
    if (stripos($Mikr_mtype,'system') !== false && (stripos($Mikr_body,'item changed') !== false || stripos($Mikr_body,'device changed by') !== false)){
      #item changed by pavel --system,info--
      preg_match('#^.* by ([a-z].*)$#iu',$Mikr_body,$test);
      $test[1]='<font color="'.$usernamecolor.'">'.$test[1].'</font> ';
      $Mikr_body_R='В конфигурацию системы внесены изменения пользователем '.$test[1];
    }
    if ($repeated) $Mikr_body_R='Сообщение повторяется '.$repeated.' раз: '.$Mikr_body_R;
    if ($translate==1) $body=$Mikr_body_R;
  }

  if ($vendor=='edgecore') {
    preg_match('#^(.*): ?(.*): ?(.*)$#iu',$body,$test);
    $EC_mtype=$test[1];
    $EC_hostname=$test[2];
    $EC_body=$test[3];
    $EC_body_R=$EC_body.' --'.$EC_mtype.'--';
    if ($translate==1) $body=$EC_body_R;
  }
  if ($vendor=='hp') {
    $body=str_replace('%%','%',$body);
#HP_V1910-01: %%10IFNET/4/INTERFACE UPDOWN(t):   Trap 1.3.6.1.6.3.1.1.5.3: Interface 9437205 is Down, ifAdminStatus is 1, ifOperStatus is 2
#HP_V1910-01: %%10IFNET/4/LINK UPDOWN(l):   GigabitEthernet1/0/22: link status is DOWN
    preg_match('#^(.*):\s*(%.*[^:].*):\s*(.*):\s*([a-z].*)$#iu',$body,$test);
    $HP_hostname=$test[1];
    $HP_mtype=$test[2];
    $HP_interface=$test[3];
    $HP_body=$test[4];
    $HP_body_R='!'.$HP_body.' --'.$HP_mtype.'--';
    if (stripos($HP_mtype,'4/LINK UPDOWN') !== false) {
      preg_match('#^.*us is ([a-z]\S*)#iu',$HP_body, $test);
      $HP_LinkStatus=$test[1];
      preg_match('#^\s*([a-z].*)Ethernet\d+/\d+/(\d+)\s*#iu',$HP_interface, $test);
      $HP_body_R=($HP_LinkStatus=='DOWN')?'Упал линк на ':'Появился линк на ';
      $HP_body_R.=($test[1]=='Gigabit')?'Гигабитном порту ':'100Мбитном порту <font color="'.$pornamecolor.'">';
      $HP_body_R.=$test[2].'</font>';
#      $HP_body_R=$test[1];
    }
    elseif (stripos($HP_mtype,'4/INTERFACE UPDOWN') !== false) {
      $HP_body_R='!SKIP!'; // Ставим флаг пропуска строки при выводе на экран
    }
    if ($translate==1) $body=$HP_body_R;
  }
$data2[$i].=$body;
#echo '<pre>';
#echo $data2[$i]."\n";


#echo '<pre>';
#print_r($element);
#echo '</pre>';
#exit();




}


#echo "<pre>"; // выводим лог текстом, как есть
echo '<div id="log">';
for ($i = $numrows-$nn-1; $i <= $numrows-1; $i++) {
    if (stripos($data2[$i],'!SKIP!') == false) echo $data2[$i].'</br>'.PHP_EOL;
}
echo '</div>';
#echo "</pre>";
echo '<a href="http://mycactiserver.ru/cacti/plugins/weathermap/weathermap-cacti-plugin.php?action=viewmap&id=2c775b8a9b375dfe5157">Открыть карту сети</a>';
echo ' (требуется предварительная <a href="http://mycactiserver.ru/cacti/">авторизация на сайте)</a>';

echo '<br>&nbsp;';
echo '<br>&nbsp;<br><a href="'.$self.'?n='.$n.'&t='.$t.'&site=msk">Лог устройств Москва</a>';
echo '<br><a href="'.$self.'?n='.$n.'&t='.$t.'&site=Site2">Лог устройств Площадка 2</a>';
echo '<br><a href="'.$self.'?n='.$n.'&t='.$t.'&site=Site1">Лог устройств Площадка 1</a>';

?>

<?php
/*
 * portlist.php
 *
 * Отображение состояния портов Cisco и выполнение операций изменения
 * описаний, состояния интерфейса и PoE с помощью SNMP и Expect.
 *
 * Расположение: /html/network_devices/portlist.php
 * Входные GET/POST-параметры: devtype, ip, interface, descr, action, element
 * и newstatus; используются для выбора устройства и управления портом.
 * Выход: HTML-страница портов или HTTP-перенаправление; изменяющие операции
 * передаются Cisco-коммутатору через Expect.
 * Пример вызова: /network_devices/portlist.php?devtype=1&ip=192.168.22.10
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
#
# Вывод информации о состоянии интерфейсов устройства. Вызов: portlist.php?devtype=1&ip=192.168.22.10
#
# вывод информации о mac-адресах на 20-м влане:
# snmpbulkwalk -c <SNMP_COMMUNITY>@20 -v2c -OXsq 192.168.22.101 .1.3.6.1.2.1.17.4.3.1.2
# вывод: .1.3.6.1.2.1.17.4.3.1.2.Мак-адрес.десятичный Int(порт+n)
#
# snmpwalk -c <SNMP_COMMUNITY>@20 -v2c 192.168.22.101 .1.3.6.1.2.1.17.4.3.1.
# https://www.nectus5.com/how-to-collect-list-of-mac-addresses-from-cisco-switch-via-snmp-dot1dtpfdbentry/
#
#
include ('config.php');
include ('lib.php');
$self = 'portlist.php';
$pagename='Список интерфейсов устройства';
$br="\n";
$ed=0;
$PowerLine='';

function getvar($name){
 if (isset($_POST[$name])){
   global $$name;
   $$name=$_POST[$name];
 }elseif (isset($_GET[$name])){
   global $$name;
   $$name=$_GET[$name];
 }
# echo (isset($$name)?"$name=".$$name.'<br>':'');
}

getvar('devtype');
getvar('ip');
getvar('interface');
getvar('olddescr');
getvar('descr');
getvar('comment');
getvar('action');
getvar('error');
getvar('element');
getvar('newstatus');


if (isset($comment)) $comment=trim($comment); // обрезаем пробелы в начале и в конце
if (isset($descr)) $descr=trim($descr); //обрезаем пробелы в начале и в конце


function GetInterfacesInfo($ip){ // загрузка информации об интерфейсах списка через
  global $PowerLine;
  $json = file_get_contents('http://network.pmpmilk.ru/LogReadGetPortInfo.php?ip='.$ip);
  $DeviceList=json_decode($json);
  $PoeArray=GetPoeStatus($ip);
  $k=0;
  foreach ($PoeArray as &$line){
    $DeviceList[$k][5]=$line[1]=='auto'?'1':'0';
    $DeviceList[$k][6]=$line[2]=='on'?'1':'0';
    $DeviceList[$k][7]=$line[3];
    $DeviceList[$k][8]=$line[6];
    $k++;
  }
  return $DeviceList;
#
# [OID-номер интерфейса][Имя интерфейса][статус линка на интерфейсе][admin-статус линка на интерфейсе][interface description]
#  [admin-статус PoE][статус PoE][Текущий предел мощности на порту][Заданный предел мощности на порту]
#
# Fa1/0/3  auto  on   15.4  Ieee PD   3    15.4
# Fa1/0/4  auto  off  0.0   n/a       n/a  15.4
}

function GetPoeStatus($ip){
  global $PowerLine;
  exec("expect -d /var/www/html/network_devices/cisco_get_poe_status.expect '$ip'", $output, $retval);

#  echo "<pre>";
#  print_r($output);
#  echo "</pre>";
#  exit();

  $n=1;
  $PoeStatus=array();
  foreach ($output as &$line) {
    switch ($n) {
      case 1:
        if (substr($line,1,5)=='odule') $n=2;
        if (substr($line,6,4)=='ble:'){
          $PowerLine=preg_replace('/.*:(\d+\.?\d*).*:(\d+\.?\d*).*:(\d+\.?\d*).*/', '1   $1   $2   $3', $line);
          $n=4;
        }
        break;
      case 2:
        if (substr($line,1,3)=='---') $n=3;
        break;
      case 3:
        $PowerLine=$line;
        $n=4;
        break;
      case 4:
        if (substr($line,1,3)=='---') $n=5;
        break;
      case 5:
        if (stripos($line,'#')){$n=6; break;}
        array_push($PoeStatus,preg_split("/\s\s+/", $line));
        break;
      case 6:
        break;
    }
  }
  foreach ($PoeStatus as &$line) {
    $line[0]=str_replace(array('Fa','Gi'),array('FastEthernet','GigabitEthernet'),$line[0]);
  }
#  echo "<pre>";
#  print_r($PoeStatus);
#  echo "</pre>";
#exit();
 return $PoeStatus;
}

#GetPoeStatus('192.168.22.101');


if (isset($action)&&$action=='editline' ) $ed=1;


if (isset($action)) {
    if ($action == 'write'){
      if (isset($devtype)&&$devtype==1)
        exec("expect -d /var/www/html/network_devices/cisco_writeconfig.expect '$ip'>/dev/null 2>/dev/null", $output, $retval);
        sleep(8);
#        header( 'Location: '.$self.'?action=reread&devtype='.$devtype.'&ip='.$ip, true, 302  );
        header( 'Location: '.$self.'?&devtype='.$devtype.'&ip='.$ip, true, 302  );
        exit();
    }
    if ($action == 'saveline'){
      if ($devtype==1 && $olddescr !== $descr) {
        //Обновляем имя устройства
        if (isset($descr) && strlen($descr)>0){
          exec("expect -d /var/www/html/network_devices/cisco_interface_rename.expect '$ip' '$interface' '$descr' >/dev/null 2>/dev/null", $output, $retval);
        }elseif(isset($descr)){
          exec("expect -d /var/www/html/network_devices/cisco_interface_nodesc.expect '$ip' '$interface' '$descr' >/dev/null 2>/dev/null", $output, $retval);
        }
      sleep(1);
#      $query = mysqli_query($connect,"UPDATE `devices` SET `devname` = '$devname', `comment` = '$comment' WHERE `id` = $eddevice LIMIT 1;"); //
      header( 'Location: '.$self.'?action=reread&devtype='.$devtype.'&ip='.$ip, true, 302  );
      exit();
      }
      header( 'Location: '.$self.'?devtype='.$devtype.'&ip='.$ip, true, 302  );
      exit();
    }

    if ($action == 'reread'){
        sleep(5);
        header( 'Location: '.$self.'?devtype='.$devtype.'&ip='.$ip, true, 302 );
        exit();
    }
    if ($action == 'onoff'){
        if (isset($devtype)&&$devtype==1&&isset($ip)&&isset($interface)&&isset($ip)&&isset($element)&&isset($newstatus)){
          if ($element=='link'){
            $status=$newstatus>0?'no shut':'shut';
            exec("expect -d /var/www/html/network_devices/cisco_interface_change_status.expect '$ip' '$interface' '$status' >/dev/null 2>/dev/null", $output, $retval);
          }
          if ($element=='poe'){
            $status=$newstatus>0?'auto':'never';
            exec("expect -d /var/www/html/network_devices/cisco_interface_change_poe_status.expect '$ip' '$interface' '$status' >/dev/null 2>/dev/null", $output, $retval);
          }
        }
        sleep(5);
        header( 'Location: '.$self.'?devtype='.$devtype.'&ip='.$ip, true, 302 );
        exit();
    }

    if ($action == 'qwe'){
        header( 'Location: '.$self.'?rt=123&devtype='.$devtype, true, 302 );
        exit();

    }

}



if (isset($_GET['card_id'])) $CardID=$_GET['card_id']; else $CardID='';

function nav_buttons() { // навигация
    global $self, $devtype, $DevTypeName;
    echo '<table border="1" cellpadding="15">';
    echo '<tr>';
    echo '<td class="noborder" width="150">';
    echo '<input type="button" value="На главную" onclick="window.location=`main.php`;"/>';
    echo '</td>';
    echo '<td class="noborder" width="150">';
    echo '<input type="button" value="К списку устройств '.$DevTypeName.'" onclick="window.location=`devicelist.php?devtype='.$devtype.'`;" title="Вернуться к списку устройств '.$res1['devtype'].'"/>';
    echo '</td>';
    echo '<td class="noborder" width="150">';
    echo '</td>';
    echo '</tr></table>';
}


function ShowLamp($status){
  if (!$status) $output=' <img src="img/red_lamp.png" width="20" height="20" title="Off"> ';
  else $output=' <img src="img/green_lamp.png" width="20" height="20" title="On"> ';
  return $output;
}

function ShowOnOff($interface, $element, $status){
  global $self, $devtype,$ip;
  $output='<a href="'.$self.'?action=onoff&devtype='.$devtype.'&ip='.$ip.'&interface='.$interface.'&element='.$element.'&newstatus='.($status>0?'0':'1').'">';
  if (!$status) $output.='<img src="img/bttn_off.png" width="40" title="Административно отключено.'."\n".'Нажмите, чтобы активировать данный функционал"></a>';
  else $output.='<img src="img/bttn_on.png" width="40" title="Административно включено"></a>';
  return $output;
}
function ShowOnOff2($interface, $element, $status){
  return '2';
}

// Основной код программы


echo htmlHeader($pagename);
$res1=mysqli_fetch_array(mysqli_query($connect,"SELECT d.*, dt.* FROM `devices` d, `dev_type` dt WHERE d.ip_address='$ip' AND d.type_id=dt.id LIMIT 1;"));
$DevTypeName=$res1['devtype'];
$DevIpAddress=$ip;
$DevName=$res1['devname'];
nav_buttons();
#echo 'devtype='.$devtype.'. ip='.$ip.'<br>';

if (!isset($devtype) || !isset($ip)) exit();

echo 'Информация об интерфейсах устройсва <strong>'.$DevTypeName.'</strong> с ip-адресом <strong>'.$DevIpAddress.'</strong> - "<strong>'.$DevName.'</strong>"</br>'.$br;
echo '<i>Произведенные <strong>изменения не сохраняются</strong> на флэшке коммутатора до нажатия кнопки `<strong>write</strong>`</i></br>'.$br;
echo '<hr><br>'.$br;

#exit();


$InterfacesArray=GetInterfacesInfo($ip);

#echo 'count(InterfacesArray):'.count($InterfacesArray).'<br>';

if (!count($InterfacesArray)) {
  echo 'Ошибка получения информации об интерфейсах устройства. '.$br;
  echo'<input type="button" value="Перезагрузить страницу" title="Возможно, обновление страницы исправит ситуацию.<br>Жми!" onclick="window.location=`'.$self.'?devtype='.$devtype.'&ip='.$ip.'`;"/>';
  exit();
}

$Power=preg_split("/\s\s+/", $PowerLine);
echo '<table border="1" cellpadding="15">'.$br;
echo '<tr><th>Предел<br>мощности<br>PoE (Вт)</th><th>Использованная<br>мощность<br>PoE (Вт)</th><th>Запас<br>мощности<br>PoE (Вт)</th></tr>';
echo '<tr><td>'.$Power[1].'</td><td>'.$Power[2].'</td><td>'.$Power[3].'</td></tr>';
echo '</table>';
echo '<br>';


$writebttn=isset($devtype)&&$devtype==1?'<input type="button" value="write" title="Выполнить на коммутаторе команду `write config`" onclick="window.location=`'.$self.'?action=write&devtype='.$devtype.'&ip='.$ip.'`;"/>':'';
$th='<tr>
     <th>Link<br>status</th><th>Link<br>adm</th><th>PoE<br>status</th><th>PoE<br>adm</th><th>PoE<br>max</th><th>PoE<br>cur</th><th>Interface Name</th><th>Interface Descr.</th><th>comment</th><td class="noborder">'.
     $writebttn.'</td></tr>'.$br;
echo '<table border="1" cellpadding="15">'.$br;
echo $th;

#$ed=0;
foreach ($InterfacesArray as &$Interface) {
  $Interface[2]=$Interface[2]==1?1:0; // статус линка на интерфейсе
  $Interface[3]=$Interface[3]==1?1:0; // admin-статус линка на интерфейсе
  $Interface[5]=$Interface[5]==1?1:0; // admin-статус PoE
  $Interface[6]=$Interface[6]==1?1:0; // статус PoE
  $Interface[7]=$Interface[7]!=0?'<font color="green"><strong>'.$Interface[7].'</strong></font>':'<small>'.$Interface[7].'</small>'; // Текущая мощность на PoE порту
# полное описание массива:
# [OID-номер интерфейса][Имя интерфейса][статус линка на интерфейсе][admin-статус линка на интерфейсе][interface description]
#  [admin-статус PoE][статус PoE][Текущий предел мощности на порту][Заданный предел мощности на порту]

  echo'<tr>'.$br;
  if (!$ed){
    echo'<td>'.(ShowLamp($Interface[2])).'</td>'.$br.
        '<td>'.(ShowOnOff($Interface[1],'link',$Interface[3])).'</td>'.$br.
        '<td>'.(isset($Interface[8])&&$Interface[8]>0?ShowLamp($Interface[6]):'').'</td>'.$br.
        '<td>'.(isset($Interface[8])&&$Interface[8]>0?ShowOnOff($Interface[1],'poe',$Interface[5]):'').'</td>'.$br.
        '<td><small>'.(isset($Interface[8])&&$Interface[8]>0?$Interface[8]:'').'</small></td>'.$br.
        '<td>'.(isset($Interface[8])&&$Interface[8]>0?$Interface[7]:'').'</td>'.$br.
        '<td>'.$Interface[1].'</td>'.$br.
        '<td>'.$Interface[4].'</td>'.$br.
        '<td> - </td>'.$br;
    echo '<td class="noborder">'.$br;
    echo ' <a href="'.$self.'?action=editline&devtype='.$devtype.'&ip='.$ip.'&interface='.$Interface[1].'">'.$br;
    echo '<img src="img/edit.png" width="15" height="15" title="Редактировать строку"></a> '.$br;
    echo '</td>'.$br;
    echo'</tr>'.$br;
  }else{
    if ($interface!=$Interface[1]){
      echo '<td></td><td></td><td></td><td></td><td></td><td></td>'.$br;
      echo '<td>'.$Interface[1].'</td><td>'.$Interface[4].'</td><td>-</td><td class="noborder"></td></tr>'.$br;
    }else{
      echo'<td><form method="POST" action='.$self.'>'.$br;
      echo'<input type="hidden" name="action" value="saveline">'.$br;
      echo'<input type="hidden" name="devtype" value="'.$devtype.'">'.$br;
      echo'<input type="hidden" name="ip" value="'.$ip.'">'.$br;
      echo'<input type="hidden" name="interface" value="'.$interface.'"></td>'.$br;
      echo'<input type="hidden" name="olddescr" value="'.$Interface[4].'"></td>'.$br;
      echo '<td></td><td></td><td></td><td></td><td></td>'.$br;
      echo'<td>'.$Interface[1].'</td><td>'.$br;
      echo'<input type="text" name="descr" size="50" value="'.$Interface[4].'" autocomplete="off"></td><td>';
      echo'<input type="text" name="comment" size="50" value="'.'-'.'" autocomplete="off"></td>';
      echo'<td class="noborder">';
      echo'<input type="submit" value="Записать"><input type="button" value="Отмена" onclick="window.location=`'.$self.'?devtype='.$devtype.'&ip='.$ip.'`;"/>';
      echo '</td>'.$br;
      echo'</tr>'.$br;
    }
  }
}
echo $th;
echo'</table>';


#        '<td>'.(ShowOnOff2($Interface[8],'link',$Interface[3])).'</td>'.$br.
#        '<td>'.(ShowLamp($Interface[3])).'</td>'.$br.

exit(0);

echo '<pre>';
print_r(GetInterfacesInfo($ip));
echo '</pre>';

/*

echo '<pre>';
echo "-----------\n";
echo "POST\n";
print_r($_POST);
echo "-----------\n";
echo "GET\n";
print_r($_GET);
echo '</pre>';

*/

#$eddevice=2;
#$ed=1;


$query = mysqli_query($connect,"SELECT * FROM `devices` WHERE `type_id`=$devtype ORDER BY INET_ATON(ip_address)"); //подгружаем список устройств
echo "SELECT * FROM `devices` WHERE `type_id`=$devtype ORDER BY `ip_address`"; exit();

if (!mysqli_num_rows($query)) {ShowPlus(0); exit();}
echo'
     <table border="1" cellpadding="15">
     <tr>
     <th>S</th><th>BL</th><th>ip address</th><th>devname</th><th>comment</th><td class="noborder"></td>
     </tr>';
while($res = mysqli_fetch_array($query)){ // Выбираем устройства
    echo'<tr>';
    if (!$ed){
        echo'<td>'.(ShowLamp(ping($res['ip_address']))).'</td><td>'.(ShowOnOff($res['id'],!$res['blocked'])).'</td><td>'.$res['ip_address'].'</td><td>'.$res['devname'].'</td><td>'.$res['comment'].'</td><td class="noborder">';
        echo ' <a href="'.$self.'?action=editline&devtype='.$devtype.'&eddevice='.$res['id'].'">';
        echo '<img src="img/edit.png" width="15" height="15" title="Редактировать строку"></a> ';
    }else{
        if ($eddevice!=$res['id']){
            echo'<td> </td><td> </td><td>'.$res['ip_address'].'</td><td>'.$res['devname'].'</td><td>'.$res['comment'].'</td><td class="noborder">';
        }else{
            echo'<td><form method="POST" action='.$self.'>';
            echo'<input type="hidden" name="action" value="saveline">';
            echo'<input type="hidden" name="devtype" value="'.$devtype.'">';
            echo'<input type="hidden" name="eddevice" value="'.$res['id'].'">';
            echo'</td><td>'.$res['ip_address'].'</td><td>';
            echo'<input type="text" name="devname" size="40" value="'.$res['devname'].'" autocomplete="off"></td><td>';
            echo'<input type="text" name="comment" size="40" value="'.$res['comment'].'" autocomplete="off"></td>';
            echo'<td class="noborder">';
            echo'<input type="submit" value="Записать"><input type="button" value="Отмена" onclick="window.location=`'.$self.'?devtype='.$devtype.'`;"/>';
        }
    }
    echo'</td>';
    echo'</tr>';
}

if(isset($action) && $action=='adddevice') ShowAdddeviceLine();

echo'</table>';
if(!isset($action)&&!$ed) ShowPlus();
if (isset($error)) echo '<font color="red">Введены некорректные данные. Проверьте!</font>';

echo'</body></html>';

?>

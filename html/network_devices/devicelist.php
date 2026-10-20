<?php
/*
 * devicelist.php
 *
 * Отображение и изменение списка коммутаторов из базы данных, проверка их
 * доступности и переход к настройке портов или переименованию интерфейсов.
 *
 * Расположение: /html/network_devices/devicelist.php
 * Входные GET/POST-параметры: devtype, action, devid, devname, ipaddress,
 * comment, eddevice; action выбирает добавление, изменение, блокировку или
 * перечитывание имени устройства.
 * Выход: HTML-страница списка устройств либо HTTP-перенаправление; операции
 * изменяют таблицу устройств и при необходимости вызывают API/Expect.
 * Пример вызова: /network_devices/devicelist.php?devtype=1
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
include ('config.php');
include ('lib.php');
$self = 'devicelist.php';
$pagename='Список устройств';
#date_default_timezone_set('Europe/Saratov');
$br="\n";
$ed=0;



function ping($ip){
  exec("fping -c 2 -q -p 50 $ip >/dev/null 2>/dev/null $ip", $output, $retval);
  if ($retval==0) $retval=1; else $retval=0; // в случае успешного пинга =1, неуспешного =0
  return $retval;
}

#echo ping('192.168.2.10');

#exit();

if (isset($_POST['devtype']) && !isset($_POST['action']) && !isset($_GET['action'])){
header( 'Location: '.$self.'?devtype='.$_POST['devtype'], true, 302  );
exit();
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
getvar('devname');
getvar('devid');
getvar('comment');
getvar('eddevice');
getvar('action');
getvar('ipaddress');
getvar('error');

#getvar('');

if (isset($eddevice)) $ed=1;


if (isset($action)) {
    if ($action == 'saveline'){
        if (isset($devname) && strlen($devname)>5){
            $a=1;
        }else{
            header( 'Location: '.$self.'?action=editline&devtype='.$devtype.'&eddevice='.$eddevice, true, 302 );
            exit();
        }
        $res1=mysqli_fetch_array(mysqli_query($connect,"SELECT `devname`, `ip_address` FROM `devices` WHERE `id`=$eddevice LIMIT 1;"));
        $name=$res1['name'];
        $ip=$res1['ip_address'];
        if ($devtype==1 && $devname !== $name) {
          //Обновляем имя устройства
           exec("expect -d /var/www/html/network_devices/cisco_host_rename.expect $ip '$devname' >/dev/null 2>/dev/null", $output, $retval);
           sleep(2);
           $query = mysqli_query($connect,"UPDATE `devices` SET `devname` = '$devname', `comment` = '$comment' WHERE `id` = $eddevice LIMIT 1;"); //
           $vendor=mysqli_fetch_array(mysqli_query($connect,"SELECT `devtype` FROM `dev_type` WHERE `id`=$devtype"))['devtype'];
           $result = file_get_contents('http://network.pmpmilk.ru/LogReadDevInventorySQL.php?devtype='.$devtype.'&vendor='.$vendor);
           sleep(2);
        }
        if ($devtype==1 && $devname !== $name) header( 'Location: '.$self.'?devtype='.$devtype.'&action=reread', true, 302  );
        else header( 'Location: '.$self.'?devtype='.$devtype, true, 302  );
        exit();
    }

    if ($action == 'savenewline'){
        if (isset($devname) && strlen($devname)>5 && isset($ipaddress) && isset($devtype)){
          if (!isset($comment)) $comment='';

          //$pattern_ip = '/192\.168\.202\.[0-9]{1,3}/';
          //preg_match('/([0-9]+\.[0-9]+\.[0-9]+\.)([0-9]+)/', $ipaddress, $matches);
          //$NewIP=(int)$matches[2];
          //if (preg_match($pattern_ip, $ipaddress) && $NewIP>1 && $NewIP<255){
            //echo 'Ok';
            $query = mysqli_query($connect,"INSERT INTO `devices`
                                                (`id`, `ip_address`, `type_id`, `devname`, `comment`)
                                        VALUES (NULL, '$ipaddress', '$devtype', '$devname', '$comment')");
            if ($query){
              $result = file_get_contents('http://network.pmpmilk.ru/LogReadDevInventorySQL.php'); // Обновляем файлы со списком в соответствии с измененной БД
//            echo '---Ok---'; exit;
              header( 'Location: '.$self.'?devtype='.$devtype.'&action=reread', true, 302 ); // Перечитываем с устройств имена в базу
              exit();
            }
          //}
          header( 'Location: '.$self.'?action=addevice&devtype='.$devtype.'&error=error', true, 302 );
          exit();
        }
    }
    if ($action == 'reread'){
        if (isset($devtype)){
          $vendor=mysqli_fetch_array(mysqli_query($connect,"SELECT `devtype` FROM `dev_type` WHERE `id`=$devtype"))['devtype'];
          $result = file_get_contents('http://network.pmpmilk.ru/LogReadDevInventorySQL.php?devtype='.$devtype.'&vendor='.$vendor.'&opt=getjson');
          $DevArray=json_decode($result);
          if (isset($DevArray[0][0]) && $DevArray[0][0]==$vendor) {
            foreach ($DevArray as $Device) { // Перебираем элемента массива со списком устройств
              $query = mysqli_query($connect,"UPDATE `devices` SET `devname` = '".$Device[2]."' WHERE `ip_address` = '".$Device[1]."' LIMIT 1;");
            }
          }
        }
        header( 'Location: '.$self.'?devtype='.$devtype, true, 302 );
        exit();
    }
    if ($action == 'onoff'){
        if (isset($devtype)&&isset($devid)){
          $query = mysqli_query($connect,"UPDATE `devices` SET `blocked` = !blocked WHERE `id` = '$devid' LIMIT 1;");
        }
        header( 'Location: '.$self.'?devtype='.$devtype, true, 302 );
        exit();
    }

    if ($action == 'qwe'){
        header( 'Location: '.$self.'?devtype='.$devtype, true, 302 );
        exit();

    }

}



if (isset($_GET['card_id'])) $CardID=$_GET['card_id']; else $CardID='';

function nav_buttons() { // навигация
    global $self;
    echo '<table border="1" cellpadding="15">';
    echo '<tr>';
    echo '<td class="noborder" width="150">';
    echo '<input type="button" value="На главную" onclick="window.location=`main.php`;"/>';
    echo '</td>';
    echo '<td class="noborder" width="150">';
    echo '</td>';
    echo '</tr></table>';
}


function TypeSelector(){ // рисует выпадающий список с типами устройств из базы
    global $connect, $devtype;
    $query = mysqli_query($connect,"SELECT * FROM `dev_type`"); //подгружаем список типов устройств
    echo'
        <select size="1" name="devtype">
        ';
    echo'<option '.(!isset($devtype)?'selected':'').' disabled>Выберите тип</option>';
    while($res = mysqli_fetch_array($query)){ // Выбираем устройства
      echo' <option '.(isset($devtype) && $devtype==$res['id']?'selected':'').' value="'.$res['id'].'">'.$res['devtype'].'</option>';
    }
    echo'</select>';
}

function DeviceTypeSelector() {
    global $self, $devtype;
    echo'
        <form method="POST" action='.$self.'>
        <table border="1" cellpadding="15">
        <tr>
        <td class="noborder">';
    TypeSelector();
    echo'
        </td>
        <td class="noborder">
          <input type="submit" value="Выбрать">
          </form>
        </td>
        <td class="noborder">';
    if (isset($devtype)) echo'<input type="button" value="Перечитать имена устройств" onclick="window.location=`'.$self.'?action=reread&devtype='.$devtype.'`;"/>';
    echo'
        </td>
        </tr>
        </table>
        ';
}
function ShowLamp($status){
  if (!$status) $output=' <img src="img/red_lamp.png" width="20" height="20" title="Устройство недоступно"> ';
  else $output=' <img src="img/green_lamp.png" width="20" height="20" title="Устройство активно и отвечает на запросы"> ';
  return $output;
}

function ShowOnOff($id, $status){
  global $self, $devtype;
  $output='<a href="'.$self.'?action=onoff&devtype='.$devtype.'&devid='.$id.'">';
  if (!$status) $output.='<img src="img/bttn_off.png" width="40" title="Резервное копирование конфигурации устройства отключено.'."\n".'Нажмите, чтобы активировать данный функционал"></a>';
  else $output.='<img src="img/bttn_on.png" width="40" title="Резервное копирование конфигурации устройства активировано"></a>';
  return $output;
}

function ShowPlus(){
  global $devtype, $self;
  echo '<a href="'.$self.'?action=adddevice&devtype='.$devtype.'">';
  echo '<img src="img/green_plus.png" width="20" height="20" title="Добавить устройство"></a> ';
}

function ShowAdddeviceLine(){
  global $devtype, $self, $connect, $error;
  $IPAddress=CalculateNewIPAddress();
  $DevTypeName=GetDevTypeName();
  echo'<tr>';
  echo'<td>new<form method="POST" action='.$self.'>';
  echo'<input type="hidden" name="action" value="savenewline">';
  echo'<input type="hidden" name="devtype" value="'.$devtype.'">';
  echo'</td><td></td><td><input type="text" name="ipaddress" size="30" value="'.$IPAddress.'" autocomplete="off"></td><td>';
  echo'<input type="text" name="devname" size="40" value="sw_'.$DevTypeName.'_" autocomplete="off"></td><td>';
  echo'<input type="text" name="comment" size="40" autocomplete="off"></td>';
  echo'<td class="noborder">';
  echo'<input type="submit" value="Записать"><input type="button" value="Отмена" onclick="window.location=`'.$self.'?devtype='.$devtype.'`;"/>';
  echo'</td></tr>';
}

function ShowIpAddres($IP){
  global $DevStatus, $devtype;
  $result='';
  if ($DevStatus && $devtype==1){
     $result='<input type="button" class="bttn_on" value="'.$IP.'" onclick="window.location=`portlist.php?devtype='.$devtype.'&ip='.$IP.'`;" title="Перейти к настройкам интерфейсов устройства"/>';
#    $result='<a href="portlist.php?devtype='.$devtype.'&ip='.$IP.'" title="Перейти к настройкам интерфейсов устройства">'.$IP.'</a>';
  }elseif($DevStatus && $devtype==2){
     $result='<input type="button" class="bttn_on" value="'.$IP.'" onclick="window.location=`mikrot_port_renamer.php?devtype='.$devtype.'&ip='.$IP.'`;" title="Сгенерировать транслит-имена портов устройства"/>';
  }
  else{
    $result='<input type="button" class="bttn_off" value="'.$IP.'" title="Устройство неактивно. Информация по интерфейсам недоступна."/>';
  }
  return $result;
}


function CalculateNewIPAddress(){
  global $devtype, $connect;
  $query = mysqli_query($connect,"SELECT * FROM `devices` WHERE type_id=$devtype ORDER BY INET_ATON(ip_address) DESC LIMIT 1"); //Находим устройство с максимальным IP
  if (!mysqli_num_rows($query)) return 'Error!!!';
  $res=mysqli_fetch_array($query);
  preg_match('/([0-9]+\.[0-9]+\.[0-9]+\.)([0-9]+)/', $res['ip_address'], $matches);
  $NewIP=(int)$matches[2]+1;
  if ($NewIP>254) return 'Закончились IP';
  return $matches[1].strval($NewIP);
}

function GetDevTypeName(){
  global $devtype, $connect;
  $query = mysqli_query($connect,"SELECT * FROM `dev_type` WHERE `id`=$devtype LIMIT 1"); //получаем наименование выбранного типа устройств
  if (!mysqli_num_rows($query)) return 'Error!!!';
  return mysqli_fetch_array($query)['devtype'];
}

// Основной код программы

echo htmlHeader($pagename);

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

nav_buttons();
DeviceTypeSelector();
#$eddevice=2;
#$ed=1;

if (!isset($devtype)) exit();

$query = mysqli_query($connect,"SELECT * FROM `devices` WHERE `type_id`=$devtype ORDER BY INET_ATON(ip_address)"); //подгружаем список устройств
#echo "SELECT * FROM `devices` WHERE `type_id`=$devtype ORDER BY `ip_address`";exit();



if (!mysqli_num_rows($query)) {ShowPlus(0); exit();}
echo'
     <table border="1" cellpadding="15">
     <tr>
     <th><a title="Текущий статус устройства'.$br.'(Доступен/Недоступен)">S</a></th><th><a title="Отключение регулярного бэкапа конфигурации">BL</a></th><th>ip address</th><th>devname</th><th>comment</th><td class="noborder"></td>
     </tr>'.$br;
while($res = mysqli_fetch_array($query)){ // Выбираем устройства
    $DevStatus=ping($res['ip_address']);
#    $IpForPrint=ShowIpAddres($res['ip_address']);
    echo'<tr>'.$br;
    if (!$ed){
        echo'<td>'.(ShowLamp($DevStatus)).'</td>'.$br.'<td>'.(ShowOnOff($res['id'],!$res['blocked'])).'</td><td>'.(ShowIpAddres($res['ip_address'])).'</td><td>'.$res['devname'].'</td><td>'.$res['comment'].'</td><td class="noborder">';
        echo ' <a href="'.$self.'?action=editline&devtype='.$devtype.'&eddevice='.$res['id'].'">'.$br;
        echo '<img src="img/edit.png" width="15" height="15" title="Редактировать строку"></a> '.$br;
    }else{
        if ($eddevice!=$res['id']){
            echo'<td> </td><td> </td><td>'.$res['ip_address'].'</td><td>'.$res['devname'].'</td><td>'.$res['comment'].'</td><td class="noborder">'.$br;
        }else{
            echo'<td><form method="POST" action='.$self.'>'.$br;
            echo'<input type="hidden" name="action" value="saveline">';
            echo'<input type="hidden" name="devtype" value="'.$devtype.'">';
            echo'<input type="hidden" name="eddevice" value="'.$res['id'].'">';
            echo'</td>'.$br.'<td>'.'</td>'.$br.'<td>'.$res['ip_address'].'</td>'.$br.'<td>';
            echo'<input type="text" name="devname" size="40" value="'.$res['devname'].'" autocomplete="off"></td><td>';
            echo'<input type="text" name="comment" size="40" value="'.$res['comment'].'" autocomplete="off"></td>';
            echo'<td class="noborder">';
            echo'<input type="submit" value="Записать"><input type="button" value="Отмена" onclick="window.location=`'.$self.'?devtype='.$devtype.'`;"/>';
        }
    }
    echo'</td>';
    echo'</tr>'.$br;
}

if(isset($action) && $action=='adddevice') ShowAdddeviceLine();

echo'</table>';
if(!isset($action)&&!$ed) ShowPlus();
if (isset($error)) echo '<font color="red">Введены некорректные данные. Проверьте!</font>';

echo'</body></html>';

?>

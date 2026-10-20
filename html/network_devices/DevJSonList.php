<?php
/*
 * DevJSonList.php
 *
 * Выдача типов и устройств из базы данных в виде JSON для инвентаризации.
 *
 * Расположение: /html/network_devices/DevJSonList.php
 * Входные параметры: нет; читает типы и устройства из базы данных.
 * Выход: JSON-массив с типом, IP-адресом, именем, флагом блокировки и комментарием.
 * Пример вызова: /network_devices/DevJSonList.php
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
# генерируем список устройств из базы в формат json
include ('config.php');

function DevArrayGenerator(){
  global $connect;
  $query = mysqli_query($connect,"SELECT d.ip_address, d.devname, dt.devtype, d.blocked, d.comment
                                  FROM dev_type dt, devices d
                                  WHERE d.type_id = dt.id
                                  ORDER BY dt.devtype, INET_ATON(d.ip_address)"); //подгружаем список типов устройств
  while($res = mysqli_fetch_array($query)){ // перебираем устройства
    $DeviceList[]=array($res['devtype'], $res['ip_address'], $res['devname'], $res['blocked'], $res['comment']); // Создаем очередной элемент массива: array(Vendor, Ip, DeviceName)
 }
  return $DeviceList;
}
$json = json_encode(DevArrayGenerator());
echo($json);
?>

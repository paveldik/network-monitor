<?php
/*
 * Вспомогательные функции оформления страниц управления сетевыми устройствами.
 *
 * Расположение: /html/network_devices/lib.php
 * Входные параметры: htmlHeader принимает название страницы; Footer использует
 * глобальные данные пользователя.
 * Выход: функции подключаемого файла формируют HTML заголовка и подвала.
 * Пример вызова: require_once __DIR__.'/lib.php'; htmlHeader('Устройства');
 *
 * @author  Pavel Dik: paveldik@gmail.com
 * @version 1.0
 */
function htmlHeader($pagename){
    // проверяем в куках состояние селектора режимов работы системы
    global $work_mode, $user_role;
    if (isset($_COOKIE['pmpmilk_work_mode'])) {
        $work_mode = $_COOKIE['pmpmilk_work_mode'];
    } else $work_mode=0;
    setcookie('pmpmilk_work_mode', $work_mode, time() + 3600*6); // обновляем таймер кука
    if ($work_mode || $user_role==7) $bgcolor='#D0D0D0'; else $bgcolor='#D0FFD0';
    echo '<html lang="ru"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title>'.$pagename.'</title>
        <link rel="shortcut icon" href="img/favicon.ico?v=1.1" type="image/x-icon">
        <link rel="stylesheet" href="css/style.css?v=1.3"></head><body>
        <link rel="stylesheet" href="css/style2.css?v=1.3"></head><body>
        <script>
            function confirmationDelete(anchor,message)
            {
                var conf = confirm(message);
                if(conf) window.location=anchor.attr("href");
            }
        </script>
        <style type="text/css">
            body {background-color: '.$bgcolor.';}
        </style>
        </head><body>
        ';
#    showseconduser();
}


// подвал сайта с копирайтами и контактной информацией
function Footer(){
    global $user_role, $user_id, $user_name, $login_name, $seconduser, $self, $permissions;
    echo '&nbsp;<br>';
    echo '&nbsp;<br>';
    echo '&nbsp;<br>';
    echo '<div id="footer">';
    echo '<table width="100%">';
    echo '<tr width="95%">';
    echo '<td class="noborder" width="30%">';
    echo '&copy; Павел Дик (926)231-4521';
    echo '</td>';
    echo '<td class="noborder_center" width="20%">';
#    if ($self=='main.php' && ($user_role==0 || $user_role==2)) {
#       echo '<input type="button" value="V'.($work_mode+1).'" onclick="window.location=`main.php?action=change_mode`;" title="Переключатель режимов работы системы" /><br>';
#    }
    echo '</td>';
    echo '<td class="noborder_right" width="40%">';
    if (isset($user_id)) {
        echo '<form action = "logout.php" method = "get"><input type = "submit" value = " <- "/>';
        echo ' Пользователь: '.$login_name.' &nbsp;';
        //echo '|&nbsp;<small>(pp:'.$permissions.' | ur:'.$user_role.')</small> &nbsp;';
        echo '</form>';
    }
    echo '</td>';
    echo '</tr>';
    echo '</table>';
    echo '</div>';
}

?>

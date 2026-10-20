# SetCiscoHostname.sh
# Фрагмент Bash-кода для вызова Expect-сценария получения сведений о коммутаторе.
# Расположение: /html/network_devices/SetCiscoHostname.sh
# Входные параметры: переменные окружения ExpectScript_SwitchInfo и IP.
# Выход: вывод Expect подавляется; код возврата команды передается вызывающему shell.
# Пример вызова: bash -c 'source /var/www/html/network_devices/SetCiscoHostname.sh; getswitchinfo'
# @author  Pavel Dik: paveldik@gmail.com
# @version 1.0
function getswitchinfo() {
  expect -d $ExpectScript_SwitchInfo $IP >/dev/null 2>/dev/null
}

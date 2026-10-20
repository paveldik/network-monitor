#
# network-monitor-config.tcl
#
# Чтение параметров доступа к сетевым устройствам из общего системного файла.
#
# Расположение: /html/network_devices/network-monitor-config.tcl
# Входные параметры: нет; читает учетные параметры Cisco из системного файла.
# Выход: словарь Tcl с параметрами конфигурации.
# Пример вызова: source network-monitor-config.tcl; set cfg [load_network_monitor_config]
#
# @author  Pavel Dik: paveldik@gmail.com
# @version 1.0
#

proc load_network_monitor_config {} {
    set config_path "/etc/network-monitor/network-monitor.cfg"
    if {![file readable $config_path]} {
        error "Не удалось прочитать системный файл конфигурации приложения."
    }

    set config [dict create]
    set file_id [open $config_path r]
    set line_number 0
    while {[gets $file_id line] >= 0} {
        incr line_number
        set line [string trim $line]
        if {$line eq "" || [string index $line 0] eq "#"} {
            continue
        }
        if {![regexp {^([A-Z][A-Z0-9_]*)=(.*)$} $line -> name value]} {
            close $file_id
            error "Некорректная строка в системном файле конфигурации: $line_number"
        }
        if {[dict exists $config $name]} {
            close $file_id
            error "Повторяется параметр в системном файле конфигурации: $name"
        }
        dict set config $name [string trim $value]
    }
    close $file_id

    foreach name {CISCO_USERNAME CISCO_PASSWORD CISCO_ENABLE_PASSWORD} {
        if {![dict exists $config $name] || [dict get $config $name] eq ""} {
            error "В системном файле конфигурации отсутствует обязательный параметр: $name"
        }
    }
    return $config
}

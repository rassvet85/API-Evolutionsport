Evolution API Server

Проект сделан на основе ТЗ "Проект API сервера для СКУД Evolutionsport.docx"

Настройки параметров WEB-делегирование Sigur:
- URL делегирования: http://Apiserver/api/laravel
- URL доставки проходов: http://Apiserver/api/events

Конфиг сервера находится в таблице БД "config": 

    apipoint - конфиг точек доступа API
    kpppoint - конфиг точек доступа КПП
    nfcpoint - конфиг точек доступа NFC
    gate - конфиг точек-калиток
    invtypecard - конфиг названия типа карт лиц с инвалидностью
    soptypecard - конфиг название типа карт сопровождающих
    soprovidcard - конфиг id типа карты Сопровождающий
    psstypecard - конфиг названия типа карт ПСС
    fitnesslogin - конфиг логин к API 1СFitness
    fitnesspass - конфиг пароль к API 1СFitness
    apieventsurl - URL в конфиге apieventsurl
    apigetaccessurl – адрес API 1С Фитнесс в конфиге при запросе которого происходит логирование доступа
    apigetaccessnologurl - адрес API 1С Фитнесс в конфиге при запросе которого не происходит логирование доступа
    dualpass - конфиг времени в секундах для двойного прикладывания карты
    timestart - конфиг времени в минутах для доступа на территорию до начала занятия
    pointnull - конфиг id виртуальной нулевой точки прохода
    pointpss - конфиг id вирутальной точки прохода ПСС
    


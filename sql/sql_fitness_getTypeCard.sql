/* MSSQL 2019 - Запрос названия карты напрямую с БД 1С ФИтнесс */
SELECT TYPCARD._Description AS carddesc, CONVERT(int, CARDS._Fld2881) AS statuscard
/* _Reference87 Справочник.Карты (данные карты клиента) */
FROM _Reference87 AS CARDS
/* _Reference56 Справочник.ВидыКарт (выбираем тип карты клиента)*/
LEFT JOIN _Reference56 AS TYPCARD ON TYPCARD._IDRRef = CARDS._Fld2878RRef
WHERE CARDS._Marked = 0x0 AND CARDS._Fld2882 = :wildcard
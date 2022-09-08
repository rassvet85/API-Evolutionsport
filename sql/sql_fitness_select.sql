/*MSSQL 2019 - запрос клиента по его карте $wildcard (ID) в 1С Фитнес*/
DECLARE @WILDCARD VARCHAR(8), @IDSOP VARBINARY(32)
/* @WILDCARD - ID карты доступа клиента */
SET @WILDCARD = :wildcard;
/* @IDSOP - ID типа карты сопровождающего */
SET @IDSOP = CONVERT(VARBINARY(32), :idsop, 1);
/* T1 Таблица с данными фото клиентов */
WITH T1 AS (
/* Reference94 Справочник.КонтрагентыПрисоединенныеФайлы (фото клиента) */
    SELECT _IDRRef AS ID, CONVERT(int,_Version) AS Version
    FROM _Reference94
	WHERE _Fld3076 = 42
UNION
/* Reference168 Справочник.Файлы (фото клиента) */
SELECT _IDRRef AS ID, CONVERT(int,_Version) AS Version
FROM _Reference168
	WHERE _Fld4297 = 42
	),
/* T1 Таблица с занятиями клиентов */
	T2 AS (
/* _Document193 Документ.Занятие */
/* _Document193_VT895 Документ.Занятие.СоставЗанятия */
/* _Enum360 Перечисление.СтатусыЗанятия */
SELECT ZAN1._Fld898RRef AS ID, STAT._EnumOrder AS statuszan, ZAN2._Fld850 AS starttime, ZAN2._Fld851 AS finishtime
FROM _Document193_VT895 AS ZAN1
    JOIN _Document193 AS ZAN2 ON ZAN2._IDRRef = ZAN1._Document193_IDRRef AND ZAN2._Marked = 0x0 AND ZAN2._Posted = 1 AND DATEADD(yy, -2000, ZAN2._Fld850) > Convert(DateTime, DATEDIFF(DAY, 0, GETDATE())) AND DATEADD(yy, -2000, ZAN2._Fld850) < Convert(DateTime, DATEDIFF(DAY, -1, GETDATE()))
    JOIN _Enum360 AS STAT ON STAT._IDRRef = ZAN2._Fld874RRef
    ),
/* T3 Таблица разовых услуг клиента */
    T3 AS (
    /* _Document215 Документ.Реализация */
/* _AccumRgT7920 РегистрНакопления.ЧленстваПакетыУслуг */
SELECT DOC3._Fld1497RRef AS ID, 1 AS RAZUSL
FROM _Document215 AS DOC3
    JOIN _AccumRgT7920 AS REG ON REG._Fld7904_RRRef = DOC3._IDRRef AND REG._Fld7906 > 0
    )
/* Выборка активных услуг и данных клиента */
SELECT SUB._Description AS name, UPPER(CARDS._Fld2882) AS card, DATEADD(yy, -2000, DOC1._Fld7489) AS exptime, BINFILE.Version AS phototime, 0 AS sop, TYPCARD._Description AS carddesc, VIDUS._EnumOrder as vidus, TYPEUS._EnumOrder as typeus, DOC1._Fld7492 as dayus, DOC1._Fld7493 as posus, CONVERT(int,CARDS._Fld2881) AS statuscard, STAT._EnumOrder AS status, DATEADD(yy, -2000, DOC2._Fld1175) AS date1, T3.RAZUSL AS razusl, T2.statuszan, DATEADD(yy, -2000, T2.starttime) AS starttime, DATEADD(yy, -2000, T2.finishtime) AS finishtime
/* _Reference87 Справочник.Карты (данные карты клиента) */
FROM _Reference87 AS CARDS
         /* _Document226 Документ.ЧленствоПакетУслуг (выбираем неудалённые и не отменённые услуги) */
         LEFT JOIN _Document226 AS DOC ON DOC._Fld1963_RRRef = CARDS._Fld2879_RRRef AND DOC._Marked = 0x0 AND DOC._Fld1940 = 0x0
    /* _Enum273 Перечисление.ВидыЧленствПакетовУслуг (выбираем id названия типа услуги)*/
    LEFT JOIN _Enum273 AS TYPEUS ON TYPEUS._IDRRef = DOC._Fld1945RRef
    /* _Enum397 Перечисление.ТипыНоменклатуры (выбираем id вида услуги)*/
    LEFT JOIN _Enum397 AS VIDUS ON VIDUS._IDRRef = DOC._Fld2004RRef
    /* _InfoRg7483 РегистрСведений.ЧленстваПакетыУслугИтоги (дополнительные данные по услугам)*/
    LEFT JOIN _InfoRg7483 AS DOC1 ON DOC1._Fld7484RRef = DOC._IDRRef
    /* _Enum379 Перечисление.СтатусыЧленствПакетовУслуг (выбираем id статуса услуг)*/
    LEFT JOIN _Enum379 AS STAT ON STAT._IDRRef = DOC1._Fld7485RRef AND STAT._EnumOrder > 0
    /* _Reference56 Справочник.ВидыКарт (выбираем тип карты клиента)*/
    LEFT JOIN _Reference56 AS TYPCARD ON TYPCARD._IDRRef = CARDS._Fld2878RRef
    /* _Document202 Документ.ОперацииСЧленствомПакетомУслуг (выбираем данные по блокировке услуги, если они есть)*/
    LEFT JOIN _Document202 AS DOC2 ON DOC2._Fld1172RRef = DOC._IDRRef AND DOC2._Marked = 0x0 AND DOC2._Version = (SELECT MAX(_Version) FROM _Document202 WHERE _Fld1172RRef = DOC._IDRRef)
    /* _Reference93 Справочник.Контрагенты (выбираем персональные данные клиента)*/
    LEFT JOIN _Reference93 AS SUB ON SUB._Marked = 0x0 AND SUB._IDRRef = CARDS._Fld2879_RRRef
    LEFT JOIN T1 AS BINFILE ON BINFILE.ID = SUB._Fld3026_RRRef
    LEFT JOIN T2 ON T2.ID = CARDS._Fld2879_RRRef
    LEFT JOIN T3 ON T3.ID = CARDS._Fld2879_RRRef
WHERE CARDS._Fld2882 = @WILDCARD AND CARDS._Marked = 0x0 AND CARDS._Fld2888 = 0x0 AND DATEADD(yy, -2000, DOC1._Fld7489) > DATEADD(mm, -12, getdate())

UNION
/* Выборка активных услуг и данных сопровождаемых */
SELECT SUB._Description AS name, UPPER(CARDSX._Fld2882) AS card, DATEADD(yy, -2000, DOC1._Fld7489) AS exptime, BINFILE.Version AS phototime, 1 AS sop, TYPCARD._Description AS carddesc, VIDUS._EnumOrder as vidus, TYPEUS._EnumOrder as typeus, DOC1._Fld7492 as dayus, DOC1._Fld7493 as posus, CONVERT(int, CARDS._Fld2881) AS statuscard, STAT._EnumOrder AS status, DATEADD(yy, -2000, DOC2._Fld1175) AS date1, T3.RAZUSL AS razusl, T2.statuszan, DATEADD(yy, -2000, T2.starttime) AS starttime, DATEADD(yy, -2000, T2.finishtime) AS finishtime
/* _InfoRg6849 РегистрСведений.РодственныеСвязи (выбираем сопаровождаемых клиента) */
FROM _InfoRg6849 AS CLRLX
         /* _Reference87 Справочник.Карты (данные карты сопровождаемого) */
         JOIN _Reference87 AS CARDS ON CARDS._Fld2879_RRRef = CLRLX._Fld6850RRef
    /* _Reference56 Справочник.ВидыКарт (выбираем тип карты клиента)*/
    JOIN _Reference56 AS TYPCARD ON TYPCARD._IDRRef = CARDS._Fld2878RRef
    /* _Reference87 Справочник.Карты (данные карт сопровождаемых, должны быть активны и не удалены) */
    JOIN _Reference87 AS CARDSX ON CARDSX._Fld2879_RRRef = CLRLX._Fld6851RRef AND CARDSX._Marked = 0 AND CARDSX._Fld2881 = 0x0 AND CARDSX._Fld2888 = 0x0
    /* _Document226 Документ.ЧленствоПакетУслуг (выбираем неудалённые и не отменённые услуги сопровождаемых) */
    LEFT JOIN _Document226 AS DOC ON DOC._Fld1963_RRRef = CLRLX._Fld6851RRef AND DOC._Marked = 0x0 AND DOC._Fld1940 = 0x0
    /* _Enum273 Перечисление.ВидыЧленствПакетовУслуг (выбираем id названия типа услуги)*/
    LEFT JOIN _Enum273 AS TYPEUS ON TYPEUS._IDRRef = DOC._Fld1945RRef
    /* _Enum379 Перечисление.СтатусыЧленствПакетовУслуг (выбираем id статуса услуг)*/
    LEFT JOIN _Enum397 AS VIDUS ON VIDUS._IDRRef = DOC._Fld2004RRef
    /* _Document202 Документ.ОперацииСЧленствомПакетомУслуг (выбираем данные по блокировке услуги у сопровождаемых, если они есть)*/
    LEFT JOIN _Document202 AS DOC2 ON DOC2._Fld1172RRef = DOC._IDRRef AND DOC2._Marked = 0x0 AND DOC2._Version = (SELECT MAX(_Version) FROM _Document202 WHERE _Fld1172RRef = DOC._IDRRef)
    /* _InfoRg7483 РегистрСведений.ЧленстваПакетыУслугИтоги (дополнительные данные по услугам сопровождаемых)*/
    LEFT JOIN _InfoRg7483 AS DOC1 ON DOC1._Fld7484RRef = DOC._IDRRef
    /* _Enum379 Перечисление.СтатусыЧленствПакетовУслуг (выбираем id статуса услуг)*/
    LEFT JOIN _Enum379 AS STAT ON STAT._IDRRef = DOC1._Fld7485RRef AND STAT._EnumOrder > 0
    /* _Reference93 Справочник.Контрагенты (выбираем персональные данные сопровождаемого)*/
    LEFT JOIN _Reference93 AS SUB ON SUB._Marked = 0x0 AND SUB._IDRRef = CARDS._Fld2879_RRRef
    LEFT JOIN T1 AS BINFILE ON BINFILE.ID = SUB._Fld3026_RRRef
    LEFT JOIN T2 ON T2.ID = CLRLX._Fld6850RRef
    LEFT JOIN T3 ON T3.ID = CLRLX._Fld6851RRef
WHERE CARDS._Fld2882 = @WILDCARD AND CARDS._Fld2878RRef = @IDSOP AND (CARDSX._Fld2878RRef IS NULL OR CARDSX._Fld2878RRef <> @IDSOP) AND CARDS._Marked = 0x0 AND CARDS._Fld2888 = 0x0 AND DATEADD(yy, -2000, DOC1._Fld7489) > DATEADD(mm, -12, getdate())
/* Сортируем по статусу услуги, статусу занятия, время начала занятия, окончания действия услуги */
ORDER BY status, statuszan, starttime, exptime DESC
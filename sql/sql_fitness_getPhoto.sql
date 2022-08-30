/*Запрос версии фото клиента (не используется)*/
WITH T1 AS (
    SELECT _IDRRef AS ID, CONVERT(int,_Version) AS Version
    FROM _Reference94
	WHERE _Fld3076 = 42
UNION
SELECT _IDRRef AS ID, CONVERT(int,_Version) AS Version
FROM _Reference168
	WHERE _Fld4297 = 42
)

SELECT BINFILE.Version AS phototime
FROM _Reference87 AS CARDS
    LEFT JOIN _Reference93 AS SUB ON SUB._IDRRef = CARDS._Fld2879_RRRef
    LEFT JOIN T1 AS BINFILE ON BINFILE.ID = SUB._Fld3026_RRRef
WHERE CARDS._Fld2882 = :wildcard
WITH TA AS (
    SELECT CARDS._Fld2879_RRRef AS ID, CARDS._Fld2882 AS CARD, CARDS._Fld2878RRef AS TYPECARD
    FROM _Reference87 AS CARDS
    WHERE CARDS._Marked = 0x0 AND CARDS._Fld2881 = 0x0 AND CARDS._Fld2888 = 0x0 AND LEN(CARDS._Fld2882) NOT IN (0,1,2,3,4,5,6,7) AND LEN(CARDS._Fld2882) < 9
    ),
    TB AS (
SELECT CARDS.ID, CARDS.CARD, CARDS.TYPECARD,  MAX(DATEADD(yy, -2000, DOC1._Fld7489)) AS EXPDATE
FROM TA AS CARDS
    JOIN _Document226 AS DOC ON DOC._Fld1963_RRRef = CARDS.ID AND DOC._Marked = 0x0 AND DOC._Fld1940 = 0x0
    JOIN _InfoRg7483 AS DOC1 ON DOC1._Fld7484RRef = DOC._IDRRef
WHERE DATEADD(yy, -2000, DOC1._Fld7489) > DATEADD(mm, -12, getdate())
GROUP BY CARDS.ID, CARDS.CARD, CARDS.TYPECARD

UNION

SELECT CARDS.ID, CARDS.CARD, CARDS.TYPECARD,  MAX(DATEADD(yy, -2000, ZAN2._Fld851)) AS EXPDATE
FROM TA AS CARDS
    JOIN _Document193_VT895 AS ZAN1 ON ZAN1._Fld898RRef = CARDS.ID
    JOIN _Document193 AS ZAN2 ON ZAN2._IDRRef = ZAN1._Document193_IDRRef AND ZAN2._Marked = 0x0 AND ZAN2._Posted = 1
    JOIN _Reference56 AS CARD_TYPE ON CARD_TYPE._IDRRef = CARDS.TYPECARD
WHERE DATEADD(yy, -2000, ZAN2._Fld851) > DATEADD(mm, -12, getdate()) AND CARD_TYPE._Description LIKE '%аренд%'
GROUP BY CARDS.ID, CARDS.CARD, CARDS.TYPECARD
    ),
    TC AS (
SELECT ID, CARD, TYPECARD, MAX(EXPDATE) AS EXPDATE
FROM TB
GROUP BY ID, CARD, TYPECARD
    ),
    T0 AS (
SELECT DOC._Fld1963_RRRef as ID, count (DOC._Fld1963_RRRef) as CNTUSL
FROM _Document226 AS DOC
    JOIN _InfoRg7483 AS DOC1 ON DOC1._Fld7484RRef = DOC._IDRRef
WHERE DATEADD(yy, -2000, DOC1._Fld7489) > getdate() AND DOC._Marked = 0 AND DOC._Fld1940 = 0
GROUP BY DOC._Fld1963_RRRef
    ),
    T1 AS (
SELECT DOC._Fld1963_RRRef as ID, CASE WHEN T0.CNTUSL <= 5 THEN STRING_AGG(CONCAT(CASE WHEN STCT._EnumOrder = 2 THEN CONCAT('Услуга Заблокирована до ',FORMAT(DATEADD(yy, -2000, DOC2._Fld1175), 'dd.MM.yyyy'),':', char(10)) WHEN STCT._EnumOrder = 3 THEN CONCAT('Услуга Заморожена до ',FORMAT(DATEADD(yy, -2000, DOC2._Fld1175), 'dd.MM.yyyy'),':', char(10))  END, NAMENOM._Description, ' (Срок действия: ', FORMAT(DATEADD(yy, -2000, DOC1._Fld7489), 'dd.MM.yyyy'), ')', CASE WHEN TYPEUS._Enumorder = 0 THEN CONCAT(char(10), '- осталось дней: ', DOC1._Fld7492) END, CASE WHEN TYPEUS._Enumorder = 1 THEN CONCAT(char(10), '- осталось посещений: ', DOC1._Fld7493) END, CASE WHEN DOC1._Fld7495 > 0 THEN CONCAT(char(10), '- осталось заморозок: ', DOC1._Fld7495) END), char(10)) WITHIN GROUP (ORDER BY NAMENOM._Description) ELSE CONCAT('Действующих услуг - ',T0.CNTUSL, char(10), 'Их слишком много для отображения') END AS NAMECARD
FROM _Document226 AS DOC
    JOIN _InfoRg7483 AS DOC1 ON DOC1._Fld7484RRef = DOC._IDRRef
    JOIN _Enum273 AS TYPEUS ON TYPEUS._IDRRef = DOC._Fld1945RRef
    JOIN _Enum379 AS STCT ON STCT._IDRRef = DOC1._Fld7485RRef
    LEFT JOIN _Reference104 AS NAMENOM ON NAMENOM._IDRRef = DOC._Fld1964RRef
    LEFT JOIN _Document202 AS DOC2 ON DOC2._Fld1172RRef = DOC._IDRRef AND DOC2._Posted = 0x1 AND DOC2._Version = (SELECT MAX(_Version) FROM _Document202 WHERE _Fld1172RRef = DOC._IDRRef)
    LEFT JOIN T0 ON T0.ID = DOC._Fld1963_RRRef
WHERE DATEADD(yy, -2000, DOC1._Fld7489) > getdate() AND DOC._Marked = 0x0 AND DOC._Fld1940 = 0x0
GROUP BY DOC._Fld1963_RRRef, T0.CNTUSL
    ),
    T1A AS (
SELECT DOC3._Fld1497RRef AS ID, CONCAT(STRING_AGG(CONCAT(CONVERT(NVARCHAR(max),NAMENOM._Description),' - разовая услуга', char(10), '- осталось посещений: ', FLOOR(REG._Fld7906)), char(10)) WITHIN GROUP (ORDER BY NAMENOM._Description), char(10)) AS NAMECARD
FROM _Document215 AS DOC3
    JOIN _AccumRgT7920 AS REG ON REG._Fld7904_RRRef = DOC3._IDRRef AND REG._Fld7906 > 0
    LEFT JOIN _Reference104 AS NAMENOM ON NAMENOM._IDRRef = REG._Fld7905_RRRef
GROUP BY DOC3._Fld1497RRef
    ),
    T1B AS (
SELECT ID, CONCAT('Ближайшие занятия: ', DESC1, char(10), '- ' , STRING_AGG(CONVERT(NVARCHAR(max),CONCAT(CASE WHEN DATA1 = FORMAT(getdate(), 'dd.MM.yyyy') THEN 'сегодня в' ELSE DATA1 END,' ', DATA2)), ', ') WITHIN GROUP (ORDER BY DATA1, DATA2)) AS NAMECARD
FROM (
    SELECT ZAN1._Fld898RRef AS ID, NAMENOM._Description AS DESC1, FORMAT(DATEADD(yy, -2000, ZAN2._Fld850),'dd.MM.yyyy') AS DATA1, FORMAT(DATEADD(yy, -2000, ZAN2._Fld850),'HH:mm') AS DATA2, ROW_NUMBER() OVER (PARTITION BY ZAN1._Fld898RRef ORDER BY (SELECT NULL)) AS rn
    FROM _Document193_VT895 AS ZAN1
    JOIN _Document193 AS ZAN2 ON ZAN2._IDRRef = ZAN1._Document193_IDRRef AND ZAN2._Marked = 0x0 AND ZAN2._Posted = 1
    LEFT JOIN _Reference104 AS NAMENOM ON NAMENOM._IDRRef = ZAN2._Fld864RRef
    WHERE DATEADD(yy, -2000, ZAN2._Fld850) > getdate()
    ) T
WHERE rn <= 3
GROUP BY ID, DESC1
    ),
    T2 AS (
SELECT ID, MAX(EXPDATE) AS EXPDATE, MAGSTR, CARDTYPE, STRING_AGG( DESC1, ', ') WITHIN GROUP (ORDER BY DESC1) AS DESCR
FROM (
    SELECT CLRLX._Fld6850RRef AS ID, CONCAT(SUB._Description, ' (', CLRNAME._Description, ', ', FORMAT(CASE WHEN T1A.NAMECARD IS NOT NULL AND (TC.EXPDATE IS NULL OR TC.EXPDATE < getdate()) THEN Convert(DateTime, DATEDIFF(DAY, -1, GETDATE())) ELSE TC.EXPDATE END, 'dd.MM.yyyy'),  ')') AS DESC1, CARDS._Fld2882 AS MAGSTR, CARDS._Fld2878RRef AS CARDTYPE, CASE WHEN T1A.NAMECARD IS NOT NULL AND TC.EXPDATE < getdate() THEN Convert(DateTime, DATEDIFF(DAY, -1, GETDATE())) ELSE TC.EXPDATE END AS EXPDATE
    FROM _Reference87 AS CARDS
    JOIN _InfoRg6849 AS CLRLX ON CLRLX._Fld6850RRef = CARDS._Fld2879_RRRef
    JOIN TC ON TC.ID = CLRLX._Fld6851RRef AND (TC.TYPECARD IS NULL OR TC.TYPECARD <> 0x9F03005056AE903511ECE3C7E852A063)
    LEFT JOIN T1A ON T1A.ID = CLRLX._Fld6851RRef
    JOIN _Reference93 AS SUB ON SUB._Marked = 0x0 AND SUB._IDRRef = CLRLX._Fld6851RRef
    LEFT JOIN _Reference151 AS CLRNAME ON CLRNAME._IDRRef = CLRLX._Fld6852RRef
    WHERE CARDS._Fld2878RRef = 0x9F03005056AE903511ECE3C7E852A063 AND CARDS._Marked = 0x0 AND CARDS._Fld2881 = 0x0 AND CARDS._Fld2888 = 0x0
    ) T
GROUP BY ID, MAGSTR, CARDTYPE),
    T3 AS (
SELECT _IDRRef AS ID, CONVERT(int,_Version) AS Version
FROM _Reference94
WHERE _Fld3076 = 42
UNION
SELECT _IDRRef AS ID, CONVERT(int,_Version) AS Version
FROM _Reference168
WHERE _Fld4297 = 42
    ),
    T4 AS (
SELECT _Reference93_IDRRef AS ID, CONCAT(char(10), 'Заметка: ', STRING_AGG(_Fld3061, '; ') WITHIN GROUP (ORDER BY _LineNo3057)) AS NOTE
FROM _Reference93_VT3056
WHERE _Fld3059 = 0x1
GROUP BY _Reference93_IDRRef
    ),
    T5 AS (
SELECT TC.ID AS ID, TC.CARD AS MAGSTR, CARD_TYPE._Description AS CARDTYPE, CASE WHEN T1A.NAMECARD IS NOT NULL AND TC.EXPDATE < getdate() THEN Convert(DateTime, DATEDIFF(DAY, -1, GETDATE())) ELSE TC.EXPDATE END AS EXPDATE, CASE WHEN T1A.NAMECARD IS NOT NULL OR T1B.NAMECARD IS NOT NULL OR T1.NAMECARD IS NOT NULL THEN CONCAT(T1A.NAMECARD, T1B.NAMECARD, T1.NAMECARD) END AS DESCR
FROM TC
    LEFT JOIN T2 ON T2.ID = TC.ID
    LEFT JOIN T1 ON T1.ID = TC.ID
    LEFT JOIN T1A ON T1A.ID = TC.ID
    LEFT JOIN T1B ON T1B.ID = TC.ID
    LEFT JOIN _Reference56 AS CARD_TYPE ON CARD_TYPE._IDRRef = TC.TYPECARD

UNION

SELECT T2.ID AS ID, T2.MAGSTR AS MAGSTR, CARD_TYPE._Description AS CARDTYPE, T2.EXPDATE, CASE WHEN T2.EXPDATE < getdate() THEN CONCAT(char(10),'Было сопровождение для: ', T2.DESCR) ELSE CONCAT('Сопровождение до: ', FORMAT(T2.EXPDATE, 'dd.MM.yyyy'),char(10),'Сопровождение для: ', T2.DESCR) END AS DESCR
FROM T2
    LEFT JOIN _Reference56 AS CARD_TYPE ON CARD_TYPE._IDRRef = T2.CARDTYPE
WHERE T2.EXPDATE IS NOT NULL
    )

SELECT SUB._Description AS FULL_NAME, CONCAT('8', CONVERT(VARCHAR(32), T5.ID, 2)) AS ID, T5.MAGSTR, 'FITNESS' AS DEP, T5.CARDTYPE AS COMM, MAX(T5.EXPDATE) AS EXPDATE, BINFILE.Version AS PHOTO_VER,
       CONCAT('Тип карты: ', CASE WHEN T5.CARDTYPE IS NOT NULL THEN T5.CARDTYPE ELSE 'без названия' END, char(10), CASE WHEN MAX(T5.EXPDATE) < getdate() THEN 'Действующие услуги отсутствуют' END, STRING_AGG( T5.DESCR, char(10)) WITHIN GROUP (ORDER BY T5.DESCR), T4.NOTE) AS DESCR
FROM T5
         JOIN _Reference93 AS SUB ON SUB._Marked = 0x0 AND SUB._IDRRef = T5.ID
    LEFT JOIN T3 AS BINFILE ON BINFILE.ID = SUB._Fld3026_RRRef
    LEFT JOIN T4 ON T4.ID = T5.ID
GROUP BY SUB._Description, T5.ID, T5.MAGSTR, T5.CARDTYPE, BINFILE.Version, T4.NOTE
/*MSSQL 2019 - выбор сотрудника по $Empid (ID) 1С ЗУП*/
/*Таблица Т1 - полная таблица данных сотрудников, включая работников, имеющих несколько должностей на работе*/
/*Таблица Т2 - исключительно сотрудники, которые имеют по 2+ ставки*/
WITH T1 AS
         (
             SELECT CONVERT(VARCHAR(32), s._Fld6975RRef, 2) AS ID,
                    m._Description                          AS NAME,
                    s._Code                                 AS TABELNUMBER,
                    org._Description                        AS ORGANIZATION,
                    CONCAT(org._Description, CASE WHEN e13._Description <> '' THEN CONCAT('|', e13._Description) END,
                           CASE WHEN e12._Description <> '' THEN CONCAT('|', e12._Description) END,
                           CASE WHEN e11._Description <> '' THEN CONCAT('|', e11._Description) END,
                           CASE WHEN e10._Description <> '' THEN CONCAT('|', e10._Description) END,
                           CASE WHEN e._Description <> '' THEN CONCAT('|', e._Description) END
                        )                                   AS DEPARTMENT,
                    e3._Description                         AS DOLGNOST,
                    d._Fld22646                             AS STAVKA,
                    e5._EnumOrder                           AS STAVKANUM,
                    ISNULL(LEN(f._Fld26060), 0)             AS VERSIONFOTO,
                 /* Если уже известна дата увольнения - назначаем время работы пропуска: следующий день после даты увольнения 8 часов утра. */
                    IIF(e1._Fld25329 < e1._Fld25328, null, DATEADD(hh, +32, DATEADD(yy, -2000, e1._Fld25329))) AS TIMES
                 /* _Reference226 Справочник.Сотрудники */
             FROM _Reference226 as s
                      /* _InfoRg22638 РегистрСведений.КадроваяИсторияСотрудников (находим актуальную должность сотрудника)*/
                      LEFT JOIN _InfoRg22638 as d on s._IDRRef = d._Fld22639RRef AND d._Period = (SELECT max(_Period)
                                                                                                  FROM _InfoRg22638
                                                                                                  WHERE s._IDRRef = _Fld22639RRef)
                 /* _Reference291 Справочник.ФизическиеЛица (ФИО и вся личная информация)*/
                      LEFT JOIN _Reference291 as m on m._IDRRef = s._Fld6975RRef
                 /* _Reference172 Справочник.ПодразделенияОрганизаций (подразделение в котором работает сотрудник)*/
                      LEFT JOIN _Reference172 as e on e._IDRRef = d._Fld22643RRef
                      LEFT JOIN _Reference172 as e10 on e10._IDRRef = e._ParentIDRRef
                      LEFT JOIN _Reference172 as e11 on e11._IDRRef = e10._ParentIDRRef
                      LEFT JOIN _Reference172 as e12 on e12._IDRRef = e11._ParentIDRRef
                      LEFT JOIN _Reference172 as e13 on e13._IDRRef = e12._ParentIDRRef
                 /* _Reference75 Справочник.Должности (должность сотрудника)*/
                      LEFT JOIN _Reference75 as e3 on e3._IDRRef = d._Fld22644RRef
                 /* _InfoRg25321 РегистрСведений.ТекущиеКадровыеДанныеСотрудников (дата приема и увольнения)*/
                      LEFT JOIN _InfoRg25321 as e1 on s._IDRRef = e1._Fld25323RRef
                 /* _Reference126 Справочник.Организации (находим организацию в которой работает работник ИП Плошенко либо наш работник)*/
                      LEFT JOIN _Reference126 as org on org._IDRRef = s._Fld6976RRef
                 /* _InfoRg27645 РегистрСведений.ВидыЗанятостиСотрудников (находим последний вид занятости)*/
                      LEFT JOIN _InfoRg27645 as e4 on s._IDRRef = e4._Fld27646RRef AND e4._Period = (SELECT max(_Period)
                                                                                                     FROM _InfoRg27645
                                                                                                     WHERE s._IDRRef = _Fld27646RRef)
                 /* _InfoRg26058 РегистрСведений.ФотографииФизическихЛиц (Фото сотрудника)*/
                      LEFT JOIN _InfoRg26058 as f on f._Fld26059RRef = s._Fld6975RRef
                 /* _Enum503 Вид занятости: 0 - основное место работы; 1- внешнее совместительство; 2 - внутреннее совместительство */
                      LEFT JOIN _Enum503 as e5 on e5._IDRRef = e4._Fld27649RRef

                 /*проверка на архив*/
             WHERE s._Fld6979 = 0
                 /* Проверка на увольнение */
               AND (e1._Fld25329 < e1._Fld25328 OR e1._Fld25329 >= DATEADD(yy, +2000, getdate() - 1.33334))
         ),
     T2 AS (SELECT ID FROM T1 GROUP BY ID HAVING COUNT(*) > 1)

    /*Выбираем сотрудников имеющих несколько должностей на работе и выбираем по приоритету из _Enum503 (меньше - лучше) */
SELECT ID,
       NAME,
       TABELNUMBER,
       ORGANIZATION,
       DEPARTMENT,
       DOLGNOST,
       VERSIONFOTO,
       TIMES
FROM T1 as M1
WHERE M1.ID IN (SELECT ID FROM T2 WHERE ID IS NOT NULL)
  AND M1.STAVKANUM = (SELECT min(STAVKANUM) FROM T1 AS MM WHERE MM.ID = M1.ID)

UNION
/*Остальные сотрудники */
SELECT ID,
       NAME,
       TABELNUMBER,
       ORGANIZATION,
       DEPARTMENT,
       DOLGNOST,
       VERSIONFOTO,
       TIMES
FROM T1 as M2
WHERE M2.ID NOT IN (SELECT ID FROM T2 WHERE ID IS NOT NULL)

ORDER BY ID
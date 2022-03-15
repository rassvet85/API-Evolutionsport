	/*Таблица Т1 - полная таблица данных сотрудников, включая работников, имеющих несколько должностей на работе*/
	/*Таблица Т2 - исключительно сотрудники, которые имеют по 2+ ставки*/

WITH T1 AS 
(
	SELECT CONVERT(VARCHAR(32), s._Fld6975RRef, 2) AS ID, e5._EnumOrder AS STAVKANUM,
	/* Если уже известна дата увольнения - назначаем время работы пропуска: следующий день после даты увольнения 8 часов утра. */
	CASE
		WHEN e1._Fld25329 < e1._Fld25328 THEN null
		ELSE DATEADD(hh,+32,DATEADD(yy,-2000,e1._Fld25329))
	END AS EXPTIME
	/* _Reference226 Справочник.Сотрудники */
	FROM _Reference226 as s
	/* _InfoRg22638 РегистрСведений.КадроваяИсторияСотрудников (находим актуальную должность сотрудника)*/
	LEFT JOIN _InfoRg22638 as d on s._IDRRef = d._Fld22639RRef AND d._Period = (SELECT max(_Period) FROM _InfoRg22638 WHERE s._IDRRef = _Fld22639RRef)
	/* _InfoRg25321 РегистрСведений.ТекущиеКадровыеДанныеСотрудников (дата приема и увольнения)*/
	LEFT JOIN _InfoRg25321 as e1 on s._IDRRef = e1._Fld25323RRef
	/* _InfoRg27645 РегистрСведений.ВидыЗанятостиСотрудников (находим последний вид занятости)*/
	LEFT JOIN _InfoRg27645 as e4 on s._IDRRef = e4._Fld27646RRef AND e4._Period = (SELECT max(_Period) FROM _InfoRg27645 WHERE s._IDRRef = _Fld27646RRef)
	/* _Enum503 Вид занятости: 0 - основное место работы; 1- внешнее совместительство; 2 - внутреннее совместительство */
	LEFT JOIN _Enum503 as e5 on e5._IDRRef = e4._Fld27649RRef

	WHERE s._Fld6975RRef = 0x8128B8CA3AF80F9A11E73A07234E2FB2
	/*проверка на архив*/
	AND s._Fld6979 = 0 
	/* Проверка на увольнение */
	AND (e1._Fld25329 < e1._Fld25328 OR e1._Fld25329 >= DATEADD(yy,+2000,getdate()-1.33334) )
	),
	T2 AS (SELECT ID FROM T1 GROUP BY ID HAVING COUNT(*) >1)
		
	/*Выбираем сотрудников имеющих несколько должностей на работе и выбираем по приоритету из _Enum503 (меньше - лучше) */
	SELECT EXPTIME
	FROM T1 as M1
	WHERE M1.ID IN (SELECT ID FROM T2 WHERE ID IS NOT NULL) AND M1.STAVKANUM = (SELECT min(STAVKANUM) FROM T1 AS MM WHERE MM.ID = M1.ID)
	
	UNION
	/*Остальные сотрудники */
	SELECT EXPTIME
	FROM T1 as M2
	WHERE M2.ID NOT IN (SELECT ID FROM T2 WHERE ID IS NOT NULL)

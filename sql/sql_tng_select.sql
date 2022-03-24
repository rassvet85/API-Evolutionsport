/*Oracle 12 - вывод всех клиентов TNG*/
/*Таблица Т2 - выбор пользователя, у которого тип карты - сопровождающий */
WITH T2 AS (SELECT CARD_ID
            FROM ((SELECT CARDS.CARD_ID AS CARD_ID
                   FROM CARDS
                            LEFT JOIN CLIENT_RELATIONS CLRLX
                                      ON CARDS.CARD_ID = CLRLX.CARD_ID_1 AND CLRLX.CARD_ID_1 != CLRLX.CARD_ID_2
                            LEFT JOIN CARDS CARDSX ON CARDSX.CARD_ID = CLRLX.CARD_ID_2
                   WHERE CARDS.CARD_TYPE_ID <> 5335
                     AND CARDSX.MAGSTRIPE = :wildcard
                     AND CARDSX.CARD_TYPE_ID = 5335
                     AND CLRLX.CARD_ID_1 IS NOT NULL)
                  UNION
                  (SELECT CARDS.CARD_ID AS CARD_ID
                   FROM CARDS
                            LEFT JOIN CLIENT_RELATIONS CLRLY
                                      ON CARDS.CARD_ID = CLRLY.CARD_ID_2 AND CLRLY.CARD_ID_1 != CLRLY.CARD_ID_2
                            LEFT JOIN CARDS CARDSY ON CARDSY.CARD_ID = CLRLY.CARD_ID_1
                   WHERE CARDS.CARD_TYPE_ID <> 5335
                     AND CARDSY.MAGSTRIPE = :wildcard
                     AND CARDSY.CARD_TYPE_ID = 5335
                     AND CLRLY.CARD_ID_2 IS NOT NULL)
                  UNION
                  (SELECT CARDS.CARD_ID AS CARD_ID
                   FROM CARDS
                            LEFT JOIN CLIENT_RELATIONS CLRLX
                                      ON CARDS.CARD_ID = CLRLX.CARD_ID_1 AND CLRLX.CARD_ID_1 != CLRLX.CARD_ID_2
                            LEFT JOIN CARDS CARDSX ON CARDSX.CARD_ID = CLRLX.CARD_ID_2
                            LEFT JOIN CARD_XTRA CARDEXTRA ON CARDEXTRA.CARD_ID = CLRLX.CARD_ID_2
                   WHERE CARDS.CARD_TYPE_ID <> 5335
                     AND CARDEXTRA.MAGSTRIPE = :wildcard
                     AND CARDEXTRA.CARD_TYPE_ID = 5335
                     AND CARDEXTRA.DELETE_DATE IS NULL)
                  UNION
                  (SELECT CARDS.CARD_ID AS CARD_ID
                   FROM CARDS
                            LEFT JOIN CLIENT_RELATIONS CLRLY
                                      ON CARDS.CARD_ID = CLRLY.CARD_ID_2 AND CLRLY.CARD_ID_1 != CLRLY.CARD_ID_2
                            LEFT JOIN CARDS CARDSY ON CARDSY.CARD_ID = CLRLY.CARD_ID_1
                            LEFT JOIN CARD_XTRA CARDEXTRA ON CARDEXTRA.CARD_ID = CLRLY.CARD_ID_1
                   WHERE CARDS.CARD_TYPE_ID <> 5335
                     AND CARDEXTRA.MAGSTRIPE = :wildcard
                     AND CARDEXTRA.CARD_TYPE_ID = 5335
                     AND CARDEXTRA.DELETE_DATE IS NULL))
            GROUP BY CARD_ID),
     /*Таблица ТA - данные по сроку действия абонемента у пользователя с типом карты - не сопровождающий */
     TA AS (SELECT CARDS.VALID_TILL,
                   MAX(CASE
                           WHEN SUBX.status = 2
                               THEN CASE
                                        WHEN SUBX.IS_MEMBERSHIP = 0
                                            THEN SUBX.expiration_date + 1
                                        ELSE CASE
                                                 WHEN SUBX.expiration_date > SUBX.mmshp_end_date
                                                     THEN SUBX.expiration_date + 1
                                                 ELSE SUBX.mmshp_end_date + 1 END END
                           ELSE CASE
                                    WHEN SUBX.expiration_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') OR
                                         SUBX.mmshp_end_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS')
                                        THEN TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS')
                                    ELSE CASE
                                             WHEN (SUBX.expiration_date > SUBX.mmshp_end_date OR
                                                   SUBX.mmshp_end_date IS NULL)
                                                 THEN SUBX.expiration_date + 1
                                             ELSE SUBX.mmshp_end_date + 1 END END END) as EXPDATE
            FROM SUBSCRIPTION_ACCOUNTING SUBX
                     INNER JOIN CARDS ON CARDS.CARD_ID = SUBX.CARD_ID
            WHERE CARDS.MAGSTRIPE = :wildcard
              AND CARDS.CARD_STATUS_ID = 1
              AND length(CARDS.magstripe) = 8
            GROUP BY SUBX.CARD_ID, CARDS.VALID_TILL),
     /*Таблица ТB - данные по сроку действия абонемента у пользователя с типом карты - сопровождающий */
     TB AS (SELECT CARDS.VALID_TILL,
                   MAX(CASE
                           WHEN SUBX.status = 2
                               THEN CASE
                                        WHEN SUBX.IS_MEMBERSHIP = 0
                                            THEN SUBX.expiration_date + 1
                                        ELSE CASE
                                                 WHEN SUBX.expiration_date > SUBX.mmshp_end_date
                                                     THEN SUBX.expiration_date + 1
                                                 ELSE SUBX.mmshp_end_date + 1 END END
                           ELSE CASE
                                    WHEN SUBX.expiration_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') OR
                                         SUBX.mmshp_end_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS')
                                        THEN TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS')
                                    ELSE CASE
                                             WHEN (SUBX.expiration_date > SUBX.mmshp_end_date OR
                                                   SUBX.mmshp_end_date IS NULL)
                                                 THEN SUBX.expiration_date + 1
                                             ELSE SUBX.mmshp_end_date + 1 END END END) as EXPDATE
            FROM SUBSCRIPTION_ACCOUNTING SUBX
                     INNER JOIN CARDS ON CARDS.CARD_ID = SUBX.CARD_ID
                     LEFT JOIN T2 ON T2.CARD_ID = CARDS.CARD_ID
            WHERE SUBX.CARD_ID = T2.CARD_ID
              AND CARDS.CARD_STATUS_ID = 1
              AND length(CARDS.magstripe) = 8
            GROUP BY CARDS.VALID_TILL),
     /*Таблица ТС - выбор максимально возможного срока времени у пользователя с типом карты - не сопровождающий */
     TC AS (SELECT 1                                                                       as TT,
                   CASE WHEN VALID_TILL + 1 < EXPDATE THEN VALID_TILL + 1 ELSE EXPDATE END AS EXPDATE
            FROM TA),
     /*Таблица ТD - выбор максимально возможного срока времени у пользователя с типом карты - сопровождающий */
     TD AS (SELECT 1                                                                                as TT,
                   MAX(
                           CASE WHEN VALID_TILL + 1 < EXPDATE THEN VALID_TILL + 1 ELSE EXPDATE END) AS EXPDATE
            FROM TB)
SELECT (CASE WHEN CARD_TYPE_ID = 5335 THEN TD.EXPDATE ELSE TC.EXPDATE END) AS exptime
FROM CARDS
         LEFT JOIN TC ON TC.TT = 1
         LEFT JOIN TD ON TD.TT = 1
WHERE CARDS.MAGSTRIPE = :wildcard
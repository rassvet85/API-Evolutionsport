<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nette\Utils\DateTime;




class Apicontroller extends Controller
{

    public function Apinew(Request $request): \Illuminate\Http\JsonResponse
    {
        #функция проверки типа переменной - datetime;
        function verifyDate($date): bool
        {
            return (DateTime::createFromFormat('Y-m-d H:i:s', $date) !== false);
        }


        try {

            date_default_timezone_set("Europe/Moscow");
            $data =  $request->json()->all();

            $sys = 1; #local

            $Wildcard = "00000000";
            $Empid = null;

            if(isset($data['keyHex'])) $Wildcard = $data['keyHex']; else return response()->json(['allow'=>false, 'message' => 'Ошибка сервера: ER0002']);
            if(isset($data['extId'])) $Empid = $data['extId'];
            //$Wildcard = "4F555D66";
            //$Empid = 80652;
            if (strlen($Empid) == 32) {
                #1C
                $users = DB::connection('sqlsrv')->select(" /*Таблица Т1 - полная таблица данных сотрудников, включая работников, имеющих несколько должностей на работе*/ /*Таблица Т2 - исключительно сотрудники, которые имеют по 2+ ставки*/ WITH T1 AS( SELECT CONVERT(VARCHAR(32), s._Fld6975RRef, 2) AS ID, e5._EnumOrder AS STAVKANUM, /* Если уже известна дата увольнения - назначаем время работы пропуска: следующий день после даты увольнения 8 часов утра. */ CASE WHEN e1._Fld25329 < e1._Fld25328 THEN null ELSE DATEADD(hh,+32,DATEADD(yy,-2000,e1._Fld25329)) END AS EXPTIME /* _Reference226 Справочник.Сотрудники */ FROM _Reference226 as s /* _InfoRg22638 РегистрСведений.КадроваяИсторияСотрудников (находим актуальную должность сотрудника)*/ LEFT JOIN _InfoRg22638 as d on s._IDRRef = d._Fld22639RRef AND d._Period = (SELECT max(_Period) FROM _InfoRg22638 WHERE s._IDRRef = _Fld22639RRef) /* _InfoRg25321 РегистрСведений.ТекущиеКадровыеДанныеСотрудников (дата приема и увольнения)*/ LEFT JOIN _InfoRg25321 as e1 on s._IDRRef = e1._Fld25323RRef /* _InfoRg27645 РегистрСведений.ВидыЗанятостиСотрудников (находим последний вид занятости)*/ LEFT JOIN _InfoRg27645 as e4 on s._IDRRef = e4._Fld27646RRef AND e4._Period = (SELECT max(_Period) FROM _InfoRg27645 WHERE s._IDRRef = _Fld27646RRef) /* _Enum503 Вид занятости: 0 - основное место работы; 1- внешнее совместительство; 2 - внутреннее совместительство */ LEFT JOIN _Enum503 as e5 on e5._IDRRef = e4._Fld27649RRef WHERE s._Fld6975RRef = 0x$Empid /*проверка на архив*/ AND s._Fld6979 = 0 /* Проверка на увольнение */ AND (e1._Fld25329 < e1._Fld25328 OR e1._Fld25329 >= DATEADD(yy,+2000,getdate()-1.33334)) ), T2 AS (SELECT ID FROM T1 GROUP BY ID HAVING COUNT(*) >1) /*Выбираем сотрудников имеющих несколько должностей на работе и выбираем по приоритету из _Enum503 (меньше - лучше) */ SELECT EXPTIME FROM T1 as M1 WHERE M1.ID IN (SELECT ID FROM T2 WHERE ID IS NOT NULL) AND M1.STAVKANUM = (SELECT min(STAVKANUM) FROM T1 AS MM WHERE MM.ID = M1.ID) UNION /*Остальные сотрудники */ SELECT EXPTIME FROM T1 as M2 WHERE M2.ID NOT IN (SELECT ID FROM T2 WHERE ID IS NOT NULL)");
                if (!isset($users) && !DB::connection('sqlsrv')->getDatabaseName()) return response()->json(['allow' => false, 'message' => 'Ошибка: Сервер 1С не доступен']);
            }
            else if (strlen($Empid) != null) {
                                #TNG
                $users = DB::connection('oracle')->select("/*Таблица Т2 - выбор пользователя, у которого тип карты - сопровождающий */ WITH T2 AS( SELECT CARD_ID FROM ( (SELECT CARDS.CARD_ID AS CARD_ID FROM CARDS LEFT JOIN CLIENT_RELATIONS CLRLX ON CARDS.CARD_ID = CLRLX.CARD_ID_1 AND CLRLX.CARD_ID_1 != CLRLX.CARD_ID_2 LEFT JOIN CARDS CARDSX ON CARDSX.CARD_ID = CLRLX.CARD_ID_2 WHERE CARDS.CARD_TYPE_ID <> 5335 AND CARDSX.MAGSTRIPE = '$Wildcard' AND CARDSX.CARD_TYPE_ID = 5335 AND CLRLX.CARD_ID_1 IS NOT NULL) UNION (SELECT CARDS.CARD_ID AS CARD_ID FROM CARDS LEFT JOIN CLIENT_RELATIONS CLRLY ON CARDS.CARD_ID = CLRLY.CARD_ID_2 AND CLRLY.CARD_ID_1 != CLRLY.CARD_ID_2 LEFT JOIN CARDS CARDSY ON CARDSY.CARD_ID = CLRLY.CARD_ID_1 WHERE CARDS.CARD_TYPE_ID <> 5335 AND CARDSY.MAGSTRIPE = '$Wildcard' AND CARDSY.CARD_TYPE_ID = 5335 AND CLRLY.CARD_ID_2 IS NOT NULL) UNION (SELECT CARDS.CARD_ID AS CARD_ID FROM CARDS LEFT JOIN CLIENT_RELATIONS CLRLX ON CARDS.CARD_ID = CLRLX.CARD_ID_1 AND CLRLX.CARD_ID_1 != CLRLX.CARD_ID_2 LEFT JOIN CARDS CARDSX ON CARDSX.CARD_ID = CLRLX.CARD_ID_2 LEFT JOIN CARD_XTRA CARDEXTRA ON CARDEXTRA.CARD_ID = CLRLX.CARD_ID_2 WHERE CARDS.CARD_TYPE_ID <> 5335 AND CARDEXTRA.MAGSTRIPE = '$Wildcard' AND CARDEXTRA.CARD_TYPE_ID = 5335 AND CARDEXTRA.DELETE_DATE IS NULL) UNION (SELECT CARDS.CARD_ID AS CARD_ID FROM CARDS LEFT JOIN CLIENT_RELATIONS CLRLY ON CARDS.CARD_ID = CLRLY.CARD_ID_2 AND CLRLY.CARD_ID_1 != CLRLY.CARD_ID_2 LEFT JOIN CARDS CARDSY ON CARDSY.CARD_ID = CLRLY.CARD_ID_1 LEFT JOIN CARD_XTRA CARDEXTRA ON CARDEXTRA.CARD_ID = CLRLY.CARD_ID_1 WHERE CARDS.CARD_TYPE_ID <> 5335 AND CARDEXTRA.MAGSTRIPE = '$Wildcard' AND CARDEXTRA.CARD_TYPE_ID = 5335 AND CARDEXTRA.DELETE_DATE IS NULL)) GROUP BY CARD_ID ), /*Таблица ТA - данные по сроку действия абонемента у пользователя с типом карты - не сопровождающий */ TA AS ( SELECT CARDS.VALID_TILL, MAX (CASE WHEN SUBX.status = 2 THEN CASE WHEN SUBX.IS_MEMBERSHIP = 0 THEN SUBX.expiration_date+1 ELSE CASE WHEN SUBX.expiration_date > SUBX.mmshp_end_date THEN SUBX.expiration_date+1 ELSE SUBX.mmshp_end_date+1 END END ELSE CASE WHEN SUBX.expiration_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') OR SUBX.mmshp_end_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') THEN TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') ELSE CASE WHEN (SUBX.expiration_date > SUBX.mmshp_end_date OR SUBX.mmshp_end_date IS NULL) THEN SUBX.expiration_date+1 ELSE SUBX.mmshp_end_date+1 END END END) as EXPDATE FROM SUBSCRIPTION_ACCOUNTING SUBX INNER JOIN CARDS ON CARDS.CARD_ID = SUBX.CARD_ID WHERE CARDS.MAGSTRIPE = '$Wildcard' AND CARDS.CARD_STATUS_ID = 1 AND length(CARDS.magstripe ) = 8 GROUP BY SUBX.CARD_ID, CARDS.VALID_TILL ), /*Таблица ТB - данные по сроку действия абонемента у пользователя с типом карты - сопровождающий */ TB AS ( SELECT CARDS.VALID_TILL, MAX (CASE WHEN SUBX.status = 2 THEN CASE WHEN SUBX.IS_MEMBERSHIP = 0 THEN SUBX.expiration_date+1 ELSE CASE WHEN SUBX.expiration_date > SUBX.mmshp_end_date THEN SUBX.expiration_date+1 ELSE SUBX.mmshp_end_date+1 END END ELSE CASE WHEN SUBX.expiration_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') OR SUBX.mmshp_end_date >= TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') THEN TO_DATE(current_date, 'YYYY-MM-DD HH24:MI:SS') ELSE CASE WHEN (SUBX.expiration_date > SUBX.mmshp_end_date OR SUBX.mmshp_end_date IS NULL) THEN SUBX.expiration_date+1 ELSE SUBX.mmshp_end_date+1 END END END) as EXPDATE FROM SUBSCRIPTION_ACCOUNTING SUBX INNER JOIN CARDS ON CARDS.CARD_ID = SUBX.CARD_ID LEFT JOIN T2 ON T2.CARD_ID = CARDS.CARD_ID WHERE SUBX.CARD_ID = T2.CARD_ID AND CARDS.CARD_STATUS_ID = 1 AND length(CARDS.magstripe ) = 8 GROUP BY CARDS.VALID_TILL ), /*Таблица ТС - выбор максимально возможного срока времени у пользователя с типом карты - не сопровождающий */ TC AS ( SELECT 1 as TT, CASE WHEN VALID_TILL+1 < EXPDATE THEN VALID_TILL+1 ELSE EXPDATE END AS EXPDATE FROM TA ), /*Таблица ТD - выбор максимально возможного срока времени у пользователя с типом карты - сопровождающий */ TD AS ( SELECT 1 as TT, MAX (CASE WHEN VALID_TILL+1 < EXPDATE THEN VALID_TILL+1 ELSE EXPDATE END) AS EXPDATE FROM TB ) SELECT (CASE WHEN CARD_TYPE_ID = 5335 THEN TD.EXPDATE ELSE TC.EXPDATE END) AS EXPTIME FROM CARDS LEFT JOIN TC ON TC.TT = 1 LEFT JOIN TD ON TD.TT = 1 WHERE CARDS.MAGSTRIPE = '$Wildcard' ");
                if (!isset($users) && !DB::connection('oracle')->getDatabaseName()) return response()->json(['allow' => false, 'message' => 'Ошибка: Сервер TNG не доступен']);
            }
                #LOCAL
            else {
                $users = DB::connection('mysql')->select("SELECT EXPTIME FROM personal WHERE CODEKEY = 0x20" . $Wildcard . "000000");
                if (!isset($users) && !DB::connection('mysql')->getDatabaseName()) return response()->json(['allow' => false, 'message' => 'Ошибка: Сервер Sigur не доступен']);
            }


            if (isset($users)) {

                if (isset($users[0]->EXPTIME) && verifyDate($users[0]->EXPTIME)) {

                    if (date("Y-m-d H:i:s") <= $users[0]->EXPTIME) return response()->json(['allow' => false, 'message' => date("Y-m-d H:i:s")]);
                    else return response()->json(['allow' => false, 'message' => 'Ошибка: срок действия карты закончен '.$users[0]->EXPTIME]);
                }
                else return response()->json(['allow' => true]);
            }
            else return response()->json(['allow' => false, 'message' => 'Ошибка: карты ' . $data['keyHex'] . ' нет в системах']);

            if(isset($data)) {
                return response()->json(['allow'=>false, 'message' => $users]);
            }

        } catch (\ErrorException $e) {
            return response()->json(['allow'=>false, 'message' => 'Ошибка сервера: ER0000']);
        }
    }

}

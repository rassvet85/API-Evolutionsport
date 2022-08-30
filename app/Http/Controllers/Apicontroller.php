<?php

namespace App\Http\Controllers;

use App\Lib\Querymain;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use function response;


class Apicontroller extends Controller
{
    #Запрос доступа от SIGUR
    public function apipost(Request $request): JsonResponse
    {
            /*
            Получаем и обрабатываем данные запроса от Сигур в формате json. Пример:
            {
                "type":"NORMAL", - тип доступа, в нашем случае всегда NORMAL, в API никакой роли не играет и нужен для дальнейшей отправки к API 1С Фитнес.
                "keyHex":"5AB860AA", - id карты доступа
                "direction":2, - направление прохода (2 - вход, 1 - выход)
                "accessPoint":1, - id точки прохода в системе Сигур
                "extId": "AA22BBCCDDEE223543456456456456456", - внешнее id профиля клиента, полученное от синхронизации данных с 1С фитнес и 1С Зуп. Соттветствует id профилей в 1С фитнес (33 знака) и 1С Зуп (32 знака)
                "empId": 12345, - внутреннее id профиля клиента
            }
            */
            $data =  $request->json()->all();
            $extid = null;
            $empid = null;
            $accesspoint = null;
            $typeid = null;
            $direction = null;

            if(isset($data['keyHex']) && strlen($data['keyHex']) == 8) $wildcard = $data['keyHex']; else return response()->json(['allow'=>false, 'message' => 'Карта '.$data['keyHex'].' имеет некорректный формат']);
            if(isset($data['extId'])) $extid = $data['extId'];
            if(isset($data['empId'])) $empid = $data['empId'];
            if(isset($data['accessPoint'])) $accesspoint = $data['accessPoint'];
            if(isset($data['type'])) $typeid = $data['type'];
            if(isset($data['direction'])) $direction = $data['direction'];

            $query = new Querymain();
            //Если настроены данные авторизации запроса в SIGUR - используем их, если нет - используем авторизацию из конфига .env
            if ($request->header('Authorization') !== null) $query->setauth($request->header('Authorization'));
            $value = $query->mainQuery($empid, $extid,$wildcard, $accesspoint, $typeid, $direction);

            //Проверяем на двойное прикладывание карты
            if ($value[0]) {
                $dualpass = $query->dualPass($wildcard, $accesspoint);
                if (!$dualpass[0]) {
                    $value[0] = false;
                    $value[2] = $dualpass[1];
                }
            }

            if ($value[0]) return response()->json(['allow'=>true]);
            else return response()->json(['allow'=>false, 'message' => $value[2]]);
    }

    #Запрос для тестирования доступа
    public function apiget(Request $request): JsonResponse
    {
            /*
            Получаем и обрабатываем данные запроса ссылкой в браузере. Пример:
            http://10.82.2.19/api/laravel?empid=null&extid=null&accesspoint=32&direction=2&wildcard=b53329d3
            Данные в строке эмулируют запрос от Сигур, названия параметров соответствуют запросу Сигур в формате json
            Получаем ответ типа json. Пример:
            {
                "allow":true, - Доступ разрешен (true) либо нет (false)
                "card":"B53329D3", - номер карты
                "typesys":"FITNESS", - тип системы, которой принадлежит карта. (null - карты нет ни в одной системе, local - карта заведена непосредственно в СИГУР,
                1C ZUP - карта сотрудника из 1С ЗУП, FITNESS - карта клиента из 1С Фитрес.
                "typecard":"Сопровождающий", - тип карты в 1С Фитнес
                "accesstype":"услуга по сопровождаемому", - если тип карты сопровождающий и доступ выдан по сопровождаемому и "доступ по своей услуге" - если у клиента осуществлён доступ по своей услуге.
                "typeus":0, - id типа услуги в системе 1С Фитнес
                "dayus":9, - остаток дней услуги в случае типа услуги с ограничением по времени
                "posus":0, - остаток посещений в случае типа услуги с ограничением посещений
                "typeid":null, - тип доступа
                "accesspoint":32, - id точки дсотупа в системе Сигур
                "direction":2,  - направление прохода (2 - вход, 1 - выход)
                "exptime":"2022-08-31 23:59:59", - время окончания действия услуги (абонемента)
                "message":"Доступ по сопровождаемому" - причина доступа или его отказа
            }
            */
            $empid = $request->input('empid');
            $extid = $request->input('extid');
            $accesspoint = (int)$request->input('accesspoint');
            $typeid = $request->input('type');
            $direction = (int)$request->input('direction');
            $wildcard = strtoupper($request->input('wildcard'));
            if (strlen($wildcard) != 8) return response()->json(['allow'=>false, 'message' => 'Карта '.$wildcard.' имеет некорректный формат']);
            if ($extid == "null") $extid = null;
            if ($empid == "null") $empid = null;
            if($accesspoint == "null") $accesspoint = 0;
            if($typeid == "null") $typeid = null;
            if($direction == "null") $direction = 2;

            $query = new Querymain();

            $value = $query->mainQuery($empid, $extid, $wildcard, $accesspoint, $typeid, $direction);
            $type = match ($value[1]['systype']) {
                0 => "null",
                1 => "local",
                2 => "1C ZUP",
                3 => "FITNESS"
            };
            if ((int)$value[1]['sop'] == 1) $sop = "услуга по сопровождаемому"; else $sop = "доступ по своей услуге";

            //Проверяем на двойное прикладывание карты
            if ($accesspoint != 0 && $value[0]) {
                $dualpass = $query->dualPass($wildcard, $accesspoint);
                if (!$dualpass[0])
                {
                    $value[0] = false;
                    $value[2] = $dualpass[1];
                }
            }

            return response()->json(['allow'=>$value[0], 'card' => $wildcard, 'typesys' => $type, 'typecard' => $value[1]['typecard'], 'accesstype' => $sop, 'typeus' => (int)$value[1]['typeus'], 'dayus' => (int)$value[1]['dayus'], 'posus' => (int)$value[1]['posus'],  'typeid' => $typeid, 'accesspoint' => $accesspoint, 'direction' => $direction, 'exptime' => $value[1]['exptime'], 'message' => $value[2]]);
    }

    #Запрос для отправки проходов к 1С фитнес (подтверждение проходов через турникет)
    public function eventpost(Request $request): JsonResponse
    {
        $data =  $request->json()->all();
        $query = new Querymain();
        $value = $query->querylogs($data);
        return response()->json($value);
    }

}

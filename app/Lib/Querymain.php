<?php

namespace App\Lib;

use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Nette\Utils\DateTime;
use DateInterval;

class Querymain extends Queryhelper
{
    #Читаем конфиг при создании класса
    public function __construct()
    {
        $this->config = new Config();
        $this->logs = new Logs();
        if (!isset($this->auth)) $this->setauth("Basic ".base64_encode($this->config->getFitnesslogin().":".$this->config->getFitnesspass()));
    }

    #Устанавливаем параметр авторизации для запросов к API 1С фитнес
    public function setauth($data)
    {
        $this->auth = $data;
    }

    #Функция запроса разрешения у 1С Фитнес
    private function getAccessFitness($typeid, $accesspoint, $direction, $wildcard): array
    {
        $response = Http::withHeaders([
             'Content-Type' => 'application/json',
             'Authorization' => $this->auth
         ])
             ->timeout(10)
             ->post($this->config->getApigetaccessurl(),
                 [
                     "type" => $typeid,

                     "accessPoint" => $accesspoint,
                     "direction" => $direction,
                     "keyHex" => $wildcard
                 ]
             );
        // Если ответ не пришел - возвращаем причину ошибки.
        if (!$response->successful()) {
            if ($response->status() == 401) return [false, 'Ошибка: проблема с авторизацией запроса к серверу API FITNESS'];
            else return [false, 'Ошибка: нет ответа от сервера API FITNESS'];
        }

        return [$response['allow'], $response['message']];
    }

    #Функция запроса разрешения у 1С Фитнес для сопровождающих
    private function getAccessSop($typeid, $accesspoint, $direction, $wildcard): array
    {
        //Функция параллельного запроса для доступа сопровождающего и сопровождаемых
        $fn2 = function (Pool $pool) use ($typeid, $accesspoint, $direction, $wildcard) {
            $arrayPools = array();
            foreach ($wildcard as $aVal) {
                $arrayPools[] = $pool->withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->auth
                ])
                    ->timeout(10)
                    ->post($this->config->getApigetaccessnologurl(),
                        [
                            "type" => $typeid,
                            "accessPoint" => $accesspoint,
                            "direction" => $direction,
                            "keyHex" => $aVal
                        ]);
            }
            return $arrayPools;
        };

        $responses = Http::pool($fn2);
        $i = 0;
        foreach ($responses as $response)
        {
            // Если ответ не пришел - возвращаем причину ошибки.
            if (!$response->successful()) {
                if ($response->status() == 401) return [false, 'Ошибка: проблема с авторизацией запроса к серверу API FITNESS'];
                else return [false, 'Ошибка: нет ответа от сервера API FITNESS'];
            }
            // Если в данном ответе есть разрешение - возвращаем разрешение.
            if ($response['allow']) return [true, true, $wildcard[$i]];
            $i++;
        }
        // Если ни у кого у сопровождаемых либо у самого клиента нет доступа - возвращаем запрет прохода.
        if ($i > 0) return [true, false];
        // Если вообще нет ничего в ответе $responses - возвращаем ошибку.
        return [false, 'Ошибка: нет ответа от сервера API FITNESS'];

    }

    #Функция проверки двойного прикладывания карты
    public function dualPass($wildcard, $accesspoint): array
    {
        if ($this->config->getDualpass() == 0) return [true];
        //Если точка прохода принадлежит NFC терминалу - не обращаем внимания на двойное прикладывание, пропускаем клиента и очищаем БД двойного прикладывания для этой карты
        if (isset($wildcard) && strlen($wildcard) == 8 && in_array($accesspoint, array_map('intval', $this->config->getNfcpoint()))) try {
            return $this->logs->passLogDelete(new DateTime('-' . $this->config->getDualpass() . ' second'), (array)$wildcard);
        } catch (Exception) {
            return [false, 'Ошибка в конфиге dualpass'];
        }
        return $this->logs->passLogGet(new DateTime('now'), $wildcard, $this->config->getDualpass());
    }

    #Функция постобработки для API
    private function postProcess($system, $message, $service, $countfinish = null): array
    {
        //Проверяем конфиг на доступ 1С фитнес к точке КПП. Если доступ есть - проверяем дополнительно доступ к API 1С Фитнес.
        if (in_array($system['accesspoint'], array_map('intval', $this->config->getApipoint())) && in_array($system['accesspoint'], array_map('intval', $this->config->getKpppoint())) && strrpos($this->config->getNoScheduletypecard(), $system['typecardservice']) === false)
        {
            if ($system['typecard'] == $this->config->getSoptypecard()) {
                //Работа с типом карты "Сопровождающий"
                $response = $this->getAccessSop($system['typeid'], $system['accesspoint'], $system['direction'], $system['wildcard']);
                if (!$response[0]) {
                    return [false, $system, $response[1]];
                }
                if ($response[1]) {
                    if ($response[2] == $system['wildcardown']) {$system['sop'] = 0; return [true, $system, 'Доступ по своей услуге'];}
                    $system['sop'] = 1; return [true, $system, 'Доступ по сопровождаемому'];
                } else {
                    if (isset($countfinish) && $countfinish > 0) return [true, $system, 'Доступ по сопровождаемому'];
                    try {
                        //Если разрешения нет, но у клиента или у сопровождающего есть сегодня занятите - в причине отказа указываем ближайшее время, когда клиент сегодня может зайти на территорию
                        if (isset($service->statuszan) && $service->statuszan == 1 && $this->verifyDate($service->starttime) && (new DateTime('+' . $this->config->getTimestart() . ' minute')) < DateTime::createFromFormat('Y-m-d H:i:s', $service->starttime)) {
                            return [false, $system, 'Доступ на территорию будет разрешён с ' . DateTime::createFromFormat('Y-m-d H:i:s', $service->starttime)->sub(new DateInterval('PT' . $this->config->getTimestart() . 'M'))->format('H:i')];
                        }
                    } catch (Exception) {
                        return [false, $system, 'Ошибка в конфиге starttime'];
                    }
                    return [false, $system, 'Доступ запрещен, у клиента нет доступных оснований'];
                }
            }
            else
            {
                //Работа с остальными картами
                //Если тип карты относится к ПСС, меняем id точки прохода на виртуальную Pointpss из конфига
                if (strrpos($this->config->getPsstypecard(), $system['typecard']) !== false) {
                    $system['accesspoint'] = $this->config->getPointpss();
                }
                //Запрашиваем разрешение прохода через API у 1С Фитнес
                $response = $this->getAccessFitness($system['typeid'], $system['accesspoint'], $system['direction'], $system['wildcard'][0]);
                try {
                    //Если разрешения нет, но у клиента есть сегодня занятите - в причине отказа указываем время, когда клиент сегодня может зайти на территорию
                    if (!$response[0] && isset($service->statuszan) && $service->statuszan == 1 && $this->verifyDate($service->starttime) && (new DateTime('+' . $this->config->getTimestart() . ' minute')) < DateTime::createFromFormat('Y-m-d H:i:s', $service->starttime)) {
                        return [false, $system, 'Доступ на территорию будет разрешён с ' . DateTime::createFromFormat('Y-m-d H:i:s', $service->starttime)->sub(new DateInterval('PT' . $this->config->getTimestart() . 'M'))->format('H:i')];
                    }
                } catch (Exception) {
                    return [false, $system, 'Ошибка в конфиге starttime'];
                }
                return [$response[0], $system, $response[1]];
            }
        }
        return [true,$system,$message];
    }

    #Основная функция API
    public function mainQuery($empid, $extid, $wildcard, int $accesspoint, $typeid, int $direction): array
    {
        //тип системы,тип услуги, количество доступных дней, количество доступных посещений, количество разовых услуг
        $system = [
            'wildcard' => [$wildcard],
            'wildcardown' => $wildcard,
            'name' => null,
            'exptime' => null,
            'systype' => 0,
            'typeus' => 0,
            'dayus' => 0,
            'posus' => 0,
            'razusl' => 0,
            'sop' => 0,
            'direction' => $direction,
            'accesspoint' => $accesspoint,
            'typeid' => $typeid,
            'typecard' => 'без названия',
            'typecardservice' => 'без названия',
        ];
        //Если конфиг по какой то причине не прочитан или содержит ошибку - возвращаем ошибку.
        if (!$this->config->getSuccessful()[0]) return [false, $system, $this->config->getSuccessful()[1]];
        //Если ID карты не равно 8 - возвращаем ошибку.
        if (strlen($wildcard) != 8) return [false, $system, 'Карта '.$wildcard.' имеет некорректный формат'];

        #API (Проверяем, предназначена ли точка доступа для списания услуг и контроля клиентов по 1С Фитнес. Если точка доступа на КПП - проверку производим ниже для клиентов.
        if (in_array($accesspoint, array_map('intval', $this->config->getApipoint())) && !in_array($accesspoint, array_map('intval', $this->config->getKpppoint()))) {
            //Проверяем на сотрудника. Если это сотрудник, проверяем доступ к точкам API. Если все ОК - пускаем его с проверкой доступа к системе.
            if ((isset($empid) && !isset($extid)) || strlen($extid) == 32)
            {
                $system['systype'] = 2;
                $point = $this->config->getPointEmpl($empid);
                $error = "";
                if (!$point[0]) return [false, $system, $point[1]];
                foreach (preg_split("/\D+/", $point[1], -1,PREG_SPLIT_NO_EMPTY) as $temp)
                {
                    if (!ctype_digit($temp)) {$error = "Ошибка: Неверный формат конфига точек доступа персонала: ". $point[1]; break;}
                }
                if ($error != "") return [false, $system, $error];
                if (!in_array($accesspoint, array_map('intval', preg_split("/\D+/", $point[1], -1,PREG_SPLIT_NO_EMPTY)))){
                    return [false, $system, "Ошибка: Отсутствует доступ сотрудника к точке"];
                }
            }
            else {
                //Работа с клиентами 1с Фитнес
                $system['systype'] = 3;
                //Получаем тип карты
                $cardtype = $this->getTypeCard($wildcard);
                if (!$cardtype[0]) return [false, $system, $cardtype[1]];
                //Проверяем на доступ к калитке. Клиентам запрещаем проход, если в типе карты не стоит "Лицо с инвалидностью"
                if (in_array($accesspoint, array_map('intval', $this->config->getGate())) && $cardtype[1] != $this->config->getInvtypecard()) {
                    return [false, $system, "Ошибка: Клиенту с типом карты '".$cardtype[1]."' доступ к калитке запрещён"];
                }
                //Запрашиваем сервер 1С Фитнес на доступ к точке
                $response = $this->getAccessFitness($typeid, $accesspoint, $direction, $wildcard);
                return [$response[0], $system, $response[1]];
            }
        }
        //Делаем поочередно запрос к БД разных систем.
        if (isset($empid) && !isset($extid)) {
            #Запрос к локальной базе данных
            $system['systype'] = 1;
            try {
                $services = DB::connection('mysql')->select(file_get_contents(base_path() . '/sql/sql_sigur_select.sql'), ['wildcard' => '20' . $wildcard . '000000']);
            } catch (QueryException) {
                return [false, $system, 'Ошибка: Сервер SQL Sigur не доступен'];
            }
        } else if (strlen($extid) == 32) {
            #Запрос к 1С ЗУП
            $system['systype'] = 2;
            try {
                $services = DB::connection('sqlsrv')->select(file_get_contents(base_path() . '/sql/sql_1Czup_select.sql'), ['extid' => '0x'.$extid]);
            } catch (QueryException) {
                return [false, $system, 'Ошибка: Сервер 1С не доступен'];
            }
        } else {
            #Запрос к 1С Фитнес
            $system['systype'] = 3;
            try {
                $services = DB::connection('sqlfitness')->select(file_get_contents(base_path() . '/sql/sql_fitness_select.sql'), ['wildcard' => $wildcard, 'idsop' => $this->config->getSoprovidcard()]);
            } catch (QueryException $e) {
                return [false, $system, 'Ошибка: Сервер FITNESS не доступен'.$e];
            }
        }

       /* else ($system == 0 && !isset($services)) {
            #Запрос к TNG
            $system = 3;
            try {
                $services = DB::connection('oracle')->select(file_get_contents(base_path() . '/sql/sql_tng_select.sql'), ['wildcard' => $wildcard]);
            } catch (QueryException $e) {
                return [false, $system, 'Ошибка: Сервер TNG не доступен'.$e];
            }
        }
        */

        #Результаты запросов обрабатываем и выдаем финальный результат пропуска объекта
        if (isset($services) && count($services) > 0) {
            $system['name'] = $services[0]->name;
            if ($system['systype'] != 3) {
                //Если это сотрудник из 1С ЗУП - проверяем на наличие фото, если фото нет - не пускаем.
                if ($system['systype'] == 2 && !isset($services[0]->photo)) return [false, $system, 'У сотрудника отсутствует фото'];
                $system['exptime'] = $services[0]->exptime;
                //Если время, полученное с базы равно null - доступ без лимита.
                if ($system['exptime'] == null) return [true, $system, 'Доступ без лимита'];
                //Если время, полученное с базы существует - проверяем дату завершения пропуска и соответственно выдаем информацию о времени..
                if ($this->verifyDate($system['exptime'])) {
                    if (date("Y-m-d H:i:s") <= $system['exptime']) return [true, $system, 'Доступ действует до '.$this->formatDate($system['exptime'])];
                    else {
                        if ($system['systype'] == 2) return [false, $system, 'Доступ на территорию закрыт с '.$this->formatDate($system['exptime'])];
                        return [false, $system, 'Ошибка: срок действия услуг закончен '.$this->formatDate($system['exptime'])];
                    }
                }
                else return [false, $system, "На карте отсутствуют услуги"];
            } else
            {
                // Тут работаем только с клиентами 1С Фитнес.
                // Находим лучшую услугу, по которой есть доступ у клиента.
                $bestus = -1;
                $per = true;
                $cardarray = array();
                $finishtime = null;
                foreach ($services as $service) {
                    if ($per) $bestus++;
                    if ($service->razusl == 1 || ($service->status == 1 && (($service->typeus == 0 && $service->dayus > 0) || ($service->typeus == 1 && $service->posus > 0))) || strrpos($this->config->getArendatypecard(), $service->carddescuser) !== false) {
                        $cardarray[] = $service->card;
                        $per = false;
                        continue;
                    }
                    if ($service->status > 1 && $bestus > 0)
                    {
                        if ($per) $bestus = 0;
                        break;
                    }
                }
                // Если клиент с типом карты сопровождающий - вносим в массив данные его карты и карты сопровождаемых, у которых есть действующие услуги.
                if ($services[0]->carddesc == $this->config->getSoptypecard()) {
                    $system['wildcard'] = array_values(array_unique($cardarray));
                    //Проверяем возможность входа для сопровождающего за клиентом после окончания занятия.
                    try {
                        $finishtime = collect($services)->where('statuszan', '>=', 1)->where('statprib', 1)->where('finishtime', '<=', date("Y-m-d H:i:s"))->where('finishtime', '>', (new DateTime('-' . $this->config->getTimefinish() . ' minute'))->format("Y-m-d H:i:s"))->count();
                    } catch (Exception ) {
                        return [false, $system, 'Ошибка в конфиге finishtime'];
                    }
                }
                // Записываем данные в массив $system
                $system['exptime'] = $services[$bestus]->exptime;
                $system['typeus'] = $services[$bestus]->typeus;
                $system['dayus'] = $services[$bestus]->dayus;
                $system['posus'] = $services[$bestus]->posus;
                $system['razusl'] = $services[0]->razusl;
                $system['sop'] = $services[$bestus]->sop;
                if (isset($services[0]->carddesc)) $system['typecard'] = $services[0]->carddesc;
                if (isset($services[0]->carddescuser)) $system['typecardservice'] = $services[$bestus]->carddescuser;
                $cause1 = "";
                // Проверяем наличие фото
                if ($services[0]->phototime == null) return [false, $system, 'Отсутствует фото'];
                // Проверяем наличие блокировки карты
                if ($services[0]->statuscard == 1) return [false, $system, 'Карта заблокирована администратором'];
                //Проверяем на доступ к калитке. Клиентам запрещаем проход, если в типе карты не стоит "Лицо с инвалидностью"
                if (in_array($accesspoint, array_map('intval', $this->config->getGate())) && $system['typecard'] != $this->config->getInvtypecard()) {
                    return [false, $system, "Ошибка: Клиенту с типом карты '".$system['typecard']."' доступ к калитке запрещён"];
                }
                //Проверяем клиента на разовые услуги, если они есть - отправляем на дополнительную проверку postProcess.
                if (isset($services[0]->razusl)) {
                    if ($services[0]->sop == 0) return $this->postProcess($system, 'Доступ действует по наличию одноразовых услуг у клиента', $services[0], $finishtime);
                    else return $this->postProcess($system, 'Сопровождение действует по наличию одноразовых услуг у сопровождаемого', $services[0], $finishtime);
                }
                //Проверяем клиента на арендатора или участника аренды, а также их сопровождающих. Их сразу отправляем на дополнительную проверку postProcess без проверки причины
                if (strrpos($this->config->getArendatypecard(), $services[$bestus]->carddescuser) !== false) {
                    if ($services[0]->sop == 0) return $this->postProcess($system, 'Доступ действует по типу карты Аренда', $services[0], $finishtime);
                     else return $this->postProcess($system, 'Сопровождение действует по наличию типа карты Аренда у сопровождаемого', $services[0], $finishtime);
                }
                //Причина блокировки прохода
                if (isset($services[$bestus]->date1)) {
                    if (date_create($services[$bestus]->date1) > date_create('2001-01-01')) $cause1 = 'до '. $this->formatDate($services[$bestus]->date1,true);
                        else $cause1 = "перманентно";
                }
                // Проверяем окончание времени действия услуг
                if (isset($system['exptime']) && $this->verifyDate($system['exptime'])) {
                    if ($services[$bestus]->sop == 1) {
                        // Работа с сопровождающим
                        if ($services[$bestus]->status == 1 && date("Y-m-d H:i:s") <= $system['exptime']) {
                            //если у сопровождаемого закрыта сегодня услуга - не пропускаем на вход
                            if ($services[$bestus]->typeus == 1 && $services[$bestus]->posus == 0 && $direction == 2) return [false, $system, 'У сопровождаемых завершен лимит посещений'];
                            return $this->postProcess($system, 'Сопровождение действует до ' . $this->formatDate($system['exptime']), $services[0], $finishtime);
                        }
                        // Проверяем блокировку услуги у сопровождаемого(ых)
                        else if ($services[$bestus]->status == 2 && isset($services[$bestus]->date1)) return [false, $system, 'Услуга у сопровождаемого заблокирована ' . $cause1];
                        // Проверяем заморозку услуги у сопровождаемого(ых)
                        else if ($services[$bestus]->status == 3 && isset($services[$bestus]->date1)) return [false, $system, 'Услуга у сопровождаемого заморожена ' . $cause1];
                        else if ($services[$bestus]->status == 4) return [false, $system, 'Срок действия услуг у сопровождаемых завершён ' . $this->formatDate($system['exptime'])];
                        else {
                            if ($services[$bestus]->status == 2 || $services[$bestus]->status == 3) return [false, $system, 'Услуга у сопровождаемого заморожена или заблокирована перманентно'];
                        }
                    }
                    else {
                        // Работа не с сопровождающим
                        if ($services[$bestus]->status == 1 && date("Y-m-d H:i:s") <= $system['exptime']){
                            //если у клиента закрыта сегодня услуга - не пропускаем на вход
                            if ($services[$bestus]->typeus == 1 && $services[$bestus]->posus == 0 && $direction == 2) return [false, $system, 'Ваш лимит посещений завершен'];
                            return $this->postProcess($system, 'Доступ действует до ' . $this->formatDate($system['exptime']), $services[0]);
                        }
                        else if ($services[$bestus]->status == 2 && isset($services[$bestus]->date1)) return [false, $system, 'Услуга заблокирована ' . $cause1];
                        else if ($services[$bestus]->status == 3 && isset($services[$bestus]->date1)) return [false, $system, 'Услуга заморожена ' . $cause1];
                        else if ($services[$bestus]->status == 4) return [false, $system, 'Срок действия услуги завершён ' . $this->formatDate($system['exptime'])];
                        else {
                            if ($services[$bestus]->status == 2 || $services[$bestus]->status == 3) return [false, $system, 'Услуга заморожена или заблокирована перманентно'];
                        }
                    }
                }
                else return [false, $system, "На карте отсутствуют услуги"];
            }
        }
        return [false, $system, 'Ошибка: карты ' . $wildcard . ' нет в системах'];
    }
    #Функция подтверждения прохода через турникет
    public function querylogs($value): array
    {
        $pint = false;
        $dualpass = array();
        $data = $value;
        if (isset($data['logs'])) {
            foreach ($data['logs'] as &$log) {
                if (!$pint && in_array($log['accessPoint'],$this->config->getApipoint())) $pint = true;
                // Записываем данные карт для будущего удаления в логе двойных проходов
                if (isset($log['keyHex']) && strlen($log['keyHex']) == 8) $dualpass[] = $log['keyHex'];
                //Если карточка принадлжежит сотруднику, либо номер карточки не равен 8 (при проходе через отключенный турникет ID = 000000) - меняем ID точки на "нулевую точку прохода" для того, чтобы сотрудник не высвечивался в логах 1С Фитнес.
                if (strlen($log['keyHex']) != 8 || (strlen($log['empId']) != 33 && $log['internalEmpId'] != 0)) $log['accessPoint'] = $this->config->getPointnull();
                //Если карточка принадлежит типу "Плоскостные", точка прохода входит в массив apipoint и kpppoint то меняем ID точки на виртуальную pointpss из конфига, что означает списание услуги на КПП.
                else if (in_array($log['accessPoint'],$this->config->getApipoint()) && in_array($log['accessPoint'], array_map('intval', $this->config->getKpppoint()))) {
                    $typecard = $this->getTypeCard($log['keyHex']);
                    if ($typecard[0] && strrpos($this->config->getPsstypecard(), $typecard[1]) !== false) $log['accessPoint'] = $this->config->getPointpss();
                }
            }
            //Удаляем данные в логе двойных проходов для карт из массива проходов
            if (count($dualpass) > 0) {
                try {
                    $this->logs->passLogDelete(new DateTime('-' . $this->config->getDualpass() . ' second'), array_values(array_unique($dualpass)));
                } catch (Exception) {
                    //Log::info('Log message', array('context' => $data['logs']));
                }
            }

            if ($pint) {
                //Отправляем данные проходов к API 1С Фитнес
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'Authorization' => $this->auth
                ])
                    ->timeout(4)
                    ->post($this->config->getApieventsurl(), $data);
                //Log::info('Log message', array('context' => $data['logs']));
                if ($response->successful()) {
                    $this->logs->sendLogs($value, $response['confirmedLogId']);
                    return ['confirmedLogId' => $response['confirmedLogId']];
                }
                else return ['confirmedLogId'=> 0];
            }
            //Если в списке проходов нет точек из массива apipoint - просто подтверждаем все проходы.
            $this->logs->sendLogs($value, (int)$data['logs'][count($data['logs'])-1]['logId']);
            return ['confirmedLogId'=> (int)$data['logs'][count($data['logs'])-1]['logId']];

        }
        // если в массиве нет данных проходов (например тестовый запрос) просто отправляем нулевое подтверждение
        return ['confirmedLogId'=> 0];
    }

}
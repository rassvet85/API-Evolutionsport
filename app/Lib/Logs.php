<?php

namespace App\Lib;

use App\Jobs\LogsSend;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Nette\Utils\DateTime;

class Logs
{
    #Функция записи лога для двойного прикладывния карты
    private function passLogPast($date, $wildcard): array
    {
        try {
            DB::connection('pgsql')->insert('INSERT INTO "double_pass_log" (time, wildcard) VALUES (?, ?)', [$date->format('Y-m-d H:i:s.u'), $wildcard]);
            return [true];
        } catch (QueryException $e) {
            return [false, 'Ошибка: Сервер POSTGRESS не доступен (passLogPAss)'];
        }
    }

    #Функция удаления записей лога для двойного прикладывния карты
    public function passLogDelete($date, $wildcards): array
    {
        $cards = "";
        foreach ($wildcards as $wildcard)
        {
            if ($cards == "") $cards = "'".$wildcard."'";
            else $cards .= ", '".$wildcard."'";
        }
        try {
            DB::connection('pgsql')->delete('DELETE FROM "double_pass_log" WHERE "wildcard" IN ('.$cards.') OR "time" < TO_TIMESTAMP(\''.$date->format('Y-m-d H:i:s.u').'\', \'YYYY-MM-DD HH24:MI:SS.US\')');
            return [true];
        } catch (QueryException $e) {
            return [false, 'Ошибка: Сервер POSTGRESS не доступен'];
        }
    }

    #Функция проверки двойного прикладывания карты и записывания нового прикладывания
    public function passLogGet($date, $wildcard, $time): array
    {
        try {
            $data = DB::connection('pgsql')->select('SELECT MAX(time) AS time FROM "double_pass_log" WHERE "wildcard" = \''.$wildcard.'\'');
            if (isset($data) && isset($data[0]->time) && (int)$date->format('Uu') - (int)DateTime::createFromFormat('Y-m-d H:i:s.u', $data[0]->time)->format('Uu') < $time*1000000) return [false, 'Не прошло '.$time.' секунд с момента последнего прикладывания карты'];
            return $this->passLogPast($date, $wildcard);
        } catch (QueryException) {
            return [false, 'Ошибка: Сервер POSTGRESS не доступен'];
        }
    }

    #Функция отправки логирования в очередь
    public function sendLogs($data,$id)
    {
        dispatch(new LogsSend($data, $id));
    }

}
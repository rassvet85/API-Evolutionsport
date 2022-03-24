<?php

namespace App\Query;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Nette\Utils\DateTime;

class Querymain
{


    protected function verifyDate($date): bool
    {
        return (DateTime::createFromFormat('Y-m-d H:i:s', $date) !== false);
    }

    public function query($empid, $extid, $wildcard): array
    {
        $system = 0;
        $exptime = null;

        if (isset($empid) && $extid == null) {
            #LOCAL
            $system = 1;
            try {
                $users = DB::connection('mysql')->select(file_get_contents(base_path() . '/sql/sql_sigur_select.sql'), ['wildcard' => '20' . $wildcard . '000000']);
            } catch (QueryException $e) {
                return [false, $wildcard, $system,$exptime, 'Ошибка: Сервер Sigur не доступен'];
            }
        } else if (strlen($extid) == 32) {
            #1C
            $system = 2;
            try {
                $users = DB::connection('sqlsrv')->select(file_get_contents(base_path() . '/sql/sql_1Czup_select.sql'), ['extid' => '0x'.$extid]);
            } catch (QueryException $e) {
                return [false, $wildcard, $system, $exptime, 'Ошибка: Сервер 1С не доступен'];
            }
        }

        if ($system == 0 && !isset($users)) {
            #TNG
            $system = 3;
            try {
                $users = DB::connection('oracle')->select(file_get_contents(base_path() . '/sql/sql_tng_select.sql'), ['wildcard' => $wildcard]);
            } catch (QueryException $e) {
                return [false, $wildcard, $system, $exptime, 'Ошибка: Сервер TNG не доступен'];
            }
        }

        if (isset($users) && count($users) > 0) {
            $exptime = $users[0]->exptime;
            if ($system < 3 && $exptime == null) return [true, $wildcard ,$system, $exptime, 'Доступ без лимита'];
            if (isset($exptime) && $this->verifyDate($exptime)) {
                if (date("Y-m-d H:i:s") <= $exptime) return [true, $wildcard, $system, $exptime, 'Доступ действует до '.$exptime];
                else return [false, $wildcard ,$system, $exptime, 'Ошибка: срок действия услуг закончен '.$exptime];
            }
            else return [false, $wildcard, $system, $exptime, "На карте отсутствуют услуги"];
        }
        else return [false, $wildcard, $system, $exptime, 'Ошибка: карты ' . $wildcard . ' нет в системах'];

    }

}
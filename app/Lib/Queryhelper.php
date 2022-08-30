<?php

namespace App\Lib;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Nette\Utils\DateTime;

class Queryhelper
{
    protected string $auth;
    protected mixed $config;
    protected mixed $logs;

    #Проверка формата даты
    protected function verifyDate($date): bool
    {
        return (DateTime::createFromFormat('Y-m-d H:i:s', $date) !== false);
    }

    #Выбор формата даты
    protected function formatDate($date, $add = false): string
    {
        $date1 = DateTime::createFromFormat('Y-m-d H:i:s', $date);
        if ($add) {$date1 =  $date1->add(new DateInterval('PT1S')); return $date1->format('d.m.Y');}
        return $date1->format('d.m.Y H:i:s');
    }

    #Функция для возврата типа карты в 1С Фитнес и заодно проверка существования самой карты в системе
    protected function getTypeCard($cadr): array
    {
        try {
            $typeCard = DB::connection('sqlfitness')->select(file_get_contents(base_path() . '/sql/sql_fitness_getTypeCard.sql'), ['wildcard' => $cadr]);
            if (!isset($typeCard) || count($typeCard) == 0) return [false, 'Ошибка: карты ' . $cadr . ' нет в системах'];
            if (!isset($typeCard[0]->carddesc)) $typeCard[0]->carddesc = "Без названия";
            if (isset($typeCard[0]->statuscard) && $typeCard[0]->statuscard == 1) return [false, 'Карта заблокирована администратором'];
            return [true, $typeCard[0]->carddesc];
        } catch (QueryException $e) {
            return [false, 'Ошибка: Сервер FITNESS не доступен'];
        }
    }

}
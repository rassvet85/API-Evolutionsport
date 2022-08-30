<?php

namespace App\Lib;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

#Класс конфига
class Config
{
    private array $apipoint;
    private array $kpppoint;
    private array $nfcpoint;
    private array $gate;
    private string $invtypecard;
    private string $soptypecard;
    private string $soprovidcard;
    private string $psstypecard;
    private string $fitnesslogin;
    private string $fitnesspass;
    private string $apieventsurl;
    private string $apigetaccessurl;
    private string $apigetaccessnologurl;
    private array $successful;
    private int $dualpass;
    private int $timestart;
    private int $pointnull;
    private int $pointpss;

    #Функция валидации точек прохода
    private function validatePoint($data): bool
    {
        $ret = false;
        foreach (preg_split("/\D+/", $data,-1,PREG_SPLIT_NO_EMPTY) as $temp)
        {
            if (!$ret) $ret = true;
            if (!ctype_digit($temp)) {return false;}
        }
        return $ret;
    }
    #Функция валидации URL
    private function validateUrl($data) {
        return filter_var($data, FILTER_VALIDATE_URL);
    }
    #Функция преобразования в массив
    private function toArray($data): array|bool
    {
        return preg_split("/\D+/", $data,-1,PREG_SPLIT_NO_EMPTY);
    }

    #Запрос данных из таблицы с конфигом
    public function __construct()
    {
        try{
            $data = DB::connection('pgsql')->select('SELECT * FROM "config"');
            if (isset($data) && count($data) >= 1) {
                $cause = "";
                foreach ($data as $temp) {
                    switch($temp->id) {
                        case 1:
                            if ($this->validatePoint($temp->data)) $this->apipoint = $this->toArray($temp->data);
                            else $cause = "Ошибка: Неверный формат конфига точек доступа API: ". $temp->data;
                            break;
                        case 2:
                            if ($this->validatePoint($temp->data)) $this->kpppoint = $this->toArray($temp->data);
                            else $cause = "Ошибка: Неверный формат конфига точек доступа КПП: ". $temp->data;
                            break;
                        case 3:
                            if ($this->validatePoint($temp->data)) $this->nfcpoint = $this->toArray($temp->data);
                            else $cause = "Ошибка: Неверный формат конфига точек доступа NFC: ". $temp->data;
                            break;
                        case 4:
                            if ($this->validatePoint($temp->data)) $this->gate = $this->toArray($temp->data);
                            else $cause = "Ошибка: Неверный формат конфига точек-калиток: ". $temp->data;
                            break;
                        case 5:
                            if (isset($temp->data)) $this->invtypecard = $temp->data;
                            else $cause = "Ошибка: Не получено из конфига название типа карт лиц с инвалидностью";
                            break;
                        case 6:
                            if (isset($temp->data) && ctype_digit($temp->data)) $this->timestart = $temp->data;
                            else $cause = "Ошибка: Неверный формат конфига времени в минутах для доступа на территорию до начала занятия";
                            break;
                        case 8:
                            if (isset($temp->data) && ctype_digit($temp->data)) $this->dualpass = $temp->data;
                            else $cause = "Ошибка: Неверный формат конфига времени в секундах для двойного прикладывания карты";
                            break;
                        case 9:
                            if (isset($temp->data)) $this->soptypecard = $temp->data;
                            else $cause = "Ошибка: Не получено из конфига название типа карт сопровождающих";
                            break;
                        case 10:
                            if (isset($temp->data)) $this->fitnesslogin = $temp->data;
                            else $cause = "Ошибка: Не получен из конфига логин к API 1СFitness";
                            break;
                        case 11:
                            if (isset($temp->data)) $this->fitnesspass = $temp->data;
                            else $cause = "Ошибка: Не получен из конфига пароль к API 1СFitness";
                            break;
                        case 12:
                            if ($this->validateUrl($temp->data)) $this->apieventsurl = $temp->data;
                            else $cause = "Ошибка: Неверный формат URL в конфиге apieventsurl: ".$temp->data;
                            break;
                        case 13:
                            if ($this->validateUrl($temp->data)) $this->apigetaccessurl = $temp->data;
                            else $cause = "Ошибка: Неверный формат URL в конфиге apigetaccessurl: ".$temp->data;
                            break;
                        case 14:
                            if ($this->validateUrl($temp->data)) $this->apigetaccessnologurl = $temp->data;
                            else $cause = "Ошибка: Неверный формат URL в конфиге apigetaccessnologurl: ".$temp->data;
                            break;
                        case 15:
                            if (isset($temp->data)) $this->psstypecard = $temp->data;
                            else $cause = "Ошибка: Не получены из конфига названия типа карт ПСС";
                            break;
                        case 16:
                            if (isset($temp->data) && ctype_xdigit($temp->data)) $this->soprovidcard = "0x".$temp->data;
                            else $cause = "Ошибка: Не получены из конфига id типа карты Сопровождающий";
                            break;
                        case 17:
                            if (isset($temp->data) && ctype_digit($temp->data)) $this->pointnull = $temp->data;
                            else $cause = "Ошибка: Не получены из конфига id виртуальной нулевой точки прохода";
                            break;
                        case 18:
                            if (isset($temp->data) && ctype_digit($temp->data)) $this->pointpss = $temp->data;
                            else $cause = "Ошибка: Не получены из конфига id вирутальной точки прохода ПСС";
                            break;
                    }
                    if ($cause != "") {$this->successful = [false, $cause]; return;}
                }
                $this->successful = [true];
            }
            else $this->successful = [false, 'Ошибка: Запрос конфига с POSTGRESS не вернул данные'];
        } catch (QueryException) {
            $this->successful = [false, 'Ошибка: Сервер POSTGRESS не доступен'];
        }
    }

    #Функция запроса данных доп. точек прохода с БД SIGUR
    public function getPointEmpl($where): array
    {
        try{
            $data = DB::connection('mysql')->select('SELECT PARAM_IDX, VALUE FROM sideparamvalues WHERE OBJ_ID = '.$where.' ORDER BY PARAM_IDX ');
            if (isset($data) && count($data) > 0) {
                $pointempl = "";
                foreach ($data as $temp) {
                    if ($temp->PARAM_IDX == 0 && $temp->VALUE != 'нет') {
                        try{
                            $data1 = DB::connection('pgsql')->select("SELECT data FROM \"config_point_empl\" WHERE name = '".$temp->VALUE."'");
                            if (isset($data1) && count($data1) == 1) {
                                $pointempl .= str_replace(' ', '', $data1[0]->data);
                            } else return [false, 'Ошибка: Запрос конфига с POSTGRESS не вернул данные'];
                        } catch (QueryException) {
                            return [false, 'Ошибка: Сервер POSTGRESS не доступен'];
                        }
                    }
                    if ($temp->PARAM_IDX == 1) {
                        $pointempl .= ','.$temp->VALUE;
                    }
                }
                $pointempl = str_replace(' ', '', $pointempl);
                return [true, $pointempl];
            }
            return [false, 'Ошибка: Отсутствует доступ сотрудника к точке'];

        } catch (QueryException) {
            return [false, 'Ошибка: Сервер SQL Sigur не доступен'];
        }
    }
    #Функция запроса ID точек доступа Сигур, которые используется для запроса доступа через API 1C фитнес
    public function getApipoint(): array
    {
        return $this->apipoint;
    }
    #Функция запроса ID точек доступа Сигур, установленных на КПП
    public function getKpppoint(): array
    {
        return $this->kpppoint;
    }
    #Функция запроса ID NFC терминалов точек доступа Сигур
    public function getNfcpoint(): array
    {
        return $this->nfcpoint;
    }
    #Функция запроса ID точек доступа Сигур типа "Калитка"
    public function getGate(): array
    {
        return $this->gate;
    }
    #Функция запроса названия типа карты "Лицо с инвалидностью" для доступа к калиткам.
    public function getInvtypecard(): string
    {
        return $this->invtypecard;
    }
    #Функция запроса названия типа карты сопровождающего.
    public function getSoptypecard(): string
    {
        return $this->soptypecard;
    }
    #Функция запроса названия типа карты клиентов ,пользующихся плоскостными сооружениями.
    public function getPsstypecard(): string
    {
        return $this->psstypecard;
    }
    #Функция запроса логина к API 1С Фитнес.
    public function getFitnesslogin(): string
    {
        return $this->fitnesslogin;
    }
    #Функция запроса пароля к API 1С Фитнес.
    public function getFitnesspass(): string
    {
        return $this->fitnesspass;
    }
    #Функция запроса URL к фиксации проходов API 1С Фитнес.
    public function getApieventsurl(): string
    {
        return $this->apieventsurl;
    }
    #Функция запроса URL к данным разрешения доступа API 1С Фитнес.
    public function getApigetaccessurl(): string
    {
        return $this->apigetaccessurl;
    }
    #Функция запроса URL к данным разрешения доступа API 1С Фитнес без логирования проходов в 1С Фитнес
    public function getApigetaccessnologurl(): string
    {
        return $this->apigetaccessnologurl;
    }
    #Функция запроса успешного прочтения конфига с БД
    public function getSuccessful(): array
    {
        return $this->successful;
    }
    #Функция запроса времени в секундах запрета двойного прикладывания карты к турникетам.
    public function getDualpass(): int
    {
        return $this->dualpass;
    }
    #Функция запроса времени в минутах разрешения прохода до начала занятия.
    public function getTimestart(): int
    {
        return $this->timestart;
    }
    #Функция запроса id типа карты "Сопровождающий" в 1С Фитнес.
    public function getSoprovidcard(): string
    {
        return $this->soprovidcard;
    }
    #Функция запроса id "нулевой" точки прохода (точка прохода ,которой нет в 1С Фитнес для непопадания в логи 1С Фитнес.
    public function getPointnull(): int
    {
        return $this->pointnull;
    }
    #Функция запроса id "виртуальной" точки прохода ПСС (с таким ud точка прохода со списанием посещения должна обязательно быть создана в 1С Фитнес.
    public function getPointpss(): int
    {
        return $this->pointpss;
    }

}
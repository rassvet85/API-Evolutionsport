<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogsSend implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $data;
    protected int $id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $id)
    {
       $this->data = $data;
        $this->id = $id;
    }

    #Функция запроса точки прохода в Сигур
    private function getNamePoint($idPoint): string
    {
        //Запрашиваем название точки прохода в Сигур
        try {
            $accesspoint_name = DB::connection('mysql')->table('devices')->select('NAME')->where('ID', '=', $idPoint)->first();
        } catch (QueryException) {
            return "Ошибка запроса базы Сигур";
        }
        if (isset($accesspoint_name->NAME)) return $accesspoint_name->NAME;
        return "Ошибка запроса базы Сигур";
    }
    #Функция запроса имени
    private function getNamePersonal($card): string
    {
        //Запрашиваем название точки прохода в Сигур
        try {
            $personal = DB::connection('mysql')->table('personal')->select('NAME')->where('CODEKEY', '=', hex2bin('20'.$card.'000000'))->first();
        } catch (QueryException) {
            return "Ошибка запроса базы Сигур";
        }
        if (isset($personal->NAME)) return $personal->NAME;
        return "Без имени";
    }
    #Функция отправки логов в БД 'pass_all_logs'
    private function sendLog($data) {
        try {
            DB::connection('pgsql')->table('pass_all_logs')->insert([
                'date' => $data[0],
                'event_id' => $data[1],
                'type_id' => $data[2],
                'permission' => $data[3],
                'card' => $data[4],
                'name' => $data[5],
                'system' => $data[6],
                'accesspoint_id' => $data[7],
                'accesspoint_name' => $data[8],
                'direction_id' => $data[9],
                'accesstype' => $data[10],
                'typecard' => $data[11],
                'cause' => $data[12]
            ]);
        } catch (QueryException) {
            Log::error('Log message', array('context' => 'Оишбка записи в БД логов'));
        }

    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $data = $this->data;
        $id = $this->id;
        if ($id == 0) {
            //Тут работаем с запросами разрешения доступа
            //расшифровываем тип системы ,которой принадлежит карта
            $type = match ($data[6]) {
                0 => "null",
                1 => "local",
                2 => "1C ZUP",
                3 => "FITNESS"
            };

            if ($data[9] == 1) $sop = "услуга по сопровождаемому"; else $sop = "доступ по своей услуге";
            if (!isset($data[5])) {
                //Если нет имени - запрашиваем из БД Сигур (Это необходимо для внутренних точек)
                $data[5] = $this->getNamePersonal($data[4]);
            }
            //Отправляем логи в БД 'pass_all_logs'
            $this->sendLog([$data[0],$data[1],$data[2],$data[3],$data[4],$data[5],$type,$data[7],$this->getNamePoint($data[7]),$data[8],$sop,$data[10],$data[11]]);

        } else {
             //Тут работаем с подтверждением проходов
            $sendData = array();
            $tr = false;
            if (isset($data['logs'])) {
                foreach ($data['logs'] as $log) {
                    try {
                        //Запрашиваем последние данные запросов проходов для карты
                        $emplData = DB::connection('pgsql')->select('SELECT * FROM "pass_all_logs" WHERE "type_id" = 1 AND "id" = (SELECT MAX("id") FROM "pass_all_logs" WHERE "card" = \''.$log['keyHex'].'\')');
                    } catch (QueryException) {
                        Log::error('Log message', array('context' => 'Оишбка запроса базы логов в сигур'));
                    }
                    if (isset($emplData) && count($emplData) > 0) {
                        //Если время в логе проходов меньше времени запроса проходов, устанавливаем в логе время запроса (Это может быть на NFC терминалах, так как у них время может не совпадать с временем нашего сервера)
                        $datePass = date("Y-m-d H:i:s",$log['time']-3*60*60);
                        if ($datePass < $emplData[0]->date) $datePass = $emplData[0]->date;
                        $sendData[] = [$datePass, $log['logId'], 2, true, $log['keyHex'], $emplData[0]->name, $emplData[0]->system, $log['accessPoint'], $this->getNamePoint($log['accessPoint']), $log['direction'], $emplData[0]->accesstype, $emplData[0]->typecard, $emplData[0]->cause];
                    }
                    //Завершаем цикл просмотра массива логов проходов до значения $log['logId'] равного данным
                    if ($log['logId'] == $id) {$tr = true; break;}
                }

                if ($tr) {
                    foreach ($sendData as $row) {
                        $this->sendLog($row);
                    }
                }
            }
        }
    }
}

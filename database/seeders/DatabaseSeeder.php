<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $data1 = [
            ['id'=> 1, 'name'=> 'apipoint', 'data' => '1,2'],
            ['id'=> 2, 'name'=> 'kpppoint', 'data' => '3,4'],
            ['id'=> 3, 'name'=> 'nfcpoint', 'data' => '3,4'],
            ['id'=> 4, 'name'=> 'gate', 'data' => '1'],
            ['id'=> 5, 'name'=> 'invtypecard', 'data' => 'Лицо с инвалидностью'],
            ['id'=> 6, 'name'=> 'timestart', 'data' => '55'],
            ['id'=> 7, 'name'=> 'timefinish', 'data' => '55'],
            ['id'=> 8, 'name'=> 'dualpass', 'data' => '5'],
            ['id'=> 9, 'name'=> 'soprovtypecard', 'data' => 'Сопровождающий'],
            ['id'=> 10, 'name'=> 'fitnesslogin', 'data' => 'login_1C_Fitness'],
            ['id'=> 11, 'name'=> 'fitnesspass', 'data' => 'password_1C_Fitness'],
            ['id'=> 12, 'name'=> 'apieventsurl', 'data' => 'http://server_1C_fitness/fitnessclub/hs/sigur/events'],
            ['id'=> 13, 'name'=> 'apigetaccessurl', 'data' => 'http://server_1C_fitness/fitnessclub/hs/sigur/getaccess'],
            ['id'=> 14, 'name'=> 'apigetaccessnologurl', 'data' => 'http://server_1C_fitness/fitnessclub/hs/sigurapi/getaccess'],
            ['id'=> 15, 'name'=> 'psstypecard', 'data' => 'ПСС главный,ПСС участник'],
            ['id'=> 16, 'name'=> 'soprovidcard', 'data' => '9F03005056AE903511ECE3C7E852A063'],
            ['id'=> 17, 'name'=> 'pointnull', 'data' => '99'],
            ['id'=> 18, 'name'=> 'pointpss', 'data' => '98'],
        ];

        DB::table('config')->insert($data1);

    }
}

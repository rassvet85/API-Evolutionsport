<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Query\Querymain;
use Illuminate\Http\Request;


class Apicontroller extends Controller
{

    public function apipost(Request $request): \Illuminate\Http\JsonResponse
    {
            $data =  $request->json()->all();
            return response()->json(['allow'=>false, 'message' => json_encode($data)]);
            $extid = null;
            $empid = null;

            if(isset($data['keyHex'])) $wildcard = $data['keyHex']; else return response()->json(['allow'=>false, 'message' => 'Ошибка сервера: ER0002']);
            if(isset($data['extId'])) $extid = $data['extId'];
            if(isset($data['empId'])) $empid = $data['empId'];

            $query = new Querymain();
            $value = $query->query($empid, $extid,$wildcard);
            if (!$value[0]) return response()->json(['allow'=>$value[0], 'message' => $value[4]]);
            else return response()->json(['allow'=>$value[0]]);
    }

    public function apiget(Request $request): \Illuminate\Http\JsonResponse
    {
            $empid = $request->input('empid');
            $extid = $request->input('extid');
            $wildcard = $request->input('wildcard');
            if ($extid == "null") $extid = null;
            if ($empid == "null") $empid = null;

            $query = new Querymain();
            $value = $query->query($empid, $extid, $wildcard);
            $type = match ($value[2]) {
                0 => "null",
                1 => "local",
                2 => "1C ZUP",
                3 => "TNG",
            };
            return response()->json(['allow'=>$value[0], 'card' => $value[1], 'type' => $type, 'exptime' => $value[3], 'message' => $value[4]]);

    }

}

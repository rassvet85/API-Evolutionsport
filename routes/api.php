<?php

use App\Http\Controllers\Apicontroller;
use Illuminate\Support\Facades\Route;

date_default_timezone_set("Europe/Moscow");
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/laravel', [Apicontroller::class, 'apipost']);
Route::get('/laravel', [Apicontroller::class, 'apiget']);
Route::post('/events', [Apicontroller::class, 'eventpost']);

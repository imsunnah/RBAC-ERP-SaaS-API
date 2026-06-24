<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ZKTeco ADMS Routes
use App\Http\Controllers\Api\V1\AdmsPushController;

Route::any('/iclock/cdata', [AdmsPushController::class, 'handle']);
Route::any('/iclock/getrequest', [AdmsPushController::class, 'getrequest']);
Route::any('/cdata', [AdmsPushController::class, 'handle']);
Route::any('/getrequest', [AdmsPushController::class, 'getrequest']);

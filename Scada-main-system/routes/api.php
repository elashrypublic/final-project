<?php

use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ScadaController;

Route::get('/machines', [MachineController::class, 'index']);
Route::get('/machines/{id}', [MachineController::class, 'show']);

Route::middleware('throttle:10,1')->group(function () {
Route::post('/machines/{id}/start', [MachineController::class, 'start']);
Route::post('/machines/{id}/stop', [MachineController::class, 'stop']);
});

Route::get('/logs', [LogController::class, 'index']);


Route::get('/scada-data', [ScadaController::class, 'index']);
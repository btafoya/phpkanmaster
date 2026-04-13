<?php

use App\Http\Controllers\AgentTokenController;
use App\Http\Controllers\AgentTaskController;
use Illuminate\Support\Facades\Route;

Route::post('/agent/token', [AgentTokenController::class, 'token'])
    ->middleware('throttle:5,1');

Route::middleware('throttle:30,1')->group(function () {
    Route::post('/agent/tasks', [AgentTaskController::class, 'store']);
    Route::patch('/agent/tasks/{id}', [AgentTaskController::class, 'update']);
    Route::delete('/agent/tasks/{id}', [AgentTaskController::class, 'destroy']);
});


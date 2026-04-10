<?php

use App\Http\Controllers\AgentTokenController;
use Illuminate\Support\Facades\Route;

Route::post('/agent/token', [AgentTokenController::class, 'token']);

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChipTransferController;

Route::post('/transfer-chips', [ChipTransferController::class, 'transfer']);
Route::get('/chip-balance/{playerId}', [ChipTransferController::class, 'getBalance']);
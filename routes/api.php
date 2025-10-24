<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BalanceController;

Route::controller(BalanceController::class)->group(function () {
    Route::post('/deposit', 'deposit');
    Route::post('/withdraw', 'withdraw');
    Route::post('/transfer', 'transfer');
    Route::get('/balance/{user_id}', 'getBalance')
         ->where('user_id', '[0-9]+'); // Ограничение, что ID - это цифры
});

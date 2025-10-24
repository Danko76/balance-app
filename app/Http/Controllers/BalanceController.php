<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\Api\DepositRequest;
use App\Http\Requests\Api\WithdrawRequest;
use App\Http\Requests\Api\TransferRequest;
use App\Services\BalanceService;
use Illuminate\Database\Eloquent\ModelNotFoundException; // Для отлова 404
use Exception;

class BalanceController extends Controller
{

    public function __construct(protected BalanceService $balanceService)
    {
    }

    // 1. Пополнение

    public function deposit(DepositRequest $request)
    {
        try {
            $balance = $this->balanceService->deposit(
                $request->user_id,
                $request->amount,
                $request->comment
            );

            // Успешный ответ (200)
            return response()->json([
                'user_id' => $balance->user_id,
                'balance' => (float)$balance->balance
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        } catch (Exception $e) {
            // 400 (Bad Request) для "Сумма <= 0"
            $code = ($e->getCode() == 400) ? 400 : 500;
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    // 2. Списание

    public function withdraw(WithdrawRequest $request)
    {
        try {
            $balance = $this->balanceService->withdraw(
                $request->user_id,
                $request->amount,
                $request->comment
            );

            return response()->json([
                'user_id' => $balance->user_id,
                'balance' => (float)$balance->balance
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        } catch (Exception $e) {
            // Отлавливаем код 409 (Недостаточно средств) или 400 (Сумма <= 0)
            $code = in_array($e->getCode(), [400, 409]) ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    // 3. Перевод

    public function transfer(TransferRequest $request)
    {
        try {
            $balances = $this->balanceService->transfer(
                $request->from_user_id,
                $request->to_user_id,
                $request->amount,
                $request->comment
            );

            // Формируем ответ
            return response()->json([
                'from_user' => [
                    'user_id' => $balances['from']->user_id,
                    'balance' => (float)$balances['from']->balance
                ],
                'to_user' => [
                    'user_id' => $balances['to']->user_id,
                    'balance' => (float)$balances['to']->balance
                ]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Один из пользователей не найден'], 404);
        } catch (Exception $e) {
            $code = in_array($e->getCode(), [400, 409]) ? $e->getCode() : 500;
            return response()->json(['error' => $e->getMessage()], $code);
        }
    }

    // 4. Получение баланса

    public function getBalance(int $user_id)
    {
        try {
            
            if ($user_id <= 0) {
                 return response()->json(['error' => 'Некорректный ID пользователя'], 400);
            }

            $balanceData = $this->balanceService->getBalance($user_id);
            return response()->json($balanceData, 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Пользователь не найден'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}

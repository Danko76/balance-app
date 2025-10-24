<?php
namespace App\Services;

use App\Models\User;
use App\Models\Balance;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;

class BalanceService
{

    // 1. Начисление средств

    public function deposit(int $userId, float $amount, ?string $comment): Balance
    {
        // Проверяем пользователя
        User::findOrFail($userId);

        if ($amount <= 0) {
            throw new Exception("Сумма должна быть положительной", 400);
        }

        // Запускаем транзакцию
        return DB::transaction(function () use ($userId, $amount, $comment) {

            // Ищем баланс или создаем новый
            $balance = Balance::firstOrCreate(
                ['user_id' => $userId],
                ['balance' => 0.00]
            );

            // Блокируем запись на время обновления, чтобы избежать "гонки"
            $balance = Balance::where('user_id', $userId)->lockForUpdate()->first();

            // Пополняем
            $balance->balance += $amount;
            $balance->save();

            // Логируем
            Transaction::create([
                'user_id' => $userId,
                'type' => 'deposit',
                'amount' => $amount,
                'comment' => $comment,
            ]);

            return $balance;
        });
    }


    // 2. Списание средств

    public function withdraw(int $userId, float $amount, ?string $comment): Balance
    {
        User::findOrFail($userId); // Проверка пользователя

        if ($amount <= 0) {
            throw new Exception("Сумма должна быть положительной", 400);
        }

        return DB::transaction(function () use ($userId, $amount, $comment) {


            $balance = Balance::where('user_id', $userId)->lockForUpdate()->first();

            // Проверяем, что баланс есть и он достаточный
            if (!$balance || $balance->balance < $amount) {

                throw new Exception("Недостаточно средств", 409);
            }


            $balance->balance -= $amount;
            $balance->save();


            Transaction::create([
                'user_id' => $userId,
                'type' => 'withdraw',
                'amount' => $amount,
                'comment' => $comment,
            ]);

            return $balance;
        });
    }


    //3. Перевод между пользователями

    public function transfer(int $fromUserId, int $toUserId, float $amount, ?string $comment): array
    {
        User::findOrFail($fromUserId);
        User::findOrFail($toUserId);

        if ($amount <= 0) {
            throw new Exception("Сумма должна быть положительной", 400);
        }

        // (Валидация на 'different' есть в Request, но дублируем на всякий)
        if ($fromUserId === $toUserId) {
            throw new Exception("Нельзя переводить средства самому себе", 400);
        }

        return DB::transaction(function () use ($fromUserId, $toUserId, $amount, $comment) {

            // Блокируем оба баланса, чтобы избежать deadlock (взаимной блокировки).
            // Мы делаем это, блокируя строки всегда в одном порядке (по ID).
            $ids = [$fromUserId, $toUserId];
            sort($ids); // [1, 2]

            // ->get() чтобы получить коллекцию, ->keyBy() для удобного доступа
            $balances = Balance::whereIn('user_id', $ids)->lockForUpdate()->get()->keyBy('user_id');

            $balanceFrom = $balances->get($fromUserId);
            $balanceTo = $balances->get($toUserId);

            // Проверка баланса отправителя
            if (!$balanceFrom || $balanceFrom->balance < $amount) {
                throw new Exception("Недостаточно средств у отправителя", 409);
            }

            // Если у получателя нет баланса, создаем (как при пополнении)
            if (!$balanceTo) {
                $balanceTo = Balance::create([
                    'user_id' => $toUserId,
                    'balance' => 0.00
                ]);
            }


            $balanceFrom->balance -= $amount;
            $balanceTo->balance += $amount;
            $balanceFrom->save();
            $balanceTo->save();


            Transaction::create([
                'user_id' => $fromUserId,
                'type' => 'transfer_out',
                'amount' => $amount,
                'comment' => $comment,
                'related_user_id' => $toUserId
            ]);

            Transaction::create([
                'user_id' => $toUserId,
                'type' => 'transfer_in',
                'amount' => $amount,
                'comment' => $comment,
                'related_user_id' => $fromUserId
            ]);

            // fresh() перезагружает данные из БД
            return ['from' => $balanceFrom->fresh(), 'to' => $balanceTo->fresh()];
        });
    }


    // Получение баланса

    public function getBalance(int $userId): array
    {
        User::findOrFail($userId);

        
        $balance = Balance::where('user_id', $userId)->value('balance');


        return [
            'user_id' => $userId,
            'balance' => $balance ? (float)$balance : 0.00
        ];
    }
}

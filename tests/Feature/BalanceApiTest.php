<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Balance;

class BalanceApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
    }

    // Тест 1: Получение баланса (когда его нет)
    public function test_get_balance_when_non_existent(): void
    {
        $response = $this->getJson('/api/balance/' . $this->user1->id);

        $response->assertStatus(200)
                 ->assertJson([
                     'user_id' => $this->user1->id,
                     'balance' => 0.00
                 ]);
    }

    // Тест 2: Пополнение
    public function test_deposit_success(): void
    {
        $response = $this->postJson('/api/deposit', [
            'user_id' => $this->user1->id,
            'amount' => 100.50,
            'comment' => 'Test deposit'
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'user_id' => $this->user1->id,
                     'balance' => 100.50
                 ]);

        // Проверяем, что запись в БД создалась
        $this->assertDatabaseHas('balances', [
            'user_id' => $this->user1->id,
            'balance' => 100.50
        ]);
        // Проверяем лог транзакций
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user1->id,
            'type' => 'deposit',
            'amount' => 100.50
        ]);
    }

    // Тест 3: Списание (Недостаточно средств)
    public function test_withdraw_insufficient_funds(): void
    {
        // Баланса нет, пытаемся списать
        $response = $this->postJson('/api/withdraw', [
            'user_id' => $this->user1->id,
            'amount' => 200.00
        ]);

        $response->assertStatus(409)
                 ->assertJson(['error' => 'Недостаточно средств']);
    }

    // Тест 4: Успешное списание
    public function test_withdraw_success(): void
    {
        // Сначала пополняем
        $this->postJson('/api/deposit', ['user_id' => $this->user1->id, 'amount' => 100]);

        // Затем списываем
        $response = $this->postJson('/api/withdraw', [
            'user_id' => $this->user1->id,
            'amount' => 70.00
        ]);

        $response->assertStatus(200)
                 ->assertJson(['balance' => 30.00]);

        $this->assertDatabaseHas('balances', ['user_id' => $this->user1->id, 'balance' => 30.00]);
    }

    // Тест 5: Успешный перевод
    public function test_transfer_success(): void
    {
        // Пополняем user1
        $this->postJson('/api/deposit', ['user_id' => $this->user1->id, 'amount' => 500]);

        // Переводим от user1 к user2
        $response = $this->postJson('/api/transfer', [
            'from_user_id' => $this->user1->id,
            'to_user_id' => $this->user2->id,
            'amount' => 150.00
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'from_user' => ['user_id' => $this->user1->id, 'balance' => 350.00],
                     'to_user' => ['user_id' => $this->user2->id, 'balance' => 150.00]
                 ]);


        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user1->id,
            'type' => 'transfer_out',
            'related_user_id' => $this->user2->id
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $this->user2->id,
            'type' => 'transfer_in',
            'related_user_id' => $this->user1->id
        ]);
    }

    // Тест 6: Ошибка валидации (перевод самому себе)
    public function test_validation_transfer_to_self(): void
    {
        $response = $this->postJson('/api/transfer', [
            'from_user_id' => $this->user1->id,
            'to_user_id' => $this->user1->id, 
            'amount' => 10.00
        ]);

        $response->assertStatus(422) // 422 Unprocessable Entity
                 ->assertJsonValidationErrors(['to_user_id']); // Проверяем ошибку в поле to_user_id
    }
}

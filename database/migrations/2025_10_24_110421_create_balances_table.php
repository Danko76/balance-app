<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            // onDelete('cascade') удалит баланс, если удалится пользователь
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade')->unique();
            $table->decimal('balance', 15, 2)->default(0.00); // 15 цифр всего, 2 после запятой
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};

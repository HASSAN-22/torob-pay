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
        Schema::create('torobpay_payment_tokens', function (Blueprint $table) {
            $table->text('payment_token');
            $table->text('access_token');
            $table->string('order_id');
            $table->enum('status', ['pending', 'completed', 'failed','canceled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('torobpay_payment_tokens');
    }
};

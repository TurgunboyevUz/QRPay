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
        Schema::create('qr_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('qr_order_id');

            $table->enum('system', ['click', 'payme', 'uzum'])->default('payme'); // can be click, payme, uzum
            $table->string('transaction_id')->nullable();
            $table->double('amount', 15, 5)->default(0); // amount in UZS
            $table->integer('state')->default(1); // 1 - pending, 2 - success, -1 - cancelled, -2 - cancelled after success
            $table->text('details');

            $table->timestamp('create_time')->nullable();
            $table->timestamp('perform_time')->nullable();
            $table->timestamp('cancel_time')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_transactions');
    }
};

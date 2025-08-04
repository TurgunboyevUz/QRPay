<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('qr_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_order_id');
            $table->foreignId('product_id')->nullable();
            $table->integer('quantity')->default(1);
            $table->double('total_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('qr_order_items');
    }
};

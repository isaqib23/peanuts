<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UserShippingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_shippings', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('delivery_type');
            $table->integer('address_id')->nullable();
            $table->string('label')->nullable();
            $table->string('amount')->nullable();
            $table->string('content')->nullable();
            $table->string('order_id')->nullable();
            $table->string('cart_id')->nullable();


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
        Schema::dropIfExists('user_shippings');
    }
}

<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateProductLotteryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('product_lottery', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('product_id');
            $table->string('min_ticket')->nullable();
            $table->string('max_ticket')->nullable();
            $table->string('max_ticket_user')->nullable();
            $table->string('winner')->nullable();
            $table->string('initial_price')->nullable();
            $table->string('bottom_price')->nullable();
            $table->string('reduce_price')->nullable();
            $table->string('current_price')->nullable();
            $table->string('link_product')->nullable();
            $table->string('from_date')->nullable();
            $table->string('to_date')->nullable();

            $table->softDeletes();
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
        Schema::dropIfExists('product_lottery');
    }
}

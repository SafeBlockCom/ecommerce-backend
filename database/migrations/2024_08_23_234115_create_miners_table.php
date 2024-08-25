<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('miners', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique();  // Unique identifier for the miner
            $table->string('name');                  // Name of the miner
            $table->float('mining_rewards')->default(0);  // Total mining rewards earned
            $table->integer('nonce')->default(0);     // Nonce value for the miner
            $table->timestamps();                    // Created at and updated at timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('miners');
    }

};

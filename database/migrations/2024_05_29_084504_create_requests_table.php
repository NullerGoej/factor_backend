<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('two_factor_requests', function (Blueprint $table) {
            $table->id();
            $table->string('unique_id')->unique();
            $table->boolean('accepted')->default(false);
            $table->ipAddress('ip_address');
            $table->string('action');
            $table->foreignId('device_id')->constrained('phones');
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
        Schema::dropIfExists('requests');
    }
}
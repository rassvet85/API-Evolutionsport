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
        Schema::create('pass_all_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->dateTime('date');
            $table->integer('event_id')->nullable();
            $table->smallInteger('type_id')->nullable();
            $table->boolean('permission');
            $table->string('card');
            $table->string('name')->nullable();
            $table->string('system')->nullable();
            $table->smallInteger('accesspoint_id');
            $table->string('accesspoint_name')->nullable();
            $table->smallInteger('direction_id');
            $table->string('accesstype')->nullable();
            $table->string('typecard')->nullable();
            $table->longText('cause')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pass_all_logs');
    }
};

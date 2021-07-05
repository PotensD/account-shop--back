<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountInfosTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_infos', function (Blueprint $table) {
            $table->id();
            $table->integer('order')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->integer('account_type_id');
            $table->integer('rule_id');
            $table->unsignedBigInteger('latest_updater_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_infos');
    }
}

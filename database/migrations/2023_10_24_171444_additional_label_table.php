<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AdditionalLabelTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('additional_labels', function (Blueprint $table) {
            $table->increments('id');
            $table->string('guid_master', 120)->nullable();
            $table->string('guid_ticket', 120)->nullable();
            $table->string('content', 100 );
            $table->string('judge')->default('OK');
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
        Schema::dropIfExists('additional_labels');
    }
}

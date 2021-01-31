<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKycLog extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('kyc_log', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->integer('idm_user_id');
            $table->string('status', 20);
            $table->longText('raw');
            $table->index(['idm_user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('kyc_log');
    }
}

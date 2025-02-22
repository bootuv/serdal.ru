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
        Schema::create('direct_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('direct_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->foreign('direct_id')->references('id')->on('directs')->onDelete('CASCADE');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('CASCADE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direct_user');
    }
};

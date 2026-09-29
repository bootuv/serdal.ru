<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Кто из админов отметил взнос основателя внесённым (история взносов в разделе «Основатели»). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->foreignId('paid_by_id')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by_id');
        });
    }
};

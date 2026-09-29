<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Разовые расходы основателей (period = once): собираются один раз, вместе со сбором за месяц charge_period.
 * У взноса за разовый расход — founder_expense_id и title (название расхода на момент сбора: история не теряется,
 * если расход удалят). У обычного ежемесячного взноса title = null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('founder_expenses', function (Blueprint $table) {
            $table->date('charge_period')->nullable()->after('period');
        });

        // Сначала обычный индекс: в MySQL уникальный держит внешний ключ founder_id, без замены его не удалить
        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->index(['founder_id', 'period']);
        });

        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->dropUnique(['founder_id', 'period']);
            $table->foreignId('founder_expense_id')->nullable()->after('founder_id')->constrained()->nullOnDelete();
            $table->string('title')->nullable()->after('period');
        });
    }

    public function down(): void
    {
        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('founder_expense_id');
            $table->dropColumn('title');
            $table->unique(['founder_id', 'period']);
        });

        Schema::table('founder_contributions', function (Blueprint $table) {
            $table->dropIndex(['founder_id', 'period']);
        });

        Schema::table('founder_expenses', function (Blueprint $table) {
            $table->dropColumn('charge_period');
        });
    }
};

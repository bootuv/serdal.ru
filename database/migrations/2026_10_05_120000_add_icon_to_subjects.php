<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Значок предмета в каталоге, назначенный вручную в «Справочниках»: иконка или буква и цвет (App\Support\SubjectIcons). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('icon', 32)->nullable()->after('name');
            $table->string('color', 16)->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color']);
        });
    }
};

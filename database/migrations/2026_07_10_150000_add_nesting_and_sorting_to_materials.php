<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('material_folders', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('teacher_id')
                ->constrained('material_folders')->nullOnDelete();
        });

        Schema::table('teacher_materials', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('visibility')->index();
        });
    }

    public function down(): void
    {
        Schema::table('material_folders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });

        Schema::table('teacher_materials', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};

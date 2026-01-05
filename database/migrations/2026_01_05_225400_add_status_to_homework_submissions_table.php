<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('student_id');
        });

        // Migrate existing data
        DB::table('homework_submissions')
            ->whereNotNull('grade')
            ->update(['status' => 'graded']);

        DB::table('homework_submissions')
            ->whereNull('grade')
            ->whereNotNull('submitted_at')
            ->update(['status' => 'submitted']);
    }

    public function down(): void
    {
        Schema::table('homework_submissions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

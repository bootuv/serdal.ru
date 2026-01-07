<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('homework_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained('homework_submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // submitted, annotated, graded, revision_requested, resubmitted
            $table->json('metadata')->nullable(); // grade value, filename, feedback, etc.
            $table->timestamps();

            $table->index(['submission_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homework_activities');
    }
};

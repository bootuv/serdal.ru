<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->string('s3_url')->nullable()->after('url');
            $table->timestamp('s3_uploaded_at')->nullable()->after('s3_url');
        });
    }

    public function down(): void
    {
        Schema::table('recordings', function (Blueprint $table) {
            $table->dropColumn(['s3_url', 's3_uploaded_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preview_builds', function (Blueprint $table): void {
            $table->string('target_path')->nullable()->after('build_path');
            $table->uuid('request_id')->nullable()->unique()->after('target_path');
        });
    }

    public function down(): void
    {
        Schema::table('preview_builds', function (Blueprint $table): void {
            $table->dropUnique(['request_id']);
            $table->dropColumn(['target_path', 'request_id']);
        });
    }
};

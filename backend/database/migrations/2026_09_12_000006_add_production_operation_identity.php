<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_publications', function (Blueprint $table): void {
            $table->uuid('request_id')->nullable()->unique();
            $table->uuid('project_id')->nullable();
            $table->string('operation', 20)->default('site');
            $table->string('runner_id', 100)->nullable();
            $table->json('previous_project_state')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('production_publications', function (Blueprint $table): void {
            $table->dropUnique(['request_id']);
            $table->dropColumn(['request_id', 'project_id', 'operation', 'runner_id', 'previous_project_state']);
        });
    }
};

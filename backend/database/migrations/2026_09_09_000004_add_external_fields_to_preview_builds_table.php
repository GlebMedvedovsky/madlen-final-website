<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preview_builds', function (Blueprint $table): void {
            $table->string('execution_mode', 20)->default('local')->after('token');
            $table->string('status', 20)->default('ready')->index()->after('execution_mode');
            $table->string('source_revision', 40)->nullable()->after('status');
            $table->string('package_path')->nullable()->after('build_path');
            $table->string('package_checksum', 64)->nullable()->after('package_path');
            $table->string('result_checksum', 64)->nullable()->after('package_checksum');
            $table->text('progress_message')->nullable()->after('result_checksum');
            $table->text('error_message')->nullable()->after('progress_message');
            $table->timestamp('completed_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('preview_builds', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'execution_mode', 'status', 'source_revision', 'package_path',
                'package_checksum', 'result_checksum', 'progress_message',
                'error_message', 'completed_at',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_publications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('sequence')->unique();
            $table->string('status')->index();
            $table->string('source_revision', 64);
            $table->string('manifest_path')->nullable();
            $table->string('package_path')->nullable();
            $table->string('package_checksum', 64)->nullable();
            $table->string('target_release')->nullable();
            $table->text('progress_message')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_publications');
    }
};

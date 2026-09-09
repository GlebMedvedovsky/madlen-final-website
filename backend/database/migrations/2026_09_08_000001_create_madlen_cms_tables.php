<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label_de');
            $table->string('label_en');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_key')->nullable()->unique();
            $table->string('kind')->default('image');
            $table->string('path');
            $table->string('derivative_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('checksum', 64)->nullable()->index();
            $table->text('alt_de')->nullable();
            $table->text('alt_en')->nullable();
            $table->text('caption_de')->nullable();
            $table->text('caption_en')->nullable();
            $table->boolean('source_managed')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_key')->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('title_de');
            $table->string('title_en')->nullable();
            $table->text('description_de');
            $table->text('description_en')->nullable();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('cover_media_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->string('status')->default('draft')->index();
            $table->unsignedInteger('position')->default(0)->index();
            $table->timestamp('source_imported_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('media_asset_id')->constrained()->restrictOnDelete();
            $table->string('role')->default('gallery');
            $table->unsignedInteger('position')->default(0);
            $table->string('side', 10)->default('left');
            $table->timestamps();
            $table->unique(['project_id', 'media_asset_id', 'role']);
            $table->index(['project_id', 'position']);
        });

        Schema::create('services', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('title_de');
            $table->string('title_en')->nullable();
            $table->text('description_de');
            $table->text('description_en')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('highlighted')->default(false);
            $table->boolean('active_de')->default(true);
            $table->boolean('active_en')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('source_imported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('content_entries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->string('group_name')->index();
            $table->string('type')->default('json');
            $table->longText('value_de')->nullable();
            $table->longText('value_en')->nullable();
            $table->json('seo')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('source_imported_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('label_de');
            $table->timestamps();
        });

        Schema::create('revisions', function (Blueprint $table): void {
            $table->id();
            $table->nullableUuidMorphs('revisionable');
            $table->json('payload');
            $table->string('event', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['revisionable_type', 'revisionable_id', 'created_at'], 'revision_lookup');
        });

        Schema::create('releases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('version')->unique();
            $table->string('status')->index();
            $table->string('manifest_path');
            $table->string('build_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('preview_builds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('token', 64)->unique();
            $table->string('manifest_path');
            $table->string('build_path');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('old_path')->unique();
            $table->string('new_path');
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('backups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status')->index();
            $table->string('archive_path')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('preview_builds');
        Schema::dropIfExists('releases');
        Schema::dropIfExists('revisions');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('content_entries');
        Schema::dropIfExists('services');
        Schema::dropIfExists('project_media');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('categories');
    }
};

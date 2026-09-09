<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_media_slots', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('label_de');
            $table->foreignUuid('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_media_slots');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gallery_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('alt_text')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['organization_unit_id', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};

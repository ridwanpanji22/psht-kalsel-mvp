<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_positions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('holder_name');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['organization_unit_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_positions');
    }
};
